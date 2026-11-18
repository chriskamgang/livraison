<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\GfsService;
use App\Services\KpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private KpayService $kpay;
    private GfsService $gfs;

    public function __construct(KpayService $kpay, GfsService $gfs)
    {
        $this->kpay = $kpay;
        $this->gfs  = $gfs;
    }

    /**
     * Initialiser un paiement par compte GFS : renvoie le lien de la page de paiement GFS.
     */
    public function initiateGfsPayment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $order = Order::find($validated['order_id']);

        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }
        if ($order->payment_method !== 'gfs') {
            return response()->json(['message' => 'Cette commande n\'est pas payable par GFS'], 400);
        }
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Cette commande est déjà payée'], 400);
        }
        if (!$this->gfs->isConfigured()) {
            return response()->json(['message' => 'Le paiement GFS n\'est pas encore disponible.'], 503);
        }

        $reference = 'GFS-' . $order->id . '-' . time();

        $payment = Payment::create([
            'order_id'  => $order->id,
            'user_id'   => auth()->id(),
            'amount'    => $order->total,
            'method'    => 'gfs',
            'status'    => 'pending',
            'reference' => $reference,
        ]);

        $result = $this->gfs->createPayment([
            'amount'      => (int) $order->total,
            'reference'   => $reference,
            'description' => 'Commande Estuaire Eat #' . ($order->order_number ?? $order->id),
        ]);

        if (!$result['success']) {
            $payment->update(['status' => 'failed', 'failure_reason' => $result['message']]);
            return response()->json(['message' => $result['message']], 400);
        }

        $payment->update([
            'transaction_reference' => $result['data']['paymentRef'],
            'provider_response'     => $result['data'],
        ]);

        return response()->json([
            'message' => 'Paiement GFS créé. Validez-le avec votre numéro client et votre code PIN.',
            'payment' => [
                'id'          => $payment->id,
                'status'      => 'pending',
                'amount'      => $order->total,
                'payment_url' => $result['data']['paymentUrl'],
                'expires_at'  => $result['data']['expiresAt'] ?? null,
            ],
        ]);
    }

    /**
     * Webhook GFS — appelé quand le client a validé le paiement sur la page GFS.
     */
    public function gfsWebhook(Request $request)
    {
        $data = $request->json()->all();

        if (!$this->gfs->verifyWebhookSignature($data, (string) $request->header('X-GFS-Signature', ''))) {
            Log::warning('GFS webhook: signature invalide');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payment = Payment::where('reference', $data['orderId'] ?? '')
            ->where('method', 'gfs')
            ->first();

        if (!$payment) {
            Log::warning('GFS webhook: paiement introuvable', ['orderId' => $data['orderId'] ?? null]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        if ($payment->status === 'completed') {
            return response()->json(['message' => 'Already processed']);
        }

        // Le montant payé doit correspondre exactement à la commande
        if ((int) round((float) ($data['amount'] ?? 0)) !== (int) round((float) $payment->amount)) {
            Log::warning('GFS webhook: montant différent', ['payment_id' => $payment->id, 'amount' => $data['amount'] ?? null]);
            return response()->json(['message' => 'Amount mismatch'], 422);
        }

        if (($data['status'] ?? null) === 'COMPLETED') {
            $this->markPaid($payment, $data);
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    private function markPaid(Payment $payment, array $providerData): void
    {
        $payment->update([
            'status'            => 'completed',
            'completed_at'      => now(),
            'provider_response' => $providerData,
        ]);
        $payment->order->update([
            'payment_status' => 'paid',
            'status'         => 'confirmed',
        ]);
        Log::info('Paiement confirmé', ['payment_id' => $payment->id, 'method' => $payment->method]);
    }

    /**
     * Initialiser un paiement mobile money
     */
    public function initiateMobilePayment(Request $request)
    {
        $validated = $request->validate([
            'order_id'       => 'required|exists:orders,id',
            'phone'          => 'required|string|min:9|max:15',
            'payment_method' => 'required|in:mtn_momo,orange_money',
        ]);

        $order = Order::find($validated['order_id']);

        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Cette commande est déjà payée'], 400);
        }

        $externalId = 'PAY-' . $order->id . '-' . time();

        $payment = Payment::create([
            'order_id'  => $order->id,
            'user_id'   => auth()->id(),
            'amount'    => $order->total,
            'method'    => $validated['payment_method'],
            'status'    => 'pending',
            'phone'     => $validated['phone'],
            'reference' => $externalId,
        ]);

        $result = $this->kpay->initiatePayment([
            'phone'          => $validated['phone'],
            'amount'         => (int) $order->total,
            'externalId'     => $externalId,
            'payment_method' => $validated['payment_method'],
        ]);

        if (!$result['success']) {
            $payment->update([
                'status'         => 'failed',
                'failure_reason' => $result['message'] ?? 'Erreur inconnue',
            ]);

            return response()->json([
                'message' => $result['message'] ?? 'Échec de l\'initialisation du paiement',
            ], 400);
        }

        // Stocker l'ID KPay pour le polling de statut
        $payment->update([
            'transaction_reference' => $result['data']['paymentId'],
            'provider_response'     => json_encode($result['data']),
        ]);

        return response()->json([
            'message' => 'Paiement initialisé. Veuillez confirmer sur votre téléphone.',
            'payment' => [
                'id'        => $payment->id,
                'reference' => $result['data']['reference'],
                'status'    => 'pending',
                'amount'    => $order->total,
            ],
        ]);
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function checkPaymentStatus(Request $request, $paymentId)
    {
        $payment = Payment::find($paymentId);

        if (!$payment) {
            return response()->json(['message' => 'Paiement non trouvé'], 404);
        }

        if ($payment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        // Si déjà dans un état terminal, retourner directement
        if (in_array($payment->status, ['completed', 'failed', 'cancelled'])) {
            return response()->json([
                'payment' => [
                    'id'       => $payment->id,
                    'status'   => $payment->status,
                    'amount'   => $payment->amount,
                    'order_id' => $payment->order_id,
                ],
            ]);
        }

        // Paiement GFS : on interroge la passerelle GFS
        if ($payment->method === 'gfs' && $payment->transaction_reference) {
            $result = $this->gfs->getPaymentStatus($payment->transaction_reference);

            if ($result['success']) {
                $gfsStatus = $result['data']['status'] ?? null;
                $amountOk  = (int) round((float) ($result['data']['amount'] ?? 0)) === (int) round((float) $payment->amount);

                if ($gfsStatus === 'COMPLETED' && $amountOk) {
                    $this->markPaid($payment, $result['data']);
                } elseif (in_array($gfsStatus, ['EXPIRED', 'FAILED', 'CANCELLED'], true)) {
                    $payment->update([
                        'status'         => $gfsStatus === 'CANCELLED' ? 'cancelled' : 'failed',
                        'failure_reason' => $gfsStatus === 'EXPIRED' ? 'Lien de paiement expiré' : 'Paiement GFS échoué',
                    ]);
                }
            }
        }
        // Vérifier auprès de KPay via l'ID de transaction
        elseif ($payment->transaction_reference) {
            $result = $this->kpay->getPaymentStatus($payment->transaction_reference);

            if ($result['success']) {
                $kpayStatus = $result['data']['status'];

                if ($kpayStatus === 'COMPLETED') {
                    $payment->update([
                        'status'       => 'completed',
                        'completed_at' => now(),
                    ]);
                    $payment->order->update([
                        'payment_status' => 'paid',
                        'status'         => 'confirmed',
                    ]);
                } elseif (in_array($kpayStatus, ['FAILED', 'CANCELLED'])) {
                    $payment->update([
                        'status'         => strtolower($kpayStatus),
                        'failure_reason' => $result['data']['failureReason'] ?? 'Paiement échoué',
                    ]);
                }
            }
        }

        return response()->json([
            'payment' => [
                'id'       => $payment->id,
                'status'   => $payment->status,
                'amount'   => $payment->amount,
                'order_id' => $payment->order_id,
            ],
        ]);
    }

    /**
     * Webhook KPay — reçoit les notifications de statut
     */
    public function kpayWebhook(Request $request)
    {
        $rawBody  = $request->getContent();
        $signature = $request->header('X-KPAY-Signature', '');

        if (!$this->kpay->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('KPay webhook: signature invalide');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $data = json_decode($rawBody, true);
        Log::info('KPay webhook received', $data);

        $externalId = $data['externalId'] ?? null;
        $status     = $data['status'] ?? null;

        if (!$externalId || !$status) {
            return response()->json(['message' => 'Missing fields'], 400);
        }

        // Trouver le paiement par notre référence (externalId)
        $payment = Payment::where('reference', $externalId)->first();

        if (!$payment) {
            Log::warning('KPay webhook: payment not found', ['externalId' => $externalId]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Idempotence — ignorer si déjà traité
        if (in_array($payment->status, ['completed', 'failed', 'cancelled'])) {
            return response()->json(['message' => 'Already processed']);
        }

        if ($status === 'COMPLETED') {
            $payment->update([
                'status'            => 'completed',
                'completed_at'      => now(),
                'provider_response' => json_encode($data),
            ]);
            $payment->order->update([
                'payment_status' => 'paid',
                'status'         => 'confirmed',
            ]);
            Log::info('KPay payment completed', ['payment_id' => $payment->id]);

        } elseif (in_array($status, ['FAILED', 'CANCELLED'])) {
            $payment->update([
                'status'            => strtolower($status),
                'failure_reason'    => $data['failureReason'] ?? 'Paiement échoué',
                'provider_response' => json_encode($data),
            ]);
            Log::info('KPay payment failed/cancelled', ['payment_id' => $payment->id, 'status' => $status]);
        }

        return response()->json(['message' => 'Webhook processed']);
    }
}

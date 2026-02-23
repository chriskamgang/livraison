<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\FreemopayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    private FreemopayService $freemopayService;

    public function __construct(FreemopayService $freemopayService)
    {
        $this->freemopayService = $freemopayService;
    }

    /**
     * Initialiser un paiement mobile money
     */
    public function initiateMobilePayment(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'phone' => 'required|string|min:9|max:15',
            'payment_method' => 'required|in:mtn_momo,orange_money',
        ]);

        $order = Order::find($validated['order_id']);

        // Vérifier que la commande appartient à l'utilisateur
        if ($order->user_id !== auth()->id()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        // Vérifier que la commande n'est pas déjà payée
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'Cette commande est déjà payée'], 400);
        }

        // Créer une entrée de paiement
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => auth()->id(),
            'amount' => $order->total_amount,
            'method' => $validated['payment_method'],
            'status' => 'pending',
            'phone' => $validated['phone'],
        ]);

        // Initialiser le paiement avec Freemopay
        $callbackUrl = url('/api/webhooks/freemopay');

        $result = $this->freemopayService->initiatePayment([
            'phone' => $validated['phone'],
            'amount' => (int) $order->total_amount,
            'externalId' => $payment->id,
            'callback' => $callbackUrl,
        ]);

        if (!$result['success']) {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $result['message'] ?? 'Erreur inconnue',
            ]);

            return response()->json([
                'message' => $result['message'] ?? 'Échec de l\'initialisation du paiement',
            ], 400);
        }

        // Sauvegarder la référence Freemopay
        $payment->update([
            'transaction_reference' => $result['data']['reference'],
            'provider_response' => json_encode($result['data']),
        ]);

        return response()->json([
            'message' => 'Paiement initialisé. Veuillez confirmer sur votre téléphone.',
            'payment' => [
                'id' => $payment->id,
                'reference' => $result['data']['reference'],
                'status' => $result['data']['status'],
                'amount' => $order->total_amount,
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

        // Vérifier que le paiement appartient à l'utilisateur
        if ($payment->user_id !== auth()->id()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        // Si déjà complété, retourner l'état actuel
        if (in_array($payment->status, ['completed', 'failed', 'cancelled'])) {
            return response()->json([
                'payment' => [
                    'id' => $payment->id,
                    'status' => $payment->status,
                    'amount' => $payment->amount,
                    'order_id' => $payment->order_id,
                ],
            ]);
        }

        // Vérifier auprès de Freemopay
        if ($payment->transaction_reference) {
            $result = $this->freemopayService->getPaymentStatus($payment->transaction_reference);

            if ($result['success']) {
                $status = $result['data']['status'];

                if ($status === 'SUCCESS') {
                    $payment->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'provider_response' => json_encode($result['data']),
                    ]);

                    // Mettre à jour la commande
                    $payment->order->update(['payment_status' => 'paid']);
                } elseif ($status === 'FAILED') {
                    $payment->update([
                        'status' => 'failed',
                        'failure_reason' => $result['data']['reason'] ?? 'Paiement échoué',
                        'provider_response' => json_encode($result['data']),
                    ]);
                }
            }
        }

        return response()->json([
            'payment' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'amount' => $payment->amount,
                'order_id' => $payment->order_id,
            ],
        ]);
    }

    /**
     * Webhook Freemopay - reçoit les notifications de paiement
     */
    public function freemopayWebhook(Request $request)
    {
        Log::info('Freemopay webhook received', $request->all());

        $data = $request->validate([
            'status' => 'required|string',
            'reference' => 'required|string',
            'amount' => 'required|numeric',
            'externalId' => 'required',
            'message' => 'nullable|string',
        ]);

        // Trouver le paiement par externalId
        $payment = Payment::find($data['externalId']);

        if (!$payment) {
            Log::warning('Freemopay webhook: payment not found', ['externalId' => $data['externalId']]);
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Vérifier la référence
        if ($payment->transaction_reference !== $data['reference']) {
            Log::warning('Freemopay webhook: reference mismatch', [
                'expected' => $payment->transaction_reference,
                'received' => $data['reference'],
            ]);
        }

        // Mettre à jour le statut
        if ($data['status'] === 'SUCCESS') {
            $payment->update([
                'status' => 'completed',
                'completed_at' => now(),
                'provider_response' => json_encode($data),
            ]);

            // Mettre à jour la commande
            $payment->order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
            ]);

            Log::info('Payment completed successfully', ['payment_id' => $payment->id]);
        } elseif ($data['status'] === 'FAILED') {
            $payment->update([
                'status' => 'failed',
                'failure_reason' => $data['message'] ?? 'Paiement échoué',
                'provider_response' => json_encode($data),
            ]);

            Log::info('Payment failed', ['payment_id' => $payment->id, 'reason' => $data['message']]);
        }

        return response()->json(['message' => 'Webhook processed successfully']);
    }
}

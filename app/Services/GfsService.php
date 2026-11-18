<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Paiement par compte GFS (Global Financial Solution) via sa passerelle marchand.
 *
 * Déroulement :
 *  1. createPayment() → GFS renvoie un lien de paiement (paymentUrl)
 *  2. le client ouvre le lien et valide avec son N° client GFS + PIN
 *  3. GFS appelle notre webhook (signé) ; on peut aussi interroger getPaymentStatus()
 */
class GfsService
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.gfs.base_url', 'https://backend.gfinancials.com/api/v1'), '/');
        $this->apiKey    = (string) config('services.gfs.api_key', '');
        $this->apiSecret = (string) config('services.gfs.api_secret', '');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Crée un lien de paiement GFS.
     * $data : amount, reference (notre référence unique, envoyée comme orderId), description
     */
    public function createPayment(array $data): array
    {
        try {
            $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
                ->acceptJson()
                ->timeout(30)
                ->post($this->baseUrl . '/gateway/payments', [
                    'amount'           => (int) $data['amount'],
                    'orderId'          => $data['reference'],
                    'description'      => $data['description'] ?? null,
                    'callbackUrl'      => config('services.gfs.callback_url') ?: url('/api/webhooks/gfs'),
                    'returnUrl'        => config('services.gfs.return_url'),
                    'expiresInMinutes' => 30,
                ]);

            $body = $response->json() ?? [];

            if (!$response->successful() || empty($body['paymentRef']) || empty($body['paymentUrl'])) {
                Log::warning('GFS createPayment refusé', ['status' => $response->status(), 'body' => $body]);
                $message = $body['message'] ?? 'Paiement GFS indisponible pour le moment.';
                return ['success' => false, 'message' => is_array($message) ? implode(' ', $message) : $message];
            }

            return ['success' => true, 'data' => $body];
        } catch (\Throwable $e) {
            Log::error('GFS createPayment erreur', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Impossible de joindre GFS. Réessayez.'];
        }
    }

    /**
     * Statut d'un paiement : PENDING, COMPLETED, EXPIRED, FAILED, CANCELLED…
     */
    public function getPaymentStatus(string $paymentRef): array
    {
        try {
            $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
                ->acceptJson()
                ->timeout(20)
                ->get($this->baseUrl . '/gateway/payments/' . rawurlencode($paymentRef));

            if (!$response->successful()) {
                return ['success' => false, 'message' => $response->json('message') ?? 'Statut GFS indisponible'];
            }

            return ['success' => true, 'data' => $response->json()];
        } catch (\Throwable $e) {
            Log::warning('GFS getPaymentStatus erreur', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Impossible de joindre GFS.'];
        }
    }

    /**
     * Vérifie l'en-tête X-GFS-Signature : "sha256=" + HMAC-SHA256(clé, "paymentRef:amount:orderId").
     * GFS signe aujourd'hui avec l'API Key ; sa documentation parle de l'API Secret : on accepte les deux.
     */
    public function verifyWebhookSignature(array $payload, string $header): bool
    {
        if (!$this->isConfigured() || !str_starts_with($header, 'sha256=')) {
            return false;
        }

        $signed = ($payload['paymentRef'] ?? '') . ':' . ($payload['amount'] ?? '') . ':' . ($payload['orderId'] ?? '');
        $given  = substr($header, 7);

        foreach (array_filter([$this->apiKey, $this->apiSecret]) as $key) {
            if (hash_equals(hash_hmac('sha256', $signed, $key), $given)) {
                return true;
            }
        }
        return false;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KpayService
{

    // Mapping payment_method → provider KPay
    private const PROVIDERS = [
        'mtn_momo'     => 'MTN_MOMO_CMR',
        'orange_money' => 'ORANGE_CMR',
    ];

    private string $apiKey;
    private string $secretKey;
    private string $webhookSecret;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey        = (string) config('services.kpay.api_key');
        $this->secretKey     = (string) config('services.kpay.secret_key');
        $this->webhookSecret = (string) config('services.kpay.webhook_secret', '');
        $this->baseUrl       = rtrim(config('services.kpay.base_url', 'https://lite.kpay.site/api/v1'), '/');
    }

    private function headers(): array
    {
        return [
            'X-API-Key'    => $this->apiKey,
            'X-Secret-Key' => $this->secretKey,
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];
    }

    /**
     * Initialiser un paiement Mobile Money (mode USSD push)
     *
     * @param array $data [phone, amount, externalId, payment_method]
     * @return array
     */
    public function initiatePayment(array $data): array
    {
        $provider = self::PROVIDERS[$data['payment_method']] ?? 'MTN_MOMO_CMR';

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->post($this->baseUrl . '/payments/init', [
                    'amount'      => (int) $data['amount'],
                    'provider'    => $provider,
                    'phoneNumber' => (string) $data['phone'],
                    'externalId'  => (string) $data['externalId'],
                ]);

            $body = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => [
                        'paymentId' => $body['id'] ?? null,
                        'reference' => $body['reference'] ?? null,
                        'status'    => $body['status'] ?? 'PENDING',
                        'message'   => $body['message'] ?? 'Paiement initié. Veuillez valider sur votre téléphone.',
                    ],
                ];
            }

            Log::error('KPay payment initiation failed', [
                'status' => $response->status(),
                'body'   => $body,
            ]);

            return [
                'success' => false,
                'message' => $body['message'] ?? 'Erreur lors de l\'initialisation du paiement',
                'code'    => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('KPay payment exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'initialisation du paiement',
            ];
        }
    }

    /**
     * Récupérer le statut d'un paiement via l'ID KPay
     *
     * @param string $kpayId  (le champ `id` retourné à l'init, ex. "pay_abc123")
     * @return array
     */
    public function getPaymentStatus(string $kpayId): array
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(30)
                ->get($this->baseUrl . "/payments/{$kpayId}");

            $body = $response->json();

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data'    => [
                        'status'        => $body['status'] ?? 'PENDING',
                        'failureReason' => $body['failureReason'] ?? null,
                        'reference'     => $body['reference'] ?? null,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => $body['message'] ?? 'Erreur lors de la récupération du statut',
                'code'    => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('KPay status check exception', [
                'kpayId'  => $kpayId,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la vérification du statut',
            ];
        }
    }

    /**
     * Vérifier la signature HMAC-SHA256 du webhook KPay
     *
     * @param string $rawBody  Corps brut de la requête entrante
     * @param string $signature  Valeur du header X-KPAY-Signature
     * @return bool
     */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        if (empty($this->webhookSecret)) {
            return false; // sans secret on ne peut rien vérifier : on refuse
        }

        $expected = hash_hmac('sha256', $rawBody, $this->webhookSecret);
        return hash_equals($expected, $signature);
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FreemopayService
{
    private string $baseUrl;
    private string $appKey;
    private string $secretKey;

    public function __construct()
    {
        $this->baseUrl = config('services.freemopay.base_url', 'https://api-v2.freemopay.com');
        $this->appKey = config('services.freemopay.app_key');
        $this->secretKey = config('services.freemopay.secret_key');
    }

    /**
     * Initialiser un paiement
     *
     * @param array $data [phone, amount, externalId, callback]
     * @return array
     */
    public function initiatePayment(array $data): array
    {
        try {
            $response = Http::withBasicAuth($this->appKey, $this->secretKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/api/v2/payment", [
                    'payer' => (string) $data['phone'],
                    'amount' => (int) $data['amount'],
                    'externalId' => (string) $data['externalId'],
                    'callback' => $data['callback'],
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error('Freemopay payment initiation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Erreur lors de l\'initialisation du paiement',
                'code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Freemopay payment exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de l\'initialisation du paiement',
            ];
        }
    }

    /**
     * Récupérer le statut d'un paiement
     *
     * @param string $reference
     * @return array
     */
    public function getPaymentStatus(string $reference): array
    {
        try {
            $response = Http::withBasicAuth($this->appKey, $this->secretKey)
                ->timeout(30)
                ->get("{$this->baseUrl}/api/v2/payment/{$reference}");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Erreur lors de la récupération du statut',
                'code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('Freemopay status check exception', [
                'reference' => $reference,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la vérification du statut',
            ];
        }
    }

    /**
     * Générer un token d'authentification JWT
     *
     * @return array
     */
    public function generateToken(): array
    {
        try {
            $response = Http::withBasicAuth($this->appKey, $this->secretKey)
                ->timeout(30)
                ->post("{$this->baseUrl}/api/v2/payment/token");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => $response->json()['message'] ?? 'Erreur lors de la génération du token',
            ];
        } catch (\Exception $e) {
            Log::error('Freemopay token generation exception', [
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Une erreur est survenue lors de la génération du token',
            ];
        }
    }
}

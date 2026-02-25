<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * Envoyer une notification push à un utilisateur via Expo
     *
     * @param string $pushToken Le token Expo Push de l'utilisateur
     * @param string $title Titre de la notification
     * @param string $body Message de la notification
     * @param array $data Données supplémentaires (optionnel)
     * @return bool
     */
    public function sendPushNotification(string $pushToken, string $title, string $body, array $data = []): bool
    {
        // Vérifier que le token commence par ExponentPushToken
        if (!str_starts_with($pushToken, 'ExponentPushToken[')) {
            Log::warning('Invalid Expo push token format', ['token' => $pushToken]);
            return false;
        }

        try {
            $response = Http::post(self::EXPO_PUSH_URL, [
                'to' => $pushToken,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'default',
            ]);

            if ($response->successful()) {
                $result = $response->json();

                // Vérifier si Expo a accepté la notification
                if (isset($result['data'][0]['status']) && $result['data'][0]['status'] === 'ok') {
                    Log::info('Push notification sent successfully', [
                        'token' => substr($pushToken, 0, 20) . '...',
                        'title' => $title
                    ]);
                    return true;
                }

                // Si le token est invalide, logger l'erreur
                if (isset($result['data'][0]['status']) && $result['data'][0]['status'] === 'error') {
                    Log::warning('Expo push notification error', [
                        'error' => $result['data'][0]['message'] ?? 'Unknown error',
                        'details' => $result['data'][0]['details'] ?? null
                    ]);
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'error' => $e->getMessage(),
                'token' => substr($pushToken, 0, 20) . '...'
            ]);
            return false;
        }
    }

    /**
     * Envoyer des notifications push à plusieurs utilisateurs
     *
     * @param array $notifications Format: [['token' => '...', 'title' => '...', 'body' => '...', 'data' => [...]]]
     * @return array Résultats de l'envoi
     */
    public function sendBulkPushNotifications(array $notifications): array
    {
        $messages = [];
        $results = [];

        foreach ($notifications as $notification) {
            if (empty($notification['token']) || empty($notification['title']) || empty($notification['body'])) {
                continue;
            }

            $messages[] = [
                'to' => $notification['token'],
                'title' => $notification['title'],
                'body' => $notification['body'],
                'data' => $notification['data'] ?? [],
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'default',
            ];
        }

        if (empty($messages)) {
            return [];
        }

        try {
            $response = Http::post(self::EXPO_PUSH_URL, $messages);

            if ($response->successful()) {
                $results = $response->json()['data'] ?? [];
                Log::info('Bulk push notifications sent', ['count' => count($messages)]);
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Failed to send bulk push notifications', [
                'error' => $e->getMessage(),
                'count' => count($messages)
            ]);
            return [];
        }
    }
}

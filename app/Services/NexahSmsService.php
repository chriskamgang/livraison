<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envoi de SMS via NEXAH (smsvas.com), le fournisseur déjà utilisé par GFS.
 * Réponse attendue : { responsecode: 1, sms: [{ messageid, errorcode, status }] }
 */
class NexahSmsService
{
    public function isConfigured(): bool
    {
        return (string) config('services.nexah.user') !== '' && (string) config('services.nexah.password') !== '';
    }

    /** Numéro au format NEXAH : 237XXXXXXXXX (sans +) */
    public static function formatPhone(string $phone): ?string
    {
        $clean = preg_replace('/[\s\-.+]/', '', $phone);
        if (str_starts_with($clean, '237237')) $clean = substr($clean, 3);
        if (str_starts_with($clean, '00')) $clean = substr($clean, 2);
        if (preg_match('/^[62]\d{8}$/', $clean)) $clean = '237' . $clean;

        return preg_match('/^237[62]\d{8}$/', $clean) ? $clean : null;
    }

    /**
     * Envoie le même SMS à un ou plusieurs numéros. Retourne true si NEXAH l'a accepté.
     * @param string[] $phones
     */
    public function send(array $phones, string $message): bool
    {
        $mobiles = array_values(array_unique(array_filter(array_map(
            fn ($p) => self::formatPhone((string) $p), $phones
        ))));

        if (!$mobiles) {
            return false;
        }
        if (!$this->isConfigured()) {
            Log::info('[SMS non configuré] ' . implode(',', $mobiles) . ' : ' . $message);
            return false;
        }

        try {
            $response = Http::acceptJson()->timeout(20)->post(config('services.nexah.url'), [
                'user'     => config('services.nexah.user'),
                'password' => config('services.nexah.password'),
                'senderid' => substr((string) config('services.nexah.sender_id'), 0, 11),
                'sms'      => $message,
                'mobiles'  => implode(',', $mobiles),
            ]);

            $data  = $response->json() ?? [];
            $first = $data['sms'][0] ?? [];
            $ok = $response->successful()
                && (string) ($data['responsecode'] ?? '1') === '1'
                && (($first['errorcode'] ?? null) === null || (string) $first['errorcode'] === '0');

            if (!$ok) {
                Log::warning('Envoi SMS NEXAH refusé', ['status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);
            }
            return $ok;
        } catch (\Throwable $e) {
            Log::warning('Envoi SMS NEXAH impossible', ['error' => $e->getMessage()]);
            return false;
        }
    }
}

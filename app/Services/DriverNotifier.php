<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Prévient par push les livreurs en ligne et validés qu'une commande est à prendre.
 * Les apps enregistrent leur jeton via /api/push-token (colonne push_token) ;
 * les anciennes versions utilisaient fcm_token : on accepte les deux.
 */
class DriverNotifier
{
    /** Commande à prendre : push tout de suite, SMS après la réponse (pour ne pas ralentir). */
    public static function orderAvailable(Order $order): void
    {
        // Le restaurant et les numéros de supervision sont prévenus par SMS à chaque commande validée
        dispatch(fn () => self::smsRestaurant($order))->afterResponse();
        dispatch(fn () => self::smsSupervisors($order))->afterResponse();

        if (!$order->delivery()->where('status', 'searching')->exists()) {
            return;
        }
        self::newOrderAvailable($order);
        dispatch(fn () => self::smsNewOrder($order))->afterResponse();
    }

    /** SMS aux numéros de supervision (ORDER_ALERT_PHONES) pour relancer un livreur. */
    public static function smsSupervisors(Order $order): void
    {
        try {
            $phones = config('services.nexah.order_alert_phones', []);
            if (!$phones) {
                return;
            }
            $order->loadMissing(['restaurant:id,name', 'address', 'user:id,name,phone']);
            $message = self::ascii(
                "Estuaire Eat: nouvelle commande {$order->order_number} chez {$order->restaurant?->name}"
                . ($order->address?->address ? " -> {$order->address->address}" : '')
                . '. Total ' . number_format($order->total, 0, ',', ' ') . ' FCFA. '
                . 'Client : ' . ($order->user?->name ?? '-') . ($order->user?->phone ? " ({$order->user->phone})" : '')
                . '. Relancez un livreur.'
            );
            (new NexahSmsService())->send($phones, mb_substr($message, 0, 450));
        } catch (\Throwable $e) {
            Log::error('SMS to supervisors failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /** SMS au téléphone du restaurant : plats, total, client. */
    public static function smsRestaurant(Order $order): void
    {
        try {
            $order->loadMissing(['restaurant:id,name,phone', 'items', 'user:id,name']);
            $phone = $order->restaurant?->phone;
            if (!$phone) {
                return;
            }
            $items = $order->items->map(fn ($i) => "{$i->quantity}x {$i->item_name}")->implode(', ');
            $message = self::ascii(
                "Estuaire Eat: nouvelle commande {$order->order_number} : {$items}. "
                . 'Total ' . number_format($order->total, 0, ',', ' ') . ' FCFA. '
                . 'Client : ' . ($order->user?->name ?? '-') . '. Un livreur passe la recuperer, merci de la preparer.'
            );
            (new NexahSmsService())->send([$phone], mb_substr($message, 0, 450));
        } catch (\Throwable $e) {
            Log::error('SMS to restaurant failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    public static function newOrderAvailable(Order $order, bool $again = false): void
    {
        try {
            $drivers = User::where('role', 'driver')
                ->where('is_online', true)
                ->where('is_verified', true)
                ->where(function ($q) {
                    $q->where('push_token', 'like', 'ExponentPushToken%')
                      ->orWhere('fcm_token', 'like', 'ExponentPushToken%');
                })
                ->get();

            if ($drivers->isEmpty()) {
                return;
            }

            $restaurant = $order->restaurant;
            $title = $again ? '🛵 Commande à nouveau disponible !' : '🛵 Nouvelle commande disponible !';

            $notifications = $drivers->map(fn ($driver) => [
                'token' => str_starts_with((string) $driver->push_token, 'ExponentPushToken')
                    ? $driver->push_token
                    : $driver->fcm_token,
                'title' => $title,
                'body'  => "Restaurant {$restaurant?->name} — " . number_format($order->total, 0, ',', ' ') . ' XAF',
                'data'  => ['type' => 'new_order', 'order_id' => $order->id],
            ])->values()->toArray();

            (new ExpoPushNotificationService())->sendBulkPushNotifications($notifications);
        } catch (\Throwable $e) {
            Log::error('Push to drivers failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * SMS à tous les livreurs validés quand un client passe une commande.
     * Pas d'accents : un SMS accentué compte double chez l'opérateur.
     */
    public static function smsNewOrder(Order $order): void
    {
        try {
            $phones = User::where('role', 'driver')
                ->where('is_verified', true)
                ->whereNotNull('phone')
                ->pluck('phone')
                ->all();

            if (!$phones) {
                return;
            }

            $order->loadMissing(['restaurant:id,name', 'address']);
            $where = $order->address?->address ? ' -> ' . $order->address->address : '';
            $message = self::ascii(
                "Estuaire Eat: nouvelle commande {$order->order_number} chez {$order->restaurant?->name}{$where}. "
                . 'Total ' . number_format($order->total, 0, ',', ' ') . ' FCFA. Ouvrez l\'app livreur.'
            );

            (new NexahSmsService())->send($phones, mb_substr($message, 0, 300));
        } catch (\Throwable $e) {
            Log::error('SMS to drivers failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    private static function ascii(string $text): string
    {
        return \Illuminate\Support\Str::ascii($text);
    }
}

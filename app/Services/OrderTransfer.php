<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Transfert d'une commande vers un autre restaurant (plat épuisé, restaurant fermé…).
 * L'admin a appelé le client, qui accepte : on remplace le restaurant et les plats,
 * on recalcule les montants et on prévient le client, le nouveau restaurant et le livreur.
 */
class OrderTransfer
{
    // On ne peut plus transférer une commande que le livreur a déjà récupérée
    public const TRANSFERABLE_STATUSES = ['pending', 'confirmed', 'preparing', 'ready'];
    private const DELIVERY_NOT_PICKED = ['searching', 'assigned', 'going_to_restaurant', 'at_restaurant'];

    public static function canTransfer(Order $order): bool
    {
        $deliveryStatus = $order->delivery?->status;
        return in_array($order->status, self::TRANSFERABLE_STATUSES, true)
            && ($deliveryStatus === null || in_array($deliveryStatus, self::DELIVERY_NOT_PICKED, true));
    }

    /**
     * @param array<int, array{menu_item_id:int|string, quantity:int|string}> $items
     * @throws RuntimeException avec un message en français à afficher à l'admin
     */
    public static function transfer(Order $order, Restaurant $restaurant, array $items, ?string $note = null): Order
    {
        $order->loadMissing(['delivery', 'restaurant']);

        if (!self::canTransfer($order)) {
            throw new RuntimeException('Cette commande ne peut plus changer de restaurant (déjà récupérée, livrée ou annulée).');
        }
        if ($restaurant->id === $order->restaurant_id) {
            throw new RuntimeException('Choisissez un autre restaurant que le restaurant actuel.');
        }

        $items = array_values(array_filter($items, fn ($i) => !empty($i['menu_item_id']) && (int) ($i['quantity'] ?? 0) > 0));
        if (!$items) {
            throw new RuntimeException('Ajoutez au moins un plat du nouveau restaurant.');
        }

        // Plats : doivent appartenir au nouveau restaurant et être disponibles
        $itemsData = [];
        $subtotal = 0;
        foreach ($items as $item) {
            $menuItem = MenuItem::where('restaurant_id', $restaurant->id)->find($item['menu_item_id']);
            if (!$menuItem) {
                throw new RuntimeException("Un des plats choisis n'appartient pas à {$restaurant->name}.");
            }
            if (!$menuItem->is_available) {
                throw new RuntimeException("Le plat « {$menuItem->name} » n'est pas disponible chez {$restaurant->name}.");
            }
            $quantity = (int) $item['quantity'];
            $price = $menuItem->effective_price;
            $subtotal += $price * $quantity;
            $itemsData[] = [
                'menu_item_id' => $menuItem->id,
                'item_name'    => $menuItem->name,
                'item_price'   => $price,
                'quantity'     => $quantity,
                'subtotal'     => $price * $quantity,
            ];
        }

        // Mêmes règles de prix qu'à la commande : coupon, livraison offerte (GFS ou coupon)
        $coupon = $order->coupon_id ? Coupon::find($order->coupon_id) : null;
        $discount = 0;
        if ($coupon && $subtotal >= (float) $coupon->minimum_order) {
            $discount = match ($coupon->type) {
                'percentage'   => min($subtotal * ($coupon->value / 100), $coupon->maximum_discount ?? PHP_INT_MAX),
                'fixed_amount' => min($coupon->value, $subtotal),
                default        => 0,
            };
        }
        $freeDelivery = $order->payment_method === 'gfs' || $coupon?->type === 'free_delivery';
        $deliveryFee = $freeDelivery ? 0 : (float) $restaurant->delivery_fee;
        $total = $subtotal + $deliveryFee - $discount;

        // Déjà payé en ligne : on ne peut pas demander plus au client depuis l'admin
        if ($order->payment_status === 'paid' && $total > (float) $order->total) {
            throw new RuntimeException(
                'Le client a déjà payé ' . self::fcfa($order->total) . ' en ligne. La nouvelle commande coûte '
                . self::fcfa($total) . ' : choisissez des plats pour un montant égal ou inférieur.'
            );
        }

        $oldRestaurant = $order->restaurant;
        $oldTotal = (float) $order->total;

        DB::transaction(function () use ($order, $restaurant, $itemsData, $subtotal, $deliveryFee, $discount, $total, $note, $oldRestaurant) {
            $order->items()->delete();
            $order->items()->createMany($itemsData);

            $trace = '[Transférée de ' . ($oldRestaurant?->name ?? '?') . ' vers ' . $restaurant->name . ' le ' . now()->format('d/m/Y H:i')
                . ($note ? ' : ' . $note : '') . ']';

            // Sans les événements du modèle : on envoie nos propres messages ci-dessous,
            // et la commande repart « validée » pour que le nouveau restaurant la prépare.
            $order->forceFill([
                'restaurant_id'        => $restaurant->id,
                'subtotal'             => $subtotal,
                'delivery_fee'         => $deliveryFee,
                'discount_amount'      => $discount,
                'total'                => $total,
                'status'               => $order->status === 'pending' ? 'pending' : 'confirmed',
                'preparing_at'         => null,
                'estimated_ready_at'   => null,
                'estimated_delivery_at'=> now()->addMinutes($restaurant->delivery_time_max ?? 45),
                'special_instructions' => trim(($order->special_instructions ? $order->special_instructions . "\n" : '') . $trace),
            ])->saveQuietly();

            if ($order->delivery) {
                $order->delivery->forceFill([
                    'restaurant_latitude'  => $restaurant->latitude,
                    'restaurant_longitude' => $restaurant->longitude,
                    'pickup_address'       => $restaurant->address,
                ])->saveQuietly();
            }
        });

        $order->refresh()->load(['restaurant', 'items', 'user', 'delivery.driver', 'address']);
        self::notify($order, $oldTotal);

        return $order;
    }

    private static function notify(Order $order, float $oldTotal): void
    {
        $restaurant = $order->restaurant;
        $total = self::fcfa($order->total);
        $diff = $order->total != $oldTotal ? " Nouveau total : {$total}." : '';

        // Client : SMS + notification dans l'app
        try {
            CustomerSms::send($order, "Estuaire Eat: comme convenu, votre commande {$order->order_number} est maintenant preparee par {$restaurant->name}.{$diff}");
            \App\Models\CustomNotification::create([
                'user_id' => $order->user_id,
                'type'    => 'order',
                'title'   => 'Commande transférée',
                'body'    => "Votre commande #{$order->order_number} est maintenant préparée par {$restaurant->name}.{$diff}",
                'data'    => json_encode(['order_id' => $order->id, 'status' => $order->status]),
            ]);
            if ($order->user?->push_token) {
                (new ExpoPushNotificationService())->sendPushNotification(
                    $order->user->push_token,
                    'Commande transférée',
                    "Votre commande est maintenant préparée par {$restaurant->name}.{$diff}",
                    ['order_id' => $order->id, 'status' => $order->status]
                );
            }
        } catch (\Throwable $e) {
            Log::error('Transfer: customer notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        // Nouveau restaurant : WhatsApp + push aux admins du restaurant
        try {
            (new WhatsAppService())->notifyRestaurantNewOrder($order, $order->user);
            $admins = User::where('role', 'restaurant_admin')->where('restaurant_id', $restaurant->id)->get();
            $push = new ExpoPushNotificationService();
            foreach ($admins as $admin) {
                $token = $admin->push_token ?: $admin->fcm_token;
                if ($token && str_starts_with($token, 'ExponentPushToken')) {
                    $push->sendPushNotification($token, 'Nouvelle commande !', $order->items->count() . " article(s) — {$total}", ['type' => 'new_restaurant_order', 'order_id' => $order->id]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Transfer: restaurant notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }

        // Livreur déjà en route : il doit aller au nouveau restaurant
        $driver = $order->delivery?->driver;
        if ($driver) {
            try {
                $address = $restaurant->address ? " ({$restaurant->address})" : '';
                (new NexahSmsService())->send([$driver->phone], self::ascii(
                    "Estuaire Eat: la commande {$order->order_number} change de restaurant. Allez la chercher chez {$restaurant->name}{$address}."
                ));
                $token = $driver->push_token ?: $driver->fcm_token;
                if ($token && str_starts_with($token, 'ExponentPushToken')) {
                    (new ExpoPushNotificationService())->sendPushNotification($token, 'Changement de restaurant', "Allez chercher la commande chez {$restaurant->name}.", ['type' => 'order_transferred', 'order_id' => $order->id]);
                }
            } catch (\Throwable $e) {
                Log::error('Transfer: driver notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }
        }
    }

    private static function fcfa($amount): string
    {
        return number_format((float) $amount, 0, ',', ' ') . ' FCFA';
    }

    private static function ascii(string $text): string
    {
        return \Illuminate\Support\Str::ascii($text);
    }
}

<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;

/**
 * SMS au client : commande reçue, puis chaque changement de statut.
 * Textes sans accents : un SMS accentué compte double chez l'opérateur.
 */
class CustomerSms
{
    private const STATUS_TEXT = [
        'confirmed'  => 'a ete validee par le restaurant.',
        'preparing'  => 'est en preparation.',
        'ready'      => 'est prete. Un livreur va la recuperer.',
        'picked_up'  => 'a ete recuperee par le livreur.',
        'on_the_way' => 'est en route vers vous.',
        'delivered'  => 'a ete livree. Bon appetit !',
        'cancelled'  => 'a ete annulee.',
        'refunded'   => 'a ete remboursee.',
    ];

    public static function orderReceived(Order $order): void
    {
        $order->loadMissing('restaurant:id,name');
        self::send($order, "Estuaire Eat: commande {$order->order_number} recue chez {$order->restaurant?->name}. "
            . 'Total ' . number_format($order->total, 0, ',', ' ') . ' FCFA. Vous serez informe a chaque etape.');
    }

    public static function driverAssigned(Order $order, ?string $driverName): void
    {
        $who = $driverName ? "Le livreur {$driverName}" : 'Un livreur';
        self::send($order, "Estuaire Eat: {$who} part chercher votre commande {$order->order_number} au restaurant. Suivez-le en direct dans l'app.");
    }

    public static function statusChanged(Order $order, string $status): void
    {
        $text = self::STATUS_TEXT[$status] ?? null;
        if (!$text) {
            return;
        }
        self::send($order, "Estuaire Eat: votre commande {$order->order_number} {$text}");
    }

    public static function send(Order $order, string $message): void
    {
        try {
            $phone = $order->user()->value('phone');
            if (!$phone) {
                return;
            }
            (new NexahSmsService())->send([$phone], mb_substr(\Illuminate\Support\Str::ascii($message), 0, 300));
        } catch (\Throwable $e) {
            Log::error('SMS to customer failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}

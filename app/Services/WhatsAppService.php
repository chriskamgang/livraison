<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private string $serviceUrl;
    private string $secret;

    public function __construct()
    {
        $this->serviceUrl = config('services.whatsapp.url', 'http://localhost:3500');
        $this->secret     = config('services.whatsapp.secret', 'estuaire-wa-secret');
    }

    public function sendMessage(string $to, string $body): array
    {
        try {
            $number = preg_replace('/\D/', '', $to);
            if (strlen($number) === 9) $number = '237' . $number;

            $response = Http::timeout(10)
                ->withHeaders(['x-wa-secret' => $this->secret])
                ->post("{$this->serviceUrl}/send", [
                    'to'      => $number,
                    'message' => $body,
                ]);

            if (!$response->successful()) {
                Log::error('WhatsApp message failed', ['to' => $number, 'error' => $response->body()]);
            }

            return ['success' => $response->successful(), 'data' => $response->json()];

        } catch (\Exception $e) {
            Log::error('WhatsApp service error', ['message' => $e->getMessage(), 'to' => $to]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 🔔 Nouvelle commande → Restaurant
     */
    public function notifyRestaurantNewOrder($order, $customer): bool
    {
        $restaurant = $order->restaurant;
        if (empty($restaurant->phone)) return false;

        $method = $order->payment_method === 'cash' ? '💵 Paiement à la livraison' : '📱 Mobile Money';

        $message  = "🔔 *Nouvelle commande !*\n\n";
        $message .= "Commande *#{$order->order_number}*\n\n";
        $message .= "👤 *Client :* {$customer->name}\n";
        $message .= "📞 *Tél :* {$customer->phone}\n\n";
        $message .= "📦 *Articles :*\n";

        foreach ($order->items as $item) {
            $message .= "  • {$item->quantity}x {$item->item_name} — " . number_format($item->subtotal, 0, ',', ' ') . " XAF\n";
        }

        $message .= "\n💰 *Total :* " . number_format($order->total, 0, ',', ' ') . " XAF\n";
        $message .= "💳 *Paiement :* {$method}\n";
        $deliveryAddr = $order->address->address ?? $order->delivery?->delivery_address ?? 'N/A';
        $message .= "📍 *Livraison :* {$deliveryAddr}\n\n";
        $message .= "Merci de préparer la commande rapidement ! 🍽️\n\n";
        $message .= "_Estuaire Eat_ 🛵";

        return (bool) $this->sendMessage($restaurant->phone, $message)['success'];
    }

    /**
     * ✅ Commande reçue → Client
     */
    public function notifyOrderReceived($order, $customer): bool
    {
        if (empty($customer->phone)) return false;

        $restaurant = $order->restaurant;
        $method     = $order->payment_method === 'cash_on_delivery' ? '💵 Paiement à la livraison' : '📱 Mobile Money';

        $message  = "🎉 *Commande confirmée !*\n\n";
        $message .= "Bonjour *{$customer->name}* 👋\n\n";
        $message .= "Votre commande *#{$order->order_number}* a bien été reçue.\n\n";
        $message .= "🏪 *Restaurant :* {$restaurant->name}\n";
        $message .= "💰 *Total :* " . number_format($order->total, 0, ',', ' ') . " XAF\n";
        $message .= "📦 *Paiement :* {$method}\n";
        $message .= "📍 *Livraison :* {$order->delivery_address}\n\n";
        $message .= "Nous préparons votre commande avec soin ! ⏳\n\n";
        $message .= "_Estuaire Eat — Livraison rapide à Bafoussam_ 🛵";

        return (bool) $this->sendMessage($customer->phone, $message)['success'];
    }

    /**
     * 👨‍🍳 En préparation → Client
     */
    public function notifyOrderPreparing($order, $customer): bool
    {
        if (empty($customer->phone)) return false;

        $restaurant = $order->restaurant;

        $message  = "👨‍🍳 *En cours de préparation...*\n\n";
        $message .= "Bonjour *{$customer->name}*,\n\n";
        $message .= "Votre commande *#{$order->order_number}* est en cours de préparation chez *{$restaurant->name}*.\n\n";
        $message .= "⏱️ Temps estimé : *{$restaurant->delivery_time_min}–{$restaurant->delivery_time_max} min*\n\n";
        $message .= "On vous tient informé ! 😊\n\n";
        $message .= "_Estuaire Eat_ 🛵";

        return (bool) $this->sendMessage($customer->phone, $message)['success'];
    }

    /**
     * ✅ Commande prête → Client
     */
    public function notifyOrderReady($order, $customer): bool
    {
        if (empty($customer->phone)) return false;

        $message  = "✅ *Commande prête !*\n\n";
        $message .= "Bonjour *{$customer->name}*,\n\n";
        $message .= "Votre commande *#{$order->order_number}* est prête ! 🎉\n\n";
        $message .= "Un livreur va la récupérer et l'apporter chez vous très bientôt. 🛵\n\n";
        $message .= "_Estuaire Eat_ 🛵";

        return (bool) $this->sendMessage($customer->phone, $message)['success'];
    }

    /**
     * 🛵 Livraison assignée → Livreur
     */
    public function notifyDeliveryAssigned($order, $driver): bool
    {
        if (empty($driver->phone)) return false;

        $restaurant = $order->restaurant;
        $customer   = $order->user;

        $message  = "🛵 *Nouvelle livraison !*\n\n";
        $message .= "Bonjour *{$driver->name}* 👋\n\n";
        $message .= "Commande *#{$order->order_number}*\n\n";
        $message .= "🏪 *Récupérer chez :* {$restaurant->name}\n";
        $message .= "📍 *Adresse resto :* {$restaurant->address}\n\n";
        $message .= "👤 *Client :* {$customer->name}\n";
        $message .= "📞 *Tél client :* {$customer->phone}\n";
        $message .= "📍 *Livrer à :* {$order->delivery_address}\n\n";
        $message .= "💰 *Montant :* " . number_format($order->total, 0, ',', ' ') . " XAF\n";
        $message .= "💵 *Paiement :* " . ($order->payment_method === 'cash_on_delivery' ? 'À encaisser à la livraison' : 'Déjà payé') . "\n\n";
        $message .= "Bonne livraison ! 🚀\n\n";
        $message .= "_Estuaire Eat_";

        return (bool) $this->sendMessage($driver->phone, $message)['success'];
    }

    /**
     * 🚗 Livreur en route → Client
     */
    public function notifyDriverEnRoute($order, $driver, $customer): bool
    {
        if (empty($customer->phone)) return false;

        $message  = "🚗 *Votre commande est en route !*\n\n";
        $message .= "Bonjour *{$customer->name}*,\n\n";
        $message .= "Votre commande *#{$order->order_number}* est en cours de livraison.\n\n";
        $message .= "🛵 *Livreur :* {$driver->name}\n";
        $message .= "📞 *Contact :* {$driver->phone}\n\n";
        $message .= "Préparez-vous à recevoir votre commande ! 📦\n\n";
        $message .= "Bon appétit ! 😋\n\n";
        $message .= "_Estuaire Eat_ 🛵";

        return (bool) $this->sendMessage($customer->phone, $message)['success'];
    }

    /**
     * ✅ Commande livrée → Client
     */
    public function notifyOrderDelivered($order, $customer): bool
    {
        if (empty($customer->phone)) return false;

        $message  = "✅ *Commande livrée !*\n\n";
        $message .= "Bonjour *{$customer->name}*,\n\n";
        $message .= "Votre commande *#{$order->order_number}* a été livrée avec succès. 🎉\n\n";
        $message .= "Bon appétit ! 😋\n\n";
        $message .= "Vous pouvez noter votre expérience directement dans l'application Estuaire Eat. ⭐\n\n";
        $message .= "Merci de votre confiance et à très bientôt ! 🙏\n\n";
        $message .= "_Estuaire Eat_ 🛵";

        return (bool) $this->sendMessage($customer->phone, $message)['success'];
    }

    /**
     * ❌ Commande annulée → Client
     */
    public function notifyOrderCancelled($order, $customer, $reason = ''): bool
    {
        if (empty($customer->phone)) return false;

        $message  = "❌ *Commande annulée*\n\n";
        $message .= "Bonjour *{$customer->name}*,\n\n";
        $message .= "Nous sommes désolés, votre commande *#{$order->order_number}* a été annulée.\n\n";

        if ($reason) {
            $message .= "📋 *Raison :* {$reason}\n\n";
        }

        $message .= "Pour toute question, contactez-nous sur WhatsApp.\n\n";
        $message .= "Cordialement,\n";
        $message .= "_Estuaire Eat_ 🛵";

        return (bool) $this->sendMessage($customer->phone, $message)['success'];
    }

    /**
     * Call Center : nouvelle commande
     */
    public function notifyCallCenterNewOrder($order, $customer): void
    {
        $phones = config('services.call_center.phones', []);
        if (empty($phones)) return;

        $restaurant = $order->restaurant;
        $method = $order->payment_method === 'cash' ? 'Paiement a la livraison' : 'Mobile Money';
        $cookingTime = $restaurant->delivery_time_min . '-' . $restaurant->delivery_time_max . ' min';

        $message  = "** NOUVELLE COMMANDE **\n\n";
        $message .= "Commande *#{$order->order_number}*\n\n";
        $message .= "Restaurant : *{$restaurant->name}*\n";
        $message .= "Tel resto : {$restaurant->phone}\n\n";
        $message .= "Client : *{$customer->name}*\n";
        $message .= "Tel client : {$customer->phone}\n\n";
        $message .= "Articles :\n";

        if ($order->relationLoaded('items')) {
            foreach ($order->items as $item) {
                $message .= "  - {$item->quantity}x {$item->item_name}\n";
            }
        }

        $message .= "\nTotal : " . number_format($order->total, 0, ',', ' ') . " XAF\n";
        $message .= "Paiement : {$method}\n";
        $message .= "Temps de cuisson estime : {$cookingTime}\n\n";
        $message .= "ACTION : Appelez le restaurant pour confirmer la prise en charge.\n\n";
        $message .= "_Estuaire Eat - Call Center_";

        foreach ($phones as $phone) {
            $this->sendMessage($phone, $message);
        }
    }

    /**
     * Call Center : commande prete, appeler le livreur
     */
    public function notifyCallCenterOrderReady($order): void
    {
        $phones = config('services.call_center.phones', []);
        if (empty($phones)) return;

        $restaurant = $order->restaurant;
        $driver = $order->delivery?->driver;

        $message  = "** COMMANDE PRETE **\n\n";
        $message .= "Commande *#{$order->order_number}*\n\n";
        $message .= "Restaurant : *{$restaurant->name}*\n";
        $message .= "Tel resto : {$restaurant->phone}\n\n";

        if ($driver) {
            $message .= "Livreur : *{$driver->name}*\n";
            $message .= "Tel livreur : {$driver->phone}\n\n";
            $message .= "ACTION : Appelez le livreur pour qu'il recupere la commande.\n";
        } else {
            $message .= "Aucun livreur assigne.\n";
            $message .= "ACTION : Assignez un livreur ou attendez qu'un livreur accepte.\n";
        }

        $message .= "\n_Estuaire Eat - Call Center_";

        foreach ($phones as $phone) {
            $this->sendMessage($phone, $message);
        }
    }
}

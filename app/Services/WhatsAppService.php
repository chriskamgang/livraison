<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $instanceId;
    protected $token;
    protected $baseUrl;

    public function __construct()
    {
        $this->instanceId = config('services.ultramsg.instance_id');
        $this->token = config('services.ultramsg.token');
        $this->baseUrl = "https://api.ultramsg.com/{$this->instanceId}";
    }

    /**
     * Envoyer un message WhatsApp
     *
     * @param string $to Numéro de téléphone avec format international (ex: +237690000000)
     * @param string $body Contenu du message
     * @return array
     */
    public function sendMessage(string $to, string $body): array
    {
        try {
            // Nettoyer le numéro de téléphone (enlever espaces et tirets)
            $to = preg_replace('/[\s\-\(\)]/', '', $to);

            // S'assurer que le + est présent
            if (!str_starts_with($to, '+')) {
                $to = '+' . $to;
            }

            $response = Http::asForm()->post("{$this->baseUrl}/messages/chat", [
                'token' => $this->token,
                'to' => $to,
                'body' => $body,
            ]);

            $result = $response->json();

            if ($response->successful()) {
                Log::info('WhatsApp message sent', [
                    'to' => $to,
                    'response' => $result
                ]);
            } else {
                Log::error('WhatsApp message failed', [
                    'to' => $to,
                    'error' => $result
                ]);
            }

            return [
                'success' => $response->successful(),
                'data' => $result
            ];

        } catch (\Exception $e) {
            Log::error('WhatsApp service error', [
                'message' => $e->getMessage(),
                'to' => $to
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Notification : Commande reçue (Client)
     */
    public function notifyOrderReceived($order, $customer)
    {
        $restaurant = $order->restaurant;

        $message = "🎉 *Commande reçue avec succès !*\n\n";
        $message .= "Bonjour {$customer->name},\n\n";
        $message .= "Votre commande #{$order->id} a bien été enregistrée.\n\n";
        $message .= "📍 *Restaurant :* {$restaurant->name}\n";
        $message .= "💰 *Montant :* {$order->total} FCFA\n";
        $message .= "🚚 *Livraison :* {$order->delivery_address}\n\n";
        $message .= "Nous préparons votre commande avec soin !\n\n";
        $message .= "Merci de votre confiance 🙏\n";
        $message .= "_MyINSAM Resto_";

        return $this->sendMessage($customer->phone, $message);
    }

    /**
     * Notification : Livraison assignée (Livreur)
     */
    public function notifyDeliveryAssigned($order, $driver)
    {
        $restaurant = $order->restaurant;
        $customer = $order->user;

        $message = "🛵 *Nouvelle livraison assignée !*\n\n";
        $message .= "Bonjour {$driver->name},\n\n";
        $message .= "Commande *#{$order->id}*\n\n";
        $message .= "📍 *Restaurant :* {$restaurant->name}\n";
        $message .= "📍 *Adresse :* {$restaurant->address}\n\n";
        $message .= "👤 *Client :* {$customer->name}\n";
        $message .= "📞 *Tél :* {$customer->phone}\n";
        $message .= "🚚 *Livraison :* {$order->delivery_address}\n\n";
        $message .= "💰 *Montant :* {$order->total} FCFA\n\n";
        $message .= "Bonne livraison ! 🚀\n";
        $message .= "_Express INSAM_";

        return $this->sendMessage($driver->phone, $message);
    }

    /**
     * Notification : Livreur en route (Client)
     */
    public function notifyDriverEnRoute($order, $driver, $customer)
    {
        $message = "🚗 *Votre commande est en route !*\n\n";
        $message .= "Bonjour {$customer->name},\n\n";
        $message .= "Bonne nouvelle ! Votre commande #{$order->id} a été récupérée et est en cours de livraison.\n\n";
        $message .= "🛵 *Livreur :* {$driver->name}\n";
        $message .= "📞 *Contact :* {$driver->phone}\n\n";
        $message .= "⏱️ *Temps estimé :* 15-25 minutes\n\n";
        $message .= "Préparez-vous à recevoir votre commande ! 📦\n\n";
        $message .= "Bon appétit ! 😋\n";
        $message .= "_MyINSAM Resto_";

        return $this->sendMessage($customer->phone, $message);
    }

    /**
     * Notification : Commande livrée (Client)
     */
    public function notifyOrderDelivered($order, $customer)
    {
        $message = "✅ *Commande livrée !*\n\n";
        $message .= "Bonjour {$customer->name},\n\n";
        $message .= "Votre commande #{$order->id} a été livrée avec succès.\n\n";
        $message .= "Merci d'avoir commandé chez nous !\n\n";
        $message .= "N'hésitez pas à nous donner votre avis. 🌟\n\n";
        $message .= "À très bientôt ! 🙏\n";
        $message .= "_MyINSAM Resto_";

        return $this->sendMessage($customer->phone, $message);
    }

    /**
     * Notification : Commande annulée (Client)
     */
    public function notifyOrderCancelled($order, $customer, $reason = '')
    {
        $message = "❌ *Commande annulée*\n\n";
        $message .= "Bonjour {$customer->name},\n\n";
        $message .= "Nous sommes désolés, votre commande #{$order->id} a été annulée.\n\n";

        if ($reason) {
            $message .= "📋 *Raison :* {$reason}\n\n";
        }

        $message .= "Pour toute question, contactez-nous.\n\n";
        $message .= "Cordialement,\n";
        $message .= "_MyINSAM Resto_";

        return $this->sendMessage($customer->phone, $message);
    }

    /**
     * Notification : Commande en préparation (Client)
     */
    public function notifyOrderPreparing($order, $customer)
    {
        $restaurant = $order->restaurant;

        $message = "👨‍🍳 *Commande en préparation*\n\n";
        $message .= "Bonjour {$customer->name},\n\n";
        $message .= "Votre commande #{$order->id} est maintenant en cours de préparation chez {$restaurant->name}.\n\n";
        $message .= "⏱️ *Temps estimé :* 20-30 minutes\n\n";
        $message .= "Nous vous tiendrons informé de l'avancement.\n\n";
        $message .= "Merci de votre patience ! 😊\n";
        $message .= "_MyINSAM Resto_";

        return $this->sendMessage($customer->phone, $message);
    }

    /**
     * Notification : Commande prête (Client)
     */
    public function notifyOrderReady($order, $customer)
    {
        $message = "✅ *Commande prête !*\n\n";
        $message .= "Bonjour {$customer->name},\n\n";
        $message .= "Votre commande #{$order->id} est prête !\n\n";
        $message .= "Un livreur va bientôt la récupérer pour vous l'apporter.\n\n";
        $message .= "À tout de suite ! 🚀\n";
        $message .= "_MyINSAM Resto_";

        return $this->sendMessage($customer->phone, $message);
    }
}

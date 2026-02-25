<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number', 'user_id', 'restaurant_id', 'address_id', 'coupon_id',
        'status', 'subtotal', 'delivery_fee', 'discount_amount', 'total',
        'payment_method', 'payment_status',
        'special_instructions', 'cancellation_reason',
        'estimated_delivery_at', 'delivered_at',
    ];

    protected $casts = [
        'estimated_delivery_at' => 'datetime',
        'delivered_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relations
    public function user() { return $this->belongsTo(User::class); }
    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function address() { return $this->belongsTo(Address::class); }
    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function delivery() { return $this->hasOne(Delivery::class); }
    public function payment() { return $this->hasOne(Payment::class); }
    public function ratings() { return $this->hasMany(Rating::class); }

    // Helpers
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isDelivered(): bool { return $this->status === 'delivered'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    // Génère un numéro de commande unique
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            $order->order_number = 'RD-' . strtoupper(uniqid());
        });

        // Créer une notification lors du changement de statut
        static::updating(function ($order) {
            if ($order->isDirty('status')) {
                $oldStatus = $order->getOriginal('status');
                $newStatus = $order->status;

                // Messages de notification selon le statut
                $messages = [
                    'confirmed'  => ['title' => 'Commande confirmée', 'body' => 'Votre commande #' . $order->order_number . ' a été confirmée et est en cours de préparation.'],
                    'preparing'  => ['title' => 'En préparation', 'body' => 'Votre commande #' . $order->order_number . ' est en cours de préparation.'],
                    'ready'      => ['title' => 'Commande prête', 'body' => 'Votre commande #' . $order->order_number . ' est prête et sera bientôt livrée.'],
                    'assigned'   => ['title' => 'Livreur assigné', 'body' => 'Un livreur a été assigné à votre commande #' . $order->order_number . '.'],
                    'on_the_way' => ['title' => 'En route', 'body' => 'Votre commande #' . $order->order_number . ' est en route vers vous!'],
                    'delivered'  => ['title' => 'Livraison effectuée', 'body' => 'Votre commande #' . $order->order_number . ' a été livrée. Bon appétit!'],
                    'cancelled'  => ['title' => 'Commande annulée', 'body' => 'Votre commande #' . $order->order_number . ' a été annulée.'],
                ];

                if (isset($messages[$newStatus])) {
                    // Créer la notification dans la base de données
                    \App\Models\Notification::create([
                        'user_id' => $order->user_id,
                        'type'    => 'order',
                        'title'   => $messages[$newStatus]['title'],
                        'body'    => $messages[$newStatus]['body'],
                        'data'    => json_encode(['order_id' => $order->id, 'status' => $newStatus]),
                    ]);

                    // Envoyer la notification push via Expo
                    $user = \App\Models\User::find($order->user_id);
                    if ($user && $user->push_token) {
                        $pushService = new \App\Services\ExpoPushNotificationService();
                        $pushService->sendPushNotification(
                            $user->push_token,
                            $messages[$newStatus]['title'],
                            $messages[$newStatus]['body'],
                            ['order_id' => $order->id, 'status' => $newStatus]
                        );
                    }
                }
            }
        });
    }
}

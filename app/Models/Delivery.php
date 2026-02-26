<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Delivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'driver_id', 'status',
        'restaurant_latitude', 'restaurant_longitude',
        'delivery_latitude', 'delivery_longitude',
        'pickup_address', 'delivery_address', 'delivery_address_details',
        'distance_km', 'estimated_minutes',
        'delivery_proof_photo', 'notes',
        'assigned_at', 'picked_up_at', 'delivered_at',
        'driver_earnings',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'delivery_latitude' => 'decimal:8',
        'delivery_longitude' => 'decimal:8',
        'restaurant_latitude' => 'decimal:8',
        'restaurant_longitude' => 'decimal:8',
    ];

    // Relations
    public function order() { return $this->belongsTo(Order::class); }
    public function driver() { return $this->belongsTo(User::class, 'driver_id'); }
    public function locations() { return $this->hasMany(DeliveryLocation::class); }

    // Dernière position connue du livreur
    public function lastLocation() { return $this->hasOne(DeliveryLocation::class)->latestOfMany('recorded_at'); }

    // Scopes
    public function scopeActive($query) {
        return $query->whereNotIn('status', ['delivered', 'failed']);
    }

    // Envoyer des notifications au livreur lors du changement de statut
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($delivery) {
            if ($delivery->isDirty('status')) {
                $oldStatus = $delivery->getOriginal('status');
                $newStatus = $delivery->status;

                // Messages de notification selon le statut de livraison
                $messages = [
                    'assigned'   => ['title' => 'Nouvelle commande assignée', 'body' => 'Une nouvelle commande vous a été assignée. Rendez-vous au restaurant pour la récupérer.'],
                    'picked_up'  => ['title' => 'En route vers le client', 'body' => 'Vous avez récupéré la commande. Direction l\'adresse de livraison!'],
                    'delivered'  => ['title' => 'Livraison terminée', 'body' => 'Félicitations! La livraison a été confirmée avec succès.'],
                    'failed'     => ['title' => 'Livraison échouée', 'body' => 'La livraison n\'a pas pu être effectuée.'],
                ];

                if (isset($messages[$newStatus]) && $delivery->driver_id) {
                    // Créer la notification dans la base de données
                    \App\Models\Notification::create([
                        'user_id' => $delivery->driver_id,
                        'type'    => 'delivery',
                        'title'   => $messages[$newStatus]['title'],
                        'body'    => $messages[$newStatus]['body'],
                        'data'    => json_encode(['delivery_id' => $delivery->id, 'order_id' => $delivery->order_id, 'status' => $newStatus]),
                    ]);

                    // Envoyer la notification push via Expo au livreur
                    $driver = \App\Models\User::find($delivery->driver_id);
                    if ($driver && $driver->push_token) {
                        $pushService = new \App\Services\ExpoPushNotificationService();
                        $pushService->sendPushNotification(
                            $driver->push_token,
                            $messages[$newStatus]['title'],
                            $messages[$newStatus]['body'],
                            ['delivery_id' => $delivery->id, 'order_id' => $delivery->order_id, 'status' => $newStatus]
                        );
                    }
                }
            }
        });
    }
}

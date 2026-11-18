<?php

namespace App\Filament\Pages;

use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\User;
use Filament\Pages\Page;
use Livewire\Attributes\Renderless;

/**
 * Carte temps réel des livreurs : positions, statut en ligne,
 * livraisons actives et trajet GPS (vers le restaurant puis vers le client).
 *
 * Le rafraîchissement se fait côté JS (Alpine) qui appelle getMapData()
 * toutes les 10 s via $wire ; la méthode est #[Renderless] donc Livewire
 * ne re-rend pas (et ne morphe pas) le DOM de la carte.
 */
class DriverMapPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-map';
    protected static ?string $navigationLabel = 'Suivi des livreurs';
    protected static ?string $navigationGroup = 'Commandes';
    protected static ?string $title           = 'Suivi des livreurs';
    protected static ?string $slug            = 'suivi-livreurs';
    protected static ?int    $navigationSort  = 3;
    protected static string  $view            = 'filament.pages.driver-map-page';

    public const ACTIVE_STATUSES = [
        'assigned', 'going_to_restaurant', 'at_restaurant', 'picked_up', 'on_the_way', 'arrived',
    ];

    public const STATUS_LABELS = [
        'searching'           => 'Recherche de livreur',
        'assigned'            => 'Assignée',
        'going_to_restaurant' => 'En route vers le restaurant',
        'at_restaurant'       => 'Au restaurant',
        'picked_up'           => 'Commande récupérée',
        'on_the_way'          => 'En route vers le client',
        'arrived'             => 'Arrivé chez le client',
        'delivered'           => 'Livrée',
        'failed'              => 'Échouée',
    ];

    private const PATH_POINTS = 300;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'super_admin']);
    }

    #[Renderless]
    public function getMapData(): array
    {
        abort_unless(static::canAccess(), 403);

        $now = now();

        $deliveries = Delivery::with(['order.restaurant', 'order.user', 'driver'])
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('driver_id')
            ->orderBy('assigned_at')
            ->get();

        $deliveryData = $deliveries->map(function (Delivery $d) use ($now) {
            $order      = $d->order;
            $restaurant = $order?->restaurant;

            $points = DeliveryLocation::where('delivery_id', $d->id)
                ->orderByDesc('recorded_at')
                ->limit(self::PATH_POINTS)
                ->get(['latitude', 'longitude', 'recorded_at'])
                ->reverse();

            // Segment avant récupération (vers le restaurant) / après (vers le client)
            $toRestaurant = [];
            $toCustomer   = [];
            foreach ($points as $p) {
                $pt = [(float) $p->latitude, (float) $p->longitude];
                if ($d->picked_up_at && $p->recorded_at && $p->recorded_at->gte($d->picked_up_at)) {
                    $toCustomer[] = $pt;
                } else {
                    $toRestaurant[] = $pt;
                }
            }
            // Relier les deux segments pour un tracé continu
            if ($toRestaurant && $toCustomer) {
                array_unshift($toCustomer, end($toRestaurant));
            }

            $since = $d->assigned_at ?? $d->created_at;

            return [
                'id'              => $d->id,
                'order_id'        => $d->order_id,
                'order_number'    => $order?->order_number,
                'status'          => $d->status,
                'status_label'    => self::STATUS_LABELS[$d->status] ?? $d->status,
                'picked_up'       => in_array($d->status, ['picked_up', 'on_the_way', 'arrived']),
                'driver_id'       => $d->driver_id,
                'driver_name'     => $d->driver?->name,
                'restaurant_name' => $restaurant?->name,
                'restaurant'      => $this->latLng(
                    $d->restaurant_latitude ?? $restaurant?->latitude,
                    $d->restaurant_longitude ?? $restaurant?->longitude,
                ),
                'customer_name'    => $order?->user?->name,
                'delivery_address' => $d->delivery_address,
                'customer'         => $this->latLng($d->delivery_latitude, $d->delivery_longitude),
                'elapsed_seconds'  => $since ? (int) $since->diffInSeconds($now, true) : null,
                'path_to_restaurant' => array_values($toRestaurant),
                'path_to_customer'   => array_values($toCustomer),
            ];
        })->values();

        $byDriver = $deliveryData->keyBy('driver_id');

        $drivers = User::drivers()
            ->orderByDesc('is_online')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'phone', 'is_online', 'is_verified', 'current_latitude', 'current_longitude', 'updated_at'])
            ->map(function (User $u) use ($byDriver, $now) {
                $current = $byDriver->get($u->id);

                return [
                    'id'                => $u->id,
                    'name'              => $u->name,
                    'phone'             => $u->phone,
                    'is_online'         => (bool) $u->is_online,
                    'is_verified'       => (bool) $u->is_verified,
                    'position'          => $this->latLng($u->current_latitude, $u->current_longitude),
                    'last_seen_seconds' => $u->updated_at ? (int) $u->updated_at->diffInSeconds($now, true) : null,
                    'current_delivery'  => $current ? [
                        'id'           => $current['id'],
                        'order_number' => $current['order_number'],
                        'status'       => $current['status'],
                        'status_label' => $current['status_label'],
                    ] : null,
                ];
            })->values();

        return [
            'drivers'      => $drivers->all(),
            'deliveries'   => $deliveryData->all(),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    private function latLng($lat, $lng): ?array
    {
        if ($lat === null || $lng === null || ((float) $lat == 0.0 && (float) $lng == 0.0)) {
            return null;
        }

        return [(float) $lat, (float) $lng];
    }
}

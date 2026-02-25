<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    // GET /api/orders
    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with(['restaurant:id,name,logo', 'items', 'delivery'])
            ->latest()
            ->paginate(15);

        return response()->json($orders);
    }

    // POST /api/orders
    public function store(Request $request)
    {
        $validated = $request->validate([
            'restaurant_id'        => 'required|exists:restaurants,id',
            'address_id'           => 'required|exists:addresses,id',
            'items'                => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.options'      => 'nullable|array',
            'items.*.special_instructions' => 'nullable|string',
            'payment_method'       => 'required|in:cash,mtn_momo,orange_money',
            'coupon_code'          => 'nullable|string',
            'special_instructions' => 'nullable|string',
        ]);

        // Vérifier que l'adresse appartient à l'utilisateur
        $address = \App\Models\Address::where('id', $validated['address_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $restaurant = \App\Models\Restaurant::findOrFail($validated['restaurant_id']);

        // Calculer le sous-total
        $subtotal = 0;
        $itemsData = [];

        foreach ($validated['items'] as $item) {
            $menuItem = \App\Models\MenuItem::findOrFail($item['menu_item_id']);

            if (!$menuItem->is_available) {
                return response()->json(['message' => "Le plat '{$menuItem->name}' n'est plus disponible."], 422);
            }

            $price    = $menuItem->effective_price;
            $quantity = $item['quantity'];
            $sub      = $price * $quantity;
            $subtotal += $sub;

            $itemsData[] = [
                'menu_item_id'         => $menuItem->id,
                'item_name'            => $menuItem->name,
                'item_price'           => $price,
                'quantity'             => $quantity,
                'subtotal'             => $sub,
                'options'              => $item['options'] ?? null,
                'special_instructions' => $item['special_instructions'] ?? null,
            ];
        }

        // Gérer coupon
        $discountAmount = 0;
        $couponId       = null;

        if (!empty($validated['coupon_code'])) {
            $coupon = \App\Models\Coupon::where('code', $validated['coupon_code'])->first();

            if ($coupon && $coupon->isValid() && $subtotal >= $coupon->minimum_order) {
                $couponId = $coupon->id;
                $discountAmount = match ($coupon->type) {
                    'percentage'   => min($subtotal * ($coupon->value / 100), $coupon->maximum_discount ?? PHP_INT_MAX),
                    'fixed_amount' => min($coupon->value, $subtotal),
                    'free_delivery'=> $restaurant->delivery_fee,
                    default        => 0,
                };
            }
        }

        $deliveryFee = ($couponId && \App\Models\Coupon::find($couponId)?->type === 'free_delivery') ? 0 : $restaurant->delivery_fee;
        $total       = $subtotal + $deliveryFee - $discountAmount;

        // Créer la commande
        $order = \App\Models\Order::create([
            'user_id'              => $request->user()->id,
            'restaurant_id'        => $restaurant->id,
            'address_id'           => $address->id,
            'coupon_id'            => $couponId,
            'status'               => 'pending',
            'subtotal'             => $subtotal,
            'delivery_fee'         => $deliveryFee,
            'discount_amount'      => $discountAmount,
            'total'                => $total,
            'payment_method'       => $validated['payment_method'],
            'payment_status'       => 'pending',
            'special_instructions' => $validated['special_instructions'] ?? null,
            'estimated_delivery_at'=> now()->addMinutes($restaurant->delivery_time_max),
        ]);

        // Créer les articles
        $order->items()->createMany($itemsData);

        // Créer la livraison
        \App\Models\Delivery::create([
            'order_id'                => $order->id,
            'status'                  => 'searching',
            'delivery_latitude'       => $address->latitude,
            'delivery_longitude'      => $address->longitude,
            'delivery_address'        => $address->address,
            'delivery_address_details'=> $address->address_details,
            'restaurant_latitude'     => $restaurant->latitude,
            'restaurant_longitude'    => $restaurant->longitude,
        ]);

        // Incrémenter usage coupon
        if ($couponId) {
            \App\Models\Coupon::find($couponId)->increment('usage_count');
        }

        // Envoyer notification WhatsApp au client
        try {
            $whatsappService = new WhatsAppService();
            $whatsappService->notifyOrderReceived($order->load('restaurant'), $request->user());
        } catch (\Exception $e) {
            \Log::error('WhatsApp notification failed', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'message' => 'Commande passée avec succès !',
            'order'   => $order->load(['items', 'delivery', 'restaurant:id,name,logo']),
        ], 201);
    }

    // GET /api/orders/{id}
    public function show(Request $request, $id)
    {
        $order = $request->user()->orders()
            ->with(['restaurant', 'items.menuItem', 'delivery.driver', 'payment', 'address'])
            ->findOrFail($id);

        return response()->json($order);
    }

    // GET /api/orders/{id}/track
    public function track(Request $request, $id)
    {
        $order = $request->user()->orders()
            ->with([
                'delivery.driver:id,name,avatar,phone,rating,vehicle_type,current_latitude,current_longitude',
                'restaurant:id,name,address,latitude,longitude',
                'address',
            ])
            ->findOrFail($id);

        $driver = $order->delivery?->driver;
        $driverLocation = null;

        if ($driver && $driver->current_latitude && $driver->current_longitude) {
            $driverLocation = [
                'latitude'  => (float) $driver->current_latitude,
                'longitude' => (float) $driver->current_longitude,
            ];
        }

        return response()->json([
            'order_status'     => $order->status,
            'delivery_status'  => $order->delivery?->status,
            'driver'           => [
                'id'           => $driver->id ?? null,
                'name'         => $driver->name ?? null,
                'avatar'       => $driver->avatar ?? null,
                'phone'        => $driver->phone ?? null,
                'rating'       => $driver->rating ?? null,
                'vehicle_type' => $driver->vehicle_type ?? null,
            ],
            'driver_location'  => $driverLocation,
            'estimated_at'     => $order->estimated_delivery_at,
            'restaurant_coords'=> [
                'lat' => (float) ($order->restaurant?->latitude ?? 0),
                'lng' => (float) ($order->restaurant?->longitude ?? 0),
            ],
            'delivery_coords'  => [
                'lat' => (float) ($order->address?->latitude ?? 0),
                'lng' => (float) ($order->address?->longitude ?? 0),
            ],
        ]);
    }

    // POST /api/orders/{id}/cancel
    public function cancel(Request $request, $id)
    {
        $order = $request->user()->orders()->findOrFail($id);

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json(['message' => 'Impossible d\'annuler cette commande.'], 422);
        }

        $order->update([
            'status'              => 'cancelled',
            'cancellation_reason' => $request->get('reason', 'Annulé par le client'),
        ]);

        return response()->json(['message' => 'Commande annulée.', 'order' => $order]);
    }

    // POST /api/orders/{id}/rate
    public function rate(Request $request, $id)
    {
        $validated = $request->validate([
            'restaurant_rating' => 'required|integer|min:1|max:5',
            'driver_rating'     => 'nullable|integer|min:1|max:5',
            'restaurant_comment'=> 'nullable|string|max:500',
            'driver_comment'    => 'nullable|string|max:500',
        ]);

        $order = $request->user()->orders()
            ->with('delivery')
            ->findOrFail($id);

        if ($order->status !== 'delivered') {
            return response()->json(['message' => 'Vous ne pouvez noter qu\'une commande livrée.'], 422);
        }

        // Note restaurant
        \App\Models\Rating::updateOrCreate(
            ['order_id' => $order->id, 'user_id' => $request->user()->id, 'type' => 'restaurant'],
            ['restaurant_id' => $order->restaurant_id, 'rating' => $validated['restaurant_rating'], 'comment' => $validated['restaurant_comment'] ?? null]
        );

        // Mettre à jour la note moyenne du restaurant
        $avgRating = \App\Models\Rating::where('restaurant_id', $order->restaurant_id)->where('type', 'restaurant')->avg('rating');
        $count     = \App\Models\Rating::where('restaurant_id', $order->restaurant_id)->where('type', 'restaurant')->count();
        $order->restaurant->update(['rating' => round($avgRating, 2), 'ratings_count' => $count]);

        // Note livreur
        if (!empty($validated['driver_rating']) && $order->delivery?->driver_id) {
            \App\Models\Rating::updateOrCreate(
                ['order_id' => $order->id, 'user_id' => $request->user()->id, 'type' => 'driver'],
                ['driver_id' => $order->delivery->driver_id, 'rating' => $validated['driver_rating'], 'comment' => $validated['driver_comment'] ?? null]
            );

            $driverAvg   = \App\Models\Rating::where('driver_id', $order->delivery->driver_id)->where('type', 'driver')->avg('rating');
            $driverCount = \App\Models\Rating::where('driver_id', $order->delivery->driver_id)->where('type', 'driver')->count();
            \App\Models\User::find($order->delivery->driver_id)->update(['rating' => round($driverAvg, 2), 'ratings_count' => $driverCount]);
        }

        return response()->json(['message' => 'Merci pour votre avis !']);
    }

    // POST /api/coupons/validate
    public function validateCoupon(Request $request)
    {
        $request->validate([
            'code'          => 'required|string',
            'restaurant_id' => 'required|exists:restaurants,id',
            'subtotal'      => 'required|numeric',
        ]);

        $coupon = \App\Models\Coupon::where('code', $request->code)->first();

        if (!$coupon || !$coupon->isValid()) {
            return response()->json(['message' => 'Code promo invalide ou expiré.'], 422);
        }

        if ($request->subtotal < $coupon->minimum_order) {
            return response()->json(['message' => "Commande minimum requise : {$coupon->minimum_order} XAF"], 422);
        }

        $discount = match ($coupon->type) {
            'percentage'   => min($request->subtotal * ($coupon->value / 100), $coupon->maximum_discount ?? PHP_INT_MAX),
            'fixed_amount' => min($coupon->value, $request->subtotal),
            'free_delivery'=> \App\Models\Restaurant::find($request->restaurant_id)->delivery_fee,
            default        => 0,
        };

        return response()->json([
            'valid'         => true,
            'coupon'        => $coupon,
            'discount'      => $discount,
        ]);
    }
}

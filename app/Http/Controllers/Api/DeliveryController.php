<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    // POST /api/driver/toggle-online
    public function toggleOnline(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json(['message' => 'Accès réservé aux livreurs.'], 403);
        }

        $user->update(['is_online' => !$user->is_online]);

        return response()->json([
            'message'   => $user->is_online ? 'Vous êtes maintenant en ligne.' : 'Vous êtes hors ligne.',
            'is_online' => $user->is_online,
        ]);
    }

    // POST /api/driver/location — Envoi position GPS haute précision
    public function updateLocation(Request $request)
    {
        $validated = $request->validate([
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'accuracy'    => 'nullable|numeric',
            'speed'       => 'nullable|numeric',
            'heading'     => 'nullable|numeric',
            'delivery_id' => 'nullable|exists:deliveries,id',
        ]);

        $user = $request->user();

        // Mettre à jour la position courante du livreur
        $user->update([
            'current_latitude'  => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
        ]);

        // Sauvegarder dans l'historique GPS si livraison active
        if (!empty($validated['delivery_id'])) {
            \App\Models\DeliveryLocation::create([
                'delivery_id' => $validated['delivery_id'],
                'driver_id'   => $user->id,
                'latitude'    => $validated['latitude'],
                'longitude'   => $validated['longitude'],
                'accuracy'    => $validated['accuracy'] ?? null,
                'speed'       => $validated['speed'] ?? null,
                'heading'     => $validated['heading'] ?? null,
                'recorded_at' => now(),
            ]);

            // Diffuser la position via WebSocket
            // broadcast(new DriverLocationUpdated($user->id, $validated))->toOthers();
        }

        return response()->json(['message' => 'Position mise à jour.']);
    }

    // GET /api/driver/available-orders
    public function availableOrders(Request $request)
    {
        $user = $request->user();

        if (!$user->is_online) {
            return response()->json(['message' => 'Vous êtes hors ligne.'], 403);
        }

        $orders = \App\Models\Order::with(['restaurant:id,name,logo,address,latitude,longitude', 'delivery', 'user:id,name,phone'])
            ->whereHas('delivery', fn($q) => $q->where('status', 'searching'))
            ->where('status', 'ready')
            ->latest()
            ->take(10)
            ->get();

        return response()->json($orders);
    }

    // POST /api/driver/orders/{id}/accept
    public function acceptOrder(Request $request, $id)
    {
        $driver = $request->user();

        $order = \App\Models\Order::with('delivery')->findOrFail($id);

        if ($order->delivery->status !== 'searching') {
            return response()->json(['message' => 'Cette commande a déjà été prise.'], 422);
        }

        $order->delivery->update([
            'driver_id'   => $driver->id,
            'status'      => 'assigned',
            'assigned_at' => now(),
        ]);

        return response()->json([
            'message'  => 'Commande acceptée !',
            'delivery' => $order->delivery->load(['order.restaurant', 'order.address']),
        ]);
    }

    // POST /api/driver/orders/{id}/reject
    public function rejectOrder(Request $request, $id)
    {
        return response()->json(['message' => 'Commande refusée.']);
    }

    // POST /api/driver/deliveries/{id}/update-status
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:going_to_restaurant,at_restaurant,picked_up,on_the_way,arrived,delivered,failed',
        ]);

        $delivery = \App\Models\Delivery::where('driver_id', $request->user()->id)
            ->findOrFail($id);

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'picked_up') {
            $updates['picked_up_at'] = now();
            $delivery->order->update(['status' => 'picked_up']);
        }

        if ($validated['status'] === 'on_the_way') {
            $delivery->order->update(['status' => 'on_the_way']);
        }

        if ($validated['status'] === 'delivered') {
            $updates['delivered_at'] = now();
            $delivery->order->update(['status' => 'delivered', 'delivered_at' => now()]);
        }

        $delivery->update($updates);

        return response()->json(['message' => 'Statut mis à jour.', 'delivery' => $delivery]);
    }

    // POST /api/driver/deliveries/{id}/proof
    public function uploadProof(Request $request, $id)
    {
        $request->validate(['photo' => 'required|image|max:5120']);

        $delivery = \App\Models\Delivery::where('driver_id', $request->user()->id)->findOrFail($id);

        $path = $request->file('photo')->store('delivery-proofs', 'public');
        $delivery->update(['delivery_proof_photo' => $path]);

        return response()->json(['message' => 'Photo enregistrée.', 'path' => $path]);
    }

    // GET /api/driver/deliveries
    public function history(Request $request)
    {
        // Retourner toutes les livraisons (en cours + historique)
        $deliveries = \App\Models\Delivery::where('driver_id', $request->user()->id)
            ->with(['order.restaurant:id,name,logo,address', 'order.user:id,name', 'order.address'])
            ->latest()
            ->paginate(15);

        return response()->json($deliveries);
    }

    // GET /api/driver/earnings
    public function earnings(Request $request)
    {
        $driver = $request->user();

        $today  = \App\Models\Delivery::where('driver_id', $driver->id)->where('status', 'delivered')
            ->whereDate('delivered_at', today())->sum('driver_earnings');

        $week   = \App\Models\Delivery::where('driver_id', $driver->id)->where('status', 'delivered')
            ->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('driver_earnings');

        $month  = \App\Models\Delivery::where('driver_id', $driver->id)->where('status', 'delivered')
            ->whereMonth('delivered_at', now()->month)->sum('driver_earnings');

        $total  = \App\Models\Delivery::where('driver_id', $driver->id)->where('status', 'delivered')->sum('driver_earnings');

        return response()->json([
            'today'         => $today,
            'this_week'     => $week,
            'this_month'    => $month,
            'total'         => $total,
            'wallet_balance'=> $driver->wallet_balance,
        ]);
    }

    // GET /api/driver/orders/{id} — Détail d'une commande pour le livreur
    public function orderDetail(Request $request, $id)
    {
        $driver = $request->user();

        // L'order doit être soit disponible (ready) soit assigné au livreur
        $order = \App\Models\Order::with([
            'items',
            'delivery',
            'user:id,name,phone',
            'address',
            'restaurant:id,name,address,latitude,longitude',
        ])->findOrFail($id);

        // Vérifier que le livreur est autorisé à voir cette commande
        $delivery = $order->delivery;
        $isAssigned   = $delivery && $delivery->driver_id === $driver->id;
        $isAvailable  = $order->status === 'ready' && $delivery && $delivery->status === 'searching';

        if (!$isAssigned && !$isAvailable) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        // Mapper "user" → "client" pour l'app livreur
        $orderData = $order->toArray();
        $orderData['client'] = [
            'name'  => $order->user->name  ?? null,
            'phone' => $order->user->phone ?? null,
        ];
        $orderData['delivery_address'] = [
            'full_address' => $order->address->address ?? null,
        ];
        $orderData['note'] = $order->special_instructions;

        // Mapper les items
        $orderData['items'] = $order->items->map(fn($item) => [
            'name'       => $item->item_name,
            'quantity'   => $item->quantity,
            'unit_price' => $item->item_price,
        ])->toArray();

        $orderData['total_amount']    = $order->total;
        $orderData['payment_method']  = $order->payment_method;

        return response()->json($orderData);
    }

    // GET /api/driver/stats
    public function stats(Request $request)
    {
        $driver = $request->user();

        $totalDeliveries  = \App\Models\Delivery::where('driver_id', $driver->id)->where('status', 'delivered')->count();
        $todayDeliveries  = \App\Models\Delivery::where('driver_id', $driver->id)->where('status', 'delivered')
            ->whereDate('delivered_at', today())->count();

        // Calculer les gains du jour
        $todayEarnings = \App\Models\Delivery::where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->sum('driver_earnings');

        // Calculer les gains totaux
        $totalEarnings = \App\Models\Delivery::where('driver_id', $driver->id)
            ->where('status', 'delivered')
            ->sum('driver_earnings');

        return response()->json([
            'total_deliveries' => $totalDeliveries,
            'today_deliveries' => $todayDeliveries,
            'today_earnings'   => (float) $todayEarnings,
            'total_earnings'   => (float) $totalEarnings,
            'rating'           => $driver->rating,
            'ratings_count'    => $driver->ratings_count,
            'is_online'        => $driver->is_online,
            'is_verified'      => $driver->is_verified,
        ]);
    }

    // GET /api/deliveries/{deliveryId}/driver-location — Position du livreur pour le client
    public function getDriverLocation(Request $request, $deliveryId)
    {
        $user = $request->user();

        $delivery = \App\Models\Delivery::with('driver:id,name,phone,current_latitude,current_longitude')
            ->findOrFail($deliveryId);

        // Vérifier que c'est bien la commande du client
        if ($delivery->order->user_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        // Retourner la position actuelle du livreur
        return response()->json([
            'driver' => [
                'id'        => $delivery->driver->id ?? null,
                'name'      => $delivery->driver->name ?? null,
                'phone'     => $delivery->driver->phone ?? null,
                'latitude'  => $delivery->driver->current_latitude ?? null,
                'longitude' => $delivery->driver->current_longitude ?? null,
            ],
            'status' => $delivery->status,
            'updated_at' => $delivery->updated_at,
        ]);
    }
}

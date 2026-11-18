<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Services\DriverNotifier;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    // Étapes d'une livraison, dans l'ordre (le livreur peut en sauter, jamais revenir en arrière)
    private const FLOW = ['assigned', 'going_to_restaurant', 'at_restaurant', 'picked_up', 'on_the_way', 'arrived', 'delivered'];

    // Une commande est proposée aux livreurs dès qu'elle est validée
    // (espèces : tout de suite ; paiement en ligne : une fois payée)
    public const PICKABLE_ORDER_STATUSES = ['confirmed', 'preparing', 'ready'];

    /**
     * L'app livreur (1.0.4) n'accepte que les commandes « preparing » ou « ready ».
     * Une commande validée lui est donc présentée comme « en préparation » :
     * le livreur la voit et l'accepte dès la validation, sans nouvelle version de l'app.
     */
    private static function statusForDriverApp(string $status): string
    {
        return $status === 'confirmed' ? 'preparing' : $status;
    }

    private function driverOnly(Request $request)
    {
        if ($request->user()->role !== 'driver') {
            return response()->json(['message' => 'Accès réservé aux livreurs.'], 403);
        }
        return null;
    }

    // POST /api/driver/toggle-online
    public function toggleOnline(Request $request)
    {
        $user = $request->user();

        if ($user->role !== 'driver') {
            return response()->json(['message' => 'Accès réservé aux livreurs.'], 403);
        }

        // Si le client envoie une valeur explicite, l'utiliser; sinon toggle
        $isOnline = $request->has('is_online') ? (bool) $request->is_online : !$user->is_online;
        $user->update(['is_online' => $isOnline]);

        return response()->json([
            'message'   => $isOnline ? 'Vous êtes maintenant en ligne.' : 'Vous êtes hors ligne.',
            'is_online' => $isOnline,
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

        if ($denied = $this->driverOnly($request)) return $denied;

        $user = $request->user();

        if (!empty($validated['delivery_id'])
            && !Delivery::where('id', $validated['delivery_id'])->where('driver_id', $user->id)->exists()) {
            return response()->json(['message' => 'Cette livraison ne vous est pas assignée.'], 403);
        }

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
        if ($denied = $this->driverOnly($request)) return $denied;

        $user = $request->user();

        if (!$user->is_verified) {
            return response()->json(['message' => 'Votre compte livreur n\'est pas encore validé.'], 403);
        }

        if (!$user->is_online) {
            return response()->json(['message' => 'Vous êtes hors ligne.'], 403);
        }

        $orders = \App\Models\Order::with(['restaurant:id,name,logo,address,latitude,longitude', 'delivery', 'user:id,name,phone', 'address'])
            ->whereHas('delivery', fn($q) => $q->where('status', 'searching'))
            ->whereIn('status', self::PICKABLE_ORDER_STATUSES)
            // Une commande oubliée par un restaurant ne doit pas traîner chez les livreurs
            ->where('created_at', '>=', now()->subDay())
            ->latest()
            ->take(10)
            ->get();

        return response()->json($orders->each(fn ($o) => $o->setAttribute('status', self::statusForDriverApp($o->status))));
    }

    // POST /api/driver/orders/{id}/accept
    public function acceptOrder(Request $request, $id)
    {
        if ($denied = $this->driverOnly($request)) return $denied;

        $driver = $request->user();

        if (!$driver->is_verified) {
            return response()->json(['message' => 'Votre compte livreur n\'est pas encore validé.'], 403);
        }
        if (!$driver->is_online) {
            return response()->json(['message' => 'Passez en ligne pour accepter une commande.'], 403);
        }

        $order = Order::findOrFail($id);
        $driverEarnings = ($driver->driver_type === 'externe') ? 1000 : 750;

        // Verrou : si deux livreurs acceptent en même temps, un seul l'obtient
        $accepted = DB::transaction(function () use ($order, $driver, $driverEarnings) {
            $delivery = Delivery::where('order_id', $order->id)->lockForUpdate()->first();

            if (!$delivery || $delivery->status !== 'searching' || !in_array($order->fresh()->status, self::PICKABLE_ORDER_STATUSES, true)) {
                return false;
            }

            $delivery->update([
                'driver_id'       => $driver->id,
                'status'          => 'assigned',
                'assigned_at'     => now(),
                'driver_earnings' => $driverEarnings,
            ]);
            return true;
        });

        if (!$accepted) {
            return response()->json(['message' => 'Cette commande n\'est plus disponible.'], 422);
        }

        $order->load('delivery');

        // Notification WhatsApp au livreur
        try {
            $whatsappService = new WhatsAppService();
            $whatsappService->notifyDeliveryAssigned($order->load('restaurant', 'user'), $driver);
        } catch (\Exception $e) {
            \Log::error('WhatsApp notification failed', ['error' => $e->getMessage()]);
        }

        // SMS au client : un livreur part chercher sa commande
        dispatch(fn () => \App\Services\CustomerSms::driverAssigned($order, $driver->name))->afterResponse();

        return response()->json([
            'message'  => 'Commande acceptée !',
            'delivery' => $order->delivery->load(['order.restaurant', 'order.address']),
        ]);
    }

    // POST /api/driver/orders/{id}/reject — Annuler/refuser une livraison
    public function rejectOrder(Request $request, $id)
    {
        if ($denied = $this->driverOnly($request)) return $denied;

        $driver = $request->user();
        $order = \App\Models\Order::with('delivery', 'restaurant')->findOrFail($id);
        $delivery = $order->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'Aucune livraison trouvée.'], 404);
        }

        // Si le livreur avait accepté, remettre en recherche
        if ($delivery->driver_id === $driver->id && in_array($delivery->status, ['assigned', 'going_to_restaurant', 'at_restaurant'], true)) {
            $delivery->update([
                'driver_id'      => null,
                'status'         => 'searching',
                'assigned_at'    => null,
                'driver_earnings' => 0,
            ]);

            // Re-notifier tous les livreurs en ligne
            DriverNotifier::newOrderAvailable($order, true);

            return response()->json(['message' => 'Livraison annulée. La commande est disponible pour les autres livreurs.']);
        }

        return response()->json(['message' => 'Commande refusée.']);
    }

    // POST /api/driver/deliveries/{id}/update-status
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:going_to_restaurant,at_restaurant,picked_up,on_the_way,arrived,delivered,failed',
            'notes'  => 'nullable|string|max:500',
        ]);

        $delivery = Delivery::where('driver_id', $request->user()->id)->findOrFail($id);
        $current  = $delivery->status;
        $next     = $validated['status'];

        // Même statut renvoyé (double appui) : rien à faire
        if ($current === $next) {
            return response()->json(['message' => 'Statut déjà à jour.', 'delivery' => $delivery]);
        }

        if (!in_array($current, self::FLOW, true) || $current === 'delivered') {
            return response()->json(['message' => 'Cette livraison est terminée.'], 422);
        }

        if ($next !== 'failed') {
            $from = array_search($current, self::FLOW, true);
            $to   = array_search($next, self::FLOW, true);

            if ($to < $from) {
                return response()->json(['message' => 'Impossible de revenir à une étape précédente.'], 422);
            }
            if ($to > array_search('picked_up', self::FLOW, true) && $from < array_search('picked_up', self::FLOW, true)) {
                return response()->json(['message' => 'Récupérez d\'abord la commande au restaurant.'], 422);
            }
        }

        $updates = ['status' => $next];
        if (!empty($validated['notes'])) $updates['notes'] = $validated['notes'];
        if ($next === 'picked_up') $updates['picked_up_at'] = now();
        if ($next === 'delivered') $updates['delivered_at'] = now();

        // La livraison d'abord : les événements de la commande voient le bon état
        $delivery->update($updates);
        $order = $delivery->order;

        if ($next === 'picked_up') {
            $order->update(['status' => 'picked_up']);
        }

        if ($next === 'on_the_way') {
            $order->update(['status' => 'on_the_way']);

            // Notification WhatsApp au client : livreur en route
            try {
                (new WhatsAppService())->notifyDriverEnRoute($order->load('user'), $request->user(), $order->user);
            } catch (\Exception $e) {
                \Log::error('WhatsApp notification failed', ['error' => $e->getMessage()]);
            }
        }

        if ($next === 'delivered') {
            $orderUpdates = ['status' => 'delivered', 'delivered_at' => now()];
            // Espèces encaissées par le livreur à la remise
            if ($order->payment_status === 'cash_on_delivery') {
                $orderUpdates['payment_status'] = 'paid';
            }
            $order->update($orderUpdates);

            // Notification WhatsApp au client : commande livrée
            try {
                (new WhatsAppService())->notifyOrderDelivered($order->load('user'), $order->user);
            } catch (\Exception $e) {
                \Log::error('WhatsApp notification failed', ['error' => $e->getMessage()]);
            }
        }

        if ($next === 'failed') {
            $order->update([
                'status'              => 'cancelled',
                'cancellation_reason' => 'Livraison échouée' . (!empty($validated['notes']) ? ' : ' . $validated['notes'] : ''),
            ]);
        }

        return response()->json(['message' => 'Statut mis à jour.', 'delivery' => $delivery->fresh()]);
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
            ->whereYear('delivered_at', now()->year)->whereMonth('delivered_at', now()->month)->sum('driver_earnings');

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
        $isAvailable  = in_array($order->status, self::PICKABLE_ORDER_STATUSES, true) && $delivery && $delivery->status === 'searching';

        if (!$isAssigned && !$isAvailable) {
            return response()->json(['message' => 'Accès refusé.'], 403);
        }

        // Mapper "user" → "client" pour l'app livreur
        $orderData = $order->toArray();
        $orderData['status'] = self::statusForDriverApp($order->status);
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

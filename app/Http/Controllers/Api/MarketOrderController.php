<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketOrder;
use Illuminate\Http\Request;

class MarketOrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = MarketOrder::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($orders);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'items'               => 'required|array|min:1',
            'items.*.name'        => 'required|string',
            'items.*.quantity'    => 'required|numeric|min:1',
            'items.*.unit'        => 'nullable|string',
            'items.*.estimated_price' => 'nullable|numeric|min:0',
            'notes'               => 'nullable|string',
            'delivery_address'    => 'required|string',
            'delivery_latitude'   => 'nullable|numeric',
            'delivery_longitude'  => 'nullable|numeric',
            'payment_method'      => 'required|in:cash,mobile_money',
        ]);

        $estimatedTotal = collect($validated['items'])->sum(function ($item) {
            return ($item['estimated_price'] ?? 0) * $item['quantity'];
        });

        $order = MarketOrder::create([
            'user_id'           => $request->user()->id,
            'items'             => $validated['items'],
            'notes'             => $validated['notes'] ?? null,
            'delivery_address'  => $validated['delivery_address'],
            'delivery_latitude' => $validated['delivery_latitude'] ?? null,
            'delivery_longitude'=> $validated['delivery_longitude'] ?? null,
            'estimated_total'   => $estimatedTotal,
            'service_fee'       => 500,
            'delivery_fee'      => 500,
            'payment_method'    => $validated['payment_method'],
        ]);

        return response()->json([
            'message' => 'Commande marché créée',
            'order'   => $order,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $order = MarketOrder::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json($order);
    }

    public function cancel(Request $request, $id)
    {
        $order = MarketOrder::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if (!in_array($order->status, ['pending', 'accepted'])) {
            return response()->json(['message' => 'Impossible d\'annuler cette commande'], 422);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Commande annulée',
            'order'   => $order->fresh(),
        ]);
    }

    public function track(Request $request, $id)
    {
        $order = MarketOrder::where('user_id', $request->user()->id)
            ->with('driver:id,name,phone,current_latitude,current_longitude')
            ->findOrFail($id);

        return response()->json([
            'order'  => $order,
            'driver' => $order->driver,
        ]);
    }

    public function validatePhotos(Request $request, $id)
    {
        $order = MarketOrder::where('user_id', $request->user()->id)
            ->findOrFail($id);

        if ($order->status !== 'photo_sent') {
            return response()->json(['message' => 'Pas de photos à valider'], 422);
        }

        $request->validate([
            'validated' => 'required|boolean',
        ]);

        if ($request->validated) {
            $order->update([
                'validated_by_client' => true,
                'status' => 'validated',
            ]);
            return response()->json(['message' => 'Photos validées, le livreur arrive', 'order' => $order->fresh()]);
        } else {
            $order->update(['status' => 'shopping']);
            return response()->json(['message' => 'Photos refusées, le livreur va corriger', 'order' => $order->fresh()]);
        }
    }

    // ============ DRIVER ENDPOINTS ============

    public function availableForDriver(Request $request)
    {
        $orders = MarketOrder::where('status', 'pending')
            ->whereNull('driver_id')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($orders);
    }

    public function acceptForDriver(Request $request, $id)
    {
        $order = MarketOrder::where('status', 'pending')
            ->whereNull('driver_id')
            ->findOrFail($id);

        $order->update([
            'driver_id' => $request->user()->id,
            'status'    => 'accepted',
        ]);

        return response()->json(['message' => 'Commande acceptée', 'order' => $order->fresh()]);
    }

    public function updateStatusForDriver(Request $request, $id)
    {
        $order = MarketOrder::where('driver_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'status' => 'required|in:shopping,photo_sent,delivering,delivered',
        ]);

        $newStatus = $request->status;
        $updateData = ['status' => $newStatus];

        if ($newStatus === 'delivered') {
            $updateData['delivered_at'] = now();
        }

        $order->update($updateData);

        return response()->json(['message' => 'Statut mis à jour', 'order' => $order->fresh()]);
    }

    public function uploadPhotos(Request $request, $id)
    {
        $order = MarketOrder::where('driver_id', $request->user()->id)->findOrFail($id);

        $request->validate([
            'photos'   => 'required|array|min:1',
            'photos.*' => 'image|max:5120',
            'actual_total' => 'required|numeric|min:0',
        ]);

        $photoPaths = [];
        foreach ($request->file('photos') as $photo) {
            $path = $photo->store('market-proofs', 'public');
            $photoPaths[] = $path;
        }

        $order->update([
            'photo_proof'  => $photoPaths,
            'actual_total' => $request->actual_total,
            'status'       => 'photo_sent',
        ]);

        return response()->json(['message' => 'Photos envoyées au client', 'order' => $order->fresh()]);
    }
}

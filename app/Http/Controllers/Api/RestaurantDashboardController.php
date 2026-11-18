<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;

class RestaurantDashboardController extends Controller
{
    private function getRestaurant(Request $request): ?Restaurant
    {
        $user = $request->user();
        if ($user->role !== 'restaurant_admin') return null;
        return Restaurant::where('id', $user->restaurant_id)->first();
    }

    // GET /api/restaurant/dashboard
    public function dashboard(Request $request)
    {
        $restaurant = $this->getRestaurant($request);
        if (!$restaurant) return response()->json(['message' => 'Acces refuse.'], 403);

        $today = now()->toDateString();

        $todayOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $today)->count();

        $todayRevenue = Order::where('restaurant_id', $restaurant->id)
            ->whereDate('created_at', $today)
            ->whereIn('status', ['confirmed', 'preparing', 'ready', 'assigned', 'on_the_way', 'delivered'])
            ->sum('total');

        $pendingOrders = Order::where('restaurant_id', $restaurant->id)
            ->whereIn('status', ['confirmed', 'pending'])->count();

        $preparingOrders = Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'preparing')->count();

        $readyOrders = Order::where('restaurant_id', $restaurant->id)
            ->where('status', 'ready')->count();

        $totalOrders = Order::where('restaurant_id', $restaurant->id)->count();

        $totalRevenue = Order::where('restaurant_id', $restaurant->id)
            ->whereIn('status', ['delivered'])->sum('total');

        return response()->json([
            'restaurant'       => $restaurant->only(['id', 'name', 'logo', 'is_open', 'phone']),
            'today_orders'     => $todayOrders,
            'today_revenue'    => (float) $todayRevenue,
            'pending_orders'   => $pendingOrders,
            'preparing_orders' => $preparingOrders,
            'ready_orders'     => $readyOrders,
            'total_orders'     => $totalOrders,
            'total_revenue'    => (float) $totalRevenue,
        ]);
    }

    // GET /api/restaurant/orders
    public function orders(Request $request)
    {
        $restaurant = $this->getRestaurant($request);
        if (!$restaurant) return response()->json(['message' => 'Acces refuse.'], 403);

        $query = Order::where('restaurant_id', $restaurant->id)
            ->with(['user:id,name,phone', 'items', 'delivery.driver:id,name,phone', 'address']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->latest()->paginate(20);

        return response()->json($orders);
    }

    // GET /api/restaurant/orders/{id}
    public function orderDetail(Request $request, $id)
    {
        $restaurant = $this->getRestaurant($request);
        if (!$restaurant) return response()->json(['message' => 'Acces refuse.'], 403);

        $order = Order::where('restaurant_id', $restaurant->id)
            ->with(['user:id,name,phone', 'items', 'delivery.driver:id,name,phone', 'address'])
            ->findOrFail($id);

        return response()->json($order);
    }

    // POST /api/restaurant/orders/{id}/status
    public function updateStatus(Request $request, $id)
    {
        $restaurant = $this->getRestaurant($request);
        if (!$restaurant) return response()->json(['message' => 'Acces refuse.'], 403);

        $validated = $request->validate([
            'status' => 'required|in:confirmed,preparing,ready,cancelled',
        ]);

        $order = Order::where('restaurant_id', $restaurant->id)->findOrFail($id);

        $allowedTransitions = [
            'pending'   => ['confirmed', 'cancelled'],
            'confirmed' => ['preparing', 'ready', 'cancelled'],
            'preparing' => ['ready', 'cancelled'],
        ];

        $allowed = $allowedTransitions[$order->status] ?? [];
        if (!in_array($validated['status'], $allowed)) {
            return response()->json([
                'message' => "Impossible de passer de '{$order->status}' a '{$validated['status']}'."
            ], 422);
        }

        $order->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Statut mis a jour.',
            'order'   => $order->fresh()->load(['user:id,name,phone', 'items', 'delivery.driver:id,name,phone']),
        ]);
    }

    // POST /api/restaurant/toggle-open
    public function toggleOpen(Request $request)
    {
        $restaurant = $this->getRestaurant($request);
        if (!$restaurant) return response()->json(['message' => 'Acces refuse.'], 403);

        $isOpen = $request->has('is_open') ? (bool) $request->is_open : !$restaurant->is_open;
        $restaurant->update(['is_open' => $isOpen]);

        return response()->json([
            'message' => $isOpen ? 'Restaurant ouvert.' : 'Restaurant ferme.',
            'is_open' => $isOpen,
        ]);
    }
}

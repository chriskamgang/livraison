<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    // GET /api/restaurants
    public function index(Request $request)
    {
        $query = \App\Models\Restaurant::with('category')
            ->active()->open();

        // Recherche par nom
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Filtre par catégorie
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Tri
        $sort = $request->get('sort', 'rating');
        match ($sort) {
            'delivery_time' => $query->orderBy('delivery_time_min'),
            'delivery_fee'  => $query->orderBy('delivery_fee'),
            default         => $query->orderByDesc('rating'),
        };

        return response()->json($query->paginate(15));
    }

    // GET /api/restaurants/featured
    public function featured()
    {
        $restaurants = \App\Models\Restaurant::with('category')
            ->active()->open()->featured()
            ->orderByDesc('rating')
            ->take(10)
            ->get();

        return response()->json($restaurants);
    }

    // GET /api/restaurants/categories
    public function categories()
    {
        $categories = \App\Models\RestaurantCategory::active()->get();
        return response()->json($categories);
    }

    // GET /api/restaurants/{id}
    public function show($id)
    {
        $restaurant = \App\Models\Restaurant::with(['category', 'ratings' => function ($q) {
            $q->latest()->take(5);
        }])->findOrFail($id);

        return response()->json($restaurant);
    }

    // GET /api/restaurants/{id}/menu
    public function menu($id)
    {
        $restaurant = \App\Models\Restaurant::findOrFail($id);

        $menu = \App\Models\MenuCategory::where('restaurant_id', $id)
            ->active()
            ->with(['items' => function ($q) {
                $q->available()->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'restaurant' => $restaurant->only(['id', 'name', 'logo', 'delivery_fee', 'delivery_time_min', 'delivery_time_max', 'minimum_order', 'rating']),
            'menu'       => $menu,
        ]);
    }
}

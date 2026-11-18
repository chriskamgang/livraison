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

        // Restaurants mis en avant par l'admin toujours en premier
        self::pinnedFirst($query);

        // Tri
        $sort = $request->get('sort', 'rating');
        match ($sort) {
            'delivery_time' => $query->orderBy('delivery_time_min'),
            'delivery_fee'  => $query->orderBy('delivery_fee'),
            default         => $query->orderByDesc('rating'),
        };

        return response()->json(self::markPinned($query->paginate(15)));
    }

    // IDs à afficher en tête de liste (env PINNED_RESTAURANT_IDS, ex. « 23 »)
    private static function pinnedIds(): array
    {
        return array_values(array_filter(array_map('intval', config('services.pinned_restaurant_ids', []))));
    }

    private static function pinnedFirst($query): void
    {
        $ids = self::pinnedIds();
        if ($ids) {
            $query->orderByRaw('CASE WHEN restaurants.id IN (' . implode(',', $ids) . ') THEN 0 ELSE 1 END');
        }
    }

    // Ajoute is_pinned pour que l'app garde ces restaurants en tête (tri par distance)
    private static function markPinned($restaurants)
    {
        $ids = self::pinnedIds();
        $restaurants->each(fn ($r) => $r->setAttribute('is_pinned', in_array($r->id, $ids, true)));
        return $restaurants;
    }

    // GET /api/restaurants/featured
    public function featured()
    {
        $restaurants = \App\Models\Restaurant::with('category')
            ->active()->open()->featured()
            ->tap(fn ($q) => self::pinnedFirst($q))
            ->orderByDesc('rating')
            ->take(10)
            ->get();

        return response()->json(self::markPinned($restaurants));
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
                $q->available()->orderBy('sort_order')
                  ->with(['optionGroups' => function ($og) {
                      $og->with(['items' => function ($oi) {
                          $oi->where('is_available', true)->orderBy('sort_order');
                      }]);
                  }]);
            }])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'restaurant' => $restaurant->only(['id', 'name', 'logo', 'delivery_fee', 'delivery_time_min', 'delivery_time_max', 'minimum_order', 'rating']),
            'menu'       => $menu,
        ]);
    }
}

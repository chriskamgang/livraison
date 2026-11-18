<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    // GET /api/favorites → { restaurants: [...], items: [...] }
    public function index(Request $request)
    {
        $favorites = Favorite::where('user_id', $request->user()->id)
            ->with(['restaurant.category', 'menuItem.restaurant'])
            ->latest()
            ->get();

        return response()->json([
            'restaurants' => $favorites->pluck('restaurant')->filter()->values(),
            'items'       => $favorites->pluck('menuItem')->filter()->values(),
        ]);
    }

    // POST /api/favorites/toggle  { type: restaurant|item, id } → { favorited: bool }
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:restaurant,item',
            'id'   => 'required|integer',
        ]);

        $column = $validated['type'] === 'restaurant' ? 'restaurant_id' : 'menu_item_id';
        $model  = $validated['type'] === 'restaurant' ? Restaurant::class : MenuItem::class;
        $model::findOrFail($validated['id']);

        $existing = Favorite::where('user_id', $request->user()->id)
            ->where($column, $validated['id'])
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['favorited' => false]);
        }

        Favorite::create(['user_id' => $request->user()->id, $column => $validated['id']]);
        return response()->json(['favorited' => true]);
    }
}

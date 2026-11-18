<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PinnedRestaurantTest extends TestCase
{
    use RefreshDatabase;

    public function test_pinned_restaurant_comes_first(): void
    {
        $make = fn ($name, $rating) => Restaurant::create([
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'phone' => '69' . random_int(1000000, 9999999),
            'address' => 'Bafoussam', 'latitude' => 5.48, 'longitude' => 10.41, 'is_active' => true, 'is_open' => true, 'is_featured' => true, 'rating' => $rating,
        ]);
        $make('Top', 4.9);
        $estuaire = $make('Estuaire', 0);
        config(['services.pinned_restaurant_ids' => [(string) $estuaire->id]]);

        $this->getJson('/api/restaurants')->assertOk()
            ->assertJsonPath('data.0.id', $estuaire->id)->assertJsonPath('data.0.is_pinned', true)
            ->assertJsonPath('data.1.is_pinned', false);
        $this->getJson('/api/restaurants/featured')->assertOk()->assertJsonPath('0.id', $estuaire->id);
    }
}

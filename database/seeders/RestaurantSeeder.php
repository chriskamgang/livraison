<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RestaurantSeeder extends Seeder
{
    public function run(): void
    {
        // UN SEUL restaurant
        $restaurant = \App\Models\Restaurant::create([
            'name'              => 'Mon Restaurant',
            'slug'              => 'mon-restaurant',
            'description'       => 'Le meilleur restaurant de la ville. Commandez en ligne, livraison rapide.',
            'phone'             => '+237 690 000 000',
            'email'             => 'contact@monrestaurant.cm',
            'address'           => 'Centre-ville, Douala',
            'latitude'          => 4.0511,
            'longitude'         => 9.7085,
            'city'              => 'Douala',
            'delivery_fee'      => 500,
            'delivery_time_min' => 20,
            'delivery_time_max' => 40,
            'minimum_order'     => 2000,
            'rating'            => 0,
            'is_active'         => true,
            'is_featured'       => true,
            'is_open'           => true,
            'opening_hours'     => [
                'mon' => ['open' => '10:00', 'close' => '22:00'],
                'tue' => ['open' => '10:00', 'close' => '22:00'],
                'wed' => ['open' => '10:00', 'close' => '22:00'],
                'thu' => ['open' => '10:00', 'close' => '22:00'],
                'fri' => ['open' => '10:00', 'close' => '23:00'],
                'sat' => ['open' => '10:00', 'close' => '23:00'],
                'sun' => ['open' => '12:00', 'close' => '21:00'],
            ],
        ]);

        // Catégories + articles de démonstration
        $menu = [
            [
                'category' => 'Burgers',
                'items' => [
                    ['name' => 'Classic Burger',  'price' => 2500, 'description' => 'Steak bœuf, laitue, tomate, oignon, ketchup'],
                    ['name' => 'Cheese Burger',   'price' => 3000, 'description' => 'Double fromage fondu, bacon croustillant'],
                    ['name' => 'Poulet Burger',   'price' => 2800, 'description' => 'Filet de poulet grillé, sauce spéciale'],
                    ['name' => 'Végé Burger',     'price' => 2200, 'description' => 'Galette légumes, avocat frais'],
                ],
            ],
            [
                'category' => 'Accompagnements',
                'items' => [
                    ['name' => 'Frites maison',   'price' => 800,  'description' => 'Pommes de terre fraîches'],
                    ['name' => 'Nuggets x6',      'price' => 1200, 'description' => 'Poulet pané croustillant'],
                    ['name' => 'Salade coleslaw', 'price' => 600,  'description' => 'Chou, carotte, mayonnaise'],
                ],
            ],
            [
                'category' => 'Boissons',
                'items' => [
                    ['name' => 'Coca-Cola 33cl',  'price' => 400,  'description' => ''],
                    ['name' => 'Jus bissap',      'price' => 500,  'description' => 'Jus de fleurs d\'hibiscus'],
                    ['name' => 'Eau minérale',    'price' => 300,  'description' => '50cl'],
                ],
            ],
        ];

        foreach ($menu as $i => $catData) {
            $cat = $restaurant->menuCategories()->create([
                'name'       => $catData['category'],
                'is_active'  => true,
                'sort_order' => $i,
            ]);

            foreach ($catData['items'] as $j => $item) {
                $cat->items()->create([
                    'restaurant_id'    => $restaurant->id,
                    'name'             => $item['name'],
                    'description'      => $item['description'],
                    'price'            => $item['price'],
                    'is_available'     => true,
                    'preparation_time' => 15,
                    'sort_order'       => $j,
                ]);
            }
        }

        $this->command->info('Restaurant et menu créés avec succès !');
    }
}

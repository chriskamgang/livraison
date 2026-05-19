<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     * Uses firstOrCreate to avoid duplicating existing data.
     */
    public function run(): void
    {
        // Super admin
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@restaurant.cm'],
            [
                'name'       => 'Super Admin',
                'phone'      => '+237 699 000 001',
                'password'   => bcrypt('admin123'),
                'role'       => 'super_admin',
                'status'     => 'active',
                'is_verified'=> true,
            ]
        );

        // Client de test
        \App\Models\User::firstOrCreate(
            ['email' => 'client@test.cm'],
            [
                'name'       => 'Jean Dupont',
                'phone'      => '+237 677 111 222',
                'password'   => bcrypt('password'),
                'role'       => 'client',
                'status'     => 'active',
                'is_verified'=> true,
            ]
        );

        // Livreur de test
        \App\Models\User::firstOrCreate(
            ['email' => 'driver@test.cm'],
            [
                'name'          => 'Paul Livreur',
                'phone'         => '+237 655 333 444',
                'password'      => bcrypt('password'),
                'role'          => 'driver',
                'status'        => 'active',
                'is_verified'   => true,
                'vehicle_type'  => 'moto',
                'vehicle_number'=> 'LT-1234-DL',
                'license_number'=> 'DL-987654',
                'current_latitude' => 4.0511,
                'current_longitude'=> 9.7085,
            ]
        );

        // Restaurants + menus
        $this->call(RestaurantSeeder::class);

        // Coupon de bienvenue
        \App\Models\Coupon::firstOrCreate(
            ['code' => 'BIENVENUE'],
            [
                'description'    => '10% de réduction sur votre première commande',
                'type'           => 'percentage',
                'value'          => 10,
                'minimum_order'  => 2000,
                'maximum_discount'=> 2000,
                'per_user_limit' => 1,
                'is_active'      => true,
            ]
        );

        \App\Models\Coupon::firstOrCreate(
            ['code' => 'LIVGRATUIT'],
            [
                'description'   => 'Livraison gratuite',
                'type'          => 'free_delivery',
                'value'         => 0,
                'minimum_order' => 3000,
                'per_user_limit'=> 3,
                'is_active'     => true,
            ]
        );
    }
}

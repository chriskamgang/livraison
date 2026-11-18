<?php

namespace Tests\Feature;

use App\Filament\Pages\DriverMapPage;
use App\Models\Delivery;
use App\Models\DeliveryLocation;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DriverMapPageTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $email, array $extra = []): User
    {
        static $phone = 691000100;
        return User::create(array_merge([
            'name' => ucfirst($role), 'email' => $email, 'phone' => (string) $phone++,
            'password' => Hash::make('secret123'), 'role' => $role, 'status' => 'active',
        ], $extra));
    }

    private function seedActiveDelivery(): array
    {
        $client = $this->user('client', 'client@test.cm');
        $online = $this->user('driver', 'online@test.cm', [
            'name' => 'Paul Livreur', 'is_online' => true, 'is_verified' => true,
            'current_latitude' => 5.48, 'current_longitude' => 10.42,
        ]);
        $offline = $this->user('driver', 'offline@test.cm', ['name' => 'Jean Repos', 'is_online' => false]);

        $restaurant = Restaurant::create([
            'name' => 'Chez Maman', 'slug' => 'chez-maman', 'phone' => '690000001', 'address' => 'Bafoussam',
            'latitude' => 5.4781, 'longitude' => 10.4176, 'is_active' => true, 'is_open' => true, 'delivery_fee' => 500,
        ]);
        $order = Order::create([
            'user_id' => $client->id, 'restaurant_id' => $restaurant->id, 'status' => 'on_the_way',
            'subtotal' => 2500, 'delivery_fee' => 500, 'total' => 3000, 'payment_method' => 'cash',
        ]);
        $pickedUpAt = now()->subMinutes(5);
        $delivery = Delivery::create([
            'order_id' => $order->id, 'driver_id' => $online->id, 'status' => 'on_the_way',
            'restaurant_latitude' => 5.4781, 'restaurant_longitude' => 10.4176,
            'delivery_latitude' => 5.47, 'delivery_longitude' => 10.42, 'delivery_address' => 'Tamdja',
            'assigned_at' => now()->subMinutes(15), 'picked_up_at' => $pickedUpAt,
        ]);
        foreach ([[10, 5.476, 10.415], [7, 5.4781, 10.4176], [3, 5.475, 10.418], [1, 5.48, 10.42]] as [$min, $lat, $lng]) {
            DeliveryLocation::create([
                'delivery_id' => $delivery->id, 'driver_id' => $online->id,
                'latitude' => $lat, 'longitude' => $lng, 'recorded_at' => now()->subMinutes($min),
            ]);
        }
        // Livraison terminée : ne doit pas apparaître
        $doneOrder = Order::create([
            'user_id' => $client->id, 'restaurant_id' => $restaurant->id, 'status' => 'delivered',
            'subtotal' => 2500, 'delivery_fee' => 500, 'total' => 3000, 'payment_method' => 'cash',
        ]);
        Delivery::create(['order_id' => $doneOrder->id, 'driver_id' => $offline->id, 'status' => 'delivered', 'delivery_address' => 'Tamdja']);

        return compact('online', 'offline', 'order', 'delivery');
    }

    public function test_admin_can_open_the_driver_map_page(): void
    {
        $admin = $this->user('admin', 'admin@test.cm');

        $this->actingAs($admin)->get('/admin/suivi-livreurs')
            ->assertOk()
            ->assertSee('Suivi des livreurs')
            ->assertSee('driverMapPage', false);
    }

    public function test_map_data_returns_drivers_and_active_deliveries(): void
    {
        $s = $this->seedActiveDelivery();
        $this->actingAs($this->user('admin', 'admin@test.cm'));

        $data = (new DriverMapPage())->getMapData();

        $this->assertCount(2, $data['drivers']);
        $first = $data['drivers'][0];
        $this->assertSame($s['online']->id, $first['id']);   // en ligne d'abord
        $this->assertTrue($first['is_online']);
        $this->assertEquals([5.48, 10.42], $first['position']);
        $this->assertSame($s['order']->order_number, $first['current_delivery']['order_number']);
        $this->assertSame('En route vers le client', $first['current_delivery']['status_label']);
        $this->assertArrayHasKey('last_seen_seconds', $first);
        $this->assertNull($data['drivers'][1]['position']);
        $this->assertNull($data['drivers'][1]['current_delivery']);

        $this->assertCount(1, $data['deliveries']);
        $d = $data['deliveries'][0];
        $this->assertSame($s['delivery']->id, $d['id']);
        $this->assertSame('Chez Maman', $d['restaurant_name']);
        $this->assertEquals([5.4781, 10.4176], $d['restaurant']);
        $this->assertEquals([5.47, 10.42], $d['customer']);
        $this->assertTrue($d['picked_up']);
        $this->assertCount(2, $d['path_to_restaurant']);
        // 2 points après récupération + point de jonction
        $this->assertCount(3, $d['path_to_customer']);
        $this->assertEquals([5.48, 10.42], end($d['path_to_customer']));
        $this->assertGreaterThanOrEqual(15 * 60 - 5, $d['elapsed_seconds']);
    }

    public function test_map_data_is_callable_through_livewire(): void
    {
        $this->seedActiveDelivery();
        $this->actingAs($this->user('super_admin', 'super@test.cm'));

        $component = Livewire::test(DriverMapPage::class)->call('getMapData')->assertOk();

        // Valeur renvoyée au JS ($wire.getMapData())
        $returned = $component->effects['returns'][0] ?? null;
        $this->assertIsArray($returned);
        $this->assertCount(2, $returned['drivers']);
        $this->assertCount(1, $returned['deliveries']);
    }

    public function test_non_admins_cannot_access_the_page(): void
    {
        $this->actingAs($this->user('client', 'c@test.cm'))->get('/admin/suivi-livreurs')->assertForbidden();
        $this->actingAs($this->user('driver', 'd@test.cm'))->get('/admin/suivi-livreurs')->assertForbidden();
        $this->actingAs($this->user('restaurant_admin', 'r@test.cm'))->get('/admin/suivi-livreurs')->assertForbidden();
    }
}

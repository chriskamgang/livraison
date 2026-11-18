<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Delivery;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeliveryFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $driver;
    private Restaurant $restaurant;
    private Address $address;
    private MenuItem $dish;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // push Expo + WhatsApp

        $this->client = $this->user('client', 'client@test.cm');
        $this->driver = $this->user('driver', 'livreur@test.cm', [
            'is_online' => true, 'is_verified' => true, 'push_token' => 'ExponentPushToken[livreur1]',
        ]);
        $this->restaurant = Restaurant::create([
            'name' => 'Chez Maman', 'slug' => 'chez-maman', 'phone' => '690000001', 'address' => 'Bafoussam',
            'latitude' => 5.4781, 'longitude' => 10.4176, 'is_active' => true, 'is_open' => true, 'delivery_fee' => 500,
        ]);
        $category = MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Plats']);
        $this->dish = MenuItem::create([
            'restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id,
            'name' => 'Ndolé', 'price' => 2500, 'is_available' => true,
        ]);
        $this->address = Address::create([
            'user_id' => $this->client->id, 'label' => 'Maison', 'address' => 'Tamdja', 'latitude' => 5.47, 'longitude' => 10.42,
        ]);
    }

    private function user(string $role, string $email, array $extra = []): User
    {
        static $phone = 690000100;
        return User::create(array_merge([
            'name' => ucfirst($role), 'email' => $email, 'phone' => (string) $phone++,
            'password' => Hash::make('secret123'), 'role' => $role,
        ], $extra));
    }

    private function preparingOrder(): Order
    {
        $id = $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->assertCreated()->json('order.id');

        $order = Order::find($id);
        $order->update(['status' => 'confirmed']);
        $order->update(['status' => 'preparing']);
        return $order->fresh('delivery');
    }

    private function readyOrder(): Order
    {
        $id = $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->assertCreated()->json('order.id');

        $order = Order::find($id);
        $order->update(['status' => 'ready']);
        return $order->fresh('delivery');
    }

    private function sentPushTo(string $token): bool
    {
        return Http::recorded()->contains(fn ($pair) => str_contains($pair[0]->url(), 'exp.host')
            && str_contains($pair[0]->body(), $token));
    }

    public function test_drivers_get_push_as_soon_as_a_cash_order_is_placed(): void
    {
        $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->assertCreated();
        $this->assertTrue($this->sentPushTo('livreur1'), 'push envoyé dès la commande validée');
    }

    public function test_validated_order_is_shown_as_preparing_to_the_driver_app(): void
    {
        $id = $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->assertCreated()->json('order.id');
        $this->assertSame('confirmed', Order::find($id)->status);

        $listed = collect($this->actingAs($this->driver)->getJson('/api/driver/available-orders')->json())->firstWhere('id', $id);
        $this->assertSame('preparing', $listed['status']);
        $this->actingAs($this->driver)->getJson("/api/driver/orders/{$id}")->assertOk()->assertJsonPath('status', 'preparing');
        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$id}/accept")->assertOk();
    }

    public function test_restaurant_gets_an_sms_with_the_dishes_when_an_order_is_validated(): void
    {
        config(['services.nexah.user' => 'estuaire', 'services.nexah.password' => 'secret']);
        $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 2]],
        ])->assertCreated();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'smsvas.com')
            && $r['mobiles'] === '237690000001'
            && str_contains($r['sms'], '2x Ndole')
            && str_contains($r['sms'], '5 500 FCFA'));
    }

    public function test_supervisor_numbers_get_an_sms_for_each_validated_order(): void
    {
        config(['services.nexah.user' => 'estuaire', 'services.nexah.password' => 'secret',
                'services.nexah.order_alert_phones' => ['6 96 11 83 89', '658218189']]);
        $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->assertCreated();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'smsvas.com')
            && $r['mobiles'] === '237696118389,237658218189'
            && str_contains($r['sms'], 'Relancez un livreur')
            && str_contains($r['sms'], 'Tamdja'));
    }

    public function test_unpaid_online_order_reaches_drivers_only_once_paid(): void
    {
        $id = $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'orange_money', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->assertCreated()->json('order.id');
        $this->assertFalse($this->sentPushTo('livreur1'), 'pas de push avant paiement');
        $ids = collect($this->actingAs($this->driver)->getJson('/api/driver/available-orders')->json())->pluck('id');
        $this->assertFalse($ids->contains($id), 'commande non payée invisible');

        Order::find($id)->update(['status' => 'confirmed']); // paiement confirmé
        $this->assertTrue($this->sentPushTo('livreur1'));
    }

    public function test_driver_sees_and_accepts_an_order_in_preparation_and_picks_it_up(): void
    {
        $order = $this->preparingOrder();

        $ids = collect($this->actingAs($this->driver)->getJson('/api/driver/available-orders')->assertOk()->json())->pluck('id');
        $this->assertTrue($ids->contains($order->id));

        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertOk();
        $url = "/api/driver/deliveries/{$order->delivery->id}/update-status";
        $this->actingAs($this->driver)->postJson($url, ['status' => 'going_to_restaurant'])->assertOk();
        // Le livreur confirme lui-même que la commande est prête et la récupère
        $this->actingAs($this->driver)->postJson($url, ['status' => 'picked_up'])->assertOk();
        $this->assertSame('picked_up', $order->fresh()->status);
    }

    public function test_verified_drivers_get_an_sms_as_soon_as_the_order_is_validated(): void
    {
        config(['services.nexah.user' => 'estuaire', 'services.nexah.password' => 'secret']);
        $this->user('driver', 'nonvalide@test.cm', ['is_verified' => false, 'phone' => '677000999']);
        $this->driver->update(['phone' => '6 77 11 22 33']);

        $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->assertCreated();

        Http::assertSent(fn ($r) => str_contains($r->url(), 'smsvas.com')
            && $r['mobiles'] === '237677112233'
            && $r['user'] === 'estuaire'
            && str_contains($r['sms'], 'Chez Maman')
            && str_contains($r['sms'], '3 000 FCFA')
            && !preg_match('/[^\x20-\x7E]/', $r['sms']));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'smsvas.com') && $r['mobiles'] === '237677000999');
    }

    public function test_drivers_are_not_alerted_twice_when_the_restaurant_moves_the_order_on(): void
    {
        config(['services.nexah.user' => 'estuaire', 'services.nexah.password' => 'secret']);
        $this->driver->update(['phone' => '677112233']);
        $order = $this->preparingOrder(); // validée puis en préparation
        $order->update(['status' => 'ready']);
        $this->app->terminate();

        // (terminate() rejoue aussi l'envoi de la création : on compte les messages distincts)
        $toDriver = Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), 'smsvas.com') && $p[0]['mobiles'] === '237677112233')
            ->map(fn ($p) => $p[0]['sms'])->unique();
        $this->assertCount(1, $toDriver);
        $this->assertStringContainsString('nouvelle commande', $toDriver->first());
    }

    public function test_client_gets_an_sms_on_order_and_on_each_status_change(): void
    {
        config(['services.nexah.user' => 'estuaire', 'services.nexah.password' => 'secret']);
        $this->client->update(['phone' => '699887766']);

        $order = $this->readyOrder(); // création puis passage à « prête »
        $order->update(['status' => 'picked_up']);
        $order->update(['status' => 'delivered']);
        $this->app->terminate(); // en production : envoyés à la fin de chaque requête

        $toClient = Http::recorded()
            ->filter(fn ($pair) => str_contains($pair[0]->url(), 'smsvas.com') && $pair[0]['mobiles'] === '237699887766')
            ->map(fn ($pair) => $pair[0]['sms'])->unique()->values();

        $this->assertCount(4, $toClient);
        $this->assertStringContainsString('recue chez Chez Maman', $toClient[0]);
        $this->assertStringContainsString('est prete', $toClient[1]);
        $this->assertStringContainsString('recuperee par le livreur', $toClient[2]);
        $this->assertStringContainsString('a ete livree', $toClient[3]);
    }

    public function test_driver_can_cancel_while_heading_to_the_restaurant_but_not_after_pickup(): void
    {
        $order = $this->preparingOrder();
        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertOk();
        $url = "/api/driver/deliveries/{$order->delivery->id}/update-status";
        $this->actingAs($this->driver)->postJson($url, ['status' => 'going_to_restaurant'])->assertOk();

        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/reject")->assertOk();
        $this->assertSame('searching', $order->delivery->fresh()->status);
        $this->assertNull($order->delivery->fresh()->driver_id);

        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertOk();
        $this->actingAs($this->driver)->postJson($url, ['status' => 'picked_up'])->assertOk();
        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/reject")->assertOk();
        $this->assertSame('picked_up', $order->delivery->fresh()->status, 'une commande récupérée ne se libère pas');
    }

    public function test_drivers_do_not_see_orders_older_than_a_day(): void
    {
        $old = $this->preparingOrder();
        $old->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();
        $fresh = $this->preparingOrder();

        $ids = collect($this->actingAs($this->driver)->getJson('/api/driver/available-orders')->assertOk()->json())->pluck('id');
        $this->assertTrue($ids->contains($fresh->id));
        $this->assertFalse($ids->contains($old->id));
    }

    public function test_a_client_cannot_accept_a_delivery(): void
    {
        $order = $this->readyOrder();
        $this->actingAs($this->client)->postJson("/api/driver/orders/{$order->id}/accept")->assertForbidden();
        $this->assertSame('searching', $order->delivery->fresh()->status);
    }

    public function test_unverified_driver_cannot_accept(): void
    {
        $order = $this->readyOrder();
        $newbie = $this->user('driver', 'new@test.cm', ['is_online' => true, 'is_verified' => false]);
        $this->actingAs($newbie)->postJson("/api/driver/orders/{$order->id}/accept")->assertForbidden();
    }

    public function test_second_driver_cannot_take_an_accepted_order(): void
    {
        $order = $this->readyOrder();
        $other = $this->user('driver', 'autre@test.cm', ['is_online' => true, 'is_verified' => true]);

        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertOk();
        $this->actingAs($other)->postJson("/api/driver/orders/{$order->id}/accept")->assertStatus(422);

        $this->assertSame($this->driver->id, $order->delivery->fresh()->driver_id);
    }

    public function test_unpaid_order_cannot_be_accepted(): void
    {
        $order = $this->readyOrder();
        $order->forceFill(['status' => 'pending'])->saveQuietly();
        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertStatus(422);
    }

    public function test_full_delivery_marks_cash_order_paid_and_blocks_skipping_pickup(): void
    {
        $order = $this->readyOrder();
        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertOk();
        $deliveryId = $order->delivery->id;
        $url = "/api/driver/deliveries/{$deliveryId}/update-status";

        // Impossible de livrer sans avoir récupéré la commande
        $this->actingAs($this->driver)->postJson($url, ['status' => 'delivered'])->assertStatus(422);

        $this->actingAs($this->driver)->postJson($url, ['status' => 'picked_up'])->assertOk();
        $this->actingAs($this->driver)->postJson($url, ['status' => 'picked_up'])->assertOk(); // double appui
        $this->actingAs($this->driver)->postJson($url, ['status' => 'on_the_way'])->assertOk();
        // Pas de retour en arrière
        $this->actingAs($this->driver)->postJson($url, ['status' => 'at_restaurant'])->assertStatus(422);
        $this->actingAs($this->driver)->postJson($url, ['status' => 'delivered'])->assertOk();

        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('paid', $order->payment_status);
        // Livraison terminée : plus de changement possible
        $this->actingAs($this->driver)->postJson($url, ['status' => 'failed'])->assertStatus(422);
    }

    public function test_failed_delivery_cancels_the_order(): void
    {
        $order = $this->readyOrder();
        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertOk();

        $this->actingAs($this->driver)
            ->postJson("/api/driver/deliveries/{$order->delivery->id}/update-status", ['status' => 'failed', 'notes' => 'Client injoignable'])
            ->assertOk();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertStringContainsString('Client injoignable', $order->cancellation_reason);
    }

    public function test_cancelling_an_order_closes_its_delivery(): void
    {
        $id = $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->restaurant->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash', 'items' => [['menu_item_id' => $this->dish->id, 'quantity' => 1]],
        ])->json('order.id');

        $this->actingAs($this->client)->postJson("/api/orders/{$id}/cancel")->assertOk();
        $this->assertSame('failed', Delivery::where('order_id', $id)->value('status'));
    }

    public function test_driver_cannot_write_gps_into_someone_elses_delivery(): void
    {
        $order = $this->readyOrder();
        $other = $this->user('driver', 'autre@test.cm', ['is_online' => true, 'is_verified' => true]);
        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertOk();

        $gps = ['latitude' => 5.47, 'longitude' => 10.41, 'delivery_id' => $order->delivery->id];
        $this->actingAs($other)->postJson('/api/driver/location', $gps)->assertForbidden();
        $this->actingAs($this->client)->postJson('/api/driver/location', $gps)->assertForbidden();
        $this->actingAs($this->driver)->postJson('/api/driver/location', $gps)->assertOk();
    }
}

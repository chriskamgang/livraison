<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\Address;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\OrderTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class OrderTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $client;
    private User $driver;
    private Restaurant $first;
    private Restaurant $second;
    private MenuItem $poulet;   // chez le 1er restaurant
    private MenuItem $poisson;  // chez le 2e restaurant
    private Address $address;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        config(['services.nexah.user' => 'estuaire', 'services.nexah.password' => 'secret']);

        $this->client = $this->user('client', 'client@test.cm', ['phone' => '699887766']);
        $this->driver = $this->user('driver', 'livreur@test.cm', ['is_online' => true, 'is_verified' => true, 'phone' => '677112233']);
        $this->address = Address::create(['user_id' => $this->client->id, 'label' => 'Maison', 'address' => 'Tamdja', 'latitude' => 5.47, 'longitude' => 10.42]);

        [$this->first, $this->poulet] = $this->restaurantWithDish('Chez Maman', 'Poulet DG', 3000, 500);
        [$this->second, $this->poisson] = $this->restaurantWithDish('Le Braisé', 'Poisson braisé', 2500, 700);
    }

    private function user(string $role, string $email, array $extra = []): User
    {
        static $phone = 690000300;
        return User::create(array_merge([
            'name' => ucfirst($role), 'email' => $email, 'phone' => (string) $phone++,
            'password' => Hash::make('secret123'), 'role' => $role, 'status' => 'active',
        ], $extra));
    }

    private function restaurantWithDish(string $name, string $dish, int $price, int $fee): array
    {
        $r = Restaurant::create([
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'phone' => '6900' . random_int(10000, 99999),
            'address' => "Bafoussam {$name}", 'latitude' => 5.48, 'longitude' => 10.41,
            'is_active' => true, 'is_open' => true, 'delivery_fee' => $fee,
        ]);
        $cat = MenuCategory::create(['restaurant_id' => $r->id, 'name' => 'Plats']);
        $m = MenuItem::create(['restaurant_id' => $r->id, 'menu_category_id' => $cat->id, 'name' => $dish, 'price' => $price, 'is_available' => true]);
        return [$r, $m];
    }

    private function order(string $method = 'cash'): Order
    {
        $id = $this->actingAs($this->client)->postJson('/api/orders', [
            'restaurant_id' => $this->first->id, 'address_id' => $this->address->id, 'payment_method' => $method,
            'items' => [['menu_item_id' => $this->poulet->id, 'quantity' => 2]],
        ])->assertCreated()->json('order.id');
        return Order::find($id);
    }

    public function test_transfer_changes_restaurant_items_and_totals_and_warns_everyone(): void
    {
        $order = $this->order();
        $order->update(['status' => 'preparing']);
        $this->actingAs($this->driver)->postJson("/api/driver/orders/{$order->id}/accept")->assertOk();

        $moved = OrderTransfer::transfer($order->fresh(), $this->second, [['menu_item_id' => $this->poisson->id, 'quantity' => 2]], 'poulet épuisé');

        $this->assertSame($this->second->id, $moved->restaurant_id);
        $this->assertSame(['Poisson braisé'], $moved->items->pluck('item_name')->all());
        $this->assertEquals(5000, $moved->subtotal);
        $this->assertEquals(700, $moved->delivery_fee);   // frais du nouveau restaurant
        $this->assertEquals(5700, $moved->total);
        $this->assertSame('confirmed', $moved->status);    // le nouveau restaurant la prépare
        $this->assertStringContainsString('Transférée de Chez Maman vers Le Braisé', $moved->special_instructions);
        $this->assertEquals(5.48, (float) $moved->delivery->restaurant_latitude);
        $this->assertSame($this->driver->id, $moved->delivery->driver_id); // le livreur reste sur la course

        $sms = Http::recorded()->filter(fn ($p) => str_contains($p[0]->url(), 'smsvas.com'))->map(fn ($p) => [$p[0]['mobiles'], $p[0]['sms']]);
        $this->assertTrue($sms->contains(fn ($s) => $s[0] === '237699887766' && str_contains($s[1], 'Le Braise') && str_contains($s[1], '5 700 FCFA')), 'SMS client');
        $this->assertTrue($sms->contains(fn ($s) => $s[0] === '237677112233' && str_contains($s[1], 'change de restaurant')), 'SMS livreur');
    }

    public function test_dish_from_another_restaurant_is_refused(): void
    {
        $order = $this->order();
        $this->expectException(RuntimeException::class);
        OrderTransfer::transfer($order, $this->second, [['menu_item_id' => $this->poulet->id, 'quantity' => 1]]);
    }

    public function test_picked_up_order_cannot_be_transferred(): void
    {
        $order = $this->order();
        $order->update(['status' => 'picked_up']);
        $this->assertFalse(OrderTransfer::canTransfer($order->fresh('delivery')));
        $this->expectException(RuntimeException::class);
        OrderTransfer::transfer($order->fresh(), $this->second, [['menu_item_id' => $this->poisson->id, 'quantity' => 1]]);
    }

    public function test_paid_order_cannot_become_more_expensive(): void
    {
        $order = $this->order('gfs'); // 6 000 F, livraison offerte
        $order->forceFill(['payment_status' => 'paid'])->saveQuietly();

        try {
            OrderTransfer::transfer($order->fresh(), $this->second, [['menu_item_id' => $this->poisson->id, 'quantity' => 3]]); // 7 500 F
            $this->fail('aurait dû refuser');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('déjà payé', $e->getMessage());
        }

        $moved = OrderTransfer::transfer($order->fresh(), $this->second, [['menu_item_id' => $this->poisson->id, 'quantity' => 2]]); // 5 000 F
        $this->assertEquals(0, $moved->delivery_fee); // GFS : toujours offerte
        $this->assertEquals(5000, $moved->total);
    }

    public function test_admin_transfers_from_the_orders_table(): void
    {
        $order = $this->order();
        $admin = $this->user('admin', 'admin@test.cm');

        $this->actingAs($admin);
        $lw = Livewire::test(ListOrders::class)->mountTableAction('change_restaurant', $order);
        $rowKey = array_key_first($lw->get('mountedTableActionsData.0.items'));
        $lw->set('mountedTableActionsData.0.restaurant_id', $this->second->id)
            ->set("mountedTableActionsData.0.items.{$rowKey}.menu_item_id", $this->poisson->id)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame($this->second->id, $order->fresh()->restaurant_id);
    }

    public function test_restaurant_admin_does_not_see_the_transfer_button(): void
    {
        $order = $this->order();
        $owner = $this->user('restaurant_admin', 'resto@test.cm', ['restaurant_id' => $this->first->id]);

        $this->actingAs($owner);
        Livewire::test(ListOrders::class)->assertTableActionHidden('change_restaurant', $order);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GfsPaymentTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'gfs_test_key';

    private User $user;
    private Restaurant $restaurant;
    private Address $address;
    private MenuItem $dish;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gfs.api_key' => self::KEY, 'services.gfs.base_url' => 'https://gfs.test/api/v1']);

        $this->user = User::create([
            'name' => 'Client', 'email' => 'client@test.cm', 'phone' => '690000000',
            'password' => Hash::make('secret123'), 'role' => 'client',
        ]);
        $this->restaurant = Restaurant::create([
            'name' => 'Chez Maman', 'slug' => 'chez-maman', 'phone' => '690000001', 'address' => 'Bafoussam',
            'latitude' => 5.4781, 'longitude' => 10.4176, 'is_active' => true, 'is_open' => true,
            'delivery_fee' => 500,
        ]);
        $category = MenuCategory::create(['restaurant_id' => $this->restaurant->id, 'name' => 'Plats']);
        $this->dish = MenuItem::create([
            'restaurant_id' => $this->restaurant->id, 'menu_category_id' => $category->id,
            'name' => 'Ndolé', 'price' => 2500, 'is_available' => true,
        ]);
        $this->address = Address::create([
            'user_id' => $this->user->id, 'label' => 'Maison', 'address' => 'Tamdja',
            'latitude' => 5.47, 'longitude' => 10.42,
        ]);
    }

    private function order(string $method, ?string $coupon = null)
    {
        return $this->actingAs($this->user)->postJson('/api/orders', [
            'restaurant_id'  => $this->restaurant->id,
            'address_id'     => $this->address->id,
            'payment_method' => $method,
            'coupon_code'    => $coupon,
            'items'          => [['menu_item_id' => $this->dish->id, 'quantity' => 2]],
        ]);
    }

    private function sign(array $p): string
    {
        return 'sha256=' . hash_hmac('sha256', "{$p['paymentRef']}:{$p['amount']}:{$p['orderId']}", self::KEY);
    }

    public function test_gfs_order_has_free_delivery_and_cash_order_pays_delivery(): void
    {
        $gfs = $this->order('gfs')->assertCreated()->json('order');
        $this->assertEquals(0, $gfs['delivery_fee']);
        $this->assertEquals(5000, $gfs['total']);
        $this->assertSame('pending', Order::find($gfs['id'])->payment_status);

        $cash = $this->order('cash')->assertCreated()->json('order');
        $this->assertEquals(500, $cash['delivery_fee']);
        $this->assertEquals(5500, $cash['total']);
        $this->assertSame('cash_on_delivery', Order::find($cash['id'])->payment_status);
    }

    public function test_free_delivery_coupon_is_not_counted_twice(): void
    {
        Coupon::create(['code' => 'LIVRAISON', 'type' => 'free_delivery', 'value' => 0, 'minimum_order' => 0, 'is_active' => true]);

        $order = $this->order('cash', 'LIVRAISON')->assertCreated()->json('order');
        $this->assertEquals(0, $order['delivery_fee']);
        $this->assertEquals(0, $order['discount_amount']);
        $this->assertEquals(5000, $order['total']); // avant : 4500 (frais retirés deux fois)
    }

    public function test_full_gfs_payment_flow_with_signed_webhook(): void
    {
        Http::fake(['gfs.test/*' => Http::response([
            'paymentRef' => 'GFSPAY123', 'paymentUrl' => 'https://admin.gfinancials.com/pay/GFSPAY123',
            'amount' => 5000, 'status' => 'PENDING',
        ], 201)]);

        $orderId = $this->order('gfs')->json('order.id');

        $res = $this->actingAs($this->user)->postJson('/api/payments/initiate-gfs', ['order_id' => $orderId])
            ->assertOk()
            ->assertJsonPath('payment.payment_url', 'https://admin.gfinancials.com/pay/GFSPAY123');

        Http::assertSent(fn ($r) => $r->hasHeader('X-API-Key', self::KEY) && $r['amount'] === 5000);

        $payment = Payment::find($res->json('payment.id'));
        $payload = ['event' => 'payment.completed', 'paymentRef' => 'GFSPAY123', 'orderId' => $payment->reference, 'amount' => 5000, 'status' => 'COMPLETED'];

        // Mauvaise signature → refusé, commande toujours impayée
        $this->postJson('/api/webhooks/gfs', $payload, ['X-GFS-Signature' => 'sha256=faux'])->assertStatus(401);
        $this->assertSame('pending', Order::find($orderId)->payment_status);

        // Montant falsifié → refusé
        $cheap = array_merge($payload, ['amount' => 100]);
        $this->postJson('/api/webhooks/gfs', $cheap, ['X-GFS-Signature' => $this->sign($cheap)])->assertStatus(422);

        // Bonne signature → commande payée et confirmée
        $this->postJson('/api/webhooks/gfs', $payload, ['X-GFS-Signature' => $this->sign($payload)])->assertOk();
        $order = Order::find($orderId);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('completed', $payment->fresh()->status);
    }

    public function test_status_polling_marks_gfs_payment_paid(): void
    {
        Http::fake([
            'gfs.test/api/v1/gateway/payments' => Http::response(['paymentRef' => 'REF9', 'paymentUrl' => 'https://x/pay/REF9'], 201),
            'gfs.test/api/v1/gateway/payments/REF9' => Http::response(['paymentRef' => 'REF9', 'amount' => 5000, 'status' => 'COMPLETED']),
        ]);

        $orderId = $this->order('gfs')->json('order.id');
        $paymentId = $this->actingAs($this->user)->postJson('/api/payments/initiate-gfs', ['order_id' => $orderId])->json('payment.id');

        $this->actingAs($this->user)->getJson("/api/payments/{$paymentId}/status")
            ->assertOk()->assertJsonPath('payment.status', 'completed');
        $this->assertSame('paid', Order::find($orderId)->payment_status);
    }

    public function test_webhook_signed_with_api_secret_is_also_accepted(): void
    {
        config(['services.gfs.api_secret' => 'gfs_test_secret']);
        Http::fake(['gfs.test/*' => Http::response(['paymentRef' => 'R2', 'paymentUrl' => 'https://x/pay/R2'], 201)]);
        $orderId = $this->order('gfs')->json('order.id');
        $paymentId = $this->actingAs($this->user)->postJson('/api/payments/initiate-gfs', ['order_id' => $orderId])->json('payment.id');

        $p = ['paymentRef' => 'R2', 'orderId' => Payment::find($paymentId)->reference, 'amount' => 5000, 'status' => 'COMPLETED'];
        $sig = 'sha256=' . hash_hmac('sha256', "{$p['paymentRef']}:{$p['amount']}:{$p['orderId']}", 'gfs_test_secret');
        $this->postJson('/api/webhooks/gfs', $p, ['X-GFS-Signature' => $sig])->assertOk();
        $this->assertSame('paid', Order::find($orderId)->payment_status);
    }

    public function test_cannot_start_gfs_payment_for_a_cash_order(): void
    {
        $orderId = $this->order('cash')->json('order.id');
        $this->actingAs($this->user)->postJson('/api/payments/initiate-gfs', ['order_id' => $orderId])->assertStatus(400);
    }
}

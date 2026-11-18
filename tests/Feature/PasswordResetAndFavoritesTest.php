<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordResetAndFavoritesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name' => 'Client Test',
            'email' => 'client@test.cm',
            'phone' => '690000000',
            'password' => Hash::make('ancien123'),
            'role' => 'client',
        ], $attrs));
    }

    public function test_forgot_sends_code_over_whatsapp_and_reset_changes_password(): void
    {
        $this->makeUser();
        $sentCode = null;
        $this->mock(WhatsAppService::class, function ($mock) use (&$sentCode) {
            $mock->shouldReceive('sendMessage')->once()->andReturnUsing(function ($to, $body) use (&$sentCode) {
                preg_match('/\b(\d{6})\b/', $body, $m);
                $sentCode = $m[1] ?? null;
                return ['success' => true];
            });
        });

        $this->postJson('/api/auth/password/forgot', ['email' => 'CLIENT@test.cm'])->assertOk();
        $this->assertNotNull($sentCode);

        $this->postJson('/api/auth/password/reset', ['email' => 'client@test.cm', 'code' => '000000', 'password' => 'nouveau123'])
            ->assertStatus(422);

        $this->postJson('/api/auth/password/reset', ['email' => 'client@test.cm', 'code' => $sentCode, 'password' => 'nouveau123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);

        $this->assertTrue(Hash::check('nouveau123', User::first()->password));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_forgot_does_not_reveal_unknown_email(): void
    {
        $this->postJson('/api/auth/password/forgot', ['email' => 'inconnu@test.cm'])->assertOk();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->makeUser();
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email, 'token' => Hash::make('123456'), 'created_at' => now()->subMinutes(20),
        ]);
        $this->postJson('/api/auth/password/reset', ['email' => $user->email, 'code' => '123456', 'password' => 'nouveau123'])
            ->assertStatus(422);
    }

    public function test_favorites_toggle_and_list(): void
    {
        $user = $this->makeUser();
        $restaurant = Restaurant::create(['name' => 'Chez Maman', 'slug' => 'chez-maman', 'phone' => '690000001', 'address' => 'Bafoussam', 'latitude' => 5.4781, 'longitude' => 10.4176, 'is_active' => true, 'is_open' => true]);

        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/favorites/toggle', ['type' => 'restaurant', 'id' => $restaurant->id])
            ->assertOk()->assertJson(['favorited' => true]);
        $this->getJson('/api/favorites')
            ->assertOk()->assertJsonPath('restaurants.0.id', $restaurant->id)->assertJsonCount(0, 'items');

        $this->postJson('/api/favorites/toggle', ['type' => 'restaurant', 'id' => $restaurant->id])
            ->assertOk()->assertJson(['favorited' => false]);
        $this->getJson('/api/favorites')->assertJsonCount(0, 'restaurants');
    }
}

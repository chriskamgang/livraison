<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AppleLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_forged_apple_token_is_rejected(): void
    {
        Http::fake(['appleid.apple.com/*' => Http::response(['keys' => [[
            'kty' => 'RSA', 'kid' => 'real', 'use' => 'sig', 'alg' => 'RS256',
            'n' => rtrim(strtr(base64_encode(random_bytes(256)), '+/', '-_'), '='), 'e' => 'AQAB',
        ]]])]);

        // Jeton non signé fabriqué à la main avec l'email d'un autre utilisateur
        $b64 = fn ($v) => rtrim(strtr(base64_encode(json_encode($v)), '+/', '-_'), '=');
        $forged = $b64(['alg' => 'RS256', 'kid' => 'real']) . '.'
            . $b64(['iss' => 'https://appleid.apple.com', 'aud' => 'com.chrisdev237.estuaireresto',
                    'sub' => 'attacker', 'email' => 'victim@example.com', 'exp' => time() + 3600])
            . '.c2lnbmF0dXJl';

        $this->postJson('/api/auth/apple', ['identity_token' => $forged, 'full_name' => 'Jean Dupont'])
            ->assertStatus(401);
        $this->assertDatabaseMissing('users', ['apple_id' => 'attacker']);
    }
}

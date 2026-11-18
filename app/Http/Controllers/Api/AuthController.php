<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'phone'    => 'required|string|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = \App\Models\User::create([
            ...$validated,
            'role'   => 'client',
            'status' => 'active',
        ]);

        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    public function registerDriver(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users',
            'phone'          => 'required|string|unique:users',
            'password'       => 'required|string|min:8|confirmed',
            'vehicle_type'   => 'required|in:moto,voiture,velo',
            'vehicle_number' => 'required|string',
            'license_number' => 'required|string',
        ]);

        $user = \App\Models\User::create([
            ...$validated,
            'role'   => 'driver',
            'status' => 'pending', // En attente de vérification
        ]);

        $token = $user->createToken('driver-token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription livreur soumise. En attente de validation.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!\Illuminate\Support\Facades\Auth::attempt($validated)) {
            return response()->json(['message' => 'Identifiants invalides'], 401);
        }

        $user = $request->user();

        if ($user->status === 'suspended') {
            return response()->json(['message' => 'Compte suspendu. Contactez le support.'], 403);
        }

        // On garde les sessions des autres appareils : un utilisateur connecté
        // reste connecté jusqu'à ce qu'il se déconnecte lui-même.

        $token = $user->createToken($user->role . '-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    public function googleLogin(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        // Verify the Google ID token
        // Le jeton peut venir de l'identifiant web, iOS ou Android selon l'appareil :
        // on vérifie la signature Google, puis que l'audience est l'un de nos identifiants.
        $client = new \Google_Client();
        try {
            $payload = $client->verifyIdToken($request->id_token);
        } catch (\Throwable $e) {
            $payload = null; // jeton mal formé ou expiré
        }
        $allowed = config('services.google.client_ids', []);

        if (!$payload || !in_array($payload['aud'] ?? null, $allowed, true)) {
            return response()->json(['message' => 'Token Google invalide'], 401);
        }

        $googleId = $payload['sub'];
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? 'Utilisateur Google';

        // Find or create user
        $user = \App\Models\User::where('google_id', $googleId)->first();

        if (!$user && $email) {
            $user = \App\Models\User::where('email', $email)->first();
            if ($user) {
                $user->update(['google_id' => $googleId]);
            }
        }

        if (!$user) {
            $user = \App\Models\User::create([
                'name'      => $name,
                'email'     => $email,
                'google_id' => $googleId,
                'role'      => 'client',
                'status'    => 'active',
                'password'  => bcrypt(\Illuminate\Support\Str::random(32)),
            ]);
        }

        if ($user->status === 'suspended') {
            return response()->json(['message' => 'Compte suspendu. Contactez le support.'], 403);
        }

        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion Google réussie',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    public function appleLogin(Request $request)
    {
        $request->validate([
            'identity_token' => 'required|string',
            'full_name'      => 'nullable', // texte ("Jean Dupont") ou {givenName, familyName}
            'email'          => 'nullable|email',
        ]);

        // Vérifier que le jeton vient vraiment d'Apple (signature, émetteur, app)
        $payload = $this->verifyAppleToken($request->identity_token);
        if (!$payload || !isset($payload['sub'])) {
            return response()->json(['message' => 'Token Apple invalide'], 401);
        }

        $appleId = $payload['sub'];
        // Seul l'email signé par Apple sert à retrouver un compte existant
        $email = $payload['email'] ?? null;
        $fullName = $request->full_name;
        $name = 'Utilisateur Apple';
        if (is_array($fullName)) {
            $name = trim(($fullName['givenName'] ?? '') . ' ' . ($fullName['familyName'] ?? '')) ?: $name;
        } elseif (is_string($fullName) && trim($fullName) !== '') {
            $name = mb_substr(trim($fullName), 0, 255);
        }

        $user = \App\Models\User::where('apple_id', $appleId)->first();

        if (!$user && $email) {
            $user = \App\Models\User::where('email', $email)->first();
            if ($user) {
                $user->update(['apple_id' => $appleId]);
            }
        }

        if (!$user) {
            $user = \App\Models\User::create([
                'name'     => $name,
                'email'    => $email,
                'apple_id' => $appleId,
                'role'     => 'client',
                'status'   => 'active',
                'password' => bcrypt(\Illuminate\Support\Str::random(32)),
            ]);
        }

        if ($user->status === 'suspended') {
            return response()->json(['message' => 'Compte suspendu. Contactez le support.'], 403);
        }

        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion Apple réussie',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Vérifie un identity token Apple avec les clés publiques d'Apple.
     * Retourne le contenu du jeton, ou null s'il est invalide.
     */
    private function verifyAppleToken(string $jwt): ?array
    {
        $fetchKeys = fn () => \Illuminate\Support\Facades\Http::timeout(10)
            ->get('https://appleid.apple.com/auth/keys')->throw()->json();

        foreach ([false, true] as $refresh) {
            try {
                if ($refresh) {
                    \Illuminate\Support\Facades\Cache::forget('apple_jwks'); // Apple a peut-être changé de clé
                }
                $keys = \Illuminate\Support\Facades\Cache::remember('apple_jwks', 43200, $fetchKeys);
                $payload = (array) \Firebase\JWT\JWT::decode($jwt, \Firebase\JWT\JWK::parseKeySet($keys));
            } catch (\Throwable $e) {
                if ($refresh) {
                    \Illuminate\Support\Facades\Log::warning('Apple token rejeté', ['error' => $e->getMessage()]);
                    return null;
                }
                continue;
            }

            if (($payload['iss'] ?? null) !== 'https://appleid.apple.com') {
                return null;
            }
            if (!in_array($payload['aud'] ?? null, config('services.apple.client_ids', []), true)) {
                return null;
            }
            return $payload;
        }

        return null;
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Déconnexion réussie']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }
}

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

        // Révoquer les anciens tokens
        $user->tokens()->delete();

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
        $client = new \Google_Client(['client_id' => config('services.google.client_id')]);
        $payload = $client->verifyIdToken($request->id_token);

        if (!$payload) {
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

        $user->tokens()->delete();
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
            'full_name'      => 'nullable|array',
            'email'          => 'nullable|email',
        ]);

        // Decode Apple identity token (JWT)
        $tokenParts = explode('.', $request->identity_token);
        if (count($tokenParts) !== 3) {
            return response()->json(['message' => 'Token Apple invalide'], 401);
        }

        $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true);
        if (!$payload || !isset($payload['sub'])) {
            return response()->json(['message' => 'Token Apple invalide'], 401);
        }

        $appleId = $payload['sub'];
        $email = $request->email ?? $payload['email'] ?? null;
        $fullName = $request->full_name;
        $name = 'Utilisateur Apple';
        if ($fullName) {
            $name = trim(($fullName['givenName'] ?? '') . ' ' . ($fullName['familyName'] ?? '')) ?: $name;
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

        $user->tokens()->delete();
        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Connexion Apple réussie',
            'user'    => $user,
            'token'   => $token,
        ]);
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

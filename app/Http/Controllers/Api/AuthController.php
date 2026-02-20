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

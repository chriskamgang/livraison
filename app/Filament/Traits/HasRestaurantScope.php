<?php

namespace App\Filament\Traits;

use Illuminate\Support\Facades\Auth;

/**
 * Trait pour gérer le scope multi-restaurant dans les ressources Filament.
 *
 * - super_admin / admin : voit tout
 * - restaurant_admin : voit uniquement les données de son restaurant
 */
trait HasRestaurantScope
{
    /**
     * Retourne le restaurant_id de l'utilisateur connecté, ou null si super_admin.
     */
    protected static function getRestaurantId(): ?int
    {
        $user = Auth::user();
        if (!$user) return null;

        if (in_array($user->role, ['admin', 'super_admin'])) {
            return null; // Pas de filtre — voit tout
        }

        return $user->restaurant_id;
    }

    /**
     * Vérifie si l'utilisateur est un super admin (admin ou super_admin).
     */
    protected static function isSuperAdmin(): bool
    {
        $user = Auth::user();
        return $user && in_array($user->role, ['admin', 'super_admin']);
    }

    /**
     * Vérifie si l'utilisateur est un admin de restaurant.
     */
    protected static function isRestaurantAdmin(): bool
    {
        $user = Auth::user();
        return $user && $user->role === 'restaurant_admin';
    }
}

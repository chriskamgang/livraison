<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Privacy Policy routes
Route::get('/privacy-policy', function () {
    return response()->file(public_path('privacy-policy.html'));
});

Route::get('/privacy-policy-driver', function () {
    return response()->file(public_path('privacy-policy-driver.html'));
});

Route::get('/terms-of-service', function () {
    return response()->file(public_path('terms-of-service.html'));
});

Route::get('/terms-of-service-driver', function () {
    return response()->file(public_path('terms-of-service-driver.html'));
});

Route::get('/delete-account', function () {
    return response()->file(public_path('delete-account.html'));
});

// Impersonation: super admin se connecte en tant que propriétaire d'un restaurant
Route::get('/impersonate/restaurant/{restaurant}', function (\App\Models\Restaurant $restaurant) {
    $currentUser = \Illuminate\Support\Facades\Auth::user();

    if (!$currentUser || !in_array($currentUser->role, ['admin', 'super_admin'])) {
        abort(403);
    }

    $owner = $restaurant->owner;
    if (!$owner) {
        return redirect('/admin/restaurants')->with('error', 'Ce restaurant n\'a pas de propriétaire assigné.');
    }

    $impersonatorId = $currentUser->id;

    // Invalider la session actuelle et en créer une nouvelle
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    // Connecter le propriétaire du restaurant
    \Illuminate\Support\Facades\Auth::login($owner);

    // Stocker le password hash manuellement pour AuthenticateSession
    request()->session()->put('password_hash_web', $owner->getAuthPassword());

    // Sauvegarder l'impersonator
    request()->session()->put('impersonator_id', $impersonatorId);

    request()->session()->save();

    return redirect('/admin');
})->middleware(['web', 'auth'])->name('admin.restaurants.impersonate');

// Revenir au compte super admin
Route::get('/impersonate/leave', function () {
    $impersonatorId = session('impersonator_id');

    if (!$impersonatorId) {
        return redirect('/admin');
    }

    $admin = \App\Models\User::find($impersonatorId);
    if (!$admin) {
        return redirect('/admin');
    }

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    \Illuminate\Support\Facades\Auth::login($admin);
    request()->session()->put('password_hash_web', $admin->getAuthPassword());
    request()->session()->save();

    return redirect('/admin/restaurants');
})->middleware(['web', 'auth'])->name('admin.impersonate.leave');

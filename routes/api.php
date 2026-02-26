<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RestaurantController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AccountDeletionController;

// ==========================================
// ROUTES PUBLIQUES
// ==========================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register/driver', [AuthController::class, 'registerDriver']);
});

// Restaurants publics (pas besoin d'auth pour parcourir)
Route::prefix('restaurants')->group(function () {
    Route::get('/', [RestaurantController::class, 'index']);
    Route::get('/featured', [RestaurantController::class, 'featured']);
    Route::get('/categories', [RestaurantController::class, 'categories']);
    Route::get('/{id}', [RestaurantController::class, 'show']);
    Route::get('/{id}/menu', [RestaurantController::class, 'menu']);
});

// Webhooks (publics)
Route::post('/webhooks/freemopay', [PaymentController::class, 'freemopayWebhook']);

// Account deletion (public endpoints)
Route::prefix('account-deletion')->group(function () {
    Route::post('/request', [AccountDeletionController::class, 'requestDeletion']);
    Route::post('/status', [AccountDeletionController::class, 'checkStatus']);
});

// ==========================================
// ROUTES PROTÉGÉES (Client + Livreur)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Profil
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::post('/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::get('/addresses', [ProfileController::class, 'addresses']);
        Route::post('/addresses', [ProfileController::class, 'addAddress']);
        Route::put('/addresses/{id}', [ProfileController::class, 'updateAddress']);
        Route::delete('/addresses/{id}', [ProfileController::class, 'deleteAddress']);
        Route::post('/push-token', [ProfileController::class, 'updatePushToken']);
        Route::delete('/push-token', [ProfileController::class, 'deletePushToken']);
    });

    // ==========================================
    // ROUTES CLIENT
    // ==========================================
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{id}', [OrderController::class, 'show']);
        Route::post('/{id}/cancel', [OrderController::class, 'cancel']);
        Route::post('/{id}/rate', [OrderController::class, 'rate']);
        Route::get('/{id}/track', [OrderController::class, 'track']); // Suivi temps réel
    });

    Route::prefix('coupons')->group(function () {
        Route::post('/validate', [OrderController::class, 'validateCoupon']);
    });

    Route::prefix('payments')->group(function () {
        Route::post('/initiate-mobile', [PaymentController::class, 'initiateMobilePayment']);
        Route::get('/{id}/status', [PaymentController::class, 'checkPaymentStatus']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [ProfileController::class, 'notifications']);
        Route::post('/{id}/read', [ProfileController::class, 'markNotificationRead']);
        Route::post('/read-all', [ProfileController::class, 'markAllNotificationsRead']);
    });

    // Position du livreur en temps réel pour le client
    Route::get('/deliveries/{id}/driver-location', [DeliveryController::class, 'getDriverLocation']);

    // ==========================================
    // ROUTES LIVREUR
    // ==========================================
    Route::prefix('driver')->group(function () {
        // Statut disponibilité
        Route::post('/toggle-online', [DeliveryController::class, 'toggleOnline']);

        // Commandes disponibles
        Route::get('/available-orders', [DeliveryController::class, 'availableOrders']);

        // Accepter / Refuser une commande
        Route::post('/orders/{id}/accept', [DeliveryController::class, 'acceptOrder']);
        Route::post('/orders/{id}/reject', [DeliveryController::class, 'rejectOrder']);

        // Mettre à jour le statut de livraison
        Route::post('/deliveries/{id}/update-status', [DeliveryController::class, 'updateStatus']);

        // Envoyer la position GPS en temps réel
        Route::post('/location', [DeliveryController::class, 'updateLocation']);

        // Upload photo preuve livraison
        Route::post('/deliveries/{id}/proof', [DeliveryController::class, 'uploadProof']);

        // Historique et gains
        Route::get('/deliveries', [DeliveryController::class, 'history']);
        Route::get('/earnings', [DeliveryController::class, 'earnings']);
        Route::get('/stats', [DeliveryController::class, 'stats']);

        // Détail d'une commande pour le livreur
        Route::get('/orders/{id}', [DeliveryController::class, 'orderDetail']);
    });

    // Adresse par défaut
    Route::post('/profile/addresses/{id}/default', [ProfileController::class, 'setDefaultAddress']);

    // Cancel account deletion request (authenticated users only)
    Route::post('/account-deletion/cancel', [AccountDeletionController::class, 'cancelRequest']);
});

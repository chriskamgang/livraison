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

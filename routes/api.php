<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventCheckoutController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\RsvpController;
use App\Http\Controllers\Api\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('rsvp/{token}', [RsvpController::class, 'show']);
    Route::post('rsvp/{token}', [RsvpController::class, 'respond']);
});

// Stripe delivers webhooks without auth — must be outside the Sanctum group
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('auth/password', [AuthController::class, 'updatePassword']);
    Route::delete('auth/account', [AuthController::class, 'deleteAccount']);
    Route::apiResource('events', EventController::class);
    Route::post('events/{event}/publish', [EventController::class, 'publish']);
    Route::post('events/{event}/archive', [EventController::class, 'archive']);
    Route::post('events/{event}/checkout', [EventCheckoutController::class, 'create']);

    Route::prefix('events/{event}')->group(function () {
        Route::get('guests', [GuestController::class, 'index']);
        Route::get('guests/export', [GuestController::class, 'export']);
        Route::post('guests', [GuestController::class, 'store']);
        Route::put('guests/{guest}', [GuestController::class, 'update']);
        Route::delete('guests/{guest}', [GuestController::class, 'destroy']);
        Route::post('guests/{guest}/invite', [GuestController::class, 'invite']);
        Route::post('guests/bulk-invite', [GuestController::class, 'bulkInvite']);
    });
});

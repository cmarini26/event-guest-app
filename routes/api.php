<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AttachmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomDomainController;
use App\Http\Controllers\Api\EventCheckoutController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GuestController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\RsvpController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubEventController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::get('health', [HealthController::class, 'check']);

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1');
    Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');
    Route::get('verify-email/{id}/{hash}', [VerifyEmailController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('rsvp/{token}', [RsvpController::class, 'show']);
    Route::post('rsvp/{token}', [RsvpController::class, 'respond']);
});

// Stripe delivers webhooks without auth — must be outside the Sanctum group
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    Route::get('admin/stats', [AdminController::class, 'stats']);
    Route::get('admin/users', [AdminController::class, 'users']);
    Route::get('admin/users/{user}/events', [AdminController::class, 'userEvents']);
    Route::post('admin/users/{user}/toggle-admin', [AdminController::class, 'toggleAdmin']);
});

Route::middleware(['auth:sanctum', 'throttle:120,1'])->group(function () {
    Route::put('auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('auth/password', [AuthController::class, 'updatePassword']);
    Route::post('auth/set-password', [AuthController::class, 'setPassword']);
    Route::post('auth/resend-verification', [VerifyEmailController::class, 'resend'])->middleware('throttle:5,5');
    Route::post('auth/google/link-token', [AuthController::class, 'googleLinkToken']);
    Route::delete('auth/account', [AuthController::class, 'deleteAccount']);
    Route::apiResource('events', EventController::class);
    Route::post('events/{event}/publish', [EventController::class, 'publish']);
    Route::post('events/{event}/archive', [EventController::class, 'archive']);
    Route::post('events/{event}/checkout', [EventCheckoutController::class, 'create']);
    Route::post('subscriptions/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('subscriptions/portal', [SubscriptionController::class, 'portal']);

    Route::get('custom-domains', [CustomDomainController::class, 'index']);
    Route::post('custom-domains', [CustomDomainController::class, 'store']);
    Route::post('custom-domains/{customDomain}/verify', [CustomDomainController::class, 'verify']);
    Route::delete('custom-domains/{customDomain}', [CustomDomainController::class, 'destroy']);

    Route::prefix('events/{event}')->group(function () {
        Route::get('analytics', [AnalyticsController::class, 'show']);

        Route::get('sub-events', [SubEventController::class, 'index']);
        Route::post('sub-events', [SubEventController::class, 'store']);
        Route::put('sub-events/{subEvent}', [SubEventController::class, 'update']);
        Route::delete('sub-events/{subEvent}', [SubEventController::class, 'destroy']);

        Route::get('attachments', [AttachmentController::class, 'index']);
        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);

        Route::get('guests', [GuestController::class, 'index']);
        Route::get('guests/export', [GuestController::class, 'export']);
        Route::post('guests', [GuestController::class, 'store']);
        Route::put('guests/{guest}', [GuestController::class, 'update']);
        Route::delete('guests/{guest}', [GuestController::class, 'destroy']);
        Route::post('guests/{guest}/invite', [GuestController::class, 'invite']);
        Route::post('guests/bulk-invite', [GuestController::class, 'bulkInvite']);
    });
});

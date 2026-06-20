<?php

use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/google/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/google/link', [SocialAuthController::class, 'linkRedirect']);
Route::get('/auth/google/callback', [SocialAuthController::class, 'callback']);

Route::fallback(function () {
    return view('app');
});

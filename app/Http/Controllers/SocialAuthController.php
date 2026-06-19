<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $base = config('app.url');

        try {
            $socialUser = Socialite::driver('google')->user();
        } catch (\Exception) {
            return redirect($base . '/login?error=google_failed');
        }

        $user = User::where('google_id', $socialUser->getId())->first();

        if (! $user) {
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                $user->update(['google_id' => $socialUser->getId()]);
            } else {
                $user = User::create([
                    'name'      => $socialUser->getName() ?? $socialUser->getEmail(),
                    'email'     => $socialUser->getEmail(),
                    'google_id' => $socialUser->getId(),
                    'password'  => bcrypt(Str::random(32)),
                ]);
            }
        }

        $token = $user->createToken('google-oauth')->plainTextToken;

        return redirect($base . '/auth/callback?token=' . urlencode($token));
    }
}

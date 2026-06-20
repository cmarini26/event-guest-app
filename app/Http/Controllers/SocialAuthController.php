<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function linkRedirect(Request $request): RedirectResponse
    {
        $token = $request->query('token', '');
        $userId = Cache::get("google-link:{$token}");

        if (! $userId) {
            return redirect(config('app.url') . '/settings?error=link_expired');
        }

        session(['link_user_id' => $userId]);

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

        // Handle account-linking flow (signed-in user clicked "Connect" in settings)
        $linkUserId = session()->pull('link_user_id');
        if ($linkUserId) {
            $user = User::find($linkUserId);
            if ($user) {
                $conflicting = User::where('google_id', $socialUser->getId())
                    ->where('id', '!=', $user->id)
                    ->first();
                if ($conflicting) {
                    return redirect($base . '/settings?error=google_already_linked');
                }
                $user->update(['google_id' => $socialUser->getId()]);
                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
                $token = $user->createToken('google-oauth')->plainTextToken;
                return redirect($base . '/auth/callback?token=' . urlencode($token));
            }
        }

        // Standard sign-in / registration flow
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
                ]);
            }
        }

        // Google accounts have verified emails
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        $token = $user->createToken('google-oauth')->plainTextToken;

        return redirect($base . '/auth/callback?token=' . urlencode($token));
    }
}

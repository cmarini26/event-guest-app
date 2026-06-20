<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan'     => ['required', 'in:pro,business'],
            'interval' => ['required', 'in:monthly,annual'],
        ]);

        $priceId = config("services.stripe.plans.{$data['plan']}_{$data['interval']}");

        if (! $priceId) {
            return response()->json(['message' => 'This plan is not available yet.'], 503);
        }

        $user = $request->user();

        if ($user->subscribed('default')) {
            return response()->json(['message' => 'You already have an active subscription. Use the billing portal to make changes.'], 422);
        }

        $checkout = $user->newSubscription('default', $priceId)->checkout([
            'success_url' => config('app.url') . '/settings?subscription=success',
            'cancel_url'  => config('app.url') . '/settings?subscription=cancelled',
        ]);

        return response()->json(['checkout_url' => $checkout->url]);
    }

    public function portal(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->stripe_id) {
            return response()->json(['message' => 'No billing account found.'], 404);
        }

        $url = $user->billingPortalUrl(config('app.url') . '/settings');

        return response()->json(['portal_url' => $url]);
    }

    public static function planForPriceId(string $priceId): ?string
    {
        $plans = config('services.stripe.plans', []);
        foreach ($plans as $key => $id) {
            if ($id && $id === $priceId) {
                return str_starts_with($key, 'pro') ? 'pro' : 'business';
            }
        }
        return null;
    }
}

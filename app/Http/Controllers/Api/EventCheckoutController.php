<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventCheckoutController extends Controller
{
    use AuthorizesRequests;

    /** Price in cents — $19 per event */
    private const EVENT_PASS_PRICE = 1900;

    public function create(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        if ($event->hasEventPass()) {
            return response()->json(['message' => 'This event already has an Event Pass.'], 422);
        }

        $successUrl = config('app.url') . "/events/{$event->id}?payment=success";
        $cancelUrl  = config('app.url') . "/events/{$event->id}?payment=cancelled";

        $checkout = $request->user()->checkoutCharge(
            self::EVENT_PASS_PRICE,
            'Event Pass — ' . $event->name,
            1,
            [
                'success_url' => $successUrl,
                'cancel_url'  => $cancelUrl,
                'metadata'    => [
                    'event_id' => $event->id,
                    'user_id'  => $request->user()->id,
                ],
            ]
        );

        $event->update(['stripe_checkout_session_id' => $checkout->id]);

        return response()->json(['checkout_url' => $checkout->url]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload   = $request->getContent();
        $signature = $request->header('Stripe-Signature', '');
        $secret    = config('cashier.webhook.secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException) {
            return response('Invalid signature', 400);
        } catch (\UnexpectedValueException) {
            return response('Invalid payload', 400);
        }

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            default                       => null,
        };

        return response('OK', 200);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        if (($session->payment_status ?? '') !== 'paid') {
            return;
        }

        $eventId = $session->metadata->event_id ?? null;
        if (! $eventId) {
            return;
        }

        $event = Event::find($eventId);
        if (! $event || $event->hasEventPass()) {
            return;
        }

        $event->update([
            'event_pass_paid_at'         => now(),
            'stripe_checkout_session_id' => $session->id,
        ]);
    }
}

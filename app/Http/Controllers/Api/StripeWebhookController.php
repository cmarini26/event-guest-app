<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
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
            'checkout.session.completed'     => $this->handleCheckoutCompleted($event->data->object),
            'customer.subscription.created',
            'customer.subscription.updated'  => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted'  => $this->handleSubscriptionDeleted($event->data->object),
            default                          => null,
        };

        return response('OK', 200);
    }

    private function handleCheckoutCompleted(object $session): void
    {
        // Subscription checkout is handled by subscription webhooks below
        if (($session->mode ?? '') === 'subscription') {
            return;
        }

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

    private function handleSubscriptionUpdated(object $subscription): void
    {
        if (! in_array($subscription->status, ['active', 'trialing'])) {
            return;
        }

        $user = User::where('stripe_id', $subscription->customer)->first();
        if (! $user) {
            return;
        }

        $priceId = $subscription->items->data[0]->price->id ?? null;
        if (! $priceId) {
            return;
        }

        $plan = SubscriptionController::planForPriceId($priceId);
        if ($plan) {
            $user->update(['plan' => $plan]);
        }
    }

    private function handleSubscriptionDeleted(object $subscription): void
    {
        $user = User::where('stripe_id', $subscription->customer)->first();
        if ($user && in_array($user->plan, ['pro', 'business'])) {
            $user->update(['plan' => 'free']);
        }
    }
}

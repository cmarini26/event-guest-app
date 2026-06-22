<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Checkout;
use Stripe\Checkout\Session as StripeSession;
use Tests\TestCase;

class StripeTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeEvent(User $user, array $attrs = []): Event
    {
        return Event::factory()->create(array_merge(['user_id' => $user->id], $attrs));
    }

    /** Build a signed Stripe-Signature header for the given payload and secret. */
    private function stripeSignature(string $payload, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        $sig = hash_hmac('sha256', "{$t}.{$payload}", $secret);
        return "t={$t},v1={$sig}";
    }

    /** Post a signed webhook event to /api/webhooks/stripe. */
    private function postWebhook(array $body, string $secret = 'whsec_test'): \Illuminate\Testing\TestResponse
    {
        $payload = json_encode($body);
        $signature = $this->stripeSignature($payload, $secret);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'CONTENT_TYPE'          => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);
    }

    // -------------------------------------------------------------------------
    // EventCheckoutController
    // -------------------------------------------------------------------------

    public function test_checkout_requires_auth(): void
    {
        $event = Event::factory()->create();

        $this->postJson("/api/events/{$event->id}/checkout")
            ->assertUnauthorized();
    }

    public function test_checkout_forbidden_for_other_users_event(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = $this->makeEvent($owner);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/events/{$event->id}/checkout")
            ->assertForbidden();
    }

    public function test_checkout_fails_if_event_already_has_pass(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['event_pass_paid_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/checkout")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This event already has an Event Pass.');
    }

    public function test_checkout_creates_stripe_session(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $fakeSession = StripeSession::constructFrom([
            'id'             => 'cs_test_abc123',
            'url'            => 'https://checkout.stripe.com/pay/cs_test_abc123',
            'payment_status' => 'unpaid',
        ]);
        $fakeCheckout = new Checkout($user, $fakeSession);

        $mockUser = \Mockery::mock($user)->makePartial();
        $mockUser->shouldReceive('checkoutCharge')->once()->andReturn($fakeCheckout);

        $this->actingAs($mockUser, 'sanctum')
            ->postJson("/api/events/{$event->id}/checkout")
            ->assertOk()
            ->assertJsonPath('checkout_url', 'https://checkout.stripe.com/pay/cs_test_abc123');

        $this->assertSame('cs_test_abc123', $event->fresh()->stripe_checkout_session_id);
    }

    // -------------------------------------------------------------------------
    // StripeWebhookController
    // -------------------------------------------------------------------------

    public function test_webhook_rejects_invalid_signature(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $payload = json_encode(['type' => 'checkout.session.completed']);

        $this->withHeaders(['Stripe-Signature' => 't=1234,v1=badsig'])
            ->call('POST', '/api/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload)
            ->assertStatus(400);
    }

    public function test_webhook_activates_event_pass_on_completed_checkout(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $user  = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->assertNull($event->event_pass_paid_at);

        $body = [
            'type'           => 'checkout.session.completed',
            'data'           => [
                'object' => [
                    'id'             => 'cs_test_xyz',
                    'payment_status' => 'paid',
                    'metadata'       => ['event_id' => $event->id, 'user_id' => $user->id],
                ],
            ],
        ];

        $this->postWebhook($body, 'whsec_test')
            ->assertOk();

        $this->assertNotNull($event->fresh()->event_pass_paid_at);
        $this->assertSame('cs_test_xyz', $event->fresh()->stripe_checkout_session_id);
    }

    public function test_webhook_ignores_unpaid_checkout(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $user  = User::factory()->create();
        $event = $this->makeEvent($user);

        $body = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'             => 'cs_test_xyz',
                    'payment_status' => 'unpaid',
                    'metadata'       => ['event_id' => $event->id],
                ],
            ],
        ];

        $this->postWebhook($body, 'whsec_test')->assertOk();

        $this->assertNull($event->fresh()->event_pass_paid_at);
    }

    public function test_webhook_is_idempotent(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $paidAt = now()->subHour();
        $user   = User::factory()->create();
        $event  = $this->makeEvent($user, ['event_pass_paid_at' => $paidAt]);

        $body = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'             => 'cs_test_new',
                    'payment_status' => 'paid',
                    'metadata'       => ['event_id' => $event->id],
                ],
            ],
        ];

        $this->postWebhook($body, 'whsec_test')->assertOk();

        // Timestamp should be unchanged — idempotent
        $this->assertEquals(
            $paidAt->timestamp,
            $event->fresh()->event_pass_paid_at->timestamp
        );
    }

    public function test_webhook_ignores_missing_event_metadata(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $body = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'             => 'cs_test_xyz',
                    'payment_status' => 'paid',
                    'metadata'       => [],
                ],
            ],
        ];

        // Should 200 gracefully — no crash
        $this->postWebhook($body, 'whsec_test')->assertOk();
    }

    public function test_webhook_handles_unknown_event_types_gracefully(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $body = ['type' => 'some.future.event', 'data' => ['object' => []]];

        $this->postWebhook($body, 'whsec_test')->assertOk();
    }

    // -------------------------------------------------------------------------
    // SubscriptionController
    // -------------------------------------------------------------------------

    public function test_subscription_checkout_requires_auth(): void
    {
        $this->postJson('/api/subscriptions/checkout', ['plan' => 'pro', 'interval' => 'monthly'])
            ->assertUnauthorized();
    }

    public function test_subscription_checkout_returns_503_when_price_not_configured(): void
    {
        config([
            'services.stripe.plans.pro_monthly' => null,
            'services.stripe.plans.pro_annual' => null,
            'services.stripe.plans.business_monthly' => null,
            'services.stripe.plans.business_annual' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/checkout', ['plan' => 'pro', 'interval' => 'monthly'])
            ->assertStatus(503)
            ->assertJsonPath('message', 'This plan is not available yet.');
    }

    public function test_subscription_checkout_validates_plan_and_interval(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/checkout', ['plan' => 'enterprise', 'interval' => 'weekly'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['plan', 'interval']);
    }

    public function test_subscription_portal_requires_auth(): void
    {
        $this->postJson('/api/subscriptions/portal')->assertUnauthorized();
    }

    public function test_subscription_portal_returns_404_without_stripe_customer(): void
    {
        $user = User::factory()->create(['stripe_id' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/subscriptions/portal')
            ->assertNotFound();
    }

    public function test_webhook_activates_pro_plan_on_subscription_created(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);
        config(['services.stripe.plans.pro_monthly' => 'price_pro_monthly_test']);

        $user = User::factory()->create(['stripe_id' => 'cus_test123', 'plan' => 'free']);

        $body = [
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'customer' => 'cus_test123',
                    'status'   => 'active',
                    'items'    => [
                        'data' => [
                            ['price' => ['id' => 'price_pro_monthly_test']],
                        ],
                    ],
                ],
            ],
        ];

        $this->postWebhook($body, 'whsec_test')->assertOk();

        $this->assertSame('pro', $user->fresh()->plan);
    }

    public function test_webhook_downgrades_plan_on_subscription_deleted(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $user = User::factory()->create(['stripe_id' => 'cus_test456', 'plan' => 'pro']);

        $body = [
            'type' => 'customer.subscription.deleted',
            'data' => [
                'object' => [
                    'customer' => 'cus_test456',
                    'status'   => 'canceled',
                    'items'    => ['data' => []],
                ],
            ],
        ];

        $this->postWebhook($body, 'whsec_test')->assertOk();

        $this->assertSame('free', $user->fresh()->plan);
    }

    public function test_webhook_ignores_subscription_mode_checkout_session(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);

        $user  = User::factory()->create();
        $event = $this->makeEvent($user);

        // Subscription-mode checkout should NOT update event_pass_paid_at
        $body = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'             => 'cs_sub_test',
                    'mode'           => 'subscription',
                    'payment_status' => 'paid',
                    'metadata'       => ['event_id' => $event->id],
                ],
            ],
        ];

        $this->postWebhook($body, 'whsec_test')->assertOk();

        $this->assertNull($event->fresh()->event_pass_paid_at);
    }
}

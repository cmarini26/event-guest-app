<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(User $user, array $attrs = []): Event
    {
        return Event::factory()->create(array_merge(['user_id' => $user->id, 'status' => 'published'], $attrs));
    }

    public function test_host_can_check_in_attending_guest(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent($user);
        $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'attending']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/rsvp/{$guest->rsvp_token}/check-in")
            ->assertOk()
            ->assertJsonPath('message', "{$guest->first_name} {$guest->last_name} checked in.");

        $this->assertNotNull($guest->fresh()->checked_in_at);
    }

    public function test_cannot_check_in_non_attending_guest(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent($user);
        $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'pending']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/rsvp/{$guest->rsvp_token}/check-in")
            ->assertUnprocessable();
    }

    public function test_other_host_cannot_check_in_guests(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = $this->makeEvent($owner);
        $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'attending']);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/rsvp/{$guest->rsvp_token}/check-in")
            ->assertForbidden();
    }

    public function test_host_can_undo_check_in(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent($user);
        $guest = Guest::factory()->create([
            'event_id'      => $event->id,
            'rsvp_status'   => 'attending',
            'checked_in_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/rsvp/{$guest->rsvp_token}/check-in")
            ->assertOk()
            ->assertJsonPath('checked_in_at', null);

        $this->assertNull($guest->fresh()->checked_in_at);
    }

    public function test_unauthenticated_check_in_rejected(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent($user);
        $guest = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'attending']);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}/check-in")
            ->assertUnauthorized();
    }

    public function test_device_token_can_be_registered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/device-tokens', [
                'token'    => 'fcm-device-token-abc123',
                'platform' => 'android',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('device_tokens', [
            'user_id'  => $user->id,
            'token'    => 'fcm-device-token-abc123',
            'platform' => 'android',
        ]);
    }

    public function test_device_token_registration_is_idempotent(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/device-tokens', ['token' => 'tok', 'platform' => 'ios'])
            ->assertStatus(201);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/device-tokens', ['token' => 'tok', 'platform' => 'ios'])
            ->assertStatus(201);

        $this->assertSame(1, $user->deviceTokens()->count());
    }

    public function test_device_token_can_be_unregistered(): void
    {
        $user = User::factory()->create();
        $user->deviceTokens()->create(['token' => 'remove-me', 'platform' => 'web']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/device-tokens/remove-me')
            ->assertNoContent();

        $this->assertDatabaseMissing('device_tokens', ['token' => 'remove-me']);
    }
}

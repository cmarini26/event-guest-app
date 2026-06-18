<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Notifications\RsvpReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RsvpTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(array $attrs = []): Event
    {
        $user = User::factory()->create();
        return Event::factory()->create(array_merge(['user_id' => $user->id, 'status' => 'published'], $attrs));
    }

    private function makeGuest(Event $event, array $attrs = []): Guest
    {
        return Guest::factory()->create(array_merge(['event_id' => $event->id], $attrs));
    }

    public function test_guest_can_view_rsvp_page(): void
    {
        $event = $this->makeEvent(['name' => 'Birthday Party', 'venue_name' => 'My House']);
        $guest = $this->makeGuest($event, ['first_name' => 'John']);

        $this->getJson("/api/rsvp/{$guest->rsvp_token}")
            ->assertOk()
            ->assertJsonPath('event.name', 'Birthday Party')
            ->assertJsonPath('guest.first_name', 'John');
    }

    public function test_invalid_token_returns_404(): void
    {
        $this->getJson('/api/rsvp/invalid-token-xyz')
            ->assertNotFound();
    }

    public function test_guest_can_accept_rsvp(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'attending'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'attending']);

        $this->assertDatabaseHas('guests', [
            'id' => $guest->id,
            'rsvp_status' => 'attending',
        ]);
        $this->assertNotNull($guest->fresh()->responded_at);
    }

    public function test_guest_can_decline_rsvp(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'declined'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'declined']);

        $this->assertDatabaseHas('guests', ['id' => $guest->id, 'rsvp_status' => 'declined']);
    }

    public function test_guest_is_waitlisted_when_event_is_at_capacity(): void
    {
        $event = $this->makeEvent(['max_guests' => 1]);
        $attending = $this->makeGuest($event, ['rsvp_status' => 'attending']);
        $newcomer = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$newcomer->rsvp_token}", ['status' => 'attending'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'waitlisted']);

        $this->assertDatabaseHas('guests', ['id' => $newcomer->id, 'rsvp_status' => 'waitlisted']);
    }

    public function test_guest_can_rsvp_with_preferences(): void
    {
        $event = $this->makeEvent([
            'collect_dietary' => true,
            'collect_accessibility' => true,
            'collect_seating' => true,
        ]);
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", [
            'status' => 'attending',
            'dietary_preference' => 'vegan',
            'accessibility_needs' => 'wheelchair ramp',
            'seating_preference' => 'near the front',
        ])->assertOk();

        $fresh = $guest->fresh();
        $this->assertSame('vegan', $fresh->dietary_preference);
        $this->assertSame('wheelchair ramp', $fresh->accessibility_needs);
        $this->assertSame('near the front', $fresh->seating_preference);
    }

    public function test_guest_can_add_plus_ones(): void
    {
        $event = $this->makeEvent(['allow_plus_ones' => true, 'max_plus_ones_per_guest' => 2]);
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", [
            'status' => 'attending',
            'plus_ones' => [
                ['name' => 'Plus One A', 'dietary_preference' => 'kosher'],
                ['name' => 'Plus One B'],
            ],
        ])->assertOk();

        $this->assertDatabaseHas('plus_ones', ['guest_id' => $guest->id, 'name' => 'Plus One A', 'dietary_preference' => 'kosher']);
        $this->assertDatabaseHas('plus_ones', ['guest_id' => $guest->id, 'name' => 'Plus One B']);
        $this->assertSame(2, $guest->fresh()->plusOnes()->count());
    }

    public function test_declining_removes_existing_plus_ones(): void
    {
        $event = $this->makeEvent(['allow_plus_ones' => true]);
        $guest = $this->makeGuest($event, ['rsvp_status' => 'attending']);
        $guest->plusOnes()->create(['name' => 'Plus One']);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'declined'])
            ->assertOk();

        $this->assertSame(0, $guest->fresh()->plusOnes()->count());
    }

    public function test_phone_is_required_when_event_requires_it(): void
    {
        $event = $this->makeEvent(['require_phone' => true]);
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'attending'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_rsvp_requires_valid_status(): void
    {
        $event = $this->makeEvent();
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'maybe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_rsvp_is_rejected_after_deadline(): void
    {
        $event = $this->makeEvent(['rsvp_deadline' => now()->subHour()]);
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'attending'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'RSVPs for this event are now closed.');
    }

    public function test_rsvp_is_allowed_before_deadline(): void
    {
        $event = $this->makeEvent(['rsvp_deadline' => now()->addDay()]);
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'attending'])
            ->assertOk();
    }

    public function test_rsvp_rejected_for_draft_event(): void
    {
        $event = $this->makeEvent(['status' => 'draft']);
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'attending'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'RSVPs are not open for this event.');
    }

    public function test_rsvp_rejected_for_archived_event(): void
    {
        $event = $this->makeEvent(['status' => 'archived']);
        $guest = $this->makeGuest($event);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'attending'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'RSVPs are not open for this event.');
    }

    public function test_host_is_notified_when_guest_rsvps(): void
    {
        Notification::fake();

        $host = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $host->id, 'status' => 'published']);
        $guest = Guest::factory()->create(['event_id' => $event->id]);

        $this->postJson("/api/rsvp/{$guest->rsvp_token}", ['status' => 'attending'])
            ->assertOk();

        Notification::assertSentTo($host, RsvpReceived::class, function ($n) use ($guest) {
            return $n->guest->id === $guest->id;
        });
    }

    public function test_rsvp_show_includes_deadline(): void
    {
        $deadline = now()->addDays(3);
        $event    = $this->makeEvent(['rsvp_deadline' => $deadline]);
        $guest    = $this->makeGuest($event);

        $this->getJson("/api/rsvp/{$guest->rsvp_token}")
            ->assertOk()
            ->assertJsonPath('event.rsvp_deadline', fn ($v) => $v !== null);
    }
}

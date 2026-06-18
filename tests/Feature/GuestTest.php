<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Notifications\GuestInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class GuestTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(User $user, array $attrs = []): Event
    {
        return Event::factory()->create(array_merge(['user_id' => $user->id], $attrs));
    }

    private function makeGuest(Event $event, array $attrs = []): Guest
    {
        return Guest::factory()->create(array_merge(['event_id' => $event->id], $attrs));
    }

    public function test_host_can_list_guests(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        $this->makeGuest($event);
        $this->makeGuest($event);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/guests")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_host_can_add_guest(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests", [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane@example.com',
            ])
            ->assertCreated()
            ->assertJsonFragment(['first_name' => 'Jane'])
            ->assertJsonStructure(['rsvp_token']);

        $this->assertDatabaseHas('guests', ['email' => 'jane@example.com', 'event_id' => $event->id]);
    }

    public function test_guest_gets_unique_rsvp_token(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $r1 = $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests", ['first_name' => 'A', 'last_name' => 'B'])
            ->assertCreated();

        $r2 = $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests", ['first_name' => 'C', 'last_name' => 'D'])
            ->assertCreated();

        $this->assertNotEquals($r1->json('rsvp_token'), $r2->json('rsvp_token'));
    }

    public function test_host_can_update_guest(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        $guest = $this->makeGuest($event);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/events/{$event->id}/guests/{$guest->id}", ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonFragment(['email' => 'new@example.com']);
    }

    public function test_host_can_delete_guest(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        $guest = $this->makeGuest($event);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}/guests/{$guest->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('guests', ['id' => $guest->id]);
    }

    public function test_host_cannot_manage_guests_of_another_users_event(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = $this->makeEvent($other);
        $guest = $this->makeGuest($event);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/guests")
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}/guests/{$guest->id}")
            ->assertForbidden();
    }

    public function test_free_plan_enforces_50_guest_limit(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        $event = $this->makeEvent($user);
        Guest::factory()->count(50)->create(['event_id' => $event->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests", ['first_name' => 'Extra', 'last_name' => 'Guest'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Guest limit of 50 reached for this event.']);
    }

    public function test_event_pass_raises_limit_to_300(): void
    {
        $user  = User::factory()->create(['plan' => 'free']);
        $event = $this->makeEvent($user, ['event_pass_paid_at' => now()]);
        Guest::factory()->count(50)->create(['event_id' => $event->id]);

        // Guest 51 should succeed — Event Pass lifts limit to 300
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests", ['first_name' => 'Bonus', 'last_name' => 'Guest'])
            ->assertCreated();
    }

    public function test_host_can_export_guests_as_csv(): void
    {
        $user  = User::factory()->create();
        $event = $this->makeEvent($user, ['collect_dietary' => true]);
        $this->makeGuest($event, ['first_name' => 'Alice', 'last_name' => 'Smith', 'email' => 'alice@example.com', 'dietary_preference' => 'Vegan']);
        $this->makeGuest($event, ['first_name' => 'Bob',   'last_name' => 'Jones', 'email' => null]);

        $response = $this->actingAs($user, 'sanctum')
            ->get("/api/events/{$event->id}/guests/export");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $response->getContent();
        $this->assertStringContainsString('Alice', $body);
        $this->assertStringContainsString('Vegan', $body);
        $this->assertStringContainsString('Dietary Preference', $body);
    }

    public function test_export_requires_auth(): void
    {
        $event = Event::factory()->create();

        $this->getJson("/api/events/{$event->id}/guests/export")
            ->assertUnauthorized();
    }

    public function test_pro_plan_has_no_guest_limit(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        $event = $this->makeEvent($user);
        Guest::factory()->count(100)->create(['event_id' => $event->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests", ['first_name' => 'Guest', 'last_name' => '101'])
            ->assertCreated();
    }

    public function test_host_can_send_invitation_email(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        $guest = $this->makeGuest($event, ['email' => 'guest@example.com', 'invited_at' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests/{$guest->id}/invite")
            ->assertOk();

        Notification::assertSentTo($guest, GuestInvitation::class);
        $this->assertNotNull($guest->fresh()->invited_at);
    }

    public function test_cannot_invite_guest_without_email(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        $guest = $this->makeGuest($event, ['email' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests/{$guest->id}/invite")
            ->assertStatus(422);
    }

    public function test_bulk_invite_sends_to_uninvited_guests_with_emails(): void
    {
        Notification::fake();

        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        $this->makeGuest($event, ['email' => 'a@example.com', 'invited_at' => null]);
        $this->makeGuest($event, ['email' => 'b@example.com', 'invited_at' => null]);
        $this->makeGuest($event, ['email' => 'c@example.com', 'invited_at' => now()]);
        $this->makeGuest($event, ['email' => null, 'invited_at' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/guests/bulk-invite")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Invitations sent to 2 guest(s).']);

        Notification::assertCount(2);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(User $user, array $attrs = []): Event
    {
        return Event::factory()->create(array_merge(['user_id' => $user->id], $attrs));
    }

    public function test_user_can_list_their_events(): void
    {
        $user = User::factory()->create();
        $this->makeEvent($user);
        $this->makeEvent($user);
        $other = User::factory()->create();
        $this->makeEvent($other);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/events')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_user_can_create_event(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', [
                'name' => 'Summer Party',
                'starts_at' => '2026-08-15 18:00:00',
                'allow_plus_ones' => true,
                'collect_dietary' => true,
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Summer Party', 'status' => 'draft'])
            ->assertJsonStructure(['id', 'slug']);

        $this->assertDatabaseHas('events', ['name' => 'Summer Party', 'user_id' => $user->id]);
    }

    public function test_event_slug_is_auto_generated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['name' => 'My Cool Event', 'starts_at' => '2026-09-01 18:00:00'])
            ->assertCreated();

        $this->assertSame('my-cool-event', $response->json('slug'));
    }

    public function test_duplicate_slug_gets_suffix(): void
    {
        $user = User::factory()->create();
        Event::factory()->create(['user_id' => $user->id, 'slug' => 'my-event', 'name' => 'My Event']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['name' => 'My Event', 'starts_at' => '2026-09-01 18:00:00'])
            ->assertCreated();

        $this->assertSame('my-event-1', $response->json('slug'));
    }

    public function test_user_can_view_their_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $event->id]);
    }

    public function test_user_cannot_view_another_users_event(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $event = $this->makeEvent($other);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}")
            ->assertForbidden();
    }

    public function test_user_can_update_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['name' => 'Old Name']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/events/{$event->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'New Name']);
    }

    public function test_user_can_delete_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }

    public function test_user_can_publish_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['status' => 'draft']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/publish")
            ->assertOk()
            ->assertJsonFragment(['status' => 'published']);
    }

    public function test_user_can_archive_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/archive")
            ->assertOk()
            ->assertJsonFragment(['status' => 'archived']);
    }

    public function test_rsvp_deadline_must_be_before_start(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['starts_at' => '2026-12-01 18:00:00']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/events/{$event->id}", [
                'rsvp_deadline' => '2026-12-02 10:00:00',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rsvp_deadline');
    }

    public function test_rsvp_deadline_before_start_passes(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['starts_at' => '2026-12-01 18:00:00']);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/events/{$event->id}", [
                'rsvp_deadline' => '2026-11-28 18:00:00',
            ])
            ->assertOk();
    }

    public function test_cannot_publish_archived_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['status' => 'archived']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/publish")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Only draft events can be published.');
    }

    public function test_cannot_archive_already_archived_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['status' => 'archived']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/archive")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Event is already archived.');
    }

    public function test_free_plan_blocks_fourth_active_event(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        Event::factory()->count(3)->create(['user_id' => $user->id, 'status' => 'published']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['name' => 'Fourth Event', 'starts_at' => '2026-10-01 18:00:00'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Upgrade your plan to create more events.');
    }

    public function test_free_plan_allows_new_event_after_archiving(): void
    {
        $user = User::factory()->create(['plan' => 'free']);
        Event::factory()->count(2)->create(['user_id' => $user->id, 'status' => 'published']);
        Event::factory()->create(['user_id' => $user->id, 'status' => 'archived']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/events', ['name' => 'New Event', 'starts_at' => '2026-10-01 18:00:00'])
            ->assertCreated();
    }

    public function test_unauthenticated_cannot_create_event(): void
    {
        $this->postJson('/api/events', ['name' => 'Test', 'starts_at' => '2026-09-01 18:00:00'])
            ->assertUnauthorized();
    }
}

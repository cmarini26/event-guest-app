<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\SubEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubEventTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(User $user, array $attrs = []): Event
    {
        return Event::factory()->create(array_merge([
            'user_id' => $user->id,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(8),
        ], $attrs));
    }

    public function test_index_requires_auth(): void
    {
        $event = $this->makeEvent(User::factory()->create());
        $this->getJson("/api/events/{$event->id}/sub-events")->assertUnauthorized();
    }

    public function test_other_user_cannot_list_sub_events(): void
    {
        $event = $this->makeEvent(User::factory()->create());
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/events/{$event->id}/sub-events")
            ->assertForbidden();
    }

    public function test_host_can_create_sub_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/sub-events", [
                'name' => 'Opening Keynote',
                'starts_at' => $event->starts_at->copy()->addHour()->toDateTimeString(),
                'ends_at' => $event->starts_at->copy()->addHours(2)->toDateTimeString(),
                'location' => 'Main Hall',
            ])
            ->assertCreated()
            ->assertJsonFragment(['name' => 'Opening Keynote', 'location' => 'Main Hall']);

        $this->assertDatabaseHas('sub_events', ['event_id' => $event->id, 'name' => 'Opening Keynote']);
    }

    public function test_session_cannot_start_before_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/sub-events", [
                'name' => 'Too Early',
                'starts_at' => $event->starts_at->copy()->subHour()->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_session_cannot_end_after_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/sub-events", [
                'name' => 'Runs Over',
                'starts_at' => $event->ends_at->copy()->subHour()->toDateTimeString(),
                'ends_at' => $event->ends_at->copy()->addHours(2)->toDateTimeString(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_host_can_update_sub_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        $sub = SubEvent::factory()->create([
            'event_id' => $event->id,
            'starts_at' => $event->starts_at->copy()->addHour(),
            'ends_at' => $event->starts_at->copy()->addHours(2),
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/events/{$event->id}/sub-events/{$sub->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Renamed']);

        $this->assertDatabaseHas('sub_events', ['id' => $sub->id, 'name' => 'Renamed']);
    }

    public function test_host_can_delete_sub_event(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        $sub = SubEvent::factory()->create([
            'event_id' => $event->id,
            'starts_at' => $event->starts_at->copy()->addHour(),
            'ends_at' => $event->starts_at->copy()->addHours(2),
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}/sub-events/{$sub->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('sub_events', ['id' => $sub->id]);
    }

    public function test_cannot_modify_sub_event_of_other_event(): void
    {
        $user = User::factory()->create();
        $eventA = $this->makeEvent($user);
        $eventB = $this->makeEvent($user);
        $sub = SubEvent::factory()->create([
            'event_id' => $eventB->id,
            'starts_at' => $eventB->starts_at->copy()->addHour(),
            'ends_at' => $eventB->starts_at->copy()->addHours(2),
        ]);

        // sub belongs to B, but we address it under A → 404
        $this->actingAs($user, 'sanctum')
            ->putJson("/api/events/{$eventA->id}/sub-events/{$sub->id}", ['name' => 'Hijack'])
            ->assertNotFound();
    }

    public function test_sub_events_are_ordered(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        SubEvent::factory()->create(['event_id' => $event->id, 'name' => 'Second', 'sort_order' => 2, 'starts_at' => $event->starts_at->copy()->addHours(3)]);
        SubEvent::factory()->create(['event_id' => $event->id, 'name' => 'First', 'sort_order' => 1, 'starts_at' => $event->starts_at->copy()->addHours(1)]);

        $names = $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/sub-events")
            ->assertOk()
            ->json('*.name');

        $this->assertSame(['First', 'Second'], $names);
    }
}

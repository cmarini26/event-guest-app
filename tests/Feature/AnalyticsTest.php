<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\PlusOne;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(User $user, array $attrs = []): Event
    {
        return Event::factory()->create(array_merge(['user_id' => $user->id], $attrs));
    }

    public function test_analytics_requires_auth(): void
    {
        $event = $this->makeEvent(User::factory()->create());

        $this->getJson("/api/events/{$event->id}/analytics")
            ->assertUnauthorized();
    }

    public function test_other_user_cannot_view_analytics(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $event = $this->makeEvent($owner);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/events/{$event->id}/analytics")
            ->assertForbidden();
    }

    public function test_analytics_computes_totals_and_rates(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['status' => 'published']);

        Guest::factory()->count(4)->create(['event_id' => $event->id, 'rsvp_status' => 'attending', 'responded_at' => now()]);
        Guest::factory()->count(2)->create(['event_id' => $event->id, 'rsvp_status' => 'declined', 'responded_at' => now()]);
        Guest::factory()->count(3)->create(['event_id' => $event->id, 'rsvp_status' => 'pending']);
        Guest::factory()->count(1)->create(['event_id' => $event->id, 'rsvp_status' => 'waitlisted', 'responded_at' => now()]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/analytics")
            ->assertOk()
            ->assertJsonPath('totals.invited', 10)
            ->assertJsonPath('totals.responded', 7)
            ->assertJsonPath('totals.response_rate', 70)
            // acceptance = 4 attending / (4 + 2 declined) = 66.7
            ->assertJsonPath('totals.acceptance_rate', 66.7)
            ->assertJsonPath('rsvp_breakdown.attending', 4)
            ->assertJsonPath('rsvp_breakdown.declined', 2)
            ->assertJsonPath('rsvp_breakdown.pending', 3)
            ->assertJsonPath('rsvp_breakdown.waitlisted', 1);
    }

    public function test_analytics_dietary_and_seating_breakdown(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        Guest::factory()->count(2)->create(['event_id' => $event->id, 'dietary_preference' => 'Vegan']);
        Guest::factory()->create(['event_id' => $event->id, 'dietary_preference' => 'Vegetarian']);
        Guest::factory()->create(['event_id' => $event->id, 'dietary_preference' => null]);
        Guest::factory()->count(3)->create(['event_id' => $event->id, 'seating_preference' => 'Front']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/analytics")
            ->assertOk()
            ->assertJsonPath('dietary.Vegan', 2)
            ->assertJsonPath('dietary.Vegetarian', 1)
            ->assertJsonPath('seating.Front', 3);
    }

    public function test_analytics_counts_plus_ones_in_headcount(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['status' => 'published']);

        $g1 = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'attending', 'responded_at' => now()]);
        $g2 = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'attending', 'responded_at' => now()]);
        PlusOne::factory()->count(2)->create(['guest_id' => $g1->id]);
        PlusOne::factory()->create(['guest_id' => $g2->id]);

        // declined guest with a plus-one should NOT count toward expected headcount
        $g3 = Guest::factory()->create(['event_id' => $event->id, 'rsvp_status' => 'declined', 'responded_at' => now()]);
        PlusOne::factory()->create(['guest_id' => $g3->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/analytics")
            ->assertOk()
            // 2 attending guests + 3 attending plus-ones = 5
            ->assertJsonPath('totals.expected_headcount', 5)
            ->assertJsonPath('totals.plus_ones', 4);
    }

    public function test_analytics_response_timeline_is_cumulative(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user, ['status' => 'published']);

        Guest::factory()->count(2)->create(['event_id' => $event->id, 'rsvp_status' => 'attending', 'responded_at' => now()->subDays(2)]);
        Guest::factory()->count(3)->create(['event_id' => $event->id, 'rsvp_status' => 'attending', 'responded_at' => now()->subDay()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/analytics")
            ->assertOk();

        $timeline = $response->json('response_timeline');
        $this->assertCount(2, $timeline);
        $this->assertSame(2, $timeline[0]['cumulative']);
        $this->assertSame(5, $timeline[1]['cumulative']);
    }

    public function test_empty_event_returns_zero_rates(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/analytics")
            ->assertOk()
            ->assertJsonPath('totals.invited', 0)
            ->assertJsonPath('totals.response_rate', 0)
            ->assertJsonPath('totals.acceptance_rate', 0);
    }
}

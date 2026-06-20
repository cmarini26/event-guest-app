<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_admin_can_fetch_stats(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create();
        $event = Event::factory()->create(['user_id' => $admin->id, 'status' => 'published']);
        Guest::factory()->count(3)->create(['event_id' => $event->id]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure(['total_users', 'total_events', 'active_events', 'total_guests', 'event_passes', 'revenue_cents'])
            ->assertJsonFragment(['total_users' => 3, 'total_guests' => 3]);
    }

    public function test_admin_can_fetch_users(): void
    {
        $admin = $this->admin();
        User::factory()->count(2)->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(3)
            ->assertJsonFragment(['email' => $admin->email, 'is_admin' => true]);
    }

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_admin_routes(): void
    {
        $this->getJson('/api/admin/stats')->assertUnauthorized();
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }

    public function test_me_returns_is_admin_flag(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonFragment(['is_admin' => true]);
    }

    public function test_me_returns_is_admin_false_for_regular_user(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonFragment(['is_admin' => false]);
    }

    public function test_admin_can_fetch_user_events(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create();
        $event = Event::factory()->create(['user_id' => $user->id, 'status' => 'published']);
        Guest::factory()->count(2)->create(['event_id' => $event->id, 'rsvp_status' => 'attending']);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/admin/users/{$user->id}/events")
            ->assertOk()
            ->assertJsonStructure(['user', 'events'])
            ->assertJsonFragment(['id' => $event->id, 'attending_count' => 2]);
    }

    public function test_admin_can_grant_admin_to_another_user(): void
    {
        $admin = $this->admin();
        $user  = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$user->id}/toggle-admin")
            ->assertOk()
            ->assertJsonFragment(['is_admin' => true]);

        $this->assertTrue($user->fresh()->is_admin);
    }

    public function test_admin_can_revoke_admin_from_another_user(): void
    {
        $admin  = $this->admin();
        $other  = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$other->id}/toggle-admin")
            ->assertOk()
            ->assertJsonFragment(['is_admin' => false]);
    }

    public function test_admin_cannot_toggle_own_admin_status(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/users/{$admin->id}/toggle-admin")
            ->assertUnprocessable();
    }

    public function test_admin_stats_include_failed_jobs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure(['failed_jobs']);
    }

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonFragment(['status' => 'ok'])
            ->assertJsonStructure(['status', 'database', 'cache', 'queue_table']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pro_user_can_list_api_keys(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        ApiKey::generate($user, 'Test key');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/api-keys')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_free_user_cannot_access_api_keys(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/api-keys')
            ->assertForbidden();
    }

    public function test_pro_user_can_create_api_key(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/api-keys', ['name' => 'My Integration'])
            ->assertCreated()
            ->assertJsonStructure(['key', 'token']);

        // Token should be shown only once
        $this->assertStringStartsWith('gl', $response->json('token'));
    }

    public function test_api_key_can_authenticate_requests(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        [, $plaintext] = ApiKey::generate($user, 'CI key');

        $this->withHeaders(['Authorization' => "Bearer {$plaintext}"])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('email', $user->email);
    }

    public function test_invalid_api_key_returns_401(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer glinvalidkey_xxxxx'])
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_revoked_api_key_returns_401(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        [$model, $plaintext] = ApiKey::generate($user, 'Revoked');
        $model->revoke();

        $this->withHeaders(['Authorization' => "Bearer {$plaintext}"])
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    public function test_user_can_revoke_api_key(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        [$model] = ApiKey::generate($user, 'Revokable');

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/api-keys/{$model->id}")
            ->assertNoContent();

        $this->assertNotNull($model->fresh()->revoked_at);
    }

    public function test_user_cannot_revoke_another_users_key(): void
    {
        $owner = User::factory()->create(['plan' => 'pro']);
        $other = User::factory()->create(['plan' => 'pro']);
        [$model] = ApiKey::generate($owner, 'Theirs');

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/api-keys/{$model->id}")
            ->assertForbidden();
    }

    public function test_api_key_marks_last_used_at(): void
    {
        $user = User::factory()->create(['plan' => 'pro']);
        [$model, $plaintext] = ApiKey::generate($user, 'Tracker');

        $this->assertNull($model->last_used_at);

        $this->withHeaders(['Authorization' => "Bearer {$plaintext}"])
            ->getJson('/api/auth/me')
            ->assertOk();

        $this->assertNotNull($model->fresh()->last_used_at);
    }
}

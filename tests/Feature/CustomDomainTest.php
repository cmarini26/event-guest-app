<?php

namespace Tests\Feature;

use App\Models\CustomDomain;
use App\Models\User;
use App\Services\DnsVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomDomainTest extends TestCase
{
    use RefreshDatabase;

    private function proUser(): User
    {
        return User::factory()->create(['plan' => 'pro']);
    }

    /**
     * Swap the DNS verifier for a fake returning the given TXT records.
     */
    private function fakeDns(array $records): void
    {
        $this->app->bind(DnsVerifier::class, fn () => new class($records) implements DnsVerifier {
            public function __construct(private array $records) {}
            public function txtRecords(string $host): array
            {
                return $this->records;
            }
        });
    }

    public function test_index_requires_auth(): void
    {
        $this->getJson('/api/custom-domains')->assertUnauthorized();
    }

    public function test_free_user_cannot_add_domain(): void
    {
        $user = User::factory()->create(['plan' => 'free']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/custom-domains', ['domain' => 'events.acme.com'])
            ->assertForbidden();
    }

    public function test_pro_user_can_add_domain(): void
    {
        $user = $this->proUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/custom-domains', ['domain' => 'events.acme.com'])
            ->assertCreated()
            ->assertJsonFragment(['domain' => 'events.acme.com', 'is_verified' => false])
            ->assertJsonStructure(['dns_record' => ['type', 'host', 'value']]);

        $this->assertDatabaseHas('custom_domains', ['user_id' => $user->id, 'domain' => 'events.acme.com']);
    }

    public function test_invalid_domain_rejected(): void
    {
        $user = $this->proUser();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/custom-domains', ['domain' => 'https://not a domain/path'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    }

    public function test_duplicate_domain_rejected(): void
    {
        $user = $this->proUser();
        CustomDomain::factory()->create(['user_id' => $user->id, 'domain' => 'taken.com']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/custom-domains', ['domain' => 'taken.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('domain');
    }

    public function test_verify_succeeds_when_txt_record_present(): void
    {
        $user = $this->proUser();
        $domain = CustomDomain::factory()->create(['user_id' => $user->id]);

        $this->fakeDns([$domain->expectedTxtValue()]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/custom-domains/{$domain->id}/verify")
            ->assertOk()
            ->assertJsonFragment(['is_verified' => true]);

        $this->assertNotNull($domain->fresh()->verified_at);
    }

    public function test_verify_fails_when_txt_record_missing(): void
    {
        $user = $this->proUser();
        $domain = CustomDomain::factory()->create(['user_id' => $user->id]);

        $this->fakeDns(['some-other-value']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/custom-domains/{$domain->id}/verify")
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'expected' => ['type', 'host', 'value']]);

        $this->assertNull($domain->fresh()->verified_at);
    }

    public function test_cannot_verify_another_users_domain(): void
    {
        $owner = $this->proUser();
        $other = $this->proUser();
        $domain = CustomDomain::factory()->create(['user_id' => $owner->id]);

        $this->fakeDns([$domain->expectedTxtValue()]);

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/custom-domains/{$domain->id}/verify")
            ->assertNotFound();
    }

    public function test_user_can_delete_domain(): void
    {
        $user = $this->proUser();
        $domain = CustomDomain::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/custom-domains/{$domain->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('custom_domains', ['id' => $domain->id]);
    }

    public function test_user_only_sees_own_domains(): void
    {
        $user = $this->proUser();
        $other = $this->proUser();
        CustomDomain::factory()->count(2)->create(['user_id' => $user->id]);
        CustomDomain::factory()->create(['user_id' => $other->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/custom-domains')
            ->assertOk()
            ->assertJsonCount(2);
    }
}

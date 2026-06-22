<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use App\Models\WhiteLabelSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WhiteLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_user_can_fetch_white_label_settings(): void
    {
        $user = User::factory()->create(['plan' => 'business']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/white-label')
            ->assertOk();
    }

    public function test_non_business_user_cannot_access_white_label(): void
    {
        foreach (['free', 'pro', 'event_pass'] as $plan) {
            $user = User::factory()->create(['plan' => $plan]);
            $this->actingAs($user, 'sanctum')
                ->getJson('/api/white-label')
                ->assertForbidden();
        }
    }

    public function test_business_user_can_update_white_label_settings(): void
    {
        $user = User::factory()->create(['plan' => 'business']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', [
                'brand_name'        => 'Acme Events',
                'primary_color'     => '#4f46e5',
                'email_sender_name' => 'Acme',
                'hide_branding'     => true,
            ])
            ->assertOk()
            ->assertJsonFragment(['brand_name' => 'Acme Events']);

        $this->assertDatabaseHas('white_label_settings', [
            'user_id'    => $user->id,
            'brand_name' => 'Acme Events',
        ]);
    }

    public function test_update_upserts_existing_settings(): void
    {
        $user = User::factory()->create(['plan' => 'business']);
        WhiteLabelSetting::create(['user_id' => $user->id, 'brand_name' => 'Old Name']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', ['brand_name' => 'New Name'])
            ->assertOk();

        $this->assertSame(1, WhiteLabelSetting::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('white_label_settings', ['user_id' => $user->id, 'brand_name' => 'New Name']);
    }

    public function test_invalid_hex_color_rejected(): void
    {
        $user = User::factory()->create(['plan' => 'business']);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/white-label', ['primary_color' => 'not-a-color'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('primary_color');
    }

    public function test_business_user_can_upload_logo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['plan' => 'business']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/white-label/logo', [
                'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            ])
            ->assertOk()
            ->assertJsonPath('logo_url', fn ($v) => $v !== null);

        $setting = $user->whiteLabelSetting()->first();
        Storage::disk('public')->assertExists($setting->logo_path);
    }

    public function test_logo_upload_replaces_old_logo(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['plan' => 'business']);
        $old  = UploadedFile::fake()->image('old.png');
        $path = $old->store('white-label/' . $user->id, 'public');
        WhiteLabelSetting::create(['user_id' => $user->id, 'logo_path' => $path]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/white-label/logo', [
                'logo' => UploadedFile::fake()->image('new.png', 200, 200),
            ])
            ->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_branding_included_in_rsvp_show_for_business_host(): void
    {
        $host  = User::factory()->create(['plan' => 'business']);
        $event = Event::factory()->create(['user_id' => $host->id, 'status' => 'published']);
        WhiteLabelSetting::create([
            'user_id'       => $host->id,
            'brand_name'    => 'Acme',
            'hide_branding' => true,
        ]);
        $guest = Guest::factory()->create(['event_id' => $event->id]);

        $this->getJson("/api/rsvp/{$guest->rsvp_token}")
            ->assertOk()
            ->assertJsonPath('branding.brand_name', 'Acme')
            ->assertJsonPath('branding.hide_branding', true);
    }

    public function test_branding_is_null_in_rsvp_show_for_non_business_host(): void
    {
        $host  = User::factory()->create(['plan' => 'pro']);
        $event = Event::factory()->create(['user_id' => $host->id, 'status' => 'published']);
        $guest = Guest::factory()->create(['event_id' => $event->id]);

        $this->getJson("/api/rsvp/{$guest->rsvp_token}")
            ->assertOk()
            ->assertJsonPath('branding', null);
    }
}

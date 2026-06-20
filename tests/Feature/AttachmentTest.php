<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeEvent(User $user): Event
    {
        return Event::factory()->create(['user_id' => $user->id]);
    }

    public function test_upload_requires_auth(): void
    {
        $event = $this->makeEvent(User::factory()->create());
        $this->postJson("/api/events/{$event->id}/attachments")->assertUnauthorized();
    }

    public function test_other_user_cannot_upload(): void
    {
        Storage::fake('public');
        $event = $this->makeEvent(User::factory()->create());

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/events/{$event->id}/attachments", [
                'file' => UploadedFile::fake()->create('agenda.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    public function test_host_can_upload_attachment(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $file = UploadedFile::fake()->create('agenda.pdf', 200, 'application/pdf');

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/attachments", ['file' => $file])
            ->assertCreated()
            ->assertJsonFragment(['original_name' => 'agenda.pdf', 'mime_type' => 'application/pdf']);

        $path = $response->json('path');
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('attachments', ['event_id' => $event->id, 'original_name' => 'agenda.pdf']);
    }

    public function test_rejects_disallowed_file_type(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/attachments", [
                'file' => UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        // 11 MB > 10 MB cap
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/attachments", [
                'file' => UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_enforces_max_attachments_per_event(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        Attachment::factory()->count(10)->create(['event_id' => $event->id]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/attachments", [
                'file' => UploadedFile::fake()->create('one-too-many.pdf', 50, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_host_can_list_attachments(): void
    {
        $user = User::factory()->create();
        $event = $this->makeEvent($user);
        Attachment::factory()->count(3)->create(['event_id' => $event->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/events/{$event->id}/attachments")
            ->assertOk()
            ->assertJsonCount(3);
    }

    public function test_host_can_delete_attachment_and_file_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $event = $this->makeEvent($user);

        $file = UploadedFile::fake()->create('map.pdf', 100, 'application/pdf');
        $path = $this->actingAs($user, 'sanctum')
            ->postJson("/api/events/{$event->id}/attachments", ['file' => $file])
            ->json('path');

        $attachment = Attachment::where('event_id', $event->id)->first();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$event->id}/attachments/{$attachment->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_cannot_delete_attachment_from_other_event(): void
    {
        $user = User::factory()->create();
        $eventA = $this->makeEvent($user);
        $eventB = $this->makeEvent($user);
        $attachment = Attachment::factory()->create(['event_id' => $eventB->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/events/{$eventA->id}/attachments/{$attachment->id}")
            ->assertNotFound();
    }
}

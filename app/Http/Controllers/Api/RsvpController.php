<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Models\PlusOne;
use App\Notifications\RsvpReceived;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RsvpController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $guest = Guest::with(['event', 'plusOnes'])
            ->where('rsvp_token', $token)
            ->firstOrFail();

        return response()->json([
            'guest' => [
                'id' => $guest->id,
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'rsvp_status' => $guest->rsvp_status,
                'plus_ones' => $guest->plusOnes,
            ],
            'event' => [
                'id' => $guest->event->id,
                'name' => $guest->event->name,
                'description' => $guest->event->description,
                'starts_at' => $guest->event->starts_at,
                'ends_at' => $guest->event->ends_at,
                'timezone' => $guest->event->timezone,
                'venue_name' => $guest->event->venue_name,
                'venue_address' => $guest->event->venue_address,
                'cover_image' => $guest->event->cover_image,
                'allow_plus_ones' => $guest->event->allow_plus_ones,
                'max_plus_ones_per_guest' => $guest->event->max_plus_ones_per_guest,
                'collect_dietary' => $guest->event->collect_dietary,
                'collect_accessibility' => $guest->event->collect_accessibility,
                'collect_seating' => $guest->event->collect_seating,
                'require_phone' => $guest->event->require_phone,
                'rsvp_deadline' => $guest->event->rsvp_deadline,
                'is_at_capacity' => $guest->event->isAtCapacity(),
            ],
        ]);
    }

    public function respond(Request $request, string $token): JsonResponse
    {
        $guest = Guest::with(['event.user'])->where('rsvp_token', $token)->firstOrFail();
        $event = $guest->event;

        if ($event->status !== 'published') {
            return response()->json(['message' => 'RSVPs are not open for this event.'], 422);
        }

        if ($event->rsvp_deadline && now()->isAfter($event->rsvp_deadline)) {
            return response()->json(['message' => 'RSVPs for this event are now closed.'], 422);
        }

        $data = $request->validate([
            'status' => ['required', 'in:attending,declined'],
            'phone' => [$event->require_phone && $request->status === 'attending' ? 'required' : 'nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'dietary_preference' => [$event->collect_dietary ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:255'],
            'accessibility_needs' => [$event->collect_accessibility ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:255'],
            'seating_preference' => [$event->collect_seating ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:255'],
            'plus_ones' => ['nullable', 'array', 'max:' . $event->max_plus_ones_per_guest],
            'plus_ones.*.name' => ['required', 'string', 'max:200'],
            'plus_ones.*.dietary_preference' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $data['status'];

        if ($status === 'attending' && $event->isAtCapacity()) {
            $status = 'waitlisted';
        }

        $guest->update([
            'rsvp_status' => $status,
            'responded_at' => now(),
            'phone' => $data['phone'] ?? $guest->phone,
            'notes' => $data['notes'] ?? null,
            'dietary_preference' => $data['dietary_preference'] ?? null,
            'accessibility_needs' => $data['accessibility_needs'] ?? null,
            'seating_preference' => $data['seating_preference'] ?? null,
        ]);

        if ($status === 'attending' && $event->allow_plus_ones && ! empty($data['plus_ones'])) {
            $guest->plusOnes()->delete();
            foreach ($data['plus_ones'] as $plusOne) {
                $guest->plusOnes()->create($plusOne);
            }
        } elseif ($status === 'declined') {
            $guest->plusOnes()->delete();
        }

        $event->user->notify(new RsvpReceived($guest, $event));

        return response()->json([
            'status' => $status,
            'message' => match ($status) {
                'attending' => 'You\'re confirmed! See you there.',
                'waitlisted' => 'You\'ve been added to the waitlist.',
                'declined' => 'Thanks for letting us know.',
            },
        ]);
    }
}

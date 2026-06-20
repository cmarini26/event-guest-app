<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EventController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $events = $request->user()->events()
            ->withCount(['guests', 'guests as attending_count' => fn ($q) => $q->where('rsvp_status', 'attending')])
            ->latest()
            ->get();

        return response()->json($events);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->canCreateEvent()) {
            return response()->json(['message' => 'Upgrade your plan to create more events.'], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'timezone'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'max_guests' => ['nullable', 'integer', 'min:1'],
            'rsvp_deadline' => ['nullable', 'date', 'before:starts_at'],
            'allow_plus_ones' => ['boolean'],
            'max_plus_ones_per_guest' => ['integer', 'min:0', 'max:10'],
            'collect_dietary' => ['boolean'],
            'collect_accessibility' => ['boolean'],
            'collect_seating' => ['boolean'],
            'require_phone' => ['boolean'],
        ]);

        $event = $request->user()->events()->create($data);

        return response()->json($event, 201);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $event->loadCount([
            'guests',
            'guests as attending_count' => fn ($q) => $q->where('rsvp_status', 'attending'),
            'guests as declined_count' => fn ($q) => $q->where('rsvp_status', 'declined'),
            'guests as pending_count' => fn ($q) => $q->where('rsvp_status', 'pending'),
        ]);

        return response()->json($event);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'timezone'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:500'],
            'max_guests' => ['nullable', 'integer', 'min:1'],
            'rsvp_deadline' => ['nullable', 'date'],
            'allow_plus_ones' => ['boolean'],
            'max_plus_ones_per_guest' => ['integer', 'min:0', 'max:10'],
            'collect_dietary' => ['boolean'],
            'collect_accessibility' => ['boolean'],
            'collect_seating' => ['boolean'],
            'require_phone' => ['boolean'],
        ]);

        $effectiveStart = isset($data['starts_at'])
            ? \Carbon\Carbon::parse($data['starts_at'])
            : $event->starts_at;

        if (! empty($data['ends_at']) && \Carbon\Carbon::parse($data['ends_at'])->lte($effectiveStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ends_at' => ['The end time must be after the start time.'],
            ]);
        }

        if (! empty($data['rsvp_deadline']) && \Carbon\Carbon::parse($data['rsvp_deadline'])->gte($effectiveStart)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'rsvp_deadline' => ['The RSVP deadline must be before the event start time.'],
            ]);
        }

        $event->update($data);

        return response()->json($event);
    }

    public function destroy(Request $request, Event $event): JsonResponse
    {
        $this->authorize('delete', $event);
        $event->delete();

        return response()->json(null, 204);
    }

    public function publish(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        if ($event->status !== 'draft') {
            return response()->json(['message' => 'Only draft events can be published.'], 422);
        }

        $event->update(['status' => 'published']);

        return response()->json($event);
    }

    public function archive(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        if ($event->status === 'archived') {
            return response()->json(['message' => 'Event is already archived.'], 422);
        }

        $event->update(['status' => 'archived']);

        return response()->json($event);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\SubEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class SubEventController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        return response()->json($event->subEvents()->get());
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $data = $this->validateData($request);

        $this->assertWithinWindow($event, $data);

        $subEvent = $event->subEvents()->create($data);

        return response()->json($subEvent, 201);
    }

    public function update(Request $request, Event $event, SubEvent $subEvent): JsonResponse
    {
        $this->authorize('update', $event);
        abort_unless($subEvent->event_id === $event->id, 404);

        $data = $this->validateData($request, partial: true);

        $merged = array_merge([
            'starts_at' => $subEvent->starts_at,
            'ends_at' => $subEvent->ends_at,
        ], $data);
        $this->assertWithinWindow($event, $merged);

        $subEvent->update($data);

        return response()->json($subEvent);
    }

    public function destroy(Request $request, Event $event, SubEvent $subEvent): JsonResponse
    {
        $this->authorize('update', $event);
        abort_unless($subEvent->event_id === $event->id, 404);

        $subEvent->delete();

        return response()->json(null, 204);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$req, 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => [$req, 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    /**
     * A session must fall inside the parent event's time window
     * (between starts_at and ends_at, when those are set).
     */
    private function assertWithinWindow(Event $event, array $data): void
    {
        if (empty($data['starts_at'])) {
            return;
        }

        $start = \Carbon\Carbon::parse($data['starts_at']);

        if ($event->starts_at && $start->lt($event->starts_at)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'starts_at' => ['The session cannot start before the event begins.'],
            ]);
        }

        if ($event->ends_at) {
            $end = ! empty($data['ends_at']) ? \Carbon\Carbon::parse($data['ends_at']) : $start;
            if ($end->gt($event->ends_at)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'ends_at' => ['The session cannot end after the event ends.'],
                ]);
            }
        }
    }
}

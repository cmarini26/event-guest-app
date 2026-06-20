<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Guest;
use App\Notifications\GuestInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class GuestController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $guests = $event->guests()
            ->with('plusOnes')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return response()->json($guests);
    }

    public function store(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $guestLimit = $event->effectiveGuestLimit();
        if ($guestLimit !== null && $event->guests()->count() >= $guestLimit) {
            return response()->json([
                'message' => "Guest limit of {$guestLimit} reached for this event.",
            ], 422);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $guest = $event->guests()->create($data);

        return response()->json($guest->load('plusOnes'), 201);
    }

    public function update(Request $request, Event $event, Guest $guest): JsonResponse
    {
        $this->authorize('update', $event);
        abort_unless($guest->event_id === $event->id, 404);

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $guest->update($data);

        return response()->json($guest->load('plusOnes'));
    }

    public function destroy(Request $request, Event $event, Guest $guest): JsonResponse
    {
        $this->authorize('update', $event);
        abort_unless($guest->event_id === $event->id, 404);

        $wasAttending = $guest->rsvp_status === 'attending';

        $guest->delete();

        if ($wasAttending) {
            $event->promoteFirstWaitlisted();
        }

        return response()->json(null, 204);
    }

    public function invite(Request $request, Event $event, Guest $guest): JsonResponse
    {
        $this->authorize('update', $event);
        abort_unless($guest->event_id === $event->id, 404);
        abort_unless($guest->email, 422, 'Guest has no email address.');

        $guest->notify(new GuestInvitation($event));
        $guest->update(['invited_at' => now()]);

        return response()->json(['message' => 'Invitation sent.']);
    }

    public function export(Request $request, Event $event): Response
    {
        $this->authorize('view', $event);

        $guests  = $event->guests()->with('plusOnes')->orderBy('last_name')->orderBy('first_name')->get();
        $filename = 'guests-' . str_replace(' ', '-', strtolower($event->name)) . '.csv';

        $buf = fopen('php://temp', 'r+');

        $headers = ['First Name', 'Last Name', 'Email', 'Phone', 'RSVP Status', 'Responded At'];
        if ($event->collect_dietary)       $headers[] = 'Dietary Preference';
        if ($event->collect_accessibility) $headers[] = 'Accessibility Needs';
        if ($event->collect_seating)       $headers[] = 'Seating Preference';
        $headers[] = 'Notes';
        if ($event->allow_plus_ones)       $headers[] = 'Plus-ones';

        fputcsv($buf, $headers);

        foreach ($guests as $guest) {
            $row = [
                $guest->first_name,
                $guest->last_name,
                $guest->email ?? '',
                $guest->phone ?? '',
                $guest->rsvp_status,
                $guest->responded_at?->toDateTimeString() ?? '',
            ];
            if ($event->collect_dietary)       $row[] = $guest->dietary_preference ?? '';
            if ($event->collect_accessibility) $row[] = $guest->accessibility_needs ?? '';
            if ($event->collect_seating)       $row[] = $guest->seating_preference ?? '';
            $row[] = $guest->notes ?? '';
            if ($event->allow_plus_ones)       $row[] = $guest->plusOnes->pluck('name')->implode(', ');

            fputcsv($buf, $row);
        }

        rewind($buf);
        $csv = stream_get_contents($buf);
        fclose($buf);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function bulkInvite(Request $request, Event $event): JsonResponse
    {
        $this->authorize('update', $event);

        $guests = $event->guests()
            ->whereNotNull('email')
            ->whereNull('invited_at')
            ->get();

        $now = now();
        foreach ($guests as $guest) {
            $guest->notify(new GuestInvitation($event));
        }

        $event->guests()
            ->whereIn('id', $guests->pluck('id'))
            ->update(['invited_at' => $now]);

        return response()->json(['message' => "Invitations sent to {$guests->count()} guest(s)."]);
    }
}

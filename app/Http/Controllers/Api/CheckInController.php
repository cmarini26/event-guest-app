<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function checkIn(Request $request, string $token): JsonResponse
    {
        $guest = Guest::with('event')->where('rsvp_token', $token)->firstOrFail();

        // Only the event's owner may check in guests
        abort_unless($guest->event->user_id === $request->user()->id, 403);

        abort_unless(
            $guest->rsvp_status === 'attending',
            422,
            'Only attending guests can be checked in.'
        );

        $guest->update(['checked_in_at' => now()]);

        return response()->json([
            'checked_in_at' => $guest->fresh()->checked_in_at,
            'message'       => "{$guest->first_name} {$guest->last_name} checked in.",
        ]);
    }

    public function undoCheckIn(Request $request, string $token): JsonResponse
    {
        $guest = Guest::with('event')->where('rsvp_token', $token)->firstOrFail();

        abort_unless($guest->event->user_id === $request->user()->id, 403);

        $guest->update(['checked_in_at' => null]);

        return response()->json(['checked_in_at' => null]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function stats(): JsonResponse
    {
        $eventPasses = Event::whereNotNull('event_pass_paid_at')->count();
        $failedJobs  = DB::table('failed_jobs')->count();

        return response()->json([
            'total_users'    => User::count(),
            'total_events'   => Event::count(),
            'active_events'  => Event::whereIn('status', ['draft', 'published'])->count(),
            'total_guests'   => Guest::count(),
            'event_passes'   => $eventPasses,
            'revenue_cents'  => $eventPasses * 1900,
            'failed_jobs'    => $failedJobs,
        ]);
    }

    public function users(): JsonResponse
    {
        $users = User::withCount('events')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $u) => [
                'id'             => $u->id,
                'name'           => $u->name,
                'email'          => $u->email,
                'plan'           => $u->plan,
                'is_admin'       => $u->is_admin,
                'email_verified' => $u->hasVerifiedEmail(),
                'events_count'   => $u->events_count,
                'created_at'     => $u->created_at,
            ]);

        return response()->json($users);
    }

    public function userEvents(User $user): JsonResponse
    {
        $events = $user->events()
            ->withCount([
                'guests',
                'guests as attending_count' => fn ($q) => $q->where('rsvp_status', 'attending'),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Event $e) => [
                'id'              => $e->id,
                'name'            => $e->name,
                'slug'            => $e->slug,
                'status'          => $e->status,
                'starts_at'       => $e->starts_at,
                'guests_count'    => $e->guests_count,
                'attending_count' => $e->attending_count,
                'event_pass'      => $e->event_pass_paid_at !== null,
            ]);

        return response()->json([
            'user'   => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'plan'  => $user->plan,
            ],
            'events' => $events,
        ]);
    }

    public function toggleAdmin(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot change your own admin status.');

        $user->update(['is_admin' => ! $user->is_admin]);

        return response()->json(['is_admin' => $user->is_admin]);
    }
}

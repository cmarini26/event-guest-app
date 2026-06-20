<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class AnalyticsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Per-event analytics. DB-agnostic: rows are loaded once and aggregated
     * in PHP so the same code path works on SQLite (tests) and PostgreSQL (prod).
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        $this->authorize('view', $event);

        $guests = $event->guests()->with('plusOnes')->get();

        $total = $guests->count();

        $byStatus = [
            'attending'   => 0,
            'declined'    => 0,
            'pending'     => 0,
            'waitlisted'  => 0,
        ];
        foreach ($guests as $g) {
            $status = $g->rsvp_status ?? 'pending';
            if (! array_key_exists($status, $byStatus)) {
                $byStatus[$status] = 0;
            }
            $byStatus[$status]++;
        }

        // Response rate = guests who responded (anything other than pending) / total
        $responded   = $total - $byStatus['pending'];
        $responseRate = $total > 0 ? round(($responded / $total) * 100, 1) : 0.0;

        // Acceptance rate = attending / (attending + declined) — of those who answered yes/no
        $decided        = $byStatus['attending'] + $byStatus['declined'];
        $acceptanceRate = $decided > 0 ? round(($byStatus['attending'] / $decided) * 100, 1) : 0.0;

        // Plus-ones (only count those tied to attending guests for the headcount)
        $plusOnesTotal     = $guests->sum(fn ($g) => $g->plusOnes->count());
        $attendingPlusOnes = $guests->where('rsvp_status', 'attending')->sum(fn ($g) => $g->plusOnes->count());
        $headcount         = $byStatus['attending'] + $attendingPlusOnes;

        return response()->json([
            'event' => [
                'id'     => $event->id,
                'name'   => $event->name,
                'status' => $event->status,
            ],
            'totals' => [
                'invited'          => $total,
                'responded'        => $responded,
                'response_rate'    => $responseRate,
                'acceptance_rate'  => $acceptanceRate,
                'plus_ones'        => $plusOnesTotal,
                'expected_headcount' => $headcount,
            ],
            'rsvp_breakdown'    => $byStatus,
            'dietary'           => $this->breakdown($guests, 'dietary_preference'),
            'seating'           => $this->breakdown($guests, 'seating_preference'),
            'accessibility'     => [
                'with_needs' => $guests->filter(fn ($g) => filled($g->accessibility_needs))->count(),
            ],
            'response_timeline' => $this->timeline($guests),
        ]);
    }

    /**
     * Count non-empty values of a column, returned as label => count, sorted desc.
     */
    private function breakdown($guests, string $column): array
    {
        $counts = [];
        foreach ($guests as $g) {
            $value = trim((string) ($g->{$column} ?? ''));
            if ($value === '') {
                continue;
            }
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }
        arsort($counts);

        return $counts;
    }

    /**
     * Cumulative RSVP responses per day (based on responded_at), ascending by date.
     */
    private function timeline($guests): array
    {
        $perDay = [];
        foreach ($guests as $g) {
            if (! $g->responded_at) {
                continue;
            }
            $day = $g->responded_at->format('Y-m-d');
            $perDay[$day] = ($perDay[$day] ?? 0) + 1;
        }
        ksort($perDay);

        $cumulative = 0;
        $series = [];
        foreach ($perDay as $day => $count) {
            $cumulative += $count;
            $series[] = ['date' => $day, 'responses' => $count, 'cumulative' => $cumulative];
        }

        return $series;
    }
}

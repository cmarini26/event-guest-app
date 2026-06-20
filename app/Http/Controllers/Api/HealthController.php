<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [];
        $healthy = true;

        // Database
        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (\Exception $e) {
            $checks['database'] = 'error';
            $healthy = false;
        }

        // Cache
        try {
            Cache::put('health:ping', 'pong', 5);
            $checks['cache'] = Cache::get('health:ping') === 'pong' ? 'ok' : 'error';
        } catch (\Exception) {
            $checks['cache'] = 'error';
            $healthy = false;
        }
        if ($checks['cache'] === 'error') {
            $healthy = false;
        }

        // Queue (check failed_jobs table reachable, not the queue process itself)
        try {
            DB::table('failed_jobs')->count();
            $checks['queue_table'] = 'ok';
        } catch (\Exception) {
            $checks['queue_table'] = 'error';
            $healthy = false;
        }

        return response()->json(
            array_merge(['status' => $healthy ? 'ok' : 'degraded'], $checks),
            $healthy ? 200 : 503
        );
    }
}

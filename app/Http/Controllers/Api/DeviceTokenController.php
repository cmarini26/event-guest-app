<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'    => ['required', 'string', 'max:500'],
            'platform' => ['required', 'in:ios,android,web'],
        ]);

        // Upsert so re-registration after reinstall is idempotent
        $request->user()->deviceTokens()->updateOrCreate(
            ['token' => $data['token']],
            ['platform' => $data['platform']],
        );

        return response()->json(['message' => 'Device token registered.'], 201);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $request->user()->deviceTokens()->where('token', $token)->delete();

        return response()->json(null, 204);
    }
}

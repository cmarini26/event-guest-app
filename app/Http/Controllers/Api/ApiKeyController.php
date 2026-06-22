<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->canUseApi(), 403, 'API keys require a Pro or Business plan.');

        return response()->json(
            $request->user()->apiKeys()->orderByDesc('created_at')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->canUseApi(), 403, 'API keys require a Pro or Business plan.');

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        abort_if(
            $request->user()->apiKeys()->whereNull('revoked_at')->count() >= 20,
            422,
            'Maximum of 20 active API keys reached.'
        );

        [$model, $plaintext] = ApiKey::generate(
            $request->user(),
            $data['name'],
            isset($data['expires_at']) ? new \DateTime($data['expires_at']) : null,
        );

        return response()->json([
            'key'    => $model,
            'token'  => $plaintext,  // shown once — never stored in plain text
        ], 201);
    }

    public function destroy(Request $request, ApiKey $apiKey): JsonResponse
    {
        abort_unless($apiKey->user_id === $request->user()->id, 403);

        $apiKey->revoke();

        return response()->json(null, 204);
    }
}

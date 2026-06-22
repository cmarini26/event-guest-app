<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    // Early-returns 401 with a helpful message for malformed/revoked gl… tokens
    // so callers get "Invalid or expired API key." instead of the generic "Unauthenticated."
    // Valid tokens are authenticated by the api-key guard (auth:sanctum,api-key).
    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer && str_starts_with($bearer, 'gl')) {
            if (! ApiKey::resolve($bearer)) {
                return response()->json(['message' => 'Invalid or expired API key.'], 401);
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Auth;

use App\Models\ApiKey;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class ApiKeyGuard implements Guard
{
    use GuardHelpers;

    public function __construct(protected Request $request) {}

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $bearer = $this->request->bearerToken();

        if (! $bearer || ! str_starts_with($bearer, 'gl')) {
            return null;
        }

        $apiKey = ApiKey::resolve($bearer);

        if (! $apiKey) {
            return null;
        }

        $apiKey->markUsed();

        return $this->user = $apiKey->user;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }
}

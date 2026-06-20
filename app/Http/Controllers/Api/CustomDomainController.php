<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Services\DnsVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomDomainController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($request->user()->customDomains()->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->canUseCustomDomains()) {
            return response()->json([
                'message' => 'Custom domains are available on the Pro and Business plans.',
            ], 403);
        }

        $data = $request->validate([
            'domain' => [
                'required',
                'string',
                'max:255',
                // basic hostname validation: labels separated by dots, no scheme/path
                'regex:/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))+$/',
                'unique:custom_domains,domain',
            ],
        ]);

        $domain = $request->user()->customDomains()->create([
            'domain' => strtolower($data['domain']),
        ]);

        return response()->json($domain, 201);
    }

    public function verify(Request $request, CustomDomain $customDomain, DnsVerifier $dns): JsonResponse
    {
        abort_unless($customDomain->user_id === $request->user()->id, 404);

        if ($customDomain->is_verified) {
            return response()->json($customDomain);
        }

        $host = '_guestlist-verify.'.$customDomain->domain;
        $records = $dns->txtRecords($host);

        if (! in_array($customDomain->expectedTxtValue(), $records, true)) {
            return response()->json([
                'message' => 'Verification TXT record not found yet. DNS changes can take time to propagate.',
                'expected' => $customDomain->dns_record,
            ], 422);
        }

        $customDomain->update(['verified_at' => now()]);

        return response()->json($customDomain->fresh());
    }

    public function destroy(Request $request, CustomDomain $customDomain): JsonResponse
    {
        abort_unless($customDomain->user_id === $request->user()->id, 404);

        $customDomain->delete();

        return response()->json(null, 204);
    }
}

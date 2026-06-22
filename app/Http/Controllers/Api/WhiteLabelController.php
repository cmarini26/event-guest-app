<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class WhiteLabelController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->canUseWhiteLabel(), 403, 'White-label is only available on the Business plan.');

        return response()->json(
            $request->user()->whiteLabelSetting ?? (object) []
        );
    }

    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()->canUseWhiteLabel(), 403, 'White-label is only available on the Business plan.');

        $data = $request->validate([
            'brand_name'        => ['nullable', 'string', 'max:100'],
            'primary_color'     => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color'      => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'email_sender_name' => ['nullable', 'string', 'max:100'],
            'hide_branding'     => ['nullable', 'boolean'],
        ]);

        $setting = $request->user()->whiteLabelSetting()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return response()->json($setting->fresh());
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()->canUseWhiteLabel(), 403, 'White-label is only available on the Business plan.');

        $request->validate([
            'logo' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,gif,webp,svg'],
        ]);

        $setting = $request->user()->whiteLabelSetting()->firstOrCreate(
            ['user_id' => $request->user()->id]
        );

        // Delete old logo if it exists
        if ($setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
        }

        $path = $request->file('logo')->store(
            'white-label/' . $request->user()->id,
            'public'
        );

        $setting->update(['logo_path' => $path]);

        return response()->json($setting->fresh());
    }

    public function removeLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()->canUseWhiteLabel(), 403, 'White-label is only available on the Business plan.');

        $setting = $request->user()->whiteLabelSetting;

        if ($setting && $setting->logo_path) {
            Storage::disk('public')->delete($setting->logo_path);
            $setting->update(['logo_path' => null]);
        }

        return response()->json($setting?->fresh() ?? (object) []);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WhiteLabelSetting extends Model
{
    protected $fillable = [
        'user_id', 'brand_name', 'logo_path', 'primary_color',
        'accent_color', 'email_sender_name', 'hide_branding',
    ];

    protected function casts(): array
    {
        return [
            'hide_branding' => 'boolean',
        ];
    }

    protected $appends = ['logo_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    protected static function booted(): void
    {
        static::deleting(function (WhiteLabelSetting $setting) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
        });
    }
}

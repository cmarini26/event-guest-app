<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'domain', 'verification_token', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    protected $appends = ['is_verified', 'dns_record'];

    protected static function booted(): void
    {
        static::creating(function (CustomDomain $domain) {
            $domain->verification_token ??= Str::random(40);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * The TXT record the user must add to prove ownership.
     */
    public function getDnsRecordAttribute(): array
    {
        return [
            'type' => 'TXT',
            'host' => '_guestlist-verify.'.$this->domain,
            'value' => 'guestlist-verify='.$this->verification_token,
        ];
    }

    public function expectedTxtValue(): string
    {
        return 'guestlist-verify='.$this->verification_token;
    }
}

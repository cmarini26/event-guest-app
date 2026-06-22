<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'prefix', 'key_hash', 'last_used_at', 'expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    // Never expose the hash; expose useful status flags instead.
    protected $hidden = ['key_hash'];

    protected $appends = ['is_active'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API key. Returns [ApiKey $model, string $plaintext].
     * The plaintext is shown to the user exactly once and never stored.
     */
    public static function generate(User $user, string $name, ?\DateTimeInterface $expiresAt = null): array
    {
        $prefix = 'gl'.Str::lower(Str::random(6)); // e.g. gl4f9 ... 8 chars, fits prefix(12)
        $secret = Str::random(40);
        $plaintext = "{$prefix}_{$secret}";

        $model = static::create([
            'user_id' => $user->id,
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => hash('sha256', $plaintext),
            'expires_at' => $expiresAt,
        ]);

        return [$model, $plaintext];
    }

    /**
     * Resolve and validate a plaintext key. Returns the active ApiKey or null.
     */
    public static function resolve(string $plaintext): ?self
    {
        $parts = explode('_', $plaintext, 2);
        if (count($parts) !== 2) {
            return null;
        }

        [$prefix] = $parts;

        $key = static::where('prefix', $prefix)->first();

        if (! $key || ! $key->isActive()) {
            return null;
        }

        if (! hash_equals($key->key_hash, hash('sha256', $plaintext))) {
            return null;
        }

        return $key;
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->isActive();
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
    }

    public function markUsed(): void
    {
        // Avoid a full model save on every request; touch only the column.
        $this->forceFill(['last_used_at' => now()])->saveQuietly();
    }
}

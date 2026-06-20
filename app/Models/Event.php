<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'draft',
        'timezone' => 'UTC',
        'allow_plus_ones' => true,
        'max_plus_ones_per_guest' => 1,
        'collect_dietary' => false,
        'collect_accessibility' => false,
        'collect_seating' => false,
        'require_phone' => false,
    ];

    protected $fillable = [
        'user_id', 'name', 'slug', 'description', 'starts_at', 'ends_at', 'timezone',
        'venue_name', 'venue_address', 'cover_image', 'status', 'max_guests',
        'rsvp_deadline', 'allow_plus_ones', 'max_plus_ones_per_guest',
        'collect_dietary', 'collect_accessibility', 'collect_seating', 'require_phone',
        'stripe_checkout_session_id', 'event_pass_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'rsvp_deadline' => 'datetime',
            'event_pass_paid_at' => 'datetime',
            'allow_plus_ones' => 'boolean',
            'collect_dietary' => 'boolean',
            'collect_accessibility' => 'boolean',
            'collect_seating' => 'boolean',
            'require_phone' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (empty($event->slug)) {
                $event->slug = static::uniqueSlug($event->name);
            }
        });
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function hasEventPass(): bool
    {
        return $this->event_pass_paid_at !== null;
    }

    public function effectiveGuestLimit(): ?int
    {
        $planLimit = $this->hasEventPass() ? 300 : $this->user?->guestLimit();
        $eventCap  = $this->max_guests ?: null;

        if ($planLimit === null) {
            return $eventCap;
        }
        if ($eventCap === null) {
            return $planLimit;
        }
        return min($planLimit, $eventCap);
    }

    public function attendingCount(): int
    {
        return $this->guests()->where('rsvp_status', 'attending')->count();
    }

    public function declinedCount(): int
    {
        return $this->guests()->where('rsvp_status', 'declined')->count();
    }

    public function pendingCount(): int
    {
        return $this->guests()->where('rsvp_status', 'pending')->count();
    }

    public function isAtCapacity(): bool
    {
        $limit = $this->effectiveGuestLimit();
        if ($limit === null) {
            return false;
        }
        return $this->attendingCount() >= $limit;
    }

    public function promoteFirstWaitlisted(): void
    {
        if ($this->status !== 'published') {
            return;
        }

        $guest = $this->guests()
            ->where('rsvp_status', 'waitlisted')
            ->orderBy('responded_at')
            ->first();

        if (! $guest) {
            return;
        }

        $guest->update(['rsvp_status' => 'attending']);

        if ($guest->email) {
            $guest->notify(new \App\Notifications\WaitlistPromotion($this));
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class Guest extends Model
{
    use HasFactory, Notifiable;

    protected $attributes = ['rsvp_status' => 'pending'];

    protected $fillable = [
        'event_id', 'first_name', 'last_name', 'email', 'phone',
        'rsvp_token', 'rsvp_status', 'responded_at', 'checked_in_at', 'notes',
        'dietary_preference', 'accessibility_needs', 'seating_preference', 'invited_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at'  => 'datetime',
            'invited_at'    => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Guest $guest) {
            $guest->rsvp_token ??= Str::uuid()->toString();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function plusOnes(): HasMany
    {
        return $this->hasMany(PlusOne::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'disk', 'path', 'original_name', 'mime_type', 'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected $appends = ['url'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->disk === 'public') {
            return Storage::disk('public')->url($this->path);
        }

        return null;
    }

    /**
     * Remove the underlying file when the record is deleted.
     */
    protected static function booted(): void
    {
        static::deleting(function (Attachment $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        });
    }
}

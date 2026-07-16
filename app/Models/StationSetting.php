<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StationSetting extends Model
{
    protected $fillable = [
        'name',
        'slogan',
        'logo_path',
        'is_live',
        'live_host_name',
        'live_started_at',
        'current_playlist_id',
    ];

    protected function casts(): array
    {
        return [
            'is_live' => 'boolean',
            'live_started_at' => 'datetime',
        ];
    }

    public function currentPlaylist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class, 'current_playlist_id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
            : null;
    }

    public static function current(): self
    {
        return static::first() ?? static::create([
            'name' => 'Mi Emisora Online',
            'slogan' => 'La mejor música en vivo',
        ]);
    }
}

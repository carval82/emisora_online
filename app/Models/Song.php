<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Song extends Model
{
    protected $fillable = [
        'title',
        'artist',
        'album',
        'file_path',
        'source',
        'duration',
        'file_size',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function isLocal(): bool
    {
        return $this->source === 'folder';
    }

    public function getUrlAttribute(): string
    {
        if ($this->isLocal()) {
            return route('api.local.audio', $this);
        }

        return Storage::disk('public')->url($this->file_path);
    }

    public function getAbsolutePathAttribute(): ?string
    {
        if ($this->isLocal()) {
            $path = rtrim((string) config('emisora.music_folder'), '/\\')
                . DIRECTORY_SEPARATOR
                . ltrim($this->file_path, '/\\');

            return is_file($path) ? $path : null;
        }

        return Storage::disk('public')->path($this->file_path);
    }

    public function getFormattedDurationAttribute(): string
    {
        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }
}

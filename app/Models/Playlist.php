<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_active',
        'is_default',
        'shuffle',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'shuffle' => 'boolean',
        ];
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class)
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }

    public function activeSongs(): BelongsToMany
    {
        return $this->songs()->where('songs.is_active', true);
    }

    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->where('is_active', true)->first()
            ?? static::where('is_active', true)->first();
    }
}

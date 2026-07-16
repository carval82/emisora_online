<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'sender_name',
        'content',
        'is_read',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_approved' => 'boolean',
        ];
    }
}

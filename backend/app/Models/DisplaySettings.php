<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisplaySettings extends Model
{
    protected $fillable = [
        'video_enabled',
        'video_path',
        'channel_id',
    ];

    protected $casts = [
        'video_enabled' => 'boolean',
    ];
}

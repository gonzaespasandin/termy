<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DisplaySettings extends Model
{
    protected $fillable = [
        'video_enabled',
        'video_path',
        'channel_id',
        'weather_city',
        'weather_lat',
        'weather_lon',
    ];

    protected $casts = [
        'video_enabled' => 'boolean',
        'weather_lat'   => 'float',
        'weather_lon'   => 'float',
    ];
}

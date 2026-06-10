<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Display extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'services',
        'topbar_color',
        'is_active',
    ];

    protected $casts = [
        'services'  => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function canShowService($serviceId)
    {
        return in_array($serviceId, $this->services);
    }

    public function getServicesModels()
    {
        return Service::whereIn('id', $this->services)->get();
    }
}

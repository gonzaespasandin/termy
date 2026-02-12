<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Totem extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'location',
        'services',
        'is_active',
        'ask_customer_name',
        'logo',
        'background_type',
        'background_color_1',
        'background_color_2',
    ];

    protected $casts = [
        'services' => 'array',
        'is_active' => 'boolean',
        'ask_customer_name' => 'boolean',
    ];

    // Relaciones
    public function turns()
    {
        return $this->hasMany(Turn::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Métodos auxiliares
    public function canShowService($serviceId)
    {
        return in_array($serviceId, $this->services);
    }

    public function getServicesModels()
    {
        return Service::whereIn('id', $this->services)->get();
    }
}


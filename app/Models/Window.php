<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Window extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'number',
        'services',
        'is_active',
        'current_turn_id',
    ];

    protected $casts = [
        'services' => 'array', // Convierte JSON a array automáticamente
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function currentTurn()
    {
        return $this->belongsTo(Turn::class, 'current_turn_id');
    }

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
    public function canAttendService($serviceId)
    {
        // Si no tiene servicios asignados, puede atender todos
        if (empty($this->services)) {
            return true;
        }
        
        return in_array($serviceId, $this->services);
    }

    public function isBusy()
    {
        return !is_null($this->current_turn_id);
    }
}
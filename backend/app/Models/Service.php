<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'is_active',
        'description',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Relaciones
    public function turns()
    {
        return $this->hasMany(Turn::class);
    }

    // Scopes útiles
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessor para contar turnos en espera
    public function waitingTurnsCount()
    {
        return $this->turns()->where('status', 'waiting')->count();
    }
}

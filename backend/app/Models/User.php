<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'services',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'services' => 'array',
        ];
    }

    // Relaciones
    public function attendedTurns()
    {
        return $this->hasMany(Turn::class, 'attended_by');
    }

    // Scopes
    public function scopeAdmins($query)
    {
        return $query->where('role', 'admin');
    }

    public function scopeOperators($query)
    {
        return $query->where('role', 'operator');
    }

    // Métodos auxiliares
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isOperator()
    {
        return $this->role === 'operator';
    }

    public function canAttendService($serviceId)
    {
        // Si no tiene servicios asignados, puede atender todos
        if (empty($this->services)) {
            return true;
        }
        
        return in_array($serviceId, $this->services);
    }

    public function getServiceNames()
    {
        if (empty($this->services)) {
            return [];
        }
        
        return Service::whereIn('id', $this->services)->pluck('name')->toArray();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Turn extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'ticket_number',
        'status',
        'priority',
        'customer_dni',
        'issued_at',
        'called_at',
        'attended_at',
        'completed_at',
        'window_id',
        'attended_by',
        'called_by',
        'notes',
        'totem_id',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'called_at' => 'datetime',
        'attended_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relaciones
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function window()
    {
        return $this->belongsTo(Window::class);
    }

    public function attendedByUser()
    {
        return $this->belongsTo(User::class, 'attended_by');
    }

    public function calledByUser()
    {
        return $this->belongsTo(User::class, 'called_by');
    }

    public function totem()
    {
        return $this->belongsTo(Totem::class);
    }

    // Scopes para filtrar turnos
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeCalled($query)
    {
        return $query->where('status', 'called');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('issued_at', Carbon::today());
    }

    public function scopePriority($query)
    {
        return $query->where('priority', 'priority');
    }

    // Métodos de negocio
    public function call($windowId = null, $userId = null)
    {
        $this->update([
            'status' => 'called',
            'called_at' => now(),
            'window_id' => $windowId,
            'called_by' => $userId,
        ]);
    }

    public function startAttention($userId = null)
    {
        $this->update([
            'status' => 'in_progress',
            'attended_at' => now(),
            'attended_by' => $userId,
        ]);
    }

    public function complete($notes = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function cancel($notes = null)
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
            'notes' => $notes,
        ]);
    }

    public function markAsNoShow()
    {
        $this->update([
            'status' => 'no_show',
            'completed_at' => now(),
        ]);
    }

    // Accessors para cálculos de tiempo (redondeados a enteros)
    // Usa issued_at (hora de emisión del ticket) que NO se modifica
    public function getWaitTimeAttribute()
    {
        if (!$this->issued_at) return 0;
        if (!$this->called_at) {
            return (int) $this->issued_at->diffInMinutes(now());
        }
        return (int) $this->issued_at->diffInMinutes($this->called_at);
    }

    public function getAttentionTimeAttribute()
    {
        if (!$this->attended_at || !$this->completed_at) {
            return null;
        }
        return (int) $this->attended_at->diffInMinutes($this->completed_at);
    }

    public function getTotalTimeAttribute()
    {
        if (!$this->issued_at || !$this->completed_at) {
            return null;
        }
        return (int) $this->issued_at->diffInMinutes($this->completed_at);
    }
}
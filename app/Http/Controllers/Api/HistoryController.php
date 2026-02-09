<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turn;
use App\Models\User;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    /**
     * Obtener historial de turnos con paginación y filtros
     */
    public function index(Request $request)
    {
        $query = Turn::with(['service', 'window', 'attendedByUser', 'totem']);

        // Búsqueda por número de ticket
        if ($request->has('search') && $request->search) {
            $query->where('ticket_number', 'like', '%' . $request->search . '%');
        }

        // Filtro por estado
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filtro por operador
        if ($request->has('operator_id') && $request->operator_id) {
            $query->where('attended_by', $request->operator_id);
        }

        // Filtro por servicio
        if ($request->has('service_id') && $request->service_id) {
            $query->where('service_id', $request->service_id);
        }

        // Filtro por fecha (desde)
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        // Filtro por fecha (hasta)
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Ordenar por más recientes primero
        $query->orderBy('created_at', 'desc');

        // Paginación
        $perPage = $request->get('per_page', 15);
        $turns = $query->paginate($perPage);

        // Transformar datos para incluir información calculada
        $turns->getCollection()->transform(function ($turn) {
            return [
                'id' => $turn->id,
                'ticket_number' => $turn->ticket_number,
                'customer_name' => $turn->customer_name,
                'status' => $turn->status,
                'priority' => $turn->priority,
                'notes' => $turn->notes,
                
                // Servicio
                'service' => $turn->service ? [
                    'id' => $turn->service->id,
                    'name' => $turn->service->name,
                    'prefix' => $turn->service->prefix,
                    'color' => $turn->service->color,
                ] : null,
                
                // Operador que atendió
                'operator' => $turn->attendedByUser ? [
                    'id' => $turn->attendedByUser->id,
                    'name' => $turn->attendedByUser->name,
                ] : null,
                
                // Ventanilla
                'window' => $turn->window ? [
                    'id' => $turn->window->id,
                    'name' => $turn->window->name,
                ] : null,
                
                // Totem de origen
                'totem' => $turn->totem ? [
                    'id' => $turn->totem->id,
                    'name' => $turn->totem->name,
                ] : null,
                
                // Timestamps
                'issued_at' => $turn->issued_at?->format('Y-m-d H:i:s'),
                'created_at' => $turn->created_at?->format('Y-m-d H:i:s'),
                'called_at' => $turn->called_at?->format('Y-m-d H:i:s'),
                'attended_at' => $turn->attended_at?->format('Y-m-d H:i:s'),
                'completed_at' => $turn->completed_at?->format('Y-m-d H:i:s'),
                
                // Tiempos calculados (en minutos)
                'wait_time' => $turn->wait_time,
                'attention_time' => $turn->attention_time,
                'total_time' => $turn->total_time,
                
                // Línea de tiempo para el desplegable
                'timeline' => $this->buildTimeline($turn),
            ];
        });

        return response()->json($turns);
    }

    /**
     * Obtener detalle de un turno específico
     */
    public function show($id)
    {
        $turn = Turn::with(['service', 'window', 'attendedByUser', 'totem'])->findOrFail($id);

        return response()->json([
            'id' => $turn->id,
            'ticket_number' => $turn->ticket_number,
            'customer_name' => $turn->customer_name,
            'status' => $turn->status,
            'priority' => $turn->priority,
            'notes' => $turn->notes,
            'service' => $turn->service,
            'operator' => $turn->attendedByUser,
            'window' => $turn->window,
            'totem' => $turn->totem,
            'issued_at' => $turn->issued_at?->format('Y-m-d H:i:s'),
            'created_at' => $turn->created_at?->format('Y-m-d H:i:s'),
            'called_at' => $turn->called_at?->format('Y-m-d H:i:s'),
            'attended_at' => $turn->attended_at?->format('Y-m-d H:i:s'),
            'completed_at' => $turn->completed_at?->format('Y-m-d H:i:s'),
            'wait_time' => $turn->wait_time,
            'attention_time' => $turn->attention_time,
            'total_time' => $turn->total_time,
            'timeline' => $this->buildTimeline($turn),
        ]);
    }

    /**
     * Obtener lista de operadores para el filtro
     */
    public function operators()
    {
        $operators = User::where('role', 'operator')
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($operators);
    }

    /**
     * Construir la línea de tiempo del turno
     */
    private function buildTimeline($turn)
    {
        $timeline = [];

        // 1. Creación del turno
        $timeline[] = [
            'event' => 'created',
            'label' => 'Turno creado',
            'description' => $turn->totem 
                ? "Generado desde {$turn->totem->name}" 
                : 'Generado desde totem',
            'timestamp' => $turn->issued_at?->format('Y-m-d H:i:s'),
            'time_display' => $turn->issued_at?->format('H:i'),
            'date_display' => $turn->issued_at?->format('d/m/Y'),
            'icon' => 'ticket-alt',
            'color' => 'blue',
        ];

        // 2. Llamado (si existe)
        if ($turn->called_at) {
            $waitMinutes = $turn->issued_at ? (int) $turn->issued_at->diffInMinutes($turn->called_at) : 0;
            $timeline[] = [
                'event' => 'called',
                'label' => 'Turno llamado',
                'description' => $turn->window 
                    ? "Llamado a {$turn->window->name}" 
                    : 'Llamado a ventanilla',
                'timestamp' => $turn->called_at->format('Y-m-d H:i:s'),
                'time_display' => $turn->called_at->format('H:i'),
                'date_display' => $turn->called_at->format('d/m/Y'),
                'duration' => "Esperó {$waitMinutes} min",
                'icon' => 'bullhorn',
                'color' => 'yellow',
            ];
        }

        // 3. Atención iniciada (si existe)
        if ($turn->attended_at) {
            $timeline[] = [
                'event' => 'attended',
                'label' => 'Atención iniciada',
                'description' => $turn->attendedByUser 
                    ? "Atendido por {$turn->attendedByUser->name}" 
                    : 'Atención iniciada',
                'timestamp' => $turn->attended_at->format('Y-m-d H:i:s'),
                'time_display' => $turn->attended_at->format('H:i'),
                'date_display' => $turn->attended_at->format('d/m/Y'),
                'icon' => 'user-check',
                'color' => 'green',
            ];
        }

        // 4. Finalización (si existe)
        if ($turn->completed_at) {
            $statusLabels = [
                'completed' => 'Turno completado',
                'cancelled' => 'Turno cancelado',
                'no_show' => 'Cliente no se presentó',
            ];
            
            $statusColors = [
                'completed' => 'purple',
                'cancelled' => 'red',
                'no_show' => 'gray',
            ];

            $statusIcons = [
                'completed' => 'check-circle',
                'cancelled' => 'times-circle',
                'no_show' => 'user-slash',
            ];

            $attentionMinutes = $turn->attended_at 
                ? (int) $turn->attended_at->diffInMinutes($turn->completed_at) 
                : null;

            $description = '';
            if ($turn->notes) {
                $description = $turn->notes;
            } elseif ($turn->status === 'completed') {
                $description = 'Atención finalizada correctamente';
            } elseif ($turn->status === 'cancelled') {
                $description = 'El turno fue cancelado';
            } else {
                $description = 'El cliente no se presentó';
            }

            $timeline[] = [
                'event' => $turn->status,
                'label' => $statusLabels[$turn->status] ?? 'Turno finalizado',
                'description' => $description,
                'timestamp' => $turn->completed_at->format('Y-m-d H:i:s'),
                'time_display' => $turn->completed_at->format('H:i'),
                'date_display' => $turn->completed_at->format('d/m/Y'),
                'duration' => $attentionMinutes !== null ? "Duración: {$attentionMinutes} min" : null,
                'icon' => $statusIcons[$turn->status] ?? 'flag-checkered',
                'color' => $statusColors[$turn->status] ?? 'gray',
            ];
        }

        return $timeline;
    }
}


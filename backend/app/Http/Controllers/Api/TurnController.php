<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Display;
use App\Models\Turn;
use App\Models\Service;

class TurnController extends Controller
{
    /**
     * Listar turnos con filtros opcionales
     */
    public function index(Request $request)
    {
        $query = Turn::with('service', 'window', 'attendedByUser');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('service_id')) {
            $query->where('service_id', $request->service_id);
        }
        
        if (!$request->has('all')) {
            $query->today();
        }

        $turns = $query->orderBy('created_at', 'desc')->get();

        return response()->json($turns);
    }

    /**
     * Crear un nuevo turno (Pantalla táctil)
     */
    public function store(Request $request)
    {
        $request->validate([
            'service_id' => 'required|exists:services,id',
            'customer_dni' => 'nullable|string|digits:8',
            'priority' => 'nullable|in:normal,priority',
            'totem_id' => 'nullable|exists:totems,id',
        ], [
            'service_id.required' => 'El servicio es requerido',
            'service_id.exists' => 'El servicio no existe',
            'customer_dni.string' => 'El DNI debe ser una cadena de texto',
            'customer_dni.digits' => 'El DNI debe tener exactamente 8 digitos',
            'priority.in' => 'La prioridad debe ser normal o priority',
            'totem_id.exists' => 'El totem no existe',
        ]);

        $service = Service::findOrFail($request->service_id);

        // Buscar el último turno del servicio (sin importar la fecha)
        $lastTurn = Turn::where('service_id', $request->service_id)
            ->orderBy('id', 'desc')
            ->first();

        $number = $lastTurn ?
            intval(substr($lastTurn->ticket_number, -3)) + 1 : 1;

        $ticketNumber = $service->code . '-' . str_pad($number, 3, '0', STR_PAD_LEFT);

        $turn = Turn::create([
            'service_id' => $request->service_id,
            'ticket_number' => $ticketNumber,
            'status' => 'waiting',
            'priority' => $request->priority ?? 'normal',
            'customer_dni' => $request->customer_dni ?: null, // null si está vacío
            'totem_id' => $request->totem_id,
            'issued_at' => now(), // Hora de emisión del ticket (nunca se modifica)
        ]);

        $turn->load('service');

        return response()->json($turn, 201);
    }

    /**
     * Ver un turno específico
     */
    public function show(string $id)
    {
        $turn = Turn::with('service', 'window', 'attendedByUser')->findOrFail($id);

        return response()->json($turn);
    }

    /**
     * Actualizar un turno
     */
    public function update(Request $request, string $id)
    {
        $turn = Turn::findOrFail($id);

        $request->validate([
            'status' => 'required|in:waiting,called,in_progress,completed,cancelled,no_show',
            'notes' => 'nullable|string',
        ], [
            'status.required' => 'El estado es requerido',
            'status.in' => 'El estado debe ser waiting, called, in_progress, completed, cancelled o no_show',
            'notes.string' => 'Las notas deben ser una cadena de texto',
        ]);

        $turn->update($request->only('status', 'notes'));

        return response()->json($turn);
    }

    /**
     * Eliminar un turno
     */
    public function destroy(string $id)
    {
        $turn = Turn::findOrFail($id);
        $turn->delete();

        return response()->json(['message' => 'Turno eliminado correctamente'], 200);
    }

    /**
     * Llamar a un turno
     */
    public function call(Request $request, string $id)
    {
        $user = $request->user();
        
        // Verificar que el operador no tenga un turno activo (llamado o en atención)
        $activeTurn = Turn::where(function ($query) use ($user) {
                $query->where('called_by', $user->id)
                      ->orWhere('attended_by', $user->id);
            })
            ->whereIn('status', ['called', 'in_progress'])
            ->first();
        
        if ($activeTurn) {
            return response()->json([
                'message' => 'Ya tienes un turno activo. Debes completarlo o cancelarlo antes de llamar otro.',
                'active_turn' => $activeTurn->ticket_number
            ], 422);
        }

        $turn = Turn::findOrFail($id);
        
        // Verificar que el turno esté en espera
        if ($turn->status !== 'waiting') {
            return response()->json([
                'message' => 'Este turno ya no está disponible'
            ], 422);
        }
        
        $request->validate([
            'window_id' => 'required|exists:windows,id',
        ], [
            'window_id.required' => 'La ventanilla es requerida',
            'window_id.exists' => 'La ventanilla no existe',
        ]);

        $turn->call($request->window_id, $user->id);
        
        // Refrescar para obtener datos actualizados (incluyendo created_at original)
        $turn->refresh();
        $turn->load('service');

        return response()->json($turn);
    }

    /**
     * Iniciar atención a un turno
     */
    public function attend(Request $request, string $id)
    {
        $turn = Turn::findOrFail($id);

        // Usar el ID del usuario autenticado
        $userId = $request->user() ? $request->user()->id : null;
        $turn->startAttention($userId);
        
        // Refrescar para obtener datos actualizados
        $turn->refresh();
        $turn->load('service');

        return response()->json($turn);
    }

    /**
     * Completar un turno
     */
    public function complete(Request $request, string $id)
    {
        $turn = Turn::findOrFail($id);

        $request->validate([
            'notes' => 'nullable|string',
        ], [
            'notes.string' => 'Las notas deben ser una cadena de texto',
        ]);

        $turn->complete($request->notes);
        
        // Refrescar para obtener datos actualizados
        $turn->refresh();
        $turn->load('service');

        return response()->json($turn);
    }
    
    /**
     * Cola de espera PÚBLICA (Pantalla de espera - muestra todos los turnos)
     * GET /api/turns/queue
     */
    public function queuePublic()
    {
        $waiting = Turn::with('service')
            ->waiting()
            ->today()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $inProgress = Turn::with('service', 'window')
            ->inProgress()
            ->today()
            ->get();
        
        // Últimos 10 turnos llamados o en atención (ordenados por más reciente primero)
        $recentCalled = Turn::with('service', 'window')
            ->whereIn('status', ['called', 'in_progress'])
            ->today()
            ->orderBy('called_at', 'desc')
            ->limit(10)
            ->get();
        
        return response()->json([
            'waiting' => $waiting,
            'in_progress' => $inProgress,
            'recent_called' => $recentCalled, // Nueva lista para el display
        ]);
    }

    /**
     * Cola de espera PÚBLICA filtrada por una pantalla (display)
     * GET /api/displays/{id}/queue
     */
    public function queueForDisplay($id)
    {
        $display = Display::findOrFail($id);
        $serviceIds = $display->services ?: [];

        $waiting = Turn::with('service')
            ->waiting()
            ->today()
            ->whereIn('service_id', $serviceIds)
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc')
            ->get();

        $inProgress = Turn::with('service', 'window')
            ->inProgress()
            ->today()
            ->whereIn('service_id', $serviceIds)
            ->get();

        $recentCalled = Turn::with('service', 'window')
            ->whereIn('status', ['called', 'in_progress'])
            ->today()
            ->whereIn('service_id', $serviceIds)
            ->orderBy('called_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'waiting'       => $waiting,
            'in_progress'   => $inProgress,
            'recent_called' => $recentCalled,
        ]);
    }

    /**
     * Cola de espera para OPERADOR (filtrada por sus servicios)
     * GET /api/operator/queue (requiere autenticación)
     */
    public function queue(Request $request)
    {
        $user = $request->user();

        $waitingQuery = Turn::with('service')
            ->waiting()
            ->today()
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'asc');

        // Filtrar por servicios del operador (si tiene asignados)
        if ($user->isOperator() && !empty($user->services)) {
            $waitingQuery->whereIn('service_id', $user->services);
        }

        // Turno activo de ESTE operador (llamado o en atención)
        $myActiveTurn = Turn::with('service', 'window')
            ->where(function ($query) use ($user) {
                $query->where('called_by', $user->id)
                      ->orWhere('attended_by', $user->id);
            })
            ->whereIn('status', ['called', 'in_progress'])
            ->first();

        $waiting = $waitingQuery->get();
        
        return response()->json([
            'waiting' => $waiting,
            'in_progress' => $myActiveTurn ? [$myActiveTurn] : [], // Solo el turno activo de este operador
            'my_turn' => $myActiveTurn, // El turno actual del operador (null si no tiene)
        ]);
    }

    /**
     * Marcar turno como "no se presentó"
     */
    public function noShow(Request $request, string $id)
    {
        $turn = Turn::findOrFail($id);
        
        // Solo se puede marcar como no_show si está llamado
        if ($turn->status !== 'called') {
            return response()->json(['message' => 'El turno debe estar en estado llamado'], 422);
        }

        $turn->markAsNoShow();
        
        $turn->refresh();
        $turn->load('service');

        return response()->json($turn);
    }

    /**
     * Devolver turno a la cola (cancelar llamado)
     */
    public function returnToQueue(Request $request, string $id)
    {
        $turn = Turn::findOrFail($id);
        
        // Solo se puede devolver si está llamado
        if ($turn->status !== 'called') {
            return response()->json(['message' => 'El turno debe estar en estado llamado'], 422);
        }

        $turn->update([
            'status' => 'waiting',
            'called_at' => null,
            'called_by' => null,
            'window_id' => null,
        ]);
        
        $turn->refresh();
        $turn->load('service');

        return response()->json($turn);
    }

    /**
     * Cancelar un turno en atención
     */
    public function cancel(Request $request, string $id)
    {
        $turn = Turn::findOrFail($id);
        
        $request->validate([
            'notes' => 'nullable|string',
        ]);

        $turn->cancel($request->notes);
        
        $turn->refresh();
        $turn->load('service');

        return response()->json($turn);
    }
}

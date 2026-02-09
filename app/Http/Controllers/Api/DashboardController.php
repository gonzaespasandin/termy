<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turn;
use App\Models\Service;
use App\Models\User;
use App\Models\Totem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Obtener estadísticas del dashboard
     */
    public function stats()
    {
        $today = Carbon::today();
        
        // Turnos del día (por fecha de emisión)
        $turnsToday = Turn::whereDate('issued_at', $today)->count();
        
        // Turnos en espera (waiting)
        $waiting = Turn::where('status', 'waiting')->count();
        
        // Turnos en atención (called o in_progress)
        $attending = Turn::whereIn('status', ['called', 'in_progress'])->count();
        
        // Turnos completados hoy
        $completedToday = Turn::where('status', 'completed')
            ->whereDate('updated_at', $today)
            ->count();
        
        // Turnos cancelados hoy
        $cancelledToday = Turn::whereIn('status', ['cancelled', 'no_show'])
            ->whereDate('updated_at', $today)
            ->count();
        
        // Estadísticas por servicio (del día por fecha de emisión)
        $byService = Turn::whereDate('issued_at', $today)
            ->selectRaw('service_id, status, COUNT(*) as count')
            ->groupBy('service_id', 'status')
            ->get()
            ->groupBy('service_id')
            ->map(function ($items, $serviceId) {
                $service = Service::find($serviceId);
                return [
                    'service_id' => $serviceId,
                    'service_name' => $service ? $service->name : 'Desconocido',
                    'service_color' => $service ? $service->color : '#gray',
                    'total' => $items->sum('count'),
                    'waiting' => $items->where('status', 'waiting')->sum('count'),
                    'completed' => $items->where('status', 'completed')->sum('count'),
                ];
            })
            ->values();
        
        // Últimos turnos atendidos
        $recentCompleted = Turn::with('service')
            ->where('status', 'completed')
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($turn) {
                return [
                    'ticket_number' => $turn->ticket_number,
                    'service_name' => $turn->service->name ?? 'N/A',
                    'completed_at' => $turn->updated_at->format('H:i'),
                ];
            });
        
        // Tiempo promedio de espera (minutos) - turnos completados hoy
        $avgWaitTime = Turn::where('status', 'completed')
            ->whereDate('issued_at', $today)
            ->whereNotNull('called_at')
            ->whereNotNull('issued_at')
            ->get()
            ->avg(function ($turn) {
                return Carbon::parse($turn->issued_at)->diffInMinutes(Carbon::parse($turn->called_at));
            });
        
        // Conteos generales
        $totalServices = Service::where('is_active', true)->count();
        $totalOperators = User::where('role', 'operator')->count();
        $totalProfiles = Totem::where('is_active', true)->count();
        
        return response()->json([
            'today' => [
                'total' => $turnsToday,
                'waiting' => $waiting,
                'attending' => $attending,
                'completed' => $completedToday,
                'cancelled' => $cancelledToday,
            ],
            'by_service' => $byService,
            'recent_completed' => $recentCompleted,
            'avg_wait_time' => $avgWaitTime ? round($avgWaitTime) : 0,
            'totals' => [
                'services' => $totalServices,
                'operators' => $totalOperators,
                'profiles' => $totalProfiles,
            ],
        ]);
    }
}



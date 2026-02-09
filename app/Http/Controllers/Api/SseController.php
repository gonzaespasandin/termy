<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Turn;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SseController extends Controller
{
    /**
     * Stream SSE para pantalla de display (público)
     * GET /api/sse/display
     */
    public function display()
    {
        // Desactivar límite de tiempo para SSE
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        
        return response()->stream(function () {
            $lastCheck = now();
            $maxIterations = 1800; // 1 hora (1800 * 2 segundos)
            $iteration = 0;
            
            while ($iteration < $maxIterations) {
                // Evitar timeout
                if (connection_aborted()) {
                    break;
                }

                // Obtener datos actuales
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

                $data = [
                    'waiting' => $waiting,
                    'in_progress' => $inProgress,
                    'timestamp' => now()->toISOString(),
                ];

                // Enviar evento SSE
                echo "data: " . json_encode($data) . "\n\n";
                
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                // Esperar 2 segundos antes del próximo envío
                sleep(2);
                $iteration++;
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // Nginx
        ]);
    }

    /**
     * Stream SSE para operadores (protegido)
     * GET /api/sse/operator?token=xxx
     */
    public function operator(Request $request)
    {
        // Obtener token desde query param o header
        $token = $request->query('token') ?? $request->bearerToken();
        
        if (!$token) {
            return response()->json(['error' => 'Token no proporcionado'], 401);
        }

        // Autenticar manualmente con el token
        $user = \Laravel\Sanctum\PersonalAccessToken::findToken($token)?->tokenable;
        
        if (!$user || !$user->isOperator()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Desactivar límite de tiempo para SSE
        set_time_limit(0);
        ini_set('max_execution_time', '0');

        return response()->stream(function () use ($user) {
            $maxIterations = 1800; // 1 hora
            $iteration = 0;
            
            while ($iteration < $maxIterations) {
                if (connection_aborted()) {
                    break;
                }

                $waitingQuery = Turn::with('service')
                    ->waiting()
                    ->today()
                    ->orderBy('priority', 'desc')
                    ->orderBy('created_at', 'asc');

                $inProgressQuery = Turn::with('service', 'window')
                    ->inProgress()
                    ->today();

                // Filtrar por servicios del operador
                if ($user->services) {
                    $waitingQuery->whereIn('service_id', $user->services);
                    $inProgressQuery->whereIn('service_id', $user->services);
                }

                $waiting = $waitingQuery->get();
                $inProgress = $inProgressQuery->get();

                $data = [
                    'waiting' => $waiting,
                    'in_progress' => $inProgress,
                    'timestamp' => now()->toISOString(),
                ];

                echo "data: " . json_encode($data) . "\n\n";
                
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                sleep(2);
                $iteration++;
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}


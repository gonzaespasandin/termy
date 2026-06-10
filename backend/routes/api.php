<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\TurnController;
use App\Http\Controllers\Api\WindowController;
use App\Http\Controllers\Api\TotemController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\SseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\DisplaySettingsController;
use App\Http\Controllers\Api\DisplayController;

// Rutas públicas (sin autenticación)
Route::get('/services', [ServiceController::class, 'index']);
Route::post('/turns', [TurnController::class, 'store']);
Route::get('/turns/queue', [TurnController::class, 'queuePublic']); // Pantalla de espera (todos)
Route::get('/display-settings', [DisplaySettingsController::class, 'show']);

// SSE para tiempo real (pantalla pública)
Route::get('/sse/display', [SseController::class, 'display']);

// SSE para operadores (autenticación manual por token en query)
Route::get('/sse/operator', [SseController::class, 'operator']);

// Obtener servicios de un totem por código (para identificar totem en HomeView)
Route::get('/totems/{code}/services', [TotemController::class, 'servicesByCode']);

// Pantallas (displays) — público para la TV
Route::get('/displays/{id}', [DisplayController::class, 'publicShow']);
Route::get('/displays/{id}/queue', [TurnController::class, 'queueForDisplay']);

// Autenticación
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);

    // Obtener ventanilla por IP del cliente (para operadores)
    Route::get('/windows/by-ip', [WindowController::class, 'getByIp']);

    // Cola de turnos para operador (filtrada por sus servicios)
    Route::get('/operator/queue', [TurnController::class, 'queue']);

    // Dashboard (estadísticas)
    Route::get('/admin/dashboard/stats', [DashboardController::class, 'stats']);

    // Historial de turnos
    Route::get('/admin/history', [HistoryController::class, 'index']);
    Route::get('/admin/history/operators', [HistoryController::class, 'operators']);
    Route::get('/admin/history/{id}', [HistoryController::class, 'show']);

    // Display settings
    Route::post('/admin/display-settings', [DisplaySettingsController::class, 'update']);
    Route::delete('/admin/display-settings/video', [DisplaySettingsController::class, 'deleteVideo']);

    // CRUD Admin (solo admins)
    Route::apiResource('admin/services', ServiceController::class);
    Route::apiResource('admin/users', UserController::class);
    Route::apiResource('admin/turns', TurnController::class);
    Route::apiResource('admin/totems', TotemController::class);
    Route::apiResource('admin/displays', DisplayController::class);
    Route::apiResource('admin/windows', WindowController::class);
    
    // Acciones de operadores sobre turnos
    Route::post('/turns/{id}/call', [TurnController::class, 'call']);
    Route::post('/turns/{id}/attend', [TurnController::class, 'attend']);
    Route::post('/turns/{id}/complete', [TurnController::class, 'complete']);
    Route::post('/turns/{id}/no-show', [TurnController::class, 'noShow']);
    Route::post('/turns/{id}/return-to-queue', [TurnController::class, 'returnToQueue']);
    Route::post('/turns/{id}/cancel', [TurnController::class, 'cancel']);
});
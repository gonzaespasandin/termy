<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Window;
use Illuminate\Http\Request;

class WindowController extends Controller
{
    /**
     * Listar ventanillas
     * GET /api/admin/windows
     */
    public function index(Request $request)
    {
        $query = Window::query();

        // Filtrar solo activas si se pide
        if ($request->has('active_only')) {
            $query->active();
        }

        $windows = $query->orderBy('number')->get();

        // Agregar nombres de servicios
        $windows->transform(function ($window) {
            $window->service_names = $window->services 
                ? \App\Models\Service::whereIn('id', $window->services)->pluck('name')->toArray()
                : [];
            return $window;
        });

        return response()->json($windows);
    }

    /**
     * Obtener ventanilla por IP del cliente
     * GET /api/windows/by-ip
     */
    public function getByIp(Request $request)
    {
        $clientIp = $request->ip();
        
        $window = Window::where('ip_address', $clientIp)
            ->where('is_active', true)
            ->first();

        if (!$window) {
            return response()->json([
                'error' => 'No hay ventanilla asignada a esta IP',
                'ip' => $clientIp
            ], 404);
        }

        $window->service_names = $window->services 
            ? \App\Models\Service::whereIn('id', $window->services)->pluck('name')->toArray()
            : [];

        return response()->json($window);
    }

    /**
     * Crear ventanilla
     * POST /api/admin/windows
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'number' => 'required|integer|unique:windows,number',
            'ip_address' => 'nullable|ip|unique:windows,ip_address',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
            'is_active' => 'boolean',
        ]);

        $data = $request->all();
        
        // Asegurarse de que services sea un array
        if (isset($data['services'])) {
            $data['services'] = is_array($data['services']) ? $data['services'] : [];
        }

        $window = Window::create($data);
        
        $window->service_names = $window->services 
            ? \App\Models\Service::whereIn('id', $window->services)->pluck('name')->toArray()
            : [];

        return response()->json([
            'message' => 'Ventanilla creada exitosamente',
            'window' => $window
        ], 201);
    }

    /**
     * Ver ventanilla específica
     * GET /api/admin/windows/{id}
     */
    public function show($id)
    {
        $window = Window::with('currentTurn')->findOrFail($id);
        
        $window->service_names = $window->services 
            ? \App\Models\Service::whereIn('id', $window->services)->pluck('name')->toArray()
            : [];
            
        return response()->json($window);
    }

    /**
     * Actualizar ventanilla
     * PUT /api/admin/windows/{id}
     */
    public function update(Request $request, $id)
    {
        $window = Window::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'number' => 'sometimes|integer|unique:windows,number,' . $id,
            'ip_address' => 'nullable|ip|unique:windows,ip_address,' . $id,
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
            'is_active' => 'boolean',
        ]);

        $data = $request->all();
        
        // Asegurarse de que services sea un array
        if (isset($data['services'])) {
            $data['services'] = is_array($data['services']) ? $data['services'] : [];
        }

        $window->update($data);
        
        $window->service_names = $window->services 
            ? \App\Models\Service::whereIn('id', $window->services)->pluck('name')->toArray()
            : [];

        return response()->json([
            'message' => 'Ventanilla actualizada exitosamente',
            'window' => $window
        ]);
    }

    /**
     * Eliminar ventanilla
     * DELETE /api/admin/windows/{id}
     */
    public function destroy($id)
    {
        $window = Window::findOrFail($id);
        
        // Verificar si tiene turnos asociados
        if ($window->turns()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar la ventanilla porque tiene turnos asociados'
            ], 409);
        }
        
        $window->delete();

        return response()->json(['message' => 'Ventanilla eliminada exitosamente']);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * Listar todos los servicios
     */
    public function index()
    {
        $services = Service::orderBy('name')->get();
        return response()->json($services);
    }

    /**
     * Crear un nuevo servicio
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:services,code',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service = Service::create($request->all());
        
        return response()->json([
            'message' => 'Servicio creado exitosamente',
            'service' => $service
        ], 201);
    }

    /**
     * Mostrar un servicio específico
     */
    public function show($id)
    {
        $service = Service::find($id);
        
        if (!$service) {
            return response()->json(['message' => 'Servicio no encontrado'], 404);
        }

        return response()->json($service);
    }

    /**
     * Actualizar un servicio
     */
    public function update(Request $request, $id)
    {
        $service = Service::find($id);
        
        if (!$service) {
            return response()->json(['message' => 'Servicio no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:10|unique:services,code,' . $id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $service->update($request->all());
        
        return response()->json([
            'message' => 'Servicio actualizado exitosamente',
            'service' => $service
        ]);
    }

    /**
     * Eliminar un servicio
     */
    public function destroy($id)
    {
        $service = Service::find($id);
        
        if (!$service) {
            return response()->json(['message' => 'Servicio no encontrado'], 404);
        }

        // Verificar si tiene turnos asociados
        if ($service->turns()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el servicio porque tiene turnos asociados'
            ], 409);
        }

        $service->delete();
        
        return response()->json([
            'message' => 'Servicio eliminado exitosamente'
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Totem;
use Illuminate\Http\Request;

class TotemController extends Controller
{
    /**
     * Listar totems/perfiles
     * GET /api/totems
     */
    public function index(Request $request)
    {
        $query = Totem::query();

        // Filtrar solo activos si se pide
        if ($request->has('active_only')) {
            $query->active();
        }

        $totems = $query->orderBy('name')->get();

        // Agregar nombres de servicios
        $totems->transform(function ($totem) {
            $totem->service_names = $totem->getServicesModels()->pluck('name')->toArray();
            return $totem;
        });

        return response()->json($totems);
    }

    /**
     * Obtener servicios de un totem por código
     * GET /api/totems/{code}/services
     */
    public function servicesByCode($code)
    {
        $totem = Totem::where('code', $code)->where('is_active', true)->firstOrFail();
        $services = $totem->getServicesModels();

        return response()->json([
            'totem' => $totem,
            'services' => $services,
        ]);
    }

    /**
     * Crear totem/perfil
     * POST /api/totems
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:totems,code',
            'location' => 'nullable|string|max:255',
            'services' => 'required|array|min:1',
            'services.*' => 'exists:services,id',
            'is_active' => 'sometimes|boolean',
            'ask_customer_name' => 'sometimes|boolean',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'background_type' => 'nullable|in:solid,gradient',
            'background_color_1' => 'nullable|string|max:20',
            'background_color_2' => 'nullable|string|max:20',
        ]);

        $data = $request->except(['logo']);
        
        // Convertir strings '1'/'0' a boolean (desde FormData)
        if (isset($data['is_active'])) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $data['is_active'] = true; // Por defecto activo
        }
        
        if (isset($data['ask_customer_name'])) {
            $data['ask_customer_name'] = filter_var($data['ask_customer_name'], FILTER_VALIDATE_BOOLEAN);
        } else {
            $data['ask_customer_name'] = true; // Por defecto pide nombre
        }
        
        // Asegurarse de que services sea un array
        if (isset($data['services'])) {
            $data['services'] = is_array($data['services']) ? $data['services'] : [];
        }

        // Manejar subida del logo
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('totem_logos', 'public');
            $data['logo'] = $logoPath;
        }

        $totem = Totem::create($data);
        $totem->service_names = $totem->getServicesModels()->pluck('name')->toArray();

        return response()->json([
            'message' => 'Perfil creado exitosamente',
            'totem' => $totem
        ], 201);
    }

    /**
     * Ver totem específico
     * GET /api/totems/{id}
     */
    public function show($id)
    {
        $totem = Totem::with('turns')->findOrFail($id);
        return response()->json($totem);
    }

    /**
     * Actualizar totem/perfil
     * PUT/POST /api/totems/{id}
     */
    public function update(Request $request, $id)
    {
        $totem = Totem::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:totems,code,' . $id,
            'location' => 'nullable|string|max:255',
            'services' => 'sometimes|array|min:1',
            'services.*' => 'exists:services,id',
            'is_active' => 'sometimes|boolean',
            'ask_customer_name' => 'sometimes|boolean',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'background_type' => 'nullable|in:solid,gradient',
            'background_color_1' => 'nullable|string|max:20',
            'background_color_2' => 'nullable|string|max:20',
        ]);

        $data = $request->except(['logo', '_method']);
        
        // Convertir strings '1'/'0' a boolean (desde FormData)
        if (isset($data['is_active'])) {
            $data['is_active'] = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($data['ask_customer_name'])) {
            $data['ask_customer_name'] = filter_var($data['ask_customer_name'], FILTER_VALIDATE_BOOLEAN);
        }
        
        // Asegurarse de que services sea un array
        if (isset($data['services'])) {
            $data['services'] = is_array($data['services']) ? $data['services'] : [];
        }

        // Manejar subida del logo
        if ($request->hasFile('logo')) {
            // Eliminar logo anterior si existe
            if ($totem->logo && \Storage::disk('public')->exists($totem->logo)) {
                \Storage::disk('public')->delete($totem->logo);
            }
            
            $logoPath = $request->file('logo')->store('totem_logos', 'public');
            $data['logo'] = $logoPath;
        }

        $totem->update($data);
        $totem->service_names = $totem->getServicesModels()->pluck('name')->toArray();

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'totem' => $totem
        ]);
    }

    /**
     * Eliminar totem/perfil
     * DELETE /api/totems/{id}
     */
    public function destroy($id)
    {
        $totem = Totem::findOrFail($id);
        
        // Verificar si tiene turnos asociados
        if ($totem->turns()->count() > 0) {
            return response()->json([
                'message' => 'No se puede eliminar el perfil porque tiene turnos asociados'
            ], 409);
        }
        
        // Eliminar logo si existe
        if ($totem->logo && \Storage::disk('public')->exists($totem->logo)) {
            \Storage::disk('public')->delete($totem->logo);
        }
        
        $totem->delete();

        return response()->json(['message' => 'Perfil eliminado exitosamente']);
    }
}


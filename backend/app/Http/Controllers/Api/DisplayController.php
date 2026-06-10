<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Display;
use Illuminate\Http\Request;

class DisplayController extends Controller
{
    /** GET /api/admin/displays */
    public function index(Request $request)
    {
        $query = Display::query();

        if ($request->has('active_only')) {
            $query->active();
        }

        $displays = $query->orderBy('name')->get();

        $displays->transform(function ($display) {
            $display->service_names = $display->getServicesModels()->pluck('name')->toArray();
            return $display;
        });

        return response()->json($displays);
    }

    /** GET /api/displays/{id} — público para la TV */
    public function publicShow($id)
    {
        $display = Display::where('is_active', true)->findOrFail($id);

        return response()->json([
            'id'           => $display->id,
            'name'         => $display->name,
            'topbar_color' => $display->topbar_color,
            'services'     => $display->services,
        ]);
    }

    /** POST /api/admin/displays */
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'services'     => 'required|array|min:1',
            'services.*'   => 'exists:services,id',
            'topbar_color' => 'nullable|string|max:20',
            'is_active'    => 'sometimes|boolean',
        ]);

        $data = $request->only(['name', 'services', 'topbar_color', 'is_active']);
        $data['is_active']    = $request->has('is_active') ? (bool) $request->boolean('is_active') : true;
        $data['topbar_color'] = $data['topbar_color'] ?? '#1e40af';

        $display = Display::create($data);
        $display->service_names = $display->getServicesModels()->pluck('name')->toArray();

        return response()->json([
            'message' => 'Pantalla creada exitosamente',
            'display' => $display,
        ], 201);
    }

    /** GET /api/admin/displays/{id} */
    public function show($id)
    {
        $display = Display::findOrFail($id);
        $display->service_names = $display->getServicesModels()->pluck('name')->toArray();

        return response()->json($display);
    }

    /** PUT /api/admin/displays/{id} */
    public function update(Request $request, $id)
    {
        $display = Display::findOrFail($id);

        $request->validate([
            'name'         => 'sometimes|string|max:255',
            'services'     => 'sometimes|array|min:1',
            'services.*'   => 'exists:services,id',
            'topbar_color' => 'nullable|string|max:20',
            'is_active'    => 'sometimes|boolean',
        ]);

        $data = $request->only(['name', 'services', 'topbar_color', 'is_active']);
        if ($request->has('is_active')) {
            $data['is_active'] = (bool) $request->boolean('is_active');
        }

        $display->update($data);
        $display->service_names = $display->getServicesModels()->pluck('name')->toArray();

        return response()->json([
            'message' => 'Pantalla actualizada exitosamente',
            'display' => $display,
        ]);
    }

    /** DELETE /api/admin/displays/{id} */
    public function destroy($id)
    {
        $display = Display::findOrFail($id);
        $display->delete();

        return response()->json(['message' => 'Pantalla eliminada exitosamente']);
    }
}

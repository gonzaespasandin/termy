<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisplaySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DisplaySettingsController extends Controller
{
    /**
     * Retorna la configuración actual del display.
     * GET /api/display-settings — público, sin auth.
     */
    public function show()
    {
        $settings = DisplaySettings::whereNull('channel_id')->first();

        if (!$settings) {
            return response()->json([
                'video_enabled' => true,
                'video_path'    => null,
                'video_url'     => null,
            ]);
        }

        return response()->json([
            'video_enabled' => $settings->video_enabled,
            'video_path'    => $settings->video_path,
            'video_url'     => $settings->video_path
                ? Storage::disk('public')->url($settings->video_path)
                : null,
        ]);
    }

    /**
     * Actualiza la configuración (toggle + upload de video).
     * POST /api/admin/display-settings — auth:sanctum + admin.
     */
    public function update(Request $request)
    {
        $request->validate([
            'video_enabled' => 'sometimes|boolean',
            'video'         => 'nullable|mimes:mp4,webm|max:51200',
        ]);

        $data = [];

        if ($request->has('video_enabled')) {
            $data['video_enabled'] = filter_var($request->input('video_enabled'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('video')) {
            // Eliminar video anterior si existe
            $existing = DisplaySettings::whereNull('channel_id')->first();
            if ($existing && $existing->video_path) {
                Storage::disk('public')->delete($existing->video_path);
            }

            $path = $request->file('video')->store('display_videos', 'public');
            $data['video_path'] = $path;
        }

        $settings = DisplaySettings::updateOrCreate(
            ['channel_id' => null],
            $data
        );

        return response()->json([
            'video_enabled' => $settings->video_enabled,
            'video_path'    => $settings->video_path,
            'video_url'     => $settings->video_path
                ? Storage::disk('public')->url($settings->video_path)
                : null,
        ]);
    }

    /**
     * Elimina el video subido (mantiene el toggle intacto).
     * DELETE /api/admin/display-settings/video — auth:sanctum + admin.
     */
    public function deleteVideo()
    {
        $settings = DisplaySettings::whereNull('channel_id')->first();

        if ($settings && $settings->video_path) {
            Storage::disk('public')->delete($settings->video_path);
            $settings->update(['video_path' => null]);
        }

        return response()->json(['message' => 'Video eliminado correctamente']);
    }
}

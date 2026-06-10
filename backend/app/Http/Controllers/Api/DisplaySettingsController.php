<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisplaySettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DisplaySettingsController extends Controller
{
    private function formatSettings(DisplaySettings $settings): array
    {
        return [
            'video_enabled' => $settings->video_enabled,
            'video_path'    => $settings->video_path,
            'video_url'     => $settings->video_path
                ? url('storage/' . $settings->video_path)
                : null,
            'weather_city'  => $settings->weather_city,
            'weather_lat'   => $settings->weather_lat,
            'weather_lon'   => $settings->weather_lon,
        ];
    }

    /**
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
                'weather_city'  => null,
                'weather_lat'   => null,
                'weather_lon'   => null,
            ]);
        }

        return response()->json($this->formatSettings($settings));
    }

    /**
     * POST /api/admin/display-settings — auth:sanctum + admin.
     */
    public function update(Request $request)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'video_enabled' => 'sometimes|boolean',
            'video'         => 'nullable|mimes:mp4,webm|max:51200',
            'weather_city'  => 'nullable|string|max:100',
            'weather_lat'   => 'nullable|numeric|between:-90,90',
            'weather_lon'   => 'nullable|numeric|between:-180,180',
        ]);

        $data = [];

        if ($request->has('video_enabled')) {
            $data['video_enabled'] = filter_var($request->input('video_enabled'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($request->hasFile('video')) {
            $existing = DisplaySettings::whereNull('channel_id')->first();
            if ($existing && $existing->video_path) {
                Storage::disk('public')->delete($existing->video_path);
            }
            $path = $request->file('video')->store('display_videos', 'public');
            $data['video_path'] = $path;
        }

        // Weather fields — allow explicit null to clear them
        foreach (['weather_city', 'weather_lat', 'weather_lon'] as $field) {
            if ($request->has($field)) {
                $val = $request->input($field);
                $data[$field] = ($val === '' || $val === null) ? null : $val;
            }
        }

        $settings = DisplaySettings::updateOrCreate(
            ['channel_id' => null],
            $data
        );

        return response()->json($this->formatSettings($settings));
    }

    /**
     * DELETE /api/admin/display-settings/video — auth:sanctum + admin.
     */
    public function deleteVideo()
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = DisplaySettings::whereNull('channel_id')->first();

        if ($settings && $settings->video_path) {
            Storage::disk('public')->delete($settings->video_path);
            $settings->update(['video_path' => null]);
        }

        return response()->json(['message' => 'Video eliminado correctamente']);
    }
}

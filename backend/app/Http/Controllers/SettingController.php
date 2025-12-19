<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Setting::orderBy('key')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:settings,key',
            'friendly_name' => 'nullable|string|max:255',
            'value' => 'nullable|string',
        ]);

        $setting = Setting::create($validated);

        return response()->json($setting, 201);
    }

    public function update(Request $request, Setting $setting): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:settings,key,' . $setting->id,
            'friendly_name' => 'nullable|string|max:255',
            'value' => 'nullable|string',
        ]);

        $setting->update($validated);

        return response()->json($setting);
    }

    public function destroy(Setting $setting): JsonResponse
    {
        $setting->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

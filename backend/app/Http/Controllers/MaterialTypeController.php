<?php

namespace App\Http\Controllers;

use App\Models\MaterialType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(MaterialType::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:material_types,name',
        ]);

        $type = MaterialType::create($validated);

        return response()->json($type, 201);
    }

    public function update(Request $request, MaterialType $materialType): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:material_types,name,' . $materialType->id,
        ]);

        $materialType->update($validated);

        return response()->json($materialType);
    }

    public function destroy(MaterialType $materialType): JsonResponse
    {
        $materialType->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

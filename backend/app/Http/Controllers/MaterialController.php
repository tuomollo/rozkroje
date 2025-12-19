<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(): JsonResponse
    {
        $query = Material::with('type')->orderBy('name');

        if ($search = request('search')) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $perPage = (int) request('per_page', 10);
        $perPage = max(1, min(50, $perPage));
        $materials = $query->paginate($perPage);

        return response()->json($materials);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:materials,name',
            'material_type_id' => 'required|exists:material_types,id',
            'has_grain' => 'boolean',
        ]);

        $material = Material::create($validated);

        return response()->json($material->load('type'), 201);
    }

    public function update(Request $request, Material $material): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:materials,name,' . $material->id,
            'material_type_id' => 'required|exists:material_types,id',
            'has_grain' => 'boolean',
        ]);

        $material->update($validated);

        return response()->json($material->load('type'));
    }

    public function destroy(Material $material): JsonResponse
    {
        $material->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

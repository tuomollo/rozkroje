<?php

namespace App\Http\Controllers;

use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaterialController extends Controller
{
    public function index(): JsonResponse
    {
        $materials = Material::with('type')->orderBy('name')->get();

        return response()->json($materials);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:materials,name',
            'material_type_id' => 'required|exists:material_types,id',
        ]);

        $material = Material::create($validated);

        return response()->json($material->load('type'), 201);
    }
}

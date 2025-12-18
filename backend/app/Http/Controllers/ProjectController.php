<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::orderBy('created_at', 'desc')->get();

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_name' => 'required|string|max:255',
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'client_name' => $validated['client_name'],
            'created_by' => optional($request->user())->id,
        ]);

        return response()->json($project, 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'client_name' => 'sometimes|required|string|max:255',
        ]);

        $project->update($validated);

        return response()->json($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

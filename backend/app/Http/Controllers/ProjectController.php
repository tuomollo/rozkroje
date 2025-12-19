<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = Project::with('client')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => 'required|exists:clients,id',
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'client_id' => $validated['client_id'],
            'created_by' => optional($request->user())->id,
        ]);

        return response()->json($project->load('client'), 201);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'client_id' => 'sometimes|required|exists:clients,id',
        ]);

        $project->update($validated);

        return response()->json($project->load('client'));
    }

    public function destroy(Project $project): JsonResponse
    {
        $project->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

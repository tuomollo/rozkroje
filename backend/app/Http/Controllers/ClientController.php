<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Client::orderBy('last_name')->orderBy('first_name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        $client = Client::create($validated);

        return response()->json($client, 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
        ]);

        $client->update($validated);

        return response()->json($client);
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(['message' => 'Deleted']);
    }
}

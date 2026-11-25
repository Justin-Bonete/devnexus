<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsTenantModels;
use App\Models\Client;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClientController extends Controller
{
    use FindsTenantModels;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Client::class);
        $status = $request->query('status', 'active');

        return response()->json(Client::query()->where('status', $status)->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Client::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'contact_json' => ['nullable', 'array'],
        ]);
        $client = Client::create(['name' => $data['name'], 'contact_json' => $data['contact_json'] ?? []]);
        AuditLogger::record('client.created', 'client', $client->id, ['name' => $client->name]);

        return response()->json($client, 201);
    }

    public function show(string $client): JsonResponse
    {
        $model = $this->findOrNotFound(Client::class, $client);
        Gate::authorize('view', $model);

        return response()->json($model);
    }

    public function update(Request $request, string $client): JsonResponse
    {
        $model = $this->findOrNotFound(Client::class, $client);
        Gate::authorize('update', $model);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'contact_json' => ['sometimes', 'nullable', 'array'],
        ]);
        $model->fill($data)->save();
        AuditLogger::record('client.updated', 'client', $model->id, ['fields' => array_keys($data)]);

        return response()->json($model);
    }

    public function destroy(string $client)
    {
        $model = $this->findOrNotFound(Client::class, $client);
        Gate::authorize('delete', $model);
        $model->update(['status' => 'archived']);
        AuditLogger::record('client.archived', 'client', $model->id);

        return response()->noContent();
    }
}

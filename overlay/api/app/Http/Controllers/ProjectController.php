<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\FindsTenantModels;
use App\Models\Project;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    use FindsTenantModels;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);
        $query = Project::query()->orderBy('name');
        $request->query('status') ? $query->where('status', $request->query('status')) : $query->where('status', '<>', 'archived');

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Project::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            // exists() runs inside the tenant transaction, so only this organization's clients match
            'client_id' => ['nullable', 'uuid', Rule::exists('clients', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['nullable', 'string', 'max:20'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'stack_json' => ['nullable', 'array'],
        ]);

        $slug = Str::slug($data['name']) ?: 'project';
        if (Project::query()->where('slug', $slug)->exists()) {
            $slug .= '-' . Str::lower(Str::random(5));
        }

        $project = Project::create($data + ['slug' => $slug, 'owner_user_id' => $request->user()->id]);
        AuditLogger::record('project.created', 'project', $project->id, ['name' => $project->name]);

        return response()->json($project, 201);
    }

    public function show(string $project): JsonResponse
    {
        $model = $this->findOrNotFound(Project::class, $project);
        Gate::authorize('view', $model);

        return response()->json($model);
    }

    public function update(Request $request, string $project): JsonResponse
    {
        $model = $this->findOrNotFound(Project::class, $project);
        Gate::authorize('update', $model);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'client_id' => ['sometimes', 'nullable', 'uuid', Rule::exists('clients', 'id')],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', Rule::in(Project::STATUSES)],
            'priority' => ['sometimes', 'nullable', 'string', 'max:20'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'stack_json' => ['sometimes', 'nullable', 'array'],
        ]);
        $model->fill($data)->save();
        AuditLogger::record('project.updated', 'project', $model->id, ['fields' => array_keys($data)]);

        return response()->json($model);
    }

    public function destroy(string $project)
    {
        $model = $this->findOrNotFound(Project::class, $project);
        Gate::authorize('delete', $model);
        $model->update(['status' => 'archived']);
        AuditLogger::record('project.archived', 'project', $model->id);

        return response()->noContent();
    }
}

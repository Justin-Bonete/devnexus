<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use BelongsToOrganization, HasUuids;

    public const STATUSES = ['discovery', 'requirements', 'planning', 'active', 'review', 'delivery', 'maintenance', 'archived'];

    protected $fillable = [
        'client_id', 'name', 'slug', 'description', 'status', 'health', 'priority',
        'start_date', 'due_date', 'stack_json', 'settings_json', 'owner_user_id',
    ];

    protected function casts(): array
    {
        return ['stack_json' => 'array', 'settings_json' => 'array'];
    }
}

<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Support\Tenancy\OrgRole;

class ProjectPolicy
{
    private const WRITERS = ['owner', 'admin', 'project_manager'];

    public function viewAny(User $user): bool
    {
        return OrgRole::current() !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return OrgRole::current() !== null;
    }

    public function create(User $user): bool
    {
        return OrgRole::in(self::WRITERS);
    }

    public function update(User $user, Project $project): bool
    {
        return OrgRole::in(self::WRITERS);
    }

    public function delete(User $user, Project $project): bool
    {
        return OrgRole::in(['owner', 'admin']);
    }
}

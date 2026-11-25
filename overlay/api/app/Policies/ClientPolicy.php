<?php

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Support\Tenancy\OrgRole;

class ClientPolicy
{
    private const WRITERS = ['owner', 'admin', 'project_manager'];

    public function viewAny(User $user): bool
    {
        return OrgRole::current() !== null;
    }

    public function view(User $user, Client $client): bool
    {
        return OrgRole::current() !== null;
    }

    public function create(User $user): bool
    {
        return OrgRole::in(self::WRITERS);
    }

    public function update(User $user, Client $client): bool
    {
        return OrgRole::in(self::WRITERS);
    }

    public function delete(User $user, Client $client): bool
    {
        return OrgRole::in(['owner', 'admin']);
    }
}

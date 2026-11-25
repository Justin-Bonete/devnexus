<?php

namespace App\Support\Tenancy;

/** The caller's role inside the active organization (set by SetTenantContext). */
final class OrgRole
{
    public static function current(): ?string
    {
        return request()->attributes->get('org_role');
    }

    /** @param  list<string>  $roles */
    public static function in(array $roles): bool
    {
        return in_array(self::current(), $roles, true);
    }
}

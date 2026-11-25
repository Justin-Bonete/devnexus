<?php

namespace App\Models\Concerns;

use App\Support\Tenancy\TenantContext;
use LogicException;

/** Stamps organization_id from the active tenant context on create. RLS enforces the rest. */
trait BelongsToOrganization
{
    protected static function bootBelongsToOrganization(): void
    {
        static::creating(function ($model) {
            if (empty($model->organization_id)) {
                $model->organization_id = TenantContext::current()
                    ?? throw new LogicException('Creating a tenant record outside TenantContext.');
            }
        });
    }
}

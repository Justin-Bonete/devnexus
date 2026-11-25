<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Str;

trait FindsTenantModels
{
    /**
     * Look a record up inside the active tenant context. Other tenants' rows are invisible (RLS),
     * so they 404 exactly like missing ones. Done manually because implicit route binding may run
     * before the tenant middleware.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  class-string<T>  $class
     * @return T
     */
    protected function findOrNotFound(string $class, string $id)
    {
        abort_unless(Str::isUuid($id), 404);

        return $class::query()->findOrFail($id);
    }
}

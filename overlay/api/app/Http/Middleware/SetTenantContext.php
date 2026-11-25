<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the caller's organization (X-Organization-Id header, or the only membership), checks
 * membership, then runs the rest of the request inside TenantContext (one transaction, RLS bound).
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $memberships = DB::table('organization_members')
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->pluck('role', 'organization_id');

        $requested = $request->header('X-Organization-Id');

        if ($requested === null || $requested === '') {
            if ($memberships->isEmpty()) {
                return response()->json(['message' => 'No active organization membership.'], 403);
            }
            if ($memberships->count() > 1) {
                return response()->json(['message' => 'Send the X-Organization-Id header.'], 409);
            }
            $orgId = (string) $memberships->keys()->first();
        } else {
            $orgId = Str::lower($requested);
            if (! Str::isUuid($orgId) || ! $memberships->has($orgId)) {
                return response()->json(['message' => 'Not a member of that organization.'], 403);
            }
        }

        $request->attributes->set('organization_id', $orgId);
        $request->attributes->set('org_role', $memberships[$orgId]);

        return TenantContext::run($orgId, fn () => $next($request));
    }
}

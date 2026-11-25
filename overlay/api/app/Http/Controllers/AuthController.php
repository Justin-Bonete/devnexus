<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)],
            'organization_name' => ['required', 'string', 'max:120'],
        ]);

        [$user, $org] = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $slug = (Str::slug($data['organization_name']) ?: 'org') . '-' . Str::lower(Str::random(6));
            $org = Organization::create(['name' => $data['organization_name'], 'slug' => $slug]);
            DB::table('organization_members')->insert([
                'organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'owner',
            ]);
            TenantContext::run($org->id, fn () => AuditLogger::record('auth.registered', 'user', $user->id, [], 'user', $user->id));

            return [$user, $org];
        });

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json($this->mePayload($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        $ok = Auth::attempt([
            'email' => Str::lower(trim($cred['email'])),
            'password' => $cred['password'],
            'status' => 'active',
        ]);
        if (! $ok) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }
        $request->session()->regenerate();

        return response()->json($this->mePayload($request->user()));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->mePayload($request->user()));
    }

    private function mePayload(User $user): array
    {
        $orgs = DB::table('organization_members as m')
            ->join('organizations as o', 'o.id', '=', 'm.organization_id')
            ->where('m.user_id', $user->id)->where('m.status', 'active')
            ->get(['o.id', 'o.name', 'o.slug', 'm.role']);

        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'organizations' => $orgs,
        ];
    }
}

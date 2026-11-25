<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private const PASSWORD = 'correct-horse-battery';

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function registerPayload(array $over = []): array
    {
        return $over + [
            'name' => 'Justin',
            'email' => 'Justin@Example.com',
            'password' => self::PASSWORD,
            'organization_name' => 'Acme Studio',
        ];
    }

    public function test_register_creates_user_org_owner_membership_and_audit_entry(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerPayload());

        $response->assertCreated()->assertJsonPath('organizations.0.role', 'owner');
        $this->assertAuthenticated();

        $user = User::where('email', 'justin@example.com')->firstOrFail();
        $orgId = $response->json('organizations.0.id');
        $this->assertDatabaseHas('organization_members', ['user_id' => $user->id, 'organization_id' => $orgId, 'role' => 'owner']);

        $audit = TenantContext::run($orgId, fn () => DB::table('audit_logs')->where('action', 'auth.registered')->count());
        $this->assertSame(1, $audit);
    }

    public function test_register_rejects_duplicate_email_ignoring_case_and_weak_passwords(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload())->assertCreated();

        $this->postJson('/api/auth/register', $this->registerPayload(['email' => 'JUSTIN@example.com']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->postJson('/api/auth/register', $this->registerPayload(['email' => 'other@example.com', 'password' => 'short']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_login_logout_and_me(): void
    {
        $user = User::factory()->create(['email' => 'dev@example.com', 'password' => self::PASSWORD]);
        $org = Organization::create(['name' => 'Org', 'slug' => 'org-' . uniqid()]);
        DB::table('organization_members')->insert(['organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'developer']);

        $this->postJson('/api/auth/login', ['email' => 'dev@example.com', 'password' => 'wrong-password-1'])
            ->assertUnprocessable();
        $this->assertGuest();

        $this->postJson('/api/auth/login', ['email' => 'DEV@example.com', 'password' => self::PASSWORD])
            ->assertOk()->assertJsonPath('organizations.0.role', 'developer');
        $this->assertAuthenticated();

        $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('user.email', 'dev@example.com');

        $this->actingAs($user)->postJson('/api/auth/logout')->assertNoContent();
        $this->assertGuest();
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_disabled_user_cannot_log_in(): void
    {
        User::factory()->create(['email' => 'off@example.com', 'password' => self::PASSWORD]);
        DB::table('users')->where('email', 'off@example.com')->update(['status' => 'disabled']);

        $this->postJson('/api/auth/login', ['email' => 'off@example.com', 'password' => self::PASSWORD])->assertUnprocessable();
        $this->assertGuest();
    }
}

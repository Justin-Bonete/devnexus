<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantApiTest extends TestCase
{
    private string $orgA;
    private string $orgB;
    private User $ownerA;
    private User $ownerB;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->orgA = Organization::create(['name' => 'A', 'slug' => 'a-' . uniqid()])->id;
        $this->orgB = Organization::create(['name' => 'B', 'slug' => 'b-' . uniqid()])->id;
        $this->ownerA = $this->member($this->orgA, 'owner');
        $this->ownerB = $this->member($this->orgB, 'owner');
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        parent::tearDown();
    }

    private function member(string $org, string $role): User
    {
        $user = User::factory()->create();
        DB::table('organization_members')->insert(['organization_id' => $org, 'user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/clients')->assertUnauthorized();
        $this->getJson('/api/projects')->assertUnauthorized();
    }

    public function test_tenants_cannot_see_or_touch_each_others_clients(): void
    {
        $id = $this->actingAs($this->ownerA)->postJson('/api/clients', ['name' => 'Acme'])
            ->assertCreated()->json('id');

        $this->actingAs($this->ownerA)->getJson('/api/clients')->assertOk()->assertJsonCount(1);

        $this->actingAs($this->ownerB)->getJson('/api/clients')->assertOk()->assertJsonCount(0);
        $this->actingAs($this->ownerB)->getJson("/api/clients/$id")->assertNotFound();
        $this->actingAs($this->ownerB)->patchJson("/api/clients/$id", ['name' => 'Hacked'])->assertNotFound();
        $this->actingAs($this->ownerB)->deleteJson("/api/clients/$id")->assertNotFound();

        $this->actingAs($this->ownerA)->getJson("/api/clients/$id")->assertOk()->assertJsonPath('name', 'Acme');
    }

    public function test_invalid_uuid_is_a_404_not_a_500(): void
    {
        $this->actingAs($this->ownerA)->getJson('/api/clients/not-a-uuid')->assertNotFound();
        $this->actingAs($this->ownerA)->getJson('/api/projects/not-a-uuid')->assertNotFound();
    }

    public function test_roles_are_enforced(): void
    {
        $dev = $this->member($this->orgA, 'developer');
        $pm = $this->member($this->orgA, 'project_manager');

        $this->actingAs($dev)->getJson('/api/clients')->assertOk();
        $this->actingAs($dev)->postJson('/api/clients', ['name' => 'X'])->assertForbidden();

        $id = $this->actingAs($pm)->postJson('/api/clients', ['name' => 'Y'])->assertCreated()->json('id');
        $this->actingAs($pm)->deleteJson("/api/clients/$id")->assertForbidden();
        $this->actingAs($this->ownerA)->deleteJson("/api/clients/$id")->assertNoContent();
        $this->actingAs($this->ownerA)->getJson('/api/clients')->assertJsonCount(0);
    }

    public function test_project_cannot_reference_another_tenants_client(): void
    {
        $clientB = $this->actingAs($this->ownerB)->postJson('/api/clients', ['name' => 'B-client'])->json('id');

        $this->actingAs($this->ownerA)->postJson('/api/projects', ['name' => 'P', 'client_id' => $clientB])
            ->assertUnprocessable()->assertJsonValidationErrors('client_id');
    }

    public function test_project_lifecycle_and_unique_slugs(): void
    {
        $client = $this->actingAs($this->ownerA)->postJson('/api/clients', ['name' => 'C'])->json('id');

        $first = $this->actingAs($this->ownerA)->postJson('/api/projects', ['name' => 'Portal', 'client_id' => $client])
            ->assertCreated();
        $second = $this->actingAs($this->ownerA)->postJson('/api/projects', ['name' => 'Portal'])->assertCreated();
        $this->assertNotSame($first->json('slug'), $second->json('slug'));

        $id = $first->json('id');
        $this->actingAs($this->ownerA)->patchJson("/api/projects/$id", ['status' => 'planning'])
            ->assertOk()->assertJsonPath('status', 'planning');
        $this->actingAs($this->ownerA)->patchJson("/api/projects/$id", ['status' => 'bogus'])->assertUnprocessable();
        $this->actingAs($this->ownerA)->deleteJson("/api/projects/$id")->assertNoContent();
        $this->actingAs($this->ownerA)->getJson('/api/projects')->assertJsonCount(1);
    }

    public function test_audit_entries_are_written_and_hash_chained(): void
    {
        $this->actingAs($this->ownerA)->postJson('/api/clients', ['name' => 'One'])->assertCreated();
        $this->actingAs($this->ownerA)->postJson('/api/clients', ['name' => 'Two', 'contact_json' => ['password' => 'x']])->assertCreated();
        $this->actingAs($this->ownerA)->postJson('/api/projects', ['name' => 'Three'])->assertCreated();

        $rows = TenantContext::run($this->orgA, fn () => DB::table('audit_logs')->orderBy('id')->get());
        $this->assertCount(3, $rows);
        $this->assertNull($rows[0]->prev_hash);
        $this->assertSame($rows[0]->row_hash, $rows[1]->prev_hash);
        $this->assertSame($rows[1]->row_hash, $rows[2]->prev_hash);
        $this->assertSame(['client.created', 'client.created', 'project.created'], $rows->pluck('action')->all());

        // organization B sees none of it
        $this->assertSame(0, TenantContext::run($this->orgB, fn () => DB::table('audit_logs')->count()));
    }

    public function test_multi_org_user_must_choose_an_organization(): void
    {
        $multi = $this->member($this->orgA, 'owner');
        DB::table('organization_members')->insert(['organization_id' => $this->orgB, 'user_id' => $multi->id, 'role' => 'developer']);

        $this->actingAs($multi)->getJson('/api/clients')->assertStatus(409);
        $this->actingAs($multi)->getJson('/api/clients', ['X-Organization-Id' => $this->orgA])->assertOk();
        // role differs per organization: developer in B cannot create
        $this->actingAs($multi)->postJson('/api/clients', ['name' => 'N'], ['X-Organization-Id' => $this->orgB])->assertForbidden();

        $outsider = $this->member($this->orgB, 'owner');
        $this->actingAs($outsider)->getJson('/api/clients', ['X-Organization-Id' => $this->orgA])->assertForbidden();
    }
}

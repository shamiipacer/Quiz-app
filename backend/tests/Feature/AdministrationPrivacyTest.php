<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdministrationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role): AdminUser
    {
        $this->seed();

        return AdminUser::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'TestPassword123', 'admin_role_id' => DB::table('admin_roles')->where('name', $role)->value('id')]);
    }

    private function token(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        DB::table('api_tokens')->insert(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()]);

        return $token;
    }

    public function test_analyst_cannot_manage_questions_or_view_private_data(): void
    {
        $this->actingAs($this->admin('Read-only Analyst'), 'admin');
        $this->get('/admin/reports')->assertOk();
        $this->get('/admin/privacy')->assertForbidden();
        $this->get('/admin/questions')->assertForbidden();
    }

    public function test_category_changes_are_audited_and_dependent_delete_rejected(): void
    {
        $this->actingAs($this->admin('Question Manager'), 'admin');
        $this->post('/admin/categories', ['name' => 'Economics', 'description' => 'Economics', 'icon' => 'book', 'display_order' => 20, 'active' => 1, 'daily_limit' => 2, 'default_reward' => 15])->assertRedirect();
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.saved']);
        $category = DB::table('categories')->where('name', 'English')->value('id');
        $this->delete('/admin/categories/'.$category)->assertStatus(409);
        $this->get('/admin/questions')->assertOk();
    }

    public function test_sharing_requires_consent_and_revocation_deletes_encrypted_data(): void
    {
        config(['sharing.enabled' => true, 'sharing.purpose' => 'Optional support request']);
        $u = User::factory()->create();
        $this->withToken($this->token($u));
        $payload = ['contacts' => [['name' => 'Selected friend', 'phone' => '+123456789']]];
        $this->postJson('/api/v1/contacts/share', $payload)->assertForbidden();
        $this->postJson('/api/v1/consents', ['purpose' => 'contacts', 'policy_version' => 'draft-1', 'authorized_fields' => ['name', 'phone'], 'granted' => true])->assertCreated();
        $this->postJson('/api/v1/contacts/share', $payload)->assertCreated();
        $cipher = DB::table('contact_submission_items')->value('encrypted_payload');
        $this->assertStringNotContainsString('Selected friend', $cipher);
        $this->assertSame('Selected friend', json_decode(Crypt::decryptString($cipher), true)['name']);
        $this->deleteJson('/api/v1/contacts')->assertNoContent();
        $this->assertDatabaseCount('contact_submission_items', 0);
        $this->postJson('/api/v1/contacts/share', $payload)->assertForbidden();
    }

    public function test_negative_balance_is_rejected_and_ledger_update_is_blocked(): void
    {
        $this->actingAs($this->admin('Super Admin'), 'admin');
        $u = User::factory()->create();
        $this->post('/admin/rewards/adjust', ['user_id' => $u->id, 'amount' => -10, 'reason' => 'Test deduction', 'reference' => (string) Str::uuid()])->assertStatus(422);
        DB::table('coin_transactions')->insert(['user_id' => $u->id, 'amount' => 10, 'reference' => 'test', 'type' => 'adjustment', 'reason' => 'Test award', 'created_at' => now()]);
        $this->expectException(QueryException::class);
        DB::table('coin_transactions')->update(['amount' => 999]);
    }

    public function test_processing_deletion_removes_identity_and_tokens(): void
    {
        $this->actingAs($this->admin('Privacy/Data Manager'), 'admin');
        $u = User::factory()->create();
        $this->token($u);
        $id = DB::table('deletion_requests')->insertGetId(['user_id' => $u->id, 'scope' => 'account', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/admin/deletion/'.$id)->assertRedirect();
        $this->assertDatabaseMissing('users', ['email' => $u->email]);
        $this->assertDatabaseCount('api_tokens', 0);
        $this->assertDatabaseHas('users', ['id' => $u->id, 'status' => 'deleted']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserBlockAndAuditTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    public function test_blocking_user_wipes_credentials_purges_2fa_and_terminates_sessions(): void
    {
        $role = Role::firstOrCreate(['name' => 'beat_officer'], [
            'display_name' => 'Beat Officer',
            'scope_level' => 'beat',
            'status' => 'active',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_blocked' => false,
            'is_active' => true,
            'totp_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'mfa_required' => true,
        ]);

        $oldPassword = $user->password;

        // Block the account
        $user->blockAccount('Test Emergency Block');

        $user->refresh();

        $this->assertTrue((bool) $user->is_blocked);
        $this->assertFalse((bool) $user->is_active);
        $this->assertNotEquals($oldPassword, $user->password);
        $this->assertNull($user->totp_secret);
        $this->assertNull($user->remember_token);
        $this->assertFalse((bool) $user->mfa_required);

        // Verify blocked account cannot authenticate
        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'AnyPassword123!',
        ]);

        $response->assertSessionHasErrors('username');
    }

    public function test_unblock_verification_requires_valid_token_cnic_and_email(): void
    {
        $role = Role::firstOrCreate(['name' => 'beat_officer'], [
            'display_name' => 'Beat Officer',
            'scope_level' => 'beat',
            'status' => 'active',
        ]);

        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'cnic' => '12345-1234567-1',
            'email' => 'officer@nhmp.gov.pk',
            'is_blocked' => true,
            'is_active' => false,
        ]);

        $plainToken = $user->generateUnblockToken();
        $this->assertNotEmpty($plainToken);

        // Verification fails if CNIC is wrong
        $failCnic = $this->from('/account/unblock?token=' . $plainToken)->post('/account/unblock/verify', [
            'token' => $plainToken,
            'email' => 'officer@nhmp.gov.pk',
            'cnic'  => '99999-9999999-9',
            'password' => 'StrongPass2026!#',
            'password_confirmation' => 'StrongPass2026!#',
        ]);
        $failCnic->assertSessionHasErrors('cnic');

        // Verification fails if Email is wrong
        $failEmail = $this->from('/account/unblock?token=' . $plainToken)->post('/account/unblock/verify', [
            'token' => $plainToken,
            'email' => 'wrong@nhmp.gov.pk',
            'cnic'  => '12345-1234567-1',
            'password' => 'StrongPass2026!#',
            'password_confirmation' => 'StrongPass2026!#',
        ]);
        $failEmail->assertSessionHasErrors('email');

        // Verification succeeds with correct CNIC and Email
        $success = $this->from('/account/unblock?token=' . $plainToken)->post('/account/unblock/verify', [
            'token' => $plainToken,
            'email' => 'officer@nhmp.gov.pk',
            'cnic'  => '12345-1234567-1',
            'password' => 'StrongPass2026!#',
            'password_confirmation' => 'StrongPass2026!#',
        ]);

        $success->assertRedirect('/login');
        $user->refresh();
        $this->assertFalse((bool) $user->is_blocked);
        $this->assertTrue((bool) $user->is_active);
        $this->assertNull($user->unblock_token);
        $this->assertTrue(Hash::check('StrongPass2026!#', $user->password));
    }

    public function test_user_deletion_safely_handles_foreign_key_relations_without_500(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        /** @var User $targetUser */
        $targetUser = User::factory()->create();

        // Create child login log record that caused 500 error previously
        LoginLog::create([
            'user_id' => $targetUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $response = $this->actingAs($admin)->deleteJson('/mgmt/users/' . $targetUser->uuid);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_user_audit_route_resolves_via_uuid(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        /** @var User $targetUser */
        $targetUser = User::factory()->create();

        AuditLog::create([
            'user_id' => $targetUser->id,
            'action' => 'login_success_mfa_exempt',
            'table_name' => 'users',
            'record_id' => $targetUser->id,
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($admin)->get('/mgmt/users/' . $targetUser->uuid . '/audit');

        $response->assertStatus(200);
        $response->assertSee($targetUser->username);
    }

    public function test_support_management_returns_json_for_ajax_requests(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->getJson('/mgmt/support?filter=pending');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'kpis',
            'pagination',
        ]);
    }

    public function test_user_provisioning_and_updating_with_is_blocked_handles_lifecycle_cleanly(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $designation = \App\Models\Designation::firstOrCreate(
            ['name' => 'Inspector'],
            ['bps' => 16, 'short_code' => 'INSP', 'is_active' => true]
        );

        $role = Role::firstOrCreate(['name' => 'patrol_officer'], [
            'display_name' => 'Patrol Officer',
            'scope_level' => 'beat',
            'status' => 'active',
        ]);

        // 1. Create a user with is_blocked = true
        $uniqueSuffix = Str::random(6);
        $createResponse = $this->actingAs($admin)->postJson('/mgmt/users', [
            'username'              => 'blocked_user_' . $uniqueSuffix,
            'full_name'             => 'Blocked Officer',
            'cnic'                  => '35201-' . rand(1000000, 9999999) . '-1',
            'email'                 => 'blocked_' . $uniqueSuffix . '@nhmp.gov.pk',
            'mobile_no'             => '0300' . rand(1000000, 9999999),
            'designation_id'        => $designation->id,
            'role_id'               => $role->id,
            'password'              => 'TempKey2026!#',
            'password_confirmation' => 'TempKey2026!#',
            'is_blocked'            => true,
        ]);

        $createResponse->assertStatus(200);
        $userData = $createResponse->json('user');
        $this->assertTrue($userData['is_blocked']);
        $this->assertEquals('blocked', $userData['status_state']);
        $this->assertEquals('Blocked', $userData['status_label']);

        $createdUser = User::find($userData['id']);
        $this->assertTrue((bool) $createdUser->is_blocked);
        $this->assertFalse((bool) $createdUser->is_active);

        // 2. Unblock user via update with a new password
        $unblockResponse = $this->actingAs($admin)->putJson('/mgmt/users/' . $createdUser->id, [
            'username'              => $createdUser->username,
            'full_name'             => $createdUser->full_name,
            'cnic'                  => $createdUser->cnic,
            'email'                 => $createdUser->email,
            'mobile_no'             => $createdUser->mobile_no,
            'designation_id'        => $designation->id,
            'role_id'               => $role->id,
            'password'              => 'NewKey2026!#',
            'password_confirmation' => 'NewKey2026!#',
            'is_blocked'            => false,
        ]);

        $unblockResponse->assertStatus(200);
        $createdUser->refresh();
        $this->assertFalse((bool) $createdUser->is_blocked);
        $this->assertTrue((bool) $createdUser->is_active);
        $this->assertTrue(Hash::check('NewKey2026!#', $createdUser->password));

        // 3. Admin cannot block their own account
        $selfBlockResponse = $this->actingAs($admin)->putJson('/mgmt/users/' . $admin->id, [
            'username'              => $admin->username,
            'full_name'             => $admin->full_name,
            'cnic'                  => $admin->cnic ?? '35201-1111111-1',
            'email'                 => $admin->email,
            'mobile_no'             => $admin->mobile_no ?? '03001234567',
            'designation_id'        => $designation->id,
            'role_id'               => $adminRole->id,
            'is_blocked'            => true,
        ]);

        $selfBlockResponse->assertStatus(403);
    }

    public function test_user_index_returns_is_blocked_in_json(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create(['role_id' => $adminRole->id]);

        $response = $this->actingAs($admin)->getJson('/mgmt/users');

        $response->assertStatus(200);
        $items = $response->json('items');
        $this->assertNotEmpty($items);
        $this->assertArrayHasKey('is_blocked', $items[0]);
        $this->assertArrayHasKey('status_state', $items[0]);
        $this->assertArrayHasKey('status_label', $items[0]);
    }
}

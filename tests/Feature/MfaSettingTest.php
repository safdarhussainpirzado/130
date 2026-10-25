<?php

namespace Tests\Feature;

use App\Models\MfaRolePolicy;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaSettingTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $operationAdmin;
    private User $agentUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin'], ['description' => 'Super Administrator']);
        $opAdminRole = Role::firstOrCreate(['name' => 'operation_admin'], ['description' => 'Operation Manager']);
        $agentRole = Role::firstOrCreate(['name' => 'agent'], ['description' => 'Agent']);

        /** @var User $superAdmin */
        $superAdmin = User::factory()->create(['role_id' => $superAdminRole->id]);
        $this->superAdmin = $superAdmin;

        /** @var User $operationAdmin */
        $operationAdmin = User::factory()->create(['role_id' => $opAdminRole->id]);
        $this->operationAdmin = $operationAdmin;

        /** @var User $agentUser */
        $agentUser = User::factory()->create(['role_id' => $agentRole->id]);
        $this->agentUser = $agentUser;
    }

    public function test_mfa_settings_page_accessible_by_super_admin(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('admin.mfa-settings.index'));
        $response->assertOk();
        $response->assertSee('Multi-Factor Authentication (MFA) Settings');
    }

    public function test_mfa_settings_page_accessible_by_operation_admin(): void
    {
        $response = $this->actingAs($this->operationAdmin)->get(route('admin.mfa-settings.index'));
        $response->assertOk();
    }

    public function test_mfa_settings_page_forbidden_for_regular_agents(): void
    {
        $response = $this->actingAs($this->agentUser)->get(route('admin.mfa-settings.index'));
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Unauthorized access. You have been redirected to your authorized workspace.');
    }

    public function test_toggle_role_enforcement(): void
    {
        $agentRole = Role::where('name', 'agent')->firstOrFail();

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['sensitive_reauth_at' => now()->timestamp, 'sensitive_reauth_purpose' => ''])
            ->from(route('admin.mfa-settings.index'))
            ->post(route('admin.mfa-settings.roles.toggle', $agentRole), [
                'mfa_required' => 1,
            ]);

        $response->assertRedirect(route('admin.mfa-settings.index'));
        $this->assertDatabaseHas('mfa_role_policies', [
            'role_id' => $agentRole->id,
            'mfa_required' => true,
        ]);

        // Toggle back off with required reason
        $response2 = $this->actingAs($this->superAdmin)
            ->withSession(['sensitive_reauth_at' => now()->timestamp, 'sensitive_reauth_purpose' => ''])
            ->from(route('admin.mfa-settings.index'))
            ->post(route('admin.mfa-settings.roles.toggle', $agentRole), [
                'mfa_required' => 0,
                'reason' => 'Operational exemption for dispatch testing',
            ]);

        $response2->assertRedirect(route('admin.mfa-settings.index'));
        $this->assertDatabaseHas('mfa_role_policies', [
            'role_id' => $agentRole->id,
            'mfa_required' => false,
        ]);
    }

    public function test_toggle_role_policy_json(): void
    {
        $agentRole = Role::where('name', 'agent')->firstOrFail();

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['sensitive_reauth_at' => now()->timestamp, 'sensitive_reauth_purpose' => ''])
            ->postJson(route('admin.mfa-settings.roles.toggle', $agentRole), [
                'mfa_required' => 1,
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'is_enforced' => true,
        ]);

        $this->assertDatabaseHas('mfa_role_policies', [
            'role_id' => $agentRole->id,
            'mfa_required' => true,
        ]);
    }

    public function test_toggle_role_policy_protected_role_rejected(): void
    {
        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['sensitive_reauth_at' => now()->timestamp, 'sensitive_reauth_purpose' => ''])
            ->postJson(route('admin.mfa-settings.roles.toggle', $superAdminRole), [
                'mfa_required' => 0,
                'reason' => 'Attempting exemption on protected role',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_enforce_user_mfa(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->withSession(['sensitive_reauth_at' => now()->timestamp, 'sensitive_reauth_purpose' => ''])
            ->from(route('admin.mfa-settings.index'))
            ->post(route('admin.mfa-settings.users.enforce', $this->agentUser));

        $response->assertRedirect(route('admin.mfa-settings.index'));
        $this->agentUser->refresh();
        $this->assertTrue((bool)$this->agentUser->mfa_required);
    }

    public function test_revoke_user_mfa(): void
    {
        $this->agentUser->update(['mfa_required' => true]);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['sensitive_reauth_at' => now()->timestamp, 'sensitive_reauth_purpose' => ''])
            ->from(route('admin.mfa-settings.index'))
            ->post(route('admin.mfa-settings.users.revoke', $this->agentUser));

        $response->assertRedirect(route('admin.mfa-settings.index'));
        $this->agentUser->refresh();
        $this->assertFalse((bool)$this->agentUser->mfa_required);
    }

    public function test_reset_user_mfa_credentials(): void
    {
        $this->agentUser->setEncryptedTotpSecret('JBSWY3DPEHPK3PXP');
        $this->agentUser->save();

        $this->assertNotNull($this->agentUser->totp_secret);

        $response = $this->actingAs($this->superAdmin)
            ->withSession(['sensitive_reauth_at' => now()->timestamp, 'sensitive_reauth_purpose' => ''])
            ->from(route('admin.mfa-settings.index'))
            ->post(route('admin.mfa-settings.users.reset', $this->agentUser));

        $response->assertRedirect(route('admin.mfa-settings.index'));
        $this->agentUser->refresh();
        $this->assertNull($this->agentUser->totp_secret);
    }
}

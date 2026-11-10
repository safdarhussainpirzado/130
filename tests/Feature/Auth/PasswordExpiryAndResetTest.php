<?php

namespace Tests\Feature\Auth;

use App\Models\Call;
use App\Models\PasswordHistory;
use App\Models\PasswordResetRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordExpiryAndResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed standard roles
        Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);
        Role::firstOrCreate(['name' => Role::OPERATOR], [
            'display_name' => 'Emergency Operator',
            'scope_level' => 'call_centre',
            'status' => 'active',
        ]);
        $itAdmin = Role::firstOrCreate(['name' => Role::IT_ADMIN], [
            'display_name' => 'Operation Manager',
            'scope_level' => 'national',
            'status' => 'active',
        ]);
        $perm = \App\Models\Permission::firstOrCreate(['name' => 'dashboard.view'], [
            'display_name' => 'View Dashboard',
            'module' => 'dashboard',
            'status' => 'active',
        ]);
        $itAdmin->permissions()->syncWithoutDetaching([$perm->id]);

        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    /* ------------------------------------------------------------------ */
    /*  15-Day Password Expiry Tests                                      */
    /* ------------------------------------------------------------------ */

    public function test_user_with_active_password_under_15_days_can_access_system(): void
    {
        $role = Role::where('name', Role::IT_ADMIN)->first();
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password_changed_at' => now()->subDays(5),
            'mfa_required' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_user_with_expired_password_over_15_days_is_redirected_to_password_expired(): void
    {
        $role = Role::where('name', Role::IT_ADMIN)->first();
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password_changed_at' => now()->subDays(16),
            'mfa_required' => false,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('password.expired'));
    }

    public function test_ajax_request_receives_403_when_password_is_expired(): void
    {
        $role = Role::where('name', Role::IT_ADMIN)->first();
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password_changed_at' => now()->subDays(20),
            'mfa_required' => false,
        ]);

        $response = $this->actingAs($user)->getJson(route('dashboard'));

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'PASSWORD_EXPIRED',
            ]);
    }

    public function test_user_can_view_password_expired_screen(): void
    {
        $role = Role::where('name', Role::OPERATOR)->first();
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password_changed_at' => now()->subDays(16),
            'mfa_required' => false,
        ]);

        $response = $this->actingAs($user)->get(route('password.expired'));

        $response->assertStatus(200)
            ->assertSee('Password Expired')
            ->assertSee('15-Day Password Rotation');
    }

    public function test_user_can_update_expired_password_and_restores_access(): void
    {
        $role = Role::where('name', Role::OPERATOR)->first();
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('CurrentValidP@ss123!'),
            'password_changed_at' => now()->subDays(16),
            'mfa_required' => false,
        ]);

        $response = $this->actingAs($user)->post(route('password.expired.update'), [
            'current_password' => 'CurrentValidP@ss123!',
            'password' => 'NewStr0ngP@ssword2026#',
            'password_confirmation' => 'NewStr0ngP@ssword2026#',
        ]);

        $response->assertRedirect($user->getLandingPageRoute());
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('NewStr0ngP@ssword2026#', $user->password));
        $this->assertFalse($user->isPasswordExpired());
        $this->assertEquals(now()->toDateString(), $user->password_changed_at->toDateString());
    }

    public function test_user_cannot_reuse_any_of_their_last_5_passwords(): void
    {
        $role = Role::where('name', Role::OPERATOR)->first();
        /** @var User $user */
        $user = User::factory()->create([
            'role_id' => $role->id,
            'password' => Hash::make('CurrentValidP@ss123!'),
            'password_changed_at' => now()->subDays(16),
            'mfa_required' => false,
        ]);

        // Create 2 previous password histories
        PasswordHistory::create([
            'user_id' => $user->id,
            'password' => Hash::make('AncientP@ssword123#'),
        ]);

        $response = $this->actingAs($user)->post(route('password.expired.update'), [
            'current_password' => 'CurrentValidP@ss123!',
            'password' => 'AncientP@ssword123#',
            'password_confirmation' => 'AncientP@ssword123#',
        ]);

        $response->assertSessionHasErrors('password');
    }

    /* ------------------------------------------------------------------ */
    /*  Forgot Password / Reset Request Tests                             */
    /* ------------------------------------------------------------------ */

    public function test_forgot_password_accepts_registered_email_and_creates_request(): void
    {
        $role = Role::where('name', Role::OPERATOR)->first();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'officer.test@nhmp.gov.pk',
            'username' => 'officer_test',
        ]);

        $response = $this->post(route('password.email'), [
            'email' => 'officer.test@nhmp.gov.pk',
        ]);

        $response->assertSessionHas('status_operator');

        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $user->id,
            'requester_email' => 'officer.test@nhmp.gov.pk',
            'status' => 'pending',
        ]);
    }

    public function test_forgot_password_accepts_username_via_identifier_field(): void
    {
        $role = Role::where('name', Role::OPERATOR)->first();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'duty.officer@nhmp.gov.pk',
            'username' => 'duty_officer_130',
        ]);

        $response = $this->post(route('password.email'), [
            'identifier' => 'duty_officer_130',
        ]);

        $response->assertSessionHas('status_operator');

        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $user->id,
            'requester_username' => 'duty_officer_130',
            'status' => 'pending',
        ]);
    }
}

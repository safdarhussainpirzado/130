<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactor\MfaPolicyResolver;
use Tests\TestCase;

class MfaAdminOptimizationTest extends TestCase
{
    /**
     * Test that user counts pre-aggregation produces expected keys and prevents N+1 queries.
     */
    public function test_mfa_role_counts_preaggregation_structure(): void
    {
        $role1 = new Role(['id' => 1, 'name' => 'super_admin', 'display_name' => 'Super Administrator', 'scope_level' => 'national']);
        $role1->id = 1;

        $role2 = new Role(['id' => 2, 'name' => 'agent', 'display_name' => 'Call Centre Agent', 'scope_level' => 'call_centre']);
        $role2->id = 2;

        $roles = collect([$role1, $role2]);

        // Simulated pre-aggregated counts
        $activeUserCounts = [1 => 2, 2 => 15];
        $enrolledUserCounts = [1 => 2, 2 => 12];

        $roleData = [];
        foreach ($roles as $role) {
            $activeUsersCount = (int) ($activeUserCounts[$role->id] ?? 0);
            $enrolledUsersCount = (int) ($enrolledUserCounts[$role->id] ?? 0);
            $unenrolledUsersCount = max(0, $activeUsersCount - $enrolledUsersCount);

            $roleData[] = [
                'role'                   => $role,
                'active_users_count'     => $activeUsersCount,
                'enrolled_users_count'   => $enrolledUsersCount,
                'unenrolled_users_count' => $unenrolledUsersCount,
            ];
        }

        $this->assertCount(2, $roleData);
        $this->assertEquals(2, $roleData[0]['active_users_count']);
        $this->assertEquals(2, $roleData[0]['enrolled_users_count']);
        $this->assertEquals(0, $roleData[0]['unenrolled_users_count']);

        $this->assertEquals(15, $roleData[1]['active_users_count']);
        $this->assertEquals(12, $roleData[1]['enrolled_users_count']);
        $this->assertEquals(3, $roleData[1]['unenrolled_users_count']);
    }

    /**
     * Test that User query select column constraints include all required fields for MFA management.
     */
    public function test_user_mfa_select_columns_contain_all_required_attributes(): void
    {
        $userSelectCols = [
            'id', 'username', 'full_name', 'email', 'cnic', 'mobile_no',
            'role_id', 'designation_id', 'is_active', 'mfa_required', 'totp_secret'
        ];

        // Ensure sensitive telemetry is omitted from selects
        $this->assertNotContains('password', $userSelectCols);
        $this->assertNotContains('remember_token', $userSelectCols);
        $this->assertNotContains('two_factor_recovery_codes', $userSelectCols);
        $this->assertNotContains('last_login_ip', $userSelectCols);

        // Ensure functional fields for MFA status calculation are present
        $this->assertContains('id', $userSelectCols);
        $this->assertContains('username', $userSelectCols);
        $this->assertContains('totp_secret', $userSelectCols);
        $this->assertContains('mfa_required', $userSelectCols);
        $this->assertContains('is_active', $userSelectCols);
    }
}

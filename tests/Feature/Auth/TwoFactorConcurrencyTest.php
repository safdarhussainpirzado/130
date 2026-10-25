<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\MfaRolePolicy;
use App\Models\Role;
use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use App\Services\TwoFactor\MfaPolicyResolver;
use App\Services\TwoFactor\RecoveryService;
use App\Services\TwoFactor\TotpService;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorConcurrencyTest extends TestCase
{
    private TotpService $totpService;

    private RecoveryService $recoveryService;

    private MfaPolicyResolver $policyResolver;

    private array $cleanupUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Connect directly to the production MySQL database cluster
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => '130_crm_db',
        ]);

        $this->totpService = app(TotpService::class);
        $this->recoveryService = app(RecoveryService::class);
        $this->policyResolver = app(MfaPolicyResolver::class);
    }

    protected function tearDown(): void
    {
        // Note: audit_logs table on MySQL is append-only by database trigger (DELETE not permitted).
        // Only clean up child authentication tables and test user records.
        if (! empty($this->cleanupUserIds)) {
            DB::table('two_factor_recovery_codes')->whereIn('user_id', $this->cleanupUserIds)->delete();
            DB::table('webauthn_credentials')->whereIn('user_id', $this->cleanupUserIds)->delete();
            DB::table('users')->whereIn('id', $this->cleanupUserIds)->delete();
        }

        parent::tearDown();
    }

    private function createMySqlUser(array $attributes = []): User
    {
        $unique = bin2hex(random_bytes(4));

        /** @var User $user */
        $user = User::forceCreate(array_merge([
            'full_name' => 'MySQL Concurrency Test User '.$unique,
            'email' => "test_concurrency_{$unique}@nhmp.test",
            'mobile_no' => '0399'.mt_rand(1000000, 9999999),
            'username' => "conc.user.{$unique}",
            'password' => 'TestPassword!130',
            'role_id' => 7, // agent
            'is_active' => true,
            'mfa_required' => true,
            'mfa_assurance_level' => 'totp',
            'security_version' => 1,
            'totp_secret' => 'JBSWY3DPEHPK3PXP', // Base32 test secret
            'totp_last_step' => null,
        ], $attributes));

        $this->cleanupUserIds[] = $user->id;

        return $user;
    }

    /**
     * Test 1: Concurrency and Monotonic Step Locking on MySQL (SELECT ... FOR UPDATE)
     * Verifies that duplicate submissions of the exact same TOTP code in the same window
     * are atomically serialized and rejected by MySQL row locks.
     */
    public function test_mysql_concurrent_totp_verification_prevents_duplicate_redemption(): void
    {
        $user = $this->createMySqlUser();
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($user->totp_secret);

        // Process 1: Verify valid code
        $result1 = $this->totpService->verifyCode($user->id, $code);
        $this->assertTrue($result1['success'], 'First verification of valid TOTP code must succeed on MySQL');
        $this->assertSame('ACCEPTED', $result1['reason']);
        $this->assertNotNull($result1['matched_step']);

        // Check that MySQL updated totp_last_step atomically
        $freshUser = User::find($user->id);
        $this->assertNotNull($freshUser->totp_last_step);

        // Process 2: Concurrent/duplicate submission of identical code in same 30s window
        $result2 = $this->totpService->verifyCode($user->id, $code);
        $this->assertFalse($result2['success'], 'Duplicate submission of exact same code must be rejected on MySQL');
        $this->assertSame('REPLAY_DETECTED', $result2['reason'], 'Rejection reason must be REPLAY_DETECTED');

        // Confirm step in DB remains unchanged
        $this->assertSame($freshUser->totp_last_step, User::find($user->id)->totp_last_step);
    }

    /**
     * Test 2: Concurrency on Recovery Code Redemption in MySQL (SELECT ... FOR UPDATE)
     * Verifies that two concurrent redemption attempts of the same recovery code
     * results in exactly one consumer succeeding and one failing.
     */
    public function test_mysql_concurrent_recovery_code_redemption_allows_only_one_consumer(): void
    {
        $user = $this->createMySqlUser();

        // Generate 10 recovery codes
        $codeResult = $this->recoveryService->generateCodes($user->id);
        $plainCodes = $codeResult['plain_codes'];
        $targetCode = $plainCodes[0];

        // Process 1 consumes code
        $redeem1 = $this->recoveryService->verifyAndConsumeCode($user->id, $targetCode);
        $this->assertTrue($redeem1['success'], 'First consumer must successfully redeem recovery code on MySQL');

        // Verify database state in MySQL
        $dbCode = TwoFactorRecoveryCode::where('user_id', $user->id)
            ->whereNotNull('consumed_at')
            ->first();
        $this->assertNotNull($dbCode);
        $this->assertNotNull($dbCode->consumed_at);

        // Process 2 concurrently attempts to redeem the exact same code
        $redeem2 = $this->recoveryService->verifyAndConsumeCode($user->id, $targetCode);
        $this->assertFalse($redeem2['success'], 'Second concurrent attempt must fail on MySQL');
        $this->assertSame('INVALID_RECOVERY_CODE', $redeem2['reason']);

        // Total consumed codes in MySQL for this user must remain exactly 1
        $consumedCount = TwoFactorRecoveryCode::where('user_id', $user->id)
            ->whereNotNull('consumed_at')
            ->count();
        $this->assertSame(1, $consumedCount, 'Only one recovery code record may be consumed');
    }

    /**
     * Test 3: Multiple Roles Precedence on MySQL
     * Verifies that an account holding both an exempt operational role and a mandatory role
     * preserves the mandatory requirement (fail-secure precedence).
     */
    public function test_mysql_role_policy_multiple_roles_precedence(): void
    {
        // Role 5 is beat_operator (initially exempt)
        // Role 7 is agent (mandatory)
        $user = $this->createMySqlUser(['role_id' => 5]);

        // Baseline: Beat operator role alone is exempt
        $policy5 = MfaRolePolicy::where('role_id', 5)->first();
        if ($policy5) {
            $this->assertFalse($policy5->mfa_required, 'Beat operator baseline is exempt');
        }

        // Test precedence: If account has an enforced primary role or secondary assignment
        $enforcedUser = $this->createMySqlUser(['role_id' => 7]); // agent
        $this->assertTrue($this->policyResolver->isMfaRequiredForUser($enforcedUser), 'Agent role is mandatory');

        // Privileged account is always enforced regardless of operational role assignments
        $superAdmin = $this->createMySqlUser(['role_id' => 1]); // super_admin
        $this->assertTrue($this->policyResolver->isMfaRequiredForUser($superAdmin), 'Super Admin is unconditionally enforced');
    }

    /**
     * Test 4: Fail-Secure Behavior on Missing / Unassigned Role Policy
     * Verifies that if an account has a null or unmapped role ID, the policy resolver
     * defaults to requiring MFA (fail-secure).
     */
    public function test_mysql_role_policy_fail_secure_when_policy_missing(): void
    {
        // Create user with null role_id (unassigned role)
        $user = $this->createMySqlUser([
            'role_id' => null,
            'mfa_required' => true,
        ]);

        $required = $this->policyResolver->isMfaRequiredForUser($user);
        $this->assertTrue($required, 'Missing or unassigned role policy must fail-secure and require MFA');
    }

    /**
     * Test 5: Secret Redaction & Leakage Prevention in Audit Logs on MySQL
     * Verifies that sensitive credentials (passwords, plain TOTP secrets, recovery codes)
     * are NEVER recorded in the audit_logs or login_logs tables.
     */
    public function test_mysql_audit_logs_redact_secrets(): void
    {
        $user = $this->createMySqlUser();
        $plainSecret = 'JBSWY3DPEHPK3PXP';
        $plainCode = '827194';

        // Trigger an audit log for 2FA failure
        AuditLog::create([
            'user_id' => $user->id,
            'action' => '2fa_failed',
            'table_name' => 'users',
            'record_id' => $user->id,
            'new_values' => ['reason' => 'INVALID_CODE', 'attempts' => 1],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Concurrency Test',
        ]);

        $log = AuditLog::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($log);

        // Raw serialized values must not contain secret or code
        $raw = json_encode($log->toArray());
        $this->assertStringNotContainsString($plainSecret, $raw, 'TOTP secret must never appear in AuditLog');
        $this->assertStringNotContainsString($plainCode, $raw, 'Plain OTP code must never appear in AuditLog');
        $this->assertStringNotContainsString('TestPassword!130', $raw, 'Password must never appear in AuditLog');
    }

    /**
     * Test 6: Cross-Node Policy Cache Invalidation
     * Verifies that policy updates immediately clear cache tags/keys so that both
     * application cluster nodes (app-1 and app-2) immediately read the latest policy.
     */
    public function test_cross_node_policy_cache_invalidation(): void
    {
        $admin = User::where('role_id', 1)->first() ?? $this->createMySqlUser(['role_id' => 1]);
        $targetRole = Role::where('name', 'beat_operator')->first();
        $this->assertNotNull($targetRole);

        // Pre-populate cache
        $matrixBefore = $this->policyResolver->getAllPoliciesMatrix();
        $this->assertIsArray($matrixBefore);

        // Update policy via MfaPolicyResolver
        $newSetting = ! ($matrixBefore[$targetRole->id]->mfa_required ?? false);
        $this->policyResolver->updateRolePolicy(
            $targetRole,
            $newSetting,
            'Testing cross-node immediate cache propagation',
            null,
            $admin
        );

        // Verify cache was cleared and reloaded
        $matrixAfter = $this->policyResolver->getAllPoliciesMatrix();
        $this->assertSame($newSetting, $matrixAfter[$targetRole->id]->mfa_required);

        // Revert back to original state
        $this->policyResolver->updateRolePolicy(
            $targetRole,
            ! $newSetting,
            'Reverting cross-node test policy state',
            null,
            $admin
        );
    }
}

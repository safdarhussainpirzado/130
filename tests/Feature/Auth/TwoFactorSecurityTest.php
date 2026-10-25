<?php

namespace Tests\Feature\Auth;

use App\Models\MfaRolePolicy;
use App\Models\Role;
use App\Models\TwoFactorRecoveryApproval;
use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Services\TwoFactor\EnrollmentAuthorizationService;
use App\Services\TwoFactor\MfaPolicyResolver;
use App\Services\TwoFactor\RecoveryService;
use App\Services\TwoFactor\TotpService;
use App\Services\TwoFactor\WebAuthnService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorSecurityTest extends TestCase
{
    private static int $nextUserId = 100;

    private TotpService $totpService;

    private WebAuthnService $webAuthnService;

    private RecoveryService $recoveryService;

    private EnrollmentAuthorizationService $enrollmentAuthService;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'auth_testing',
            'database.connections.auth_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'session.driver' => 'array',
            'app.url' => 'https://130.nhmp.gov.pk',
        ]);

        $this->createSecurityTestSchema();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->totpService = app(TotpService::class);
        $this->webAuthnService = app(WebAuthnService::class);
        $this->recoveryService = app(RecoveryService::class);
        $this->enrollmentAuthService = app(EnrollmentAuthorizationService::class);
    }

    /* ---------------------------------------------------------------------- */
    /*  1. Password Alone Cannot Access Protected Surfaces */
    /* ---------------------------------------------------------------------- */

    public function test_password_alone_cannot_access_protected_surfaces(): void
    {
        $user = $this->createStaffUser(['totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP')]);

        // Submit correct password
        $response = $this->post('/login', [
            'username' => $user->username,
            'password' => 'TestPassword!130',
        ]);

        // Must redirect to MFA challenge, NOT dashboard or landing page
        $response->assertRedirect(route('2fa.challenge'));

        // Guard must be unauthenticated (guest)
        $this->assertGuest('web');

        // Password verified user with MFA pending attempting to access CRM dashboard is redirected to MFA challenge
        $this->get('/dashboard')->assertRedirect(route('2fa.challenge'));

        // Pre-auth session attempting AJAX access receives 403 Forbidden
        $this->getJson('/calls/pending')->assertStatus(403);

        // Completely unauthenticated guest without any session attempting access is redirected to login
        $this->flushSession()->get('/dashboard')->assertRedirect(route('login'));
    }

    /* ---------------------------------------------------------------------- */
    /*  2. TOTP Replay Rejection & Monotonic Step Enforcement */
    /* ---------------------------------------------------------------------- */

    public function test_totp_monotonic_step_tracking_rejects_replay(): void
    {
        $rawSecret = 'JBSWY3DPEHPK3PXP';
        $user = $this->createStaffUser(['totp_secret' => Crypt::encryptString($rawSecret)]);

        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($rawSecret);

        // First verification: should succeed
        $result1 = $this->totpService->verifyCode($user->id, $code);
        $this->assertTrue($result1['success'], 'First TOTP submission must succeed');
        $this->assertNotNull($result1['matched_step']);

        // Check that totp_last_step was recorded in DB
        $user->refresh();
        $this->assertSame($result1['matched_step'], (int) $user->totp_last_step);

        // Immediate replay of the SAME code within the same time window
        $result2 = $this->totpService->verifyCode($user->id, $code);
        $this->assertFalse($result2['success'], 'Replaying the same code must fail');
        $this->assertSame('REPLAY_DETECTED', $result2['reason']);

        // Attacker attempting an older time-step code
        $olderTimestamp = time() - 60;
        $olderStep = (int) floor($olderTimestamp / 30);
        $olderCode = str_pad((string) $google2fa->oathTotp($rawSecret, $olderStep), 6, '0', STR_PAD_LEFT);

        $result3 = $this->totpService->verifyCode($user->id, $olderCode);
        $this->assertFalse($result3['success'], 'Stale/older time-step code must fail');
    }

    /* ---------------------------------------------------------------------- */
    /*  3. Clock Drift Verification & Controlled Failure */
    /* ---------------------------------------------------------------------- */

    public function test_clock_failures_produce_controlled_failure_and_alert(): void
    {
        $rawSecret = 'JBSWY3DPEHPK3PXP';
        $user = $this->createStaffUser(['totp_secret' => Crypt::encryptString($rawSecret)]);

        // Mock TotpService to simulate severe clock drift (> 5 seconds)
        $mockTotp = $this->getMockBuilder(TotpService::class)
            ->onlyMethods(['checkClockSync'])
            ->getMock();

        $mockTotp->method('checkClockSync')->willReturn([
            'synchronized' => false,
            'drift_seconds' => 12,
            'app_time' => time(),
            'db_time' => time() - 12,
        ]);

        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($rawSecret);

        $result = $mockTotp->verifyCode($user->id, $code);

        // Must produce controlled authentication failure, NEVER a bypass or wider window
        $this->assertFalse($result['success']);
        $this->assertSame('CLOCK_DESYNCHRONIZATION_ERROR', $result['reason']);
    }

    /* ---------------------------------------------------------------------- */
    /*  4. WebAuthn: Incorrect Origins and RP IDs Fail Verification */
    /* ---------------------------------------------------------------------- */

    public function test_webauthn_rejects_untrusted_origins_and_rp_ids(): void
    {
        $user = $this->createPrivilegedUser();

        // 1. Check registration with phishing proxy origin
        $fakeClientData = json_encode([
            'type' => 'webauthn.create',
            'challenge' => 'dGVzdC1jaGFsbGVuZ2U',
            'origin' => 'https://evil-phishing-proxy.attacker.com',
        ]);

        $regResult = $this->webAuthnService->processRegistration(
            $user,
            $fakeClientData,
            'dummyAttestation',
            'dGVzdC1jaGFsbGVuZ2U'
        );

        $this->assertFalse($regResult['success']);
        $this->assertSame('UNTRUSTED_ORIGIN', $regResult['error']);

        // 2. Check authentication assertion with spoofed origin
        $fakeAuthClientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => 'dGVzdC1jaGFsbGVuZ2U',
            'origin' => 'https://unauthorized-domain.com',
        ]);

        $authResult = $this->webAuthnService->processAuthentication(
            $user,
            'some-cred-id',
            $fakeAuthClientData,
            'dummyAuthData',
            'dummySig',
            'dGVzdC1jaGFsbGVuZ2U'
        );

        $this->assertFalse($authResult['success']);
        $this->assertSame('UNTRUSTED_ORIGIN', $authResult['error']);
    }

    /* ---------------------------------------------------------------------- */
    /*  5. WebAuthn: Challenges Cannot Be Replayed */
    /* ---------------------------------------------------------------------- */

    public function test_webauthn_challenges_cannot_be_replayed(): void
    {
        $user = $this->createPrivilegedUser();

        // Seed a valid WebAuthn credential
        $cred = WebAuthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => 'test-credential-id-123',
            'public_key' => 'dummy-public-key',
            'attestation_type' => 'none',
            'sign_count' => 0,
            'user_verification' => true,
            'device_name' => 'YubiKey 5C',
            'is_hardware_key' => true,
        ]);

        // Place challenge in session
        $this->withSession([
            'mfa_pending_user_id' => $user->id,
            'webauthn_auth_challenge' => 'valid-one-time-challenge',
            'mfa_challenge_expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        // Verify request consumes the challenge from session
        $response = $this->postJson(route('2fa.challenge.webauthn.verify'), [
            'id' => 'test-credential-id-123',
            'clientDataJSON' => base64_encode(json_encode(['origin' => 'https://130.nhmp.gov.pk'])),
            'authenticatorData' => base64_encode('dummy'),
            'signature' => base64_encode('dummy'),
        ]);

        // Second request with same session now has NO challenge
        $response2 = $this->postJson(route('2fa.challenge.webauthn.verify'), [
            'id' => 'test-credential-id-123',
            'clientDataJSON' => base64_encode(json_encode(['origin' => 'https://130.nhmp.gov.pk'])),
            'authenticatorData' => base64_encode('dummy'),
            'signature' => base64_encode('dummy'),
        ]);

        $response2->assertStatus(400);
        $response2->assertJson(['error' => 'MISSING_CHALLENGE']);
    }

    /* ---------------------------------------------------------------------- */
    /*  6. WebAuthn: One User Cannot Use Another User's Credential */
    /* ---------------------------------------------------------------------- */

    public function test_one_user_cannot_register_or_use_another_users_credential(): void
    {
        $userA = $this->createStaffUser();
        $userB = $this->createStaffUser();

        // Credential belongs exclusively to User A
        $credA = WebAuthnCredential::create([
            'user_id' => $userA->id,
            'credential_id' => 'cred-owned-by-user-a',
            'public_key' => 'dummy-key-a',
            'attestation_type' => 'none',
            'sign_count' => 0,
            'user_verification' => true,
            'device_name' => 'User A Key',
            'is_hardware_key' => true,
        ]);

        // User B attempts to assert using User A's credential
        $clientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => 'some-challenge',
            'origin' => 'https://130.nhmp.gov.pk',
        ]);

        $result = $this->webAuthnService->processAuthentication(
            $userB,
            'cred-owned-by-user-a',
            $clientData,
            'authData',
            'sig',
            'some-challenge'
        );

        $this->assertFalse($result['success']);
        $this->assertSame('CREDENTIAL_NOT_FOUND_OR_REVOKED', $result['error']);
    }

    /* ---------------------------------------------------------------------- */
    /*  7. WebAuthn: Revoked Credentials Fail */
    /* ---------------------------------------------------------------------- */

    public function test_revoked_credentials_fail(): void
    {
        $user = $this->createPrivilegedUser();

        $revokedCred = WebAuthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => 'compromised-or-revoked-key',
            'public_key' => 'dummy-key',
            'attestation_type' => 'none',
            'sign_count' => 5,
            'user_verification' => true,
            'device_name' => 'Lost Hardware Key',
            'is_hardware_key' => true,
            'revoked_at' => now()->subDay(),
        ]);

        $clientData = json_encode([
            'type' => 'webauthn.get',
            'challenge' => 'challenge-123',
            'origin' => 'https://130.nhmp.gov.pk',
        ]);

        $result = $this->webAuthnService->processAuthentication(
            $user,
            'compromised-or-revoked-key',
            $clientData,
            'authData',
            'sig',
            'challenge-123'
        );

        $this->assertFalse($result['success']);
        $this->assertSame('CREDENTIAL_NOT_FOUND_OR_REVOKED', $result['error']);
    }

    /* ---------------------------------------------------------------------- */
    /*  8. Privileged Accounts: Downgrade to TOTP Is Strictly Blocked */
    /* ---------------------------------------------------------------------- */

    public function test_privileged_account_cannot_downgrade_to_totp(): void
    {
        $privilegedUser = $this->createPrivilegedUser([
            'mfa_assurance_level' => 'webauthn',
            'totp_secret' => Crypt::encryptString('JBSWY3DPEHPK3PXP'),
        ]);

        $this->assertTrue($privilegedUser->requiresWebAuthn());

        $this->withSession([
            'mfa_pending_user_id' => $privilegedUser->id,
            'mfa_challenge_id' => 'test-challenge',
            'mfa_challenge_expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        // Attempt TOTP verification for privileged user requiring WebAuthn
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp('JBSWY3DPEHPK3PXP');

        $response = $this->post(route('2fa.challenge.totp'), [
            'code' => $code,
        ]);

        $response->assertSessionHasErrors(['code']);
        $this->assertGuest('web');

        // Verify audit log captured downgrade block event
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $privilegedUser->id,
            'action' => '2fa_downgrade_blocked',
        ]);
    }

    /* ---------------------------------------------------------------------- */
    /*  9. TOTP-Only Session Cannot Invoke WebAuthn-Required Operations */
    /* ---------------------------------------------------------------------- */

    public function test_totp_only_session_cannot_invoke_operations_requiring_webauthn(): void
    {
        $privilegedUser = $this->createPrivilegedUser([
            'mfa_assurance_level' => 'webauthn',
            'security_version' => 1,
        ]);

        // Simulate an improperly established session with auth_method = 'totp'
        $this->actingAs($privilegedUser)
            ->withSession([
                'auth_method' => 'totp', // Disallowed for privileged user
                'security_version' => 1,
                'last_activity_at' => now()->timestamp,
                'session_created_at' => now()->timestamp,
            ]);

        // TwoFactorEnforcement middleware must immediately reject and terminate session
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest('web');
    }

    /* ---------------------------------------------------------------------- */
    /*  10. Recovery Code Grants Restricted Mode & Consumes Code */
    /* ---------------------------------------------------------------------- */

    public function test_recovery_code_grants_restricted_mode_and_consumes_code(): void
    {
        $user = $this->createStaffUser();
        $codeResult = $this->recoveryService->generateCodes($user->id);
        $plainCodes = $codeResult['plain_codes'];
        $firstCode = $plainCodes[0];

        $this->withSession([
            'mfa_pending_user_id' => $user->id,
            'mfa_challenge_id' => 'test-challenge',
            'mfa_challenge_expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        // Submit recovery code
        $response = $this->post(route('2fa.recovery.verify'), [
            'password' => 'TestPassword!130',
            'recovery_code' => $firstCode,
        ]);

        // Must redirect to replacement screen in restricted recovery mode
        $response->assertRedirect(route('2fa.replace.show'));
        $this->assertTrue(session('recovery_restricted'));
        $this->assertAuthenticatedAs($user);

        // Code must be consumed (marked as used in DB)
        $dbCode = TwoFactorRecoveryCode::where('user_id', $user->id)->first();
        $this->assertNotNull($dbCode->consumed_at);

        // Attempting to reuse the exact same recovery code must fail
        $reverify = $this->recoveryService->verifyAndConsumeCode($user->id, $firstCode);
        $this->assertFalse($reverify['success']);

        // User in recovery_restricted mode attempting to access standard CRM dashboard must be blocked
        $this->get('/dashboard')->assertRedirect(route('2fa.replace.show'));
    }

    /* ---------------------------------------------------------------------- */
    /*  11. Dual-Control Approval Enforced for Privileged Recovery */
    /* ---------------------------------------------------------------------- */

    public function test_dual_control_approval_enforced_for_privileged_account(): void
    {
        $privilegedTarget = $this->createPrivilegedUser();
        $admin1 = $this->createPrivilegedUser();
        $admin2 = $this->createPrivilegedUser();

        // Target requests lost-device recovery
        $approval = TwoFactorRecoveryApproval::create([
            'user_id' => $privilegedTarget->id,
            'reason' => 'Lost hardware key during patrol',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertTrue($approval->requiresDualControl());

        // 1. Self-approval must be rejected
        $selfApprove = $this->enrollmentAuthService->recordApproval($privilegedTarget, $approval);
        $this->assertFalse($selfApprove['success']);
        $this->assertSame('Self-approval is strictly forbidden under separation of duties.', $selfApprove['error']);

        // 2. Admin 1 approves (1 of 2)
        $res1 = $this->enrollmentAuthService->recordApproval($admin1, $approval);
        $this->assertTrue($res1['success']);
        $this->assertSame('pending', $res1['status'], 'Status must remain pending after only 1 approval for privileged accounts');
        $approval->refresh();
        $this->assertSame($admin1->id, $approval->approver1_id);
        $this->assertSame('pending', $approval->status);

        // 3. Admin 1 cannot provide the second approval (must be distinct approvers)
        $dupApprove = $this->enrollmentAuthService->recordApproval($admin1, $approval);
        $this->assertFalse($dupApprove['success']);
        $this->assertSame('A distinct second approver is required for privileged recovery.', $dupApprove['error']);

        // 4. Admin 2 approves (2 of 2)
        $res2 = $this->enrollmentAuthService->recordApproval($admin2, $approval);
        $this->assertTrue($res2['success']);
        $this->assertSame('approved', $res2['status'], 'Status must be approved once dual approvals are fulfilled');
        $approval->refresh();
        $this->assertSame('approved', $approval->status);
        $this->assertNotNull($approval->token_issued_at);
    }

    /* ---------------------------------------------------------------------- */
    /*  12. Inactivity Timeout Not Extended by Softphone / Polls */
    /* ---------------------------------------------------------------------- */

    public function test_inactivity_timeout_is_not_extended_by_background_polls(): void
    {
        $user = $this->createStaffUser();

        // Session created 25 minutes ago, human last active 25 minutes ago (still valid within 30 min)
        $this->actingAs($user)->withSession([
            'auth_method' => 'totp',
            'security_version' => 1,
            'session_created_at' => now()->subMinutes(25)->timestamp,
            'last_activity_at' => now()->subMinutes(25)->timestamp,
        ]);

        // Background poll request occurs at 25 minutes
        $this->get('/auth/heartbeat')->assertNoContent();

        // Advance time by 10 minutes (now 35 minutes since last human activity)
        $this->travel(10)->minutes();

        // Next human request to dashboard
        $humanResponse = $this->get('/dashboard');

        // Must be logged out due to inactivity
        $humanResponse->assertRedirect(route('login'));
        $humanResponse->assertSessionHasErrors(['username' => 'Session expired due to 30 minutes of inactivity.']);
        $this->assertGuest('web');
    }

    /* ---------------------------------------------------------------------- */
    /*  13. Security Version Mismatch Terminates Session */
    /* ---------------------------------------------------------------------- */

    public function test_security_version_bump_terminates_all_other_sessions(): void
    {
        $user = $this->createStaffUser(['security_version' => 1]);

        // Session has security_version = 1
        $this->actingAs($user)->withSession([
            'auth_method' => 'totp',
            'security_version' => 1,
            'last_activity_at' => now()->timestamp,
            'session_created_at' => now()->timestamp,
        ]);

        // Credential is changed or "logout other devices" is clicked, bumping version to 2
        $user->security_version = 2;
        $user->save();

        // The old session attempts to access dashboard
        $response = $this->get('/dashboard');

        // Must terminate session immediately
        $response->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'security_version' => 2]);
    }

    /* ---------------------------------------------------------------------- */
    /*  14. Role-Based MFA Policy Enforcement Tests */
    /* ---------------------------------------------------------------------- */

    public function test_exempt_operational_role_can_login_with_password_alone(): void
    {
        $wirelessRole = Role::firstOrCreate(['name' => 'beat_operator'], [
            'display_name' => 'Beat Wireless Operator',
            'scope_level' => 'beat',
            'status' => 'active',
        ]);

        MfaRolePolicy::create([
            'role_id' => $wirelessRole->id,
            'mfa_required' => false,
            'reason' => 'Initial rollout exemption',
        ]);

        $resolver = app(MfaPolicyResolver::class);
        $resolver->clearPolicyCache();

        $operator = $this->createStaffUser([
            'role_id' => $wirelessRole->id,
            'mfa_required' => false,
            'totp_secret' => null,
        ]);

        $response = $this->post('/login', [
            'username' => $operator->username,
            'password' => 'TestPassword!130',
        ]);

        // Exempt account logs in directly without MFA challenge loop!
        $response->assertRedirect($operator->getLandingPageRoute());
        $this->assertAuthenticatedAs($operator);
        $this->assertSame('password_only', session('auth_method'));
    }

    public function test_enforcing_mfa_blocks_existing_password_only_sessions(): void
    {
        $role = Role::firstOrCreate(['name' => 'beat_operator'], [
            'display_name' => 'Beat Wireless Operator',
            'scope_level' => 'beat',
            'status' => 'active',
        ]);

        $operator = $this->createStaffUser([
            'role_id' => $role->id,
        ]);

        // Session was established with password_only
        $this->actingAs($operator)->withSession([
            'auth_method' => 'password_only',
            'security_version' => 1,
            'last_activity_at' => now()->timestamp,
            'session_created_at' => now()->timestamp,
        ]);

        // Admin enables MFA policy for role
        MfaRolePolicy::updateOrCreate(
            ['role_id' => $role->id],
            ['mfa_required' => true, 'reason' => 'Rollout finalized']
        );

        app(MfaPolicyResolver::class)->clearPolicyCache();

        // Middleware detects policy is now required for password_only session
        $response = $this->get('/dashboard');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest('web');
    }

    public function test_protected_roles_cannot_be_exempted_by_role_policy(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);
        $actor = $this->createPrivilegedUser();

        $resolver = app(MfaPolicyResolver::class);

        $this->expectException(AuthorizationException::class);

        // Attempting to disable MFA for super_admin must throw AuthorizationException
        $resolver->updateRolePolicy(
            $superAdminRole,
            false,
            'Attempted illegal bypass',
            null,
            $actor
        );
    }

    /* ---------------------------------------------------------------------- */
    /*  Helpers & In-Memory SQLite Schema */
    /* ---------------------------------------------------------------------- */

    private function createStaffUser(array $attrs = []): User
    {
        return User::forceCreate(array_merge([
            'id' => self::$nextUserId++,
            'full_name' => 'Staff Officer',
            'email' => 'staff'.self::$nextUserId.'@example.test',
            'mobile_no' => '0300'.self::$nextUserId,
            'username' => 'staff.'.self::$nextUserId,
            'password' => 'TestPassword!130',
            'role_id' => 7, // agent (non-privileged)
            'is_active' => true,
            'mfa_required' => true,
            'mfa_assurance_level' => 'totp',
            'security_version' => 1,
        ], $attrs));
    }

    private function createPrivilegedUser(array $attrs = []): User
    {
        return User::forceCreate(array_merge([
            'id' => self::$nextUserId++,
            'full_name' => 'Super Admin Officer',
            'email' => 'admin'.self::$nextUserId.'@example.test',
            'mobile_no' => '0311'.self::$nextUserId,
            'username' => 'admin.'.self::$nextUserId,
            'password' => 'TestPassword!130',
            'role_id' => 1, // super_admin (privileged)
            'is_active' => true,
            'mfa_required' => true,
            'mfa_assurance_level' => 'webauthn',
            'security_version' => 1,
        ], $attrs));
    }

    private function createSecurityTestSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('scope_level');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'super_admin', 'display_name' => 'Super Administrator', 'scope_level' => 'national', 'status' => 'active'],
            ['id' => 2, 'name' => 'operation_admin', 'display_name' => 'Operation Admin', 'scope_level' => 'national', 'status' => 'active'],
            ['id' => 3, 'name' => 'zone_admin', 'display_name' => 'Zone Administrator', 'scope_level' => 'zone', 'status' => 'active'],
            ['id' => 4, 'name' => 'sector_admin', 'display_name' => 'Sector Administrator', 'scope_level' => 'sector', 'status' => 'active'],
            ['id' => 5, 'name' => 'beat_operator', 'display_name' => 'Beat Wireless Operator', 'scope_level' => 'beat', 'status' => 'active'],
            ['id' => 6, 'name' => 'agent_supervisor', 'display_name' => 'Call Center Supervisor', 'scope_level' => 'call_centre', 'status' => 'active'],
            ['id' => 7, 'name' => 'agent', 'display_name' => 'Call Center Agent', 'scope_level' => 'call_centre', 'status' => 'active'],
        ]);

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('module');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained();
            $table->foreignId('permission_id')->constrained();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('mobile_no')->unique();
            $table->string('username')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->foreignId('role_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->text('totp_secret')->nullable();
            $table->unsignedBigInteger('totp_last_step')->nullable();
            $table->boolean('mfa_required')->default(true);
            $table->string('mfa_assurance_level', 20)->default('totp');
            $table->unsignedInteger('security_version')->default(1);
            $table->timestamp('mfa_enrolled_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamps();
        });

        Schema::create('two_factor_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 255);
            $table->string('generation_id', 36)->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('mfa_role_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->boolean('mfa_required')->default(true);
            $table->timestamp('scheduled_re_enable_at')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('two_factor_enrollment_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('two_factor_recovery_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('approver1_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approver1_at')->nullable();
            $table->foreignId('approver2_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approver2_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('token_issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 512)->unique();
            $table->text('public_key');
            $table->string('attestation_type', 50)->default('none');
            $table->unsignedBigInteger('sign_count')->default(0);
            $table->boolean('user_verification')->default(true);
            $table->string('device_name', 100)->nullable();
            $table->boolean('is_hardware_key')->default(false);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('login_at')->useCurrent();
            $table->timestamp('logout_at')->nullable();
        });
    }
}

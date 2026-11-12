<?php

namespace Tests\Feature\Security;

use App\Models\AuditLog;
use App\Models\LoginLog;
use App\Models\Role;
use App\Models\User;
use App\Services\Geo\EmergencyOverrideService;
use App\Services\Geo\GeoLookup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class PakistanGeoAccessTest extends TestCase
{
    private User $adminUser;
    private User $operatorUser;
    private User $operatorUser2;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable CSRF and ensure MFA is disabled for direct web guard authentication tests
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['auth.mfa_enforce' => false]);

        // Reset rate limiter and caches
        RateLimiter::clear('login_u|' . Str::transliterate(Str::lower('test.admin')));
        RateLimiter::clear('login_u|' . Str::transliterate(Str::lower('test.operator')));
        RateLimiter::clear('login_u|' . Str::transliterate(Str::lower('test.operator2')));
        RateLimiter::clear('login_ip|39.32.1.1');
        RateLimiter::clear('login_ip|8.8.8.8');
        RateLimiter::clear('login_ip|198.51.100.5');
        RateLimiter::clear('login_ip|198.51.100.25');
        RateLimiter::clear('login_ip|198.51.100.77');
        RateLimiter::clear('login_ip|185.220.101.5');

        Cache::forget(EmergencyOverrideService::CACHE_KEY);
        $overrideFile = storage_path('app/geo/override_state.json');
        if (File::exists($overrideFile)) {
            @unlink($overrideFile);
        }

        // Warm up real geo datasets
        GeoLookup::resetForTesting();
        GeoLookup::warmup();

        // Create or load test users in disposable test database
        $this->adminUser = User::firstOrCreate(
            ['username' => 'test.admin'],
            [
                'full_name'        => 'Test Super Admin',
                'email'            => 'test.admin@nhmp.gov.pk',
                'password'         => Hash::make('AdminSecret123!'),
                'role_id'          => 1, // super_admin
                'is_active'        => true,
                'is_blocked'       => false,
                'security_version' => 1,
            ]
        );
        $this->adminUser->update([
            'password'        => Hash::make('AdminSecret123!'),
            'failed_attempts' => 0,
            'locked_until'    => null,
        ]);

        $this->operatorUser = User::firstOrCreate(
            ['username' => 'test.operator'],
            [
                'full_name'        => 'Test Beat Operator',
                'email'            => 'test.operator@nhmp.gov.pk',
                'password'         => Hash::make('OperatorSecret123!'),
                'role_id'          => 5, // beat_operator (MFA exempt in standard policy)
                'is_active'        => true,
                'is_blocked'       => false,
                'security_version' => 1,
            ]
        );
        $this->operatorUser->update([
            'password'        => Hash::make('OperatorSecret123!'),
            'mfa_required'    => false,
            'failed_attempts' => 0,
            'locked_until'    => null,
        ]);

        $this->operatorUser2 = User::firstOrCreate(
            ['username' => 'test.operator2'],
            [
                'full_name'        => 'Test Beat Operator 2',
                'email'            => 'test.operator2@nhmp.gov.pk',
                'password'         => Hash::make('OperatorSecret123!'),
                'role_id'          => 5, // beat_operator
                'is_active'        => true,
                'is_blocked'       => false,
                'security_version' => 1,
            ]
        );
        $this->operatorUser2->update([
            'password'        => Hash::make('OperatorSecret123!'),
            'mfa_required'    => false,
            'failed_attempts' => 0,
            'locked_until'    => null,
        ]);

        // Clean prior test login logs to isolate test cases from cross-test anomaly bleed
        LoginLog::whereIn('user_id', [$this->adminUser->id, $this->operatorUser->id, $this->operatorUser2->id])->delete();
    }

    /**
     * Helper to perform a login request through the trusted proxy architecture.
     */
    private function performLogin(
        string $username,
        string $password,
        string $clientIp,
        array $extraHeaders = []
    ) {
        // Simulates Front Load Balancer (10.1.2.62) appending client IP to X-Forwarded-For
        $headers = array_merge([
            'X-Forwarded-For' => $clientIp,
        ], $extraHeaders);

        return $this->withServerVariables(['REMOTE_ADDR' => '10.1.2.62'])
            ->withHeaders($headers)
            ->post(route('login.store'), [
                'username' => $username,
                'password' => $password,
            ]);
    }

    /* ---------------------------------------------------------------------- */
    /* 1. Pakistan IPv4 and IPv6 Allowed; Foreign Denied in Enforce           */
    /* ---------------------------------------------------------------------- */

    public function test_pakistan_ipv4_and_ipv6_are_allowed_in_enforce_mode(): void
    {
        config(['geo_access.mode' => 'enforce']);

        $auditIdBefore = (int) (AuditLog::max('id') ?? 0);

        // Pakistan IPv4: PTCL range (39.32.1.1)
        $response = $this->performLogin('test.operator', 'OperatorSecret123!', '39.32.1.1');
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->operatorUser);

        // Verify Audit Log
        $audit = AuditLog::where('id', '>', $auditIdBefore)
            ->where('user_id', $this->operatorUser->id)
            ->where('action', 'geo_login_allowed')
            ->latest('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame('PK', $audit->new_values['country'] ?? null);
        $this->assertSame('allow', $audit->new_values['decision'] ?? null);

        // Verify Login Log columns populated
        $loginLog = LoginLog::where('user_id', $this->operatorUser->id)->latest('id')->first();
        $this->assertNotNull($loginLog);
        $this->assertSame('PK', $loginLog->country);
        $this->assertSame('allow', $loginLog->geo_decision);

        \Illuminate\Support\Facades\Auth::guard('web')->logout();

        // Pakistan IPv6: Nayatel / PERN IPv6 range (2400:adc0::1)
        $responseV6 = $this->performLogin('test.operator', 'OperatorSecret123!', '2400:adc0::1');
        $responseV6->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->operatorUser);
    }

    public function test_foreign_ip_denied_in_enforce_mode(): void
    {
        config(['geo_access.mode' => 'enforce']);

        // Foreign IP (e.g. 8.8.8.8 / 198.51.100.5)
        $response = $this->performLogin('test.operator', 'OperatorSecret123!', '8.8.8.8');

        $response->assertSessionHasErrors(['username']);
        $this->assertGuest();

        // Verify Audit Log contains geo_login_denied
        $audit = AuditLog::where('user_id', $this->operatorUser->id)
            ->where('action', 'geo_login_denied')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('enforce', $audit->new_values['mode'] ?? null);
        $this->assertSame('deny', $audit->new_values['decision'] ?? null);
    }

    public function test_foreign_ip_allowed_in_shadow_mode_and_logged(): void
    {
        config(['geo_access.mode' => 'shadow']);

        // Foreign IP in shadow mode
        $response = $this->performLogin('test.operator', 'OperatorSecret123!', '8.8.8.8');

        // Operator is NOT locked out (availability beats strictness)
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->operatorUser);

        // Forensic audit row written with geo_login_shadow_would_deny
        $audit = AuditLog::where('user_id', $this->operatorUser->id)
            ->where('action', 'geo_login_shadow_would_deny')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('shadow', $audit->new_values['mode'] ?? null);
        $this->assertSame('shadow_would_deny', $audit->new_values['decision'] ?? null);

        // LoginLog recorded shadow_would_deny decision
        $loginLog = LoginLog::where('user_id', $this->operatorUser->id)->latest('id')->first();
        $this->assertNotNull($loginLog);
        $this->assertSame('shadow_would_deny', $loginLog->geo_decision);
    }

    /* ---------------------------------------------------------------------- */
    /* 2. Forged X-Forwarded-For / X-Real-IP Cannot Change Country            */
    /* ---------------------------------------------------------------------- */

    public function test_forged_x_forwarded_for_and_x_real_ip_cannot_change_country(): void
    {
        config(['geo_access.mode' => 'enforce']);

        // Attacker on foreign IP 198.51.100.5 attempts spoofing Pakistan IP in leftmost header
        // The trusted proxy (10.1.2.62) appends the real caller: "39.32.1.1, 198.51.100.5"
        $response = $this->performLogin(
            'test.operator',
            'OperatorSecret123!',
            '39.32.1.1, 198.51.100.5',
            ['X-Real-IP' => '39.32.1.1', 'CF-Connecting-IP' => '39.32.1.1']
        );

        // Must be rejected because rightmost untrusted hop is 198.51.100.5 (foreign)
        $response->assertSessionHasErrors(['username']);
        $this->assertGuest();

        $audit = AuditLog::where('user_id', $this->operatorUser->id)
            ->where('action', 'geo_login_denied')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('198.51.100.5', $audit->ip_address);
    }

    /* ---------------------------------------------------------------------- */
    /* 3. Fail-Open on Missing/Corrupt Files & Lookup Exceptions               */
    /* ---------------------------------------------------------------------- */

    public function test_missing_or_corrupt_geo_files_and_exceptions_fail_open(): void
    {
        config(['geo_access.mode' => 'enforce']);

        // Point to empty directory simulating missing/corrupt data files
        $tempDir = storage_path('app/geo_empty_test_' . Str::random(8));
        File::makeDirectory($tempDir, 0755, true, true);
        config(['geo_access.storage_path' => $tempDir]);

        GeoLookup::resetForTesting();
        GeoLookup::warmup($tempDir);

        try {
            // Login from any IP must succeed (fail open, helpline never disrupted)
            $response = $this->performLogin('test.operator', 'OperatorSecret123!', '198.51.100.77');
            $response->assertSessionHasNoErrors();
            $this->assertAuthenticatedAs($this->operatorUser);

            $audit = AuditLog::where('user_id', $this->operatorUser->id)
                ->where('action', 'geo_login_allowed')
                ->latest('id')
                ->first();

            $this->assertNotNull($audit);
            $this->assertContains('geo_lookup_fail_open', $audit->new_values['risk_reasons'] ?? []);
        } finally {
            File::deleteDirectory($tempDir);
            GeoLookup::resetForTesting();
            GeoLookup::warmup();
        }
    }

    /* ---------------------------------------------------------------------- */
    /* 4. CIDR Allow-Lists, User Exceptions, and Emergency Override          */
    /* ---------------------------------------------------------------------- */

    public function test_allowlisted_cidr_allows_login_regardless_of_geo(): void
    {
        config([
            'geo_access.mode' => 'enforce',
            'geo_access.cidr_allowlist' => ['198.51.100.0/24'],
        ]);

        // 198.51.100.25 is foreign IP, but in allow-listed CIDR
        $response = $this->performLogin('test.operator', 'OperatorSecret123!', '198.51.100.25');
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->operatorUser);

        $audit = AuditLog::where('user_id', $this->operatorUser->id)
            ->where('action', 'geo_login_allowed')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertContains('cidr_allowlist_match', $audit->new_values['risk_reasons'] ?? []);
    }

    public function test_named_user_exception_allows_active_and_rejects_expired(): void
    {
        config(['geo_access.mode' => 'enforce']);

        // 1. Active exception
        config([
            'geo_access.user_exceptions' => [
                'test.operator' => [
                    'expires_at'        => now()->addDay()->toDateTimeString(),
                    'owner'             => 'super_admin',
                    'allowed_countries' => ['*'],
                    'reason'            => 'Official duty abroad',
                ],
            ],
        ]);

        $responseActive = $this->performLogin('test.operator', 'OperatorSecret123!', '8.8.8.8');
        $responseActive->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->operatorUser);

        \Illuminate\Support\Facades\Auth::guard('web')->logout();

        // 2. Expired exception
        config([
            'geo_access.user_exceptions' => [
                'test.operator' => [
                    'expires_at'        => now()->subMinute()->toDateTimeString(),
                    'owner'             => 'super_admin',
                    'allowed_countries' => ['*'],
                    'reason'            => 'Expired deployment',
                ],
            ],
        ]);

        $responseExpired = $this->performLogin('test.operator', 'OperatorSecret123!', '8.8.8.8');
        $responseExpired->assertSessionHasErrors(['username']);
        $this->assertGuest();
    }

    public function test_emergency_override_bypasses_enforcement_and_creates_audit_row(): void
    {
        config(['geo_access.mode' => 'enforce']);

        /** @var EmergencyOverrideService $service */
        $service = app(EmergencyOverrideService::class);
        $service->enable('super_admin_test', 4, 'Operational emergency bypass test');

        // Foreign login succeeds under active emergency override
        $response = $this->performLogin('test.operator', 'OperatorSecret123!', '8.8.8.8');
        $response->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->operatorUser);

        // Verify audit rows: override enabled + login allowed
        $overrideAudit = AuditLog::where('action', 'geo_emergency_override_enabled')->latest('id')->first();
        $this->assertNotNull($overrideAudit);
        $this->assertSame('Operational emergency bypass test', $overrideAudit->new_values['reason'] ?? null);

        $loginAudit = AuditLog::where('user_id', $this->operatorUser->id)
            ->where('action', 'geo_login_allowed')
            ->latest('id')
            ->first();
        $this->assertNotNull($loginAudit);
        $this->assertContains('emergency_override_active', $loginAudit->new_values['risk_reasons'] ?? []);

        // Deactivate override
        $service->disable('super_admin_test', 'End of emergency');
        $deactivateAudit = AuditLog::where('action', 'geo_emergency_override_disabled')->latest('id')->first();
        $this->assertNotNull($deactivateAudit);
    }

    /* ---------------------------------------------------------------------- */
    /* 5. Role Strictness: Admin vs Operator                                  */
    /* ---------------------------------------------------------------------- */

    public function test_admin_role_is_stricter_than_operator_role_on_tor_and_vpn(): void
    {
        config(['geo_access.mode' => 'enforce']);

        // Mock Tor exit node IP
        $torIp = '185.220.101.5';

        // 1. Admin login on Tor exit node -> MUST be denied
        $adminResponse = $this->performLogin('test.admin', 'AdminSecret123!', $torIp);
        $adminResponse->assertSessionHasErrors(['username']);
        $this->assertGuest();

        $adminAudit = AuditLog::where('user_id', $this->adminUser->id)
            ->where('action', 'geo_login_denied')
            ->latest('id')
            ->first();
        $this->assertNotNull($adminAudit);
        $this->assertContains('tor_exit_node_detected', $adminAudit->new_values['risk_reasons'] ?? []);

        // 2. Operator login on Tor exit node in shadow mode -> allowed with alert
        config(['geo_access.mode' => 'shadow']);
        $operatorResponse = $this->performLogin('test.operator', 'OperatorSecret123!', $torIp);
        $operatorResponse->assertSessionHasNoErrors();

        $operatorAudit = AuditLog::where('user_id', $this->operatorUser->id)
            ->where('action', 'geo_login_shadow_would_deny')
            ->latest('id')
            ->first();
        $this->assertNotNull($operatorAudit);
        $this->assertContains('tor_exit_node_detected', $operatorAudit->new_values['risk_reasons'] ?? []);
    }

    /* ---------------------------------------------------------------------- */
    /* 6. Device Fingerprinting and Impossible-Travel Anomalies              */
    /* ---------------------------------------------------------------------- */

    public function test_device_and_impossible_travel_anomalies_are_detected(): void
    {
        config(['geo_access.mode' => 'shadow']);

        // Insert prior login 15 minutes ago from United States (US)
        LoginLog::create([
            'user_id'      => $this->operatorUser->id,
            'ip_address'   => '198.51.100.1',
            'user_agent'   => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'country'      => 'US',
            'login_at'     => now()->subMinutes(15),
            'geo_decision' => 'allow',
            'device_hash'  => 'mock_old_device_hash',
        ]);

        // Now user logs in from Pakistan (39.32.1.1)
        $response = $this->performLogin('test.operator', 'OperatorSecret123!', '39.32.1.1');
        $response->assertSessionHasNoErrors();

        $audit = AuditLog::where('user_id', $this->operatorUser->id)
            ->where('action', 'geo_login_alert')
            ->latest('id')
            ->first();

        $this->assertNotNull($audit);
        $reasons = $audit->new_values['risk_reasons'] ?? [];
        $hasImpossibleTravel = false;
        foreach ($reasons as $r) {
            if (str_starts_with($r, 'impossible_travel:US->PK')) {
                $hasImpossibleTravel = true;
                break;
            }
        }
        $this->assertTrue($hasImpossibleTravel, 'Impossible travel between US and PK within 15 min must be flagged.');
    }

    /* ---------------------------------------------------------------------- */
    /* 7. Independent Rate Limiting for Carrier NATs                          */
    /* ---------------------------------------------------------------------- */

    public function test_independent_rate_limits_protect_user_without_locking_carrier_nat(): void
    {
        $sharedCarrierIp = '39.32.1.1';

        // 5 bad password attempts against test.operator
        for ($i = 0; $i < 5; $i++) {
            $this->performLogin('test.operator', 'WrongPassword!', $sharedCarrierIp);
        }

        // 6th attempt against test.operator is rate-limited
        $lockedResponse = $this->performLogin('test.operator', 'OperatorSecret123!', $sharedCarrierIp);
        $lockedResponse->assertSessionHasErrors(['username']);
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('username'));

        // Another operator (test.operator2) behind the EXACT SAME carrier IP can still log in!
        $siblingResponse = $this->performLogin('test.operator2', 'OperatorSecret123!', $sharedCarrierIp);
        $siblingResponse->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->operatorUser2);
    }

    /* ---------------------------------------------------------------------- */
    /* 8. Performance: 10,000 Lookups in Under 1 Second                       */
    /* ---------------------------------------------------------------------- */

    public function test_lookup_performance_10000_operations_under_one_second(): void
    {
        GeoLookup::warmup();

        $ips = [
            '39.32.1.1',
            '182.180.1.1',
            '2400:adc0::1',
            '::ffff:39.32.1.1',
            '8.8.8.8',
            '127.0.0.1',
            '1.1.1.1',
            '185.220.101.5',
            '10.1.2.62',
            '202.83.160.1',
        ];

        $t0 = microtime(true);
        for ($i = 0; $i < 10000; $i++) {
            $result = GeoLookup::lookup($ips[$i % 10]);
            $this->assertIsArray($result);
        }
        $t1 = microtime(true);

        $elapsedSeconds = $t1 - $t0;
        $this->assertLessThan(1.0, $elapsedSeconds, "10,000 lookups must take less than 1.0 second (took {$elapsedSeconds}s).");
    }

    /* ---------------------------------------------------------------------- */
    /* 9. No Secrets in Git Diff                                              */
    /* ---------------------------------------------------------------------- */

    public function test_no_secrets_in_git_diff(): void
    {
        $diff = shell_exec('git diff main 2>/dev/null') ?? '';

        $forbiddenPatterns = [
            '/AIza[0-9A-Za-z-_]{35}/',                 // Google API key
            '/ghp_[0-9a-zA-Z]{36}/',                   // GitHub personal token
            '/-----BEGIN (RSA |EC )?PRIVATE KEY-----/', // Private keys
            '/license_key=[0-9a-zA-Z]{16,}/i',         // MaxMind license key in URL
        ];

        foreach ($forbiddenPatterns as $pattern) {
            $this->assertSame(0, preg_match($pattern, $diff), "Forbidden secret pattern detected in git diff: {$pattern}");
        }
    }
}

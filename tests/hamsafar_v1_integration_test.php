<?php
// Standalone test runner

use App\Models\Office;
use App\Models\PatrolDutySession;
use App\Models\PatrolOfficerProfile;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HamsafarV1TestRunner
{
    private array $results = [];
    private string $baseUrl = 'https://haproxy';

    public function run(): void
    {
        echo "\n=======================================================\n";
        echo "   NHMP HAMSAFAR V1 ECOSYSTEM - INTEGRATION TEST SUITE\n";
        echo "=======================================================\n\n";

        $this->testCommuterEmailRegistration();
        $this->testCommuterProfileCompletion();
        $this->testCommuterLogin();
        $this->testCommuterProfileGet();
        $this->testGoogleAuthRejectionOnInvalidToken();
        $this->testPatrolOfficerLoginAndStatus();
        $this->testPatrolRosterContext();
        $this->testPatrolDutyStartAndEnd();
        $this->testRbacSecurityBoundaries();

        echo "\n-------------------------------------------------------\n";
        $total = count($this->results);
        $passed = count(array_filter($this->results, fn($r) => $r['pass']));
        $failed = $total - $passed;

        echo "Total Tests: {$total} | Passed: {$passed} | Failed: {$failed}\n";
        if ($failed > 0) {
            echo "FAILED TESTS:\n";
            foreach ($this->results as $r) {
                if (!$r['pass']) {
                    echo "  ❌ {$r['name']}: {$r['error']}\n";
                }
            }
            exit(1);
        } else {
            echo "🎉 ALL HAMSAFAR V1 TESTS PASSED SUCCESSFULLY!\n";
        }
    }

    private function assert(string $testName, bool $condition, string $error = ''): void
    {
        $this->results[] = [
            'name'  => $testName,
            'pass'  => $condition,
            'error' => $error,
        ];

        if ($condition) {
            echo "  ✅ PASS: {$testName}\n";
        } else {
            echo "  ❌ FAIL: {$testName} - {$error}\n";
        }
    }

    private function post(string $uri, array $data, ?string $token = null): array
    {
        $ch = curl_init($this->baseUrl . $uri);
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'json' => json_decode($body, true), 'raw' => $body];
    }

    private function get(string $uri, ?string $token = null): array
    {
        $ch = curl_init($this->baseUrl . $uri);
        $headers = ['Accept: application/json'];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'json' => json_decode($body, true), 'raw' => $body];
    }

    private function put(string $uri, array $data, ?string $token = null): array
    {
        $ch = curl_init($this->baseUrl . $uri);
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($token) {
            $headers[] = "Authorization: Bearer {$token}";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['status' => $status, 'json' => json_decode($body, true), 'raw' => $body];
    }

    private string $commuterToken = '';
    private string $commuterEmail = '';
    private string $commuterMobile = '';
    private string $commuterCnic = '';
    private string $patrolToken = '';
    private string $patrolUsername = '';

    private function testCommuterEmailRegistration(): void
    {
        $this->commuterEmail = 'zainab_' . Str::lower(Str::random(6)) . '@example.com';
        $this->commuterMobile = '0300' . rand(1000000, 9999999);
        $this->commuterCnic = '37405-' . rand(1000000, 9999999) . '-2';

        $res = $this->post('/api/v1/auth/commuter/register', [
            'full_name'             => 'Zainab Bibi',
            'email'                 => $this->commuterEmail,
            'password'              => 'SecurePass123!@#',
            'password_confirmation' => 'SecurePass123!@#',
        ]);

        $this->assert('Commuter registration returns HTTP 201', $res['status'] === 201, "Got status {$res['status']}");
        $this->assert('Registration response returns Bearer token', !empty($res['json']['access_token']), 'Token missing');
        $this->assert('Profile is marked incomplete on initial registration', ($res['json']['is_profile_complete'] ?? null) === false, 'Profile complete mismatch');

        $this->commuterToken = $res['json']['access_token'] ?? '';
    }

    private function testCommuterProfileCompletion(): void
    {
        $res = $this->put('/api/v1/commuter/profile', [
            'full_name'          => 'Ayesha Khan',
            'mobile_no'          => $this->commuterMobile,
            'gender'             => 'female',
            'cnic'               => $this->commuterCnic,
            'blood_group'        => 'B+',
            'preferred_language' => 'ur',
            'emergency_contacts' => [
                ['name' => 'Father', 'phone' => '03007654321', 'relationship' => 'parent']
            ],
            'primary_vehicle'    => [
                'registration_no' => 'ICT-LE-921',
                'make_model'      => 'Toyota Corolla',
                'color'           => 'white',
                'type'            => 'car'
            ]
        ], $this->commuterToken);

        $this->assert('Commuter profile update returns HTTP 200', $res['status'] === 200, "Got status {$res['status']}");
        $this->assert('Profile is now marked complete', ($res['json']['is_profile_complete'] ?? false) === true, 'Profile incomplete');
        $this->assert('Gender is stored as female', ($res['json']['profile']['gender'] ?? null) === 'female', 'Gender mismatch');
        $this->assert('User full_name is updated', ($res['json']['user']['full_name'] ?? null) === 'Ayesha Khan', 'full_name mismatch');
        $this->assert('User name is updated', ($res['json']['user']['name'] ?? null) === 'Ayesha Khan', 'name mismatch');
        $this->assert('Profile object also includes full_name', ($res['json']['profile']['full_name'] ?? null) === 'Ayesha Khan', 'profile full_name mismatch');
    }

    private function testCommuterLogin(): void
    {
        $res = $this->post('/api/v1/auth/commuter/login', [
            'email'    => $this->commuterEmail,
            'password' => 'SecurePass123!@#',
        ]);

        $this->assert('Commuter login returns HTTP 200', $res['status'] === 200, "Got status {$res['status']}");
        $this->assert('Login returns access token', !empty($res['json']['access_token']), 'Token missing');
        $this->assert('Login recognizes completed profile', ($res['json']['is_profile_complete'] ?? false) === true, 'Profile mismatch');
    }

    private function testCommuterProfileGet(): void
    {
        $res = $this->get('/api/v1/commuter/profile', $this->commuterToken);

        $this->assert('Get commuter profile returns HTTP 200', $res['status'] === 200, "Got status {$res['status']}");
        $this->assert('Profile contains full_name', ($res['json']['user']['full_name'] ?? null) === 'Ayesha Khan', 'full_name missing');
        $this->assert('Profile contains name', ($res['json']['user']['name'] ?? null) === 'Ayesha Khan', 'name missing');
        $this->assert('Profile contains mobile number', ($res['json']['user']['mobile_no'] ?? null) === $this->commuterMobile, 'Mobile missing');
        $this->assert('Profile contains saved vehicle', count($res['json']['profile']['vehicle_profiles'] ?? []) > 0, 'Vehicle missing');
    }

    private function testGoogleAuthRejectionOnInvalidToken(): void
    {
        $res = $this->post('/api/v1/auth/commuter/google', [
            'id_token' => 'invalid_dummy_google_jwt_token_for_security_test'
        ]);

        $this->assert('Invalid Google token is rejected with HTTP 401', $res['status'] === 401, "Got status {$res['status']}");
    }

    private function testPatrolOfficerLoginAndStatus(): void
    {
        // Setup a test patrol officer in database
        $this->patrolUsername = 'spo_test_' . Str::lower(Str::random(5));
        $patrolRole = Role::where('name', 'patrol_officer')->first();
        $beat = Office::where('type', 'beat')->first() ?? Office::first();

        $officer = User::create([
            'full_name'      => 'Tahir Mehmood (Test SPO)',
            'email'          => $this->patrolUsername . '@nhmp.gov.pk',
            'username'       => $this->patrolUsername,
            'password'       => Hash::make('PatrolTactical999#'),
            'role_id'        => $patrolRole->id,
            'designation_id' => 11, // Senior Patrol Officer BPS-17
            'is_active'      => true,
        ]);

        PatrolOfficerProfile::create([
            'user_id'          => $officer->id,
            'badge_number'     => 'SPO-7741',
            'assigned_beat_id' => $beat->id,
        ]);

        // Test login with invalid password
        $failRes = $this->post('/api/v1/auth/patrol/login', [
            'username' => $this->patrolUsername,
            'password' => 'WrongPassword!',
        ]);
        $this->assert('Patrol login with bad credentials returns HTTP 401', $failRes['status'] === 401, "Got status {$failRes['status']}");

        // Test login with valid credentials
        $res = $this->post('/api/v1/auth/patrol/login', [
            'username' => $this->patrolUsername,
            'password' => 'PatrolTactical999#',
        ]);

        $this->assert('Patrol login with valid credentials returns HTTP 200', $res['status'] === 200, "Got status {$res['status']}");
        $this->assert('Patrol login returns token', !empty($res['json']['access_token']), 'Token missing');
        $this->assert('Patrol officer designation is Senior Patrol Officer', str_contains($res['json']['officer']['designation'] ?? '', 'Senior Patrol Officer'), 'Designation mismatch');
        $this->assert('Patrol officer initial status is NOT on duty', ($res['json']['officer']['is_on_duty'] ?? true) === false, 'Duty status mismatch');

        $this->patrolToken = $res['json']['access_token'] ?? '';
    }

    private function testPatrolRosterContext(): void
    {
        $res = $this->get('/api/v1/patrol/duty/roster-context', $this->patrolToken);

        $this->assert('Get roster context returns HTTP 200', $res['status'] === 200, "Got status {$res['status']}");
        $this->assert('Roster context contains officer details', !empty($res['json']['officer']['username']), 'Officer missing');
        $this->assert('Roster context contains assigned beat', !empty($res['json']['assigned_beat']['id']), 'Beat missing');
    }

    private function testPatrolDutyStartAndEnd(): void
    {
        $beat = Office::where('type', 'beat')->first() ?? Office::first();

        // 1. Start Duty
        $res = $this->post('/api/v1/patrol/duty/start', [
            'vehicle_callsign'  => 'P-12',
            'vehicle_reg_no'    => 'NHMP-1042',
            'beat_office_id'    => $beat->id,
            'initial_latitude'  => 32.78214,
            'initial_longitude' => 72.70512,
        ], $this->patrolToken);

        $this->assert('Patrol duty start returns HTTP 201', $res['status'] === 201, "Got status {$res['status']}");
        $this->assert('Duty session is marked AVAILABLE', ($res['json']['status'] ?? null) === 'AVAILABLE', 'Status mismatch');
        $this->assert('Vehicle callsign is bound to P-12', ($res['json']['vehicle_callsign'] ?? null) === 'P-12', 'Callsign mismatch');

        // 2. Verify /me now reports on duty
        $meRes = $this->get('/api/v1/patrol/me', $this->patrolToken);
        $this->assert('Patrol /me reports officer is ON DUTY', ($meRes['json']['officer']['is_on_duty'] ?? false) === true, 'Duty flag mismatch');
        $this->assert('Active duty vehicle is P-12', ($meRes['json']['officer']['active_duty']['vehicle_callsign'] ?? null) === 'P-12', 'Active vehicle mismatch');

        // 3. End Duty
        $endRes = $this->post('/api/v1/patrol/duty/end', [], $this->patrolToken);
        $this->assert('Patrol duty end returns HTTP 200', $endRes['status'] === 200, "Got status {$endRes['status']}");

        // 4. Verify /me reports OFF DUTY
        $meAfterRes = $this->get('/api/v1/patrol/me', $this->patrolToken);
        $this->assert('Patrol /me reports officer is now OFF DUTY', ($meAfterRes['json']['officer']['is_on_duty'] ?? true) === false, 'Duty flag mismatch after end');
    }

    private function testRbacSecurityBoundaries(): void
    {
        // 1. Commuter token attempting to access patrol duty start
        $beat = Office::where('type', 'beat')->first() ?? Office::first();
        $breachRes = $this->post('/api/v1/patrol/duty/start', [
            'vehicle_callsign' => 'HACK-01',
            'beat_office_id'   => $beat->id,
        ], $this->commuterToken);

        $this->assert('Commuter is forbidden from starting patrol duty (HTTP 403)', $breachRes['status'] === 403, "Got status {$breachRes['status']}");

        // 2. Unauthenticated request to protected endpoint
        $unauthRes = $this->get('/api/v1/commuter/profile');
        $this->assert('Unauthenticated request is rejected with HTTP 401', $unauthRes['status'] === 401, "Got status {$unauthRes['status']}");
    }
}

// Bootstrap Laravel & run test runner
require_once __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$runner = new HamsafarV1TestRunner();
$runner->run();

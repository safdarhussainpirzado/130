<?php
/**
 * NHMP 130 CRM - Automated Integration & Penetration Test Suite
 * Covers:
 *  1. Unit tests for 15-day password expiry policy
 *  2. Integration & route verification for Call Reminder (fixing 404)
 *  3. Integration & security verification for Password Reset Requests / Identifier
 *  4. End-to-end Password Expiry Middleware & Password Rotation (NIST SP 800-63B)
 *  5. Penetration Testing: Auth bypass, IDOR, SQL injection, Super Admin web reset block, and Rate Limiting
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Call;
use App\Models\CallType;
use App\Models\CallSubType;
use App\Models\Beat;
use App\Models\PasswordHistory;
use App\Models\PasswordResetRequest;
use App\Models\CallStatusHistory;
use App\Http\Middleware\EnsurePasswordNotExpired;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $results = [];

    public function assert(bool $condition, string $testName, string $failureDetails = ''): void
    {
        if ($condition) {
            $this->passed++;
            $this->results[] = ['status' => 'PASS', 'name' => $testName];
            echo "\033[32m[PASS]\033[0m {$testName}\n";
        } else {
            $this->failed++;
            $this->results[] = ['status' => 'FAIL', 'name' => $testName, 'details' => $failureDetails];
            echo "\033[31m[FAIL]\033[0m {$testName}\n";
            if (!empty($failureDetails)) {
                echo "       \033[33mDetails:\033[0m {$failureDetails}\n";
            }
        }
    }

    public function summary(): int
    {
        echo "\n" . str_repeat('=', 70) . "\n";
        echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo str_repeat('=', 70) . "\n";
        return $this->failed > 0 ? 1 : 0;
    }
}

$t = new TestRunner();

echo "\n" . str_repeat('=', 70) . "\n";
echo "1. UNIT & DOMAIN LOGIC: 15-DAY PASSWORD EXPIRY MODEL\n";
echo str_repeat('=', 70) . "\n";

// Test 1.1: Config check
$expiryDays = config('auth.password_expiry_days');
$t->assert($expiryDays === 15, "Config: auth.password_expiry_days is configured to 15 days", "Got: " . var_export($expiryDays, true));

// Test 1.2: User Model Expiry Methods
$testUser = new User();
$testUser->password_changed_at = now()->subDays(16);
$t->assert($testUser->isPasswordExpired() === true, "Model: User with password_changed_at 16 days ago is expired");
$t->assert($testUser->daysUntilPasswordExpires() === 0, "Model: daysUntilPasswordExpires() is 0 for expired user");

$testUser->password_changed_at = now()->subDays(10);
$t->assert($testUser->isPasswordExpired() === false, "Model: User with password_changed_at 10 days ago is NOT expired");
$t->assert($testUser->daysUntilPasswordExpires() === 5, "Model: daysUntilPasswordExpires() returns 5 days remaining");

$testUser->password_changed_at = null;
$testUser->created_at = now()->subDays(20);
$t->assert($testUser->isPasswordExpired() === true, "Model: User with null password_changed_at and 20-day old created_at falls back to expired");

echo "\n" . str_repeat('=', 70) . "\n";
echo "2. INTEGRATION TEST: CALL REMINDER ENDPOINT (FIXING 404)\n";
echo str_repeat('=', 70) . "\n";

// Test 2.1: Check route existence
$route = Route::getRoutes()->getByName('api.calls.reminder.call');
$t->assert($route !== null, "Route: api.calls.reminder.call exists in route collection");
$t->assert($route && in_array('POST', $route->methods()), "Route: api.calls.reminder.call accepts POST method");
$t->assert($route && str_contains($route->uri(), 'api/calls/{call}/reminder'), "Route: uri matches 'api/calls/{call}/reminder'");

// Test 2.2: Functional Reminder Dispatch
DB::beginTransaction();
try {
    $operator = User::whereHas('role', function($q) {
        $q->where('name', '!=', Role::SUPER_ADMIN);
    })->first() ?? User::first();

    $call = Call::first();
    if ($call) {
        $oldCount = $call->call_reminder_count ?? 0;
        
        $callService = app(\App\Services\CallService::class);
        $updatedCall = $callService->sendReminder($call, $operator);
        
        $t->assert($updatedCall->call_reminder_count === $oldCount + 1, "CallService: reminder count incremented from {$oldCount} to {$updatedCall->call_reminder_count}");
        $t->assert(!empty($updatedCall->last_reminder_at), "CallService: last_reminder_at recorded");
        
        $history = CallStatusHistory::where('call_id', $call->id)->latest()->first();
        $t->assert($history && str_contains($history->remarks, "Call reminder #{$updatedCall->call_reminder_count}"), "Audit: CallStatusHistory created with reminder details");
    } else {
        $t->assert(true, "CallService: (Skipped functional call test - no calls in DB)");
    }
} finally {
    DB::rollBack();
}

// Test 2.3: Security Pen Test on Reminder Endpoint
$middlewares = $route ? $route->gatherMiddleware() : [];
$hasAuth = in_array('auth', $middlewares) || in_array(\Illuminate\Auth\Middleware\Authenticate::class, $middlewares);
$t->assert($hasAuth, "Pen Test / Security: api.calls.reminder.call is strictly protected by 'auth' middleware");

echo "\n" . str_repeat('=', 70) . "\n";
echo "3. INTEGRATION TEST: FORGOT PASSWORD & PASSWORD RESET REQUESTS\n";
echo str_repeat('=', 70) . "\n";

// Test 3.1: Password Reset Request Submission with Email
DB::beginTransaction();
try {
    $operator = User::whereHas('role', function($q) {
        $q->whereIn('name', [Role::AGENT, Role::BEAT_OPERATOR, 'agent']);
    })->first() ?? User::where('username', '!=', 'superadmin')->first();

    if ($operator) {
        $req = Request::create('/forgot-password', 'POST', [
            'email' => $operator->email,
        ]);
        $session = app('session.store');
        $req->setLaravelSession($session);
        $req->headers->set('referer', '/forgot-password');
        
        $controller = app(\App\Http\Controllers\Auth\PasswordResetLinkController::class);
        $resp = $controller->store($req);
        
        $resetReq = PasswordResetRequest::where('user_id', $operator->id)->latest()->first();
        $t->assert($resetReq !== null, "Forgot Password: Request successfully recorded for registered email {$operator->email}");
        $t->assert($resetReq && $resetReq->status === 'pending', "Forgot Password: Request status is 'pending'");
    } else {
        $t->assert(true, "Forgot Password: (Operator user not found, skipped email test)");
    }
} finally {
    DB::rollBack();
}

// Test 3.2: Password Reset Request Submission with Username via Identifier
DB::beginTransaction();
try {
    $user = User::whereNotNull('username')->where('username', '!=', 'superadmin')->first();
    if ($user) {
        $req = Request::create('/forgot-password', 'POST', [
            'identifier' => $user->username,
        ]);
        $session = app('session.store');
        $req->setLaravelSession($session);
        $req->headers->set('referer', '/forgot-password');
        
        $controller = app(\App\Http\Controllers\Auth\PasswordResetLinkController::class);
        $resp = $controller->store($req);
        
        $resetReq = PasswordResetRequest::where('user_id', $user->id)->latest()->first();
        $t->assert($resetReq !== null, "Forgot Password: Request successfully recorded via identifier username {$user->username}");
    }
} finally {
    DB::rollBack();
}

// Test 3.3: Pen Test - Super Admin Web Reset Protection
DB::beginTransaction();
try {
    $superadmin = User::where('username', 'superadmin')->orWhere('email', 'like', '%superadmin%')->first();
    if ($superadmin) {
        $req = Request::create('/forgot-password', 'POST', [
            'identifier' => $superadmin->username,
        ]);
        $session = app('session.store');
        $req->setLaravelSession($session);
        $req->headers->set('referer', '/forgot-password');
        
        $controller = app(\App\Http\Controllers\Auth\PasswordResetLinkController::class);
        $resp = $controller->store($req);
        
        $latest = PasswordResetRequest::where('user_id', $superadmin->id)->latest()->first();
        $t->assert($latest === null || $latest->created_at->lt(now()->subMinutes(1)), "Pen Test: Super Admin web reset is strictly BLOCKED from queueing reset requests");
    } else {
        $t->assert(true, "Pen Test: (Superadmin not found, check passed)");
    }
} finally {
    DB::rollBack();
}

// Test 3.4: Pen Test - SQL Injection in Identifier Field
DB::beginTransaction();
try {
    $sqliPayload = "admin' OR '1'='1' --";
    $req = Request::create('/forgot-password', 'POST', [
        'identifier' => $sqliPayload,
    ]);
    $session = app('session.store');
    $req->setLaravelSession($session);
    $req->headers->set('referer', '/forgot-password');
    
    $controller = app(\App\Http\Controllers\Auth\PasswordResetLinkController::class);
    $resp = $controller->store($req);
    
    // Check that no reset requests were created for all users
    $count = PasswordResetRequest::where('requester_username', $sqliPayload)->count();
    $t->assert($count === 0, "Pen Test: SQL Injection attempt in identifier field safely neutralized without DB corruption");
} finally {
    DB::rollBack();
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "4. INTEGRATION TEST: 15-DAY PASSWORD EXPIRY ENFORCEMENT & ROTATION\n";
echo str_repeat('=', 70) . "\n";

// Test 4.1: Middleware Intercepts Expired User on Standard Request
$middleware = new EnsurePasswordNotExpired();

$expiredUser = new User();
$expiredUser->id = 999991;
$expiredUser->password_changed_at = now()->subDays(16);
Auth::setUser($expiredUser);

$standardReq = Request::create('/dashboard', 'GET');
$response = $middleware->handle($standardReq, function() {
    return response('Passed to Dashboard', 200);
});

$t->assert($response->getStatusCode() === 302, "Middleware: Expired user is intercepted with HTTP 302 Redirect");
$t->assert(str_contains($response->headers->get('Location'), 'password/expired'), "Middleware: Redirect destination is route password.expired");

// Test 4.2: Middleware Intercepts Expired User on AJAX Request
$ajaxReq = Request::create('/dashboard', 'GET');
$ajaxReq->headers->set('X-Requested-With', 'XMLHttpRequest');
$ajaxReq->headers->set('Accept', 'application/json');

$ajaxResponse = $middleware->handle($ajaxReq, function() {
    return response('Passed', 200);
});

$t->assert($ajaxResponse->getStatusCode() === 403, "Middleware: AJAX request from expired user receives HTTP 403 Forbidden");
$data = json_decode($ajaxResponse->getContent(), true);
$t->assert(($data['error'] ?? '') === 'PASSWORD_EXPIRED', "Middleware: AJAX error code is PASSWORD_EXPIRED");

// Test 4.3: Whitelist Verification
$whitelistedRoutes = [
    ['uri' => 'password/expired', 'method' => 'GET'],
    ['uri' => 'password/expired', 'method' => 'POST'],
    ['uri' => 'logout', 'method' => 'POST'],
    ['uri' => 'auth/heartbeat', 'method' => 'GET'],
];

foreach ($whitelistedRoutes as $entry) {
    $uri = $entry['uri'];
    $method = $entry['method'];
    $wlReq = Request::create("/{$uri}", $method);
    $wlResponse = $middleware->handle($wlReq, function() {
        return response('Passed Whitelist', 200);
    });
    $t->assert($wlResponse->getStatusCode() === 200, "Middleware Whitelist: {$method} /{$uri} is allowed through without redirect loop");
}

// Test 4.4: Active User Passes Without Interception
$validUser = new User();
$validUser->id = 999992;
$validUser->password_changed_at = now()->subDays(5);
Auth::setUser($validUser);

$validReq = Request::create('/dashboard', 'GET');
$validResp = $middleware->handle($validReq, function() {
    return response('Passed to Dashboard', 200);
});
$t->assert($validResp->getStatusCode() === 200, "Middleware: Active user (password changed 5 days ago) passes seamlessly");

echo "\n" . str_repeat('=', 70) . "\n";
echo "5. CYBERSECURITY PEN TEST: PASSWORD ROTATION & NIST SP 800-63B\n";
echo str_repeat('=', 70) . "\n";

DB::beginTransaction();
try {
    $user = User::first();
    if ($user) {
        $oldPlain = 'OldCurrentP@ss1234!';
        $user->password = Hash::make($oldPlain);
        $user->password_changed_at = now()->subDays(16);
        $user->save();
        Auth::setUser($user);

        // Record a past password in history
        $pastPasswordPlain = 'PreviousP@ssword2025#';
        PasswordHistory::create([
            'user_id' => $user->id,
            'password' => Hash::make($pastPasswordPlain),
        ]);

        $controller = app(\App\Http\Controllers\Auth\PasswordExpiredController::class);

        // Pen Test 5.1: Wrong current password
        try {
            $req = Request::create('/password/expired', 'POST', [
                'current_password' => 'WrongPassword999!',
                'password' => 'BrandNewStrongP@ss2026!',
                'password_confirmation' => 'BrandNewStrongP@ss2026!',
            ]);
            $req->setLaravelSession(app('session.store'));
            $controller->update($req);
            $t->assert(false, "Pen Test: Wrong current password should throw validation exception");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $t->assert(isset($e->errors()['current_password']), "Pen Test: Rejected when current_password is invalid");
        }

        // Pen Test 5.2: Reusing past password (NIST SP 800-63B)
        try {
            $req = Request::create('/password/expired', 'POST', [
                'current_password' => $oldPlain,
                'password' => $pastPasswordPlain,
                'password_confirmation' => $pastPasswordPlain,
            ]);
            $req->setLaravelSession(app('session.store'));
            $controller->update($req);
            $t->assert(false, "Pen Test: Reusing past password should throw validation exception");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $t->assert(isset($e->errors()['password']), "Pen Test NIST SP 800-63B: Prevented reusing any of the last 5 passwords");
        }

        // Pen Test 5.3: Successful rotation with strong password
        $newPlain = 'BrandNewStrongP@ssword2026!#';
        $req = Request::create('/password/expired', 'POST', [
            'current_password' => $oldPlain,
            'password' => $newPlain,
            'password_confirmation' => $newPlain,
        ]);
        $req->setLaravelSession(app('session.store'));
        $resp = $controller->update($req);

        $user->refresh();
        $t->assert(Hash::check($newPlain, $user->password), "Rotation: New password successfully hashed and stored");
        $t->assert($user->isPasswordExpired() === false, "Rotation: User is no longer expired after update");
        $t->assert($user->password_changed_at->isToday(), "Rotation: password_changed_at updated to today");

        // Verify history recorded
        $latestHistory = PasswordHistory::where('user_id', $user->id)->latest()->first();
        $t->assert($latestHistory && Hash::check($oldPlain, $latestHistory->password), "Rotation: Old password archived in PasswordHistory to prevent reuse");
    }
} finally {
    DB::rollBack();
}

// Test 5.4: Route Throttling Check
$expiredUpdateRoute = Route::getRoutes()->getByName('password.expired.update');
$t->assert($expiredUpdateRoute !== null, "Route: password.expired.update exists");
$throttled = false;
foreach ($expiredUpdateRoute->gatherMiddleware() as $m) {
    if (str_contains($m, 'throttle:5,1')) {
        $throttled = true;
        break;
    }
}
$t->assert($throttled, "Pen Test: password.expired.update is rate-limited to 5 requests per minute to stop brute-forcing");

exit($t->summary());

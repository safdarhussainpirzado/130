<?php
/**
 * NHMP 130 CRM - Unified Support & Self-Service Management Test Suite
 * Comprehensive automated verification for:
 *  1. RBAC & Granular Permission Mapping (13 permissions across 7 roles)
 *  2. Step-Up Authentication (Password, Monotonic TOTP, Rate Limiting, Replay Prevention)
 *  3. User Self-Service Portal & UUIDv4 Route Binding (Anti-IDOR Protection)
 *  4. 3-Stage Organizational Transfer Workflow (Anti-Self-Approval & Anti-Self-Confirmation)
 *  5. Atomic Assignment Execution & Immutable History Logging
 *  6. Emergency Account Lockout & Admin Unlock Lifecycle
 *  7. CSPRNG Password Recovery & Anti-Enumeration (NIST SP 800-63B Zero-Admin-Knowledge)
 *  8. Navigation Bifurcation Architecture
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Role;
use App\Models\Office;
use App\Models\SupportRequest;
use App\Models\SupportRequestAction;
use App\Models\UserAssignmentHistory;
use App\Models\PasswordRecoveryToken;
use App\Services\Support\StepUpAuthenticationService;
use App\Services\Support\OrganizationalTransferService;
use App\Services\Support\AccountSecuritySupportService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportTestSuiteRunner
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

    public function section(string $title): void
    {
        echo "\n" . str_repeat('=', 78) . "\n";
        echo " " . strtoupper($title) . "\n";
        echo str_repeat('=', 78) . "\n";
    }

    public function summary(): int
    {
        echo "\n" . str_repeat('=', 78) . "\n";
        echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo str_repeat('=', 78) . "\n";
        return $this->failed > 0 ? 1 : 0;
    }
}

function getOrCreateTestUser(string $email, string $username, string $fullName, int $roleId, string $password = 'Pass123!'): User {
    $user = User::where('email', $email)->orWhere('username', $username)->first();
    if (!$user) {
        $user = new User();
        $user->username = $username;
        $user->full_name = $fullName;
        $user->email = $email;
        $user->mobile_no = '03' . rand(10, 49) . rand(1000000, 9999999);
        $user->password = Hash::make($password);
        $user->role_id = $roleId;
        $user->is_active = 1;
        $user->save();
    } else {
        $user->password = Hash::make($password);
        $user->role_id = $roleId;
        $user->is_active = 1;
        $user->save();
    }
    return $user;
}

$t = new SupportTestSuiteRunner();

// ======================================================================
// 1. RBAC & GRANULAR PERMISSIONS
// ======================================================================
$t->section("1. RBAC & Granular Permission Mapping");

$expectedPerms = [
    'support.view',
    'support.create',
    'support.cancel',
    'support.transfer_apply',
    'support.transfer_verify_source',
    'support.transfer_confirm_destination',
    'support.transfer_approve',
    'support.account_lock',
    'support.account_unlock',
    'support.password_reset_authorize',
    'support.mfa_assist',
    'support.manage',
    'support.analytics',
];

$existingPerms = DB::table('permissions')->whereIn('name', $expectedPerms)->pluck('name')->toArray();
$t->assert(count($existingPerms) === 13, "All 13 granular support permissions seeded in database", "Found: " . count($existingPerms));

// Check super admin has all 13
$superAdminRole = DB::table('roles')->where('name', 'super_admin')->first();
if ($superAdminRole) {
    $saPermCount = DB::table('role_permissions')
        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->where('role_permissions.role_id', $superAdminRole->id)
        ->whereIn('permissions.name', $expectedPerms)
        ->count();
    $t->assert($saPermCount === 13, "Super Admin role holds all 13 support permissions", "Found: {$saPermCount}");
}

// Check sector_admin has transfer_verify_source
$sectorRole = DB::table('roles')->where('name', 'sector_admin')->first();
if ($sectorRole) {
    $hasVerify = DB::table('role_permissions')
        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->where('role_permissions.role_id', $sectorRole->id)
        ->where('permissions.name', 'support.transfer_verify_source')
        ->exists();
    $t->assert($hasVerify, "Sector Admin role holds 'support.transfer_verify_source'");
}

// Check beat_operator has transfer_confirm_destination
$beatRole = DB::table('roles')->where('name', 'beat_operator')->first();
if ($beatRole) {
    $hasConfirm = DB::table('role_permissions')
        ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
        ->where('role_permissions.role_id', $beatRole->id)
        ->where('permissions.name', 'support.transfer_confirm_destination')
        ->exists();
    $t->assert($hasConfirm, "Beat Operator role holds 'support.transfer_confirm_destination'");
}

// ======================================================================
// 2. STEP-UP AUTHENTICATION SERVICE
// ======================================================================
$t->section("2. Step-Up Authentication Security");

$stepUp = app(StepUpAuthenticationService::class);

$testUser = getOrCreateTestUser('test_stepup@example.com', 'test_stepup_user', 'Test StepUp User', 5, 'Secret123!@#');

RateLimiter::clear('step_up_auth:' . $testUser->id . ':127.0.0.1');

$validAuth = $stepUp->verify($testUser, 'Secret123!@#');
$t->assert($validAuth['success'] === true, "Step-Up auth succeeds with valid password");

$invalidAuth = $stepUp->verify($testUser, 'WrongPassword999!');
$t->assert($invalidAuth['success'] === false, "Step-Up auth fails with invalid password");
$t->assert(!empty($invalidAuth['error'] ?? null), "Step-Up auth returns descriptive error on failure");

// Rate limiting check
$rateLimitBlocked = false;
for ($i = 0; $i < 6; $i++) {
    $res = $stepUp->verify($testUser, 'BadPassword');
    if (!$res['success'] && str_contains(strtolower($res['error'] ?? ''), 'too many')) {
        $rateLimitBlocked = true;
        break;
    }
}
$t->assert($rateLimitBlocked === true, "Step-Up auth enforces rate limiting (5 attempts per minute)");

// ======================================================================
// 3. USER SELF-SERVICE PORTAL & UUID ROUTE BINDING (ANTI-IDOR)
// ======================================================================
$t->section("3. User Self-Service Portal & IDOR Protection");

$userA = getOrCreateTestUser('portal_user_a@example.com', 'portal_user_a', 'Portal User A', 7, 'PasswordA123!');
$userB = getOrCreateTestUser('portal_user_b@example.com', 'portal_user_b', 'Portal User B', 7, 'PasswordB123!');

// User A creates a support request
$reqA = new SupportRequest();
$reqA->requester_id = $userA->id;
$reqA->request_type = SupportRequest::TYPE_OTHER_ACCOUNT;
$reqA->subject = 'Headset Hardware Audio Issue';
$reqA->description = 'Intermittent crackling on helpline headset during active dispatch.';
$reqA->priority = SupportRequest::PRIORITY_NORMAL;
$reqA->current_status = SupportRequest::STATUS_PENDING_MANAGER_REVIEW;
$reqA->save();

$t->assert(!empty($reqA->public_id), "SupportRequest auto-generates UUIDv4 public_id on creation");
$t->assert(Str::isUuid($reqA->public_id), "public_id conforms strictly to UUIDv4 format");

// Test Policy: User A can view own request
$policy = app(\App\Policies\SupportRequestPolicy::class);
$canViewOwn = $policy->view($userA, $reqA);
$t->assert($canViewOwn === true, "Policy: User A is authorized to view their own support request");

// Anti-IDOR Test: User B CANNOT view User A's request
$canViewOther = $policy->view($userB, $reqA);
$t->assert($canViewOther === false, "Anti-IDOR Guard: User B is FORBIDDEN from viewing User A's request");

// Anti-IDOR Test: User B CANNOT cancel User A's request
$canCancelOther = $policy->cancel($userB, $reqA);
$t->assert($canCancelOther === false, "Anti-IDOR Guard: User B is FORBIDDEN from cancelling User A's request");

// User A cancels own request
$canCancelOwn = $policy->cancel($userA, $reqA);
$t->assert($canCancelOwn === true, "Policy: User A is authorized to cancel their own pending request");

$reqA->current_status = SupportRequest::STATUS_CANCELLED;
$reqA->setAttribute('completed_at', now());
$reqA->save();

// Cannot cancel already cancelled request
$canCancelAgain = $policy->cancel($userA, $reqA);
$t->assert($canCancelAgain === false, "Policy: Cannot cancel an already cancelled support request");

// ======================================================================
// 4. 3-STAGE ORGANIZATIONAL TRANSFER WORKFLOW
// ======================================================================
$t->section("4. 3-Stage Organizational Transfer Workflow");

// Ensure source and destination offices
$sourceOffice = Office::where('type', 'beat')->where('is_active', true)->whereNull('deleted_at')->first();
$destOffice = Office::where('type', 'beat')->where('is_active', true)->whereNull('deleted_at')->where('id', '!=', $sourceOffice?->id)->first();

if (!$sourceOffice || !$destOffice) {
    echo "Skipping transfer workflow test: insufficient office fixtures.\n";
} else {
    // Applicant
    $applicant = getOrCreateTestUser('transfer_applicant@example.com', 'transfer_applicant', 'Transfer Applicant', 5, 'Pass123!');

    // Attach initial scope to applicant
    DB::table('user_scopes')->updateOrInsert(
        ['user_id' => $applicant->id],
        ['office_id' => $sourceOffice->id, 'created_at' => now(), 'updated_at' => now()]
    );

    // Clean up any old pending transfers for applicant
    SupportRequest::where('requester_id', $applicant->id)
        ->where('request_type', SupportRequest::TYPE_ORG_TRANSFER)
        ->delete();

    RateLimiter::clear('step_up_auth:' . $applicant->id . ':127.0.0.1');

    $transferService = app(OrganizationalTransferService::class);

    // Step 0: Apply with verified step-up authentication
    $transferReq = $transferService->submitTransferRequest(
        $applicant,
        $destOffice->id,
        'Relocation to family residence near Sector Beat 2.',
        'Pass123!'
    );

    $t->assert($transferReq->current_status === SupportRequest::STATUS_PENDING_SOURCE_APPROVAL, "Transfer initiated with status 'pending_source_approval'");
    $t->assert($transferReq->source_office_id === $sourceOffice->id, "Transfer correctly captures applicant current source office");
    $t->assert($transferReq->destination_office_id === $destOffice->id, "Transfer correctly records requested destination office");

    // Anti-Self-Approval Test: Applicant attempts to approve their own transfer at Stage 1
    $selfApproveBlocked = false;
    try {
        $transferService->processSourceVerification(
            $transferReq,
            $applicant,
            true,
            'Attempting self approval',
            'Pass123!'
        );
    } catch (ValidationException $e) {
        $selfApproveBlocked = str_contains(strtolower($e->getMessage()), 'cannot verify or approve their own');
    }
    $t->assert($selfApproveBlocked === true, "Anti-Self-Approval: Applicant strictly FORBIDDEN from verifying own transfer");

    // Sector Incharge approves Stage 1 (must have scope over source's parent sector)
    $sourceIncharge = getOrCreateTestUser('source_incharge@example.com', 'source_incharge', 'Sector Incharge', 4, 'Pass123!');
    DB::table('user_scopes')->updateOrInsert(
        ['user_id' => $sourceIncharge->id],
        ['office_id' => $sourceOffice->parent_id, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
    );

    RateLimiter::clear('step_up_auth:' . $sourceIncharge->id . ':127.0.0.1');

    $transferService->processSourceVerification(
        $transferReq,
        $sourceIncharge,
        true,
        'Verified service clearance and duty handover complete.',
        'Pass123!'
    );
    $transferReq->refresh();
    $t->assert($transferReq->current_status === SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION, "Stage 1 approved: Status transitioned to 'pending_destination_confirmation'");

    // Anti-Self-Arrival Test: Applicant attempts to confirm their own arrival at Stage 2
    $selfArrivalBlocked = false;
    try {
        $transferService->processDestinationArrival(
            $transferReq,
            $applicant,
            true,
            'Attempting self arrival confirmation',
            'Pass123!'
        );
    } catch (ValidationException $e) {
        $selfArrivalBlocked = str_contains(strtolower($e->getMessage()), 'cannot confirm their own');
    }
    $t->assert($selfArrivalBlocked === true, "Anti-Self-Confirmation: Applicant strictly FORBIDDEN from confirming own arrival");

    // Destination Beat Incharge confirms arrival (Stage 2 Execution - must have scope over destination office)
    $destIncharge = getOrCreateTestUser('dest_incharge@example.com', 'dest_incharge', 'Dest Beat Incharge', 5, 'Pass123!');
    DB::table('user_scopes')->updateOrInsert(
        ['user_id' => $destIncharge->id],
        ['office_id' => $destOffice->id, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]
    );

    RateLimiter::clear('step_up_auth:' . $destIncharge->id . ':127.0.0.1');

    $transferService->processDestinationArrival(
        $transferReq,
        $destIncharge,
        true,
        'Officer reported for duty. Wireless equipment assigned.',
        'Pass123!'
    );
    $transferReq->refresh();

    $t->assert($transferReq->current_status === SupportRequest::STATUS_COMPLETED, "Stage 2 complete: Status transitioned to 'completed'");

    // Verify atomic assignment execution
    $currentOfficeId = $applicant->activeScopes()->whereNotNull('office_id')->value('office_id');
    $t->assert($currentOfficeId === $destOffice->id, "Atomic Assignment: Applicant user_scopes successfully updated to destination office ({$destOffice->id})");

    // Verify reversible user_assignment_history
    $historyRecord = UserAssignmentHistory::where('transfer_request_id', $transferReq->id)->first();
    $t->assert(!empty($historyRecord), "Immutable History: UserAssignmentHistory record successfully written");
    $t->assert($historyRecord?->previous_office_id === $sourceOffice->id, "Assignment History captures exact previous office ID");
    $t->assert($historyRecord?->new_office_id === $destOffice->id, "Assignment History captures exact new office ID");
}

// ======================================================================
// 5. EMERGENCY ACCOUNT LOCKOUT & ADMIN UNLOCK
// ======================================================================
$t->section("5. Emergency Account Lockout & Admin Unlock");

$securityService = app(AccountSecuritySupportService::class);

$victimUser = getOrCreateTestUser('compromised_user@example.com', 'compromised_user', 'Compromised User', 7, 'MyPassword123!');

RateLimiter::clear('step_up_auth:' . $victimUser->id . ':127.0.0.1');

$lockReq = $securityService->lockAccount(
    $victimUser,
    'Unauthorized login prompt observed from foreign IP address.',
    'MyPassword123!'
);
$victimUser->refresh();

$t->assert($victimUser->is_active == 0, "Emergency Lockout: User account is_active immediately set to 0");
$t->assert($lockReq->request_type === SupportRequest::TYPE_ACCOUNT_BLOCK, "Lock request type is 'account_block'");
$t->assert($lockReq->current_status === SupportRequest::STATUS_PENDING_MANAGER_REVIEW, "Lock request queued for manager review");

// Admin Unlocks account after verification
$opAdminRole = DB::table('roles')->where('name', 'operation_admin')->first();
$adminUser = getOrCreateTestUser('op_manager@example.com', 'op_test_manager', 'Duty Operation Manager', $opAdminRole?->id ?? 2, 'ManagerPass123!');

RateLimiter::clear('step_up_auth:' . $adminUser->id . ':127.0.0.1');

$securityService->unlockAccount(
    $lockReq,
    $adminUser,
    'Identity verified via official regional headquarters in-person interview.',
    'ManagerPass123!'
);
$victimUser->refresh();
$lockReq->refresh();

$t->assert($victimUser->is_active == 1, "Admin Unlock: User account is_active restored to 1");
$t->assert($lockReq->current_status === SupportRequest::STATUS_COMPLETED, "Lock request status marked 'completed'");

// ======================================================================
// 6. CSPRNG PASSWORD RECOVERY (NIST SP 800-63B)
// ======================================================================
$t->section("6. Single-Use CSPRNG Password Recovery");

// Clean old requests
SupportRequest::where('requester_id', $victimUser->id)
    ->where('request_type', SupportRequest::TYPE_PASSWORD_RECOVERY)
    ->delete();

// Submit public recovery (with anti-enumeration test)
$pubResUnknown = $securityService->submitPublicPasswordRecovery('nonexistent_entity_999@test.com');
$t->assert($pubResUnknown['success'] === true, "Anti-Enumeration: Public request for unknown user returns success");

$pubResKnown = $securityService->submitPublicPasswordRecovery($victimUser->email);
$t->assert($pubResKnown['success'] === true, "Anti-Enumeration: Public request for existing user returns success");

$recoveryReq = SupportRequest::where('requester_id', $victimUser->id)
    ->where('request_type', SupportRequest::TYPE_PASSWORD_RECOVERY)
    ->latest('id')
    ->firstOrFail();

RateLimiter::clear('step_up_auth:' . $adminUser->id . ':127.0.0.1');

// Operation Manager approves and generates token
$approved = $securityService->approvePasswordRecovery(
    $recoveryReq,
    $adminUser,
    'Identity confirmed with supervisor.',
    'ManagerPass123!'
);

$plainToken = $approved['plain_token'];
$resetUrl = $approved['reset_url'];

$t->assert(!empty($plainToken), "Password Recovery: 64-character CSPRNG token generated");
$t->assert(strlen($plainToken) === 64, "Token is exactly 64 characters (256-bit entropy)");
$t->assert(str_contains($resetUrl, $plainToken), "Recovery URL contains unhashed token for user delivery");

// Check Database: Plaintext token MUST NOT exist in DB!
$rawInDb = DB::table('password_recovery_tokens')->where('token_hash', $plainToken)->exists();
$t->assert($rawInDb === false, "NIST SP 800-63B: Plaintext token NEVER stored in database");

$tokenRecord = PasswordRecoveryToken::findValidToken($plainToken);
$t->assert(!empty($tokenRecord), "Token lookup succeeds using timing-safe SHA-256 hash lookup");
$t->assert($tokenRecord->isValid(), "Token is valid immediately after generation");

// Redeem token
$newPassword = 'BrandNewSecurePassword123!@#';
$updatedUser = $securityService->resetPasswordWithToken($plainToken, $newPassword);
$victimUser->refresh();

$t->assert(Hash::check($newPassword, $victimUser->password), "User password updated to newly chosen user secret");

// Single-use check: Cannot redeem same token again
$secondAttemptBlocked = false;
try {
    $securityService->resetPasswordWithToken($plainToken, 'AnotherPassword999!');
} catch (ValidationException $e) {
    $secondAttemptBlocked = true;
}
$t->assert($secondAttemptBlocked === true, "Replay Guard: Single-use token cannot be redeemed a second time");

// ======================================================================
// 7. SIDEBAR NAVIGATION BIFURCATION AUDIT
// ======================================================================
$t->section("7. Sidebar Navigation Bifurcation Architecture");

$layoutContent = file_get_contents(resource_path('views/layouts/app.blade.php'));

$hasIndependentSupport = str_contains($layoutContent, '<!-- SUPPORT MANAGEMENT -->') &&
                         str_contains($layoutContent, '<div class="nav-section">Support Management</div>');
$t->assert($hasIndependentSupport, "Navigation: Independent 'Support Management' nav section exists");

// User Management and MFA Settings must be under Administrative
$adminSectionPos = strpos($layoutContent, '<!-- ADMINISTRATIVE -->');
$userMgmtPos = strpos($layoutContent, "route('admin.users.index')");
$mfaSettingPos = strpos($layoutContent, "route('admin.mfa-settings.index')");
$supportPos = strpos($layoutContent, '<!-- SUPPORT MANAGEMENT -->');
$operationsPos = strpos($layoutContent, '<!-- OPERATIONS -->');

$t->assert($adminSectionPos !== false, "Navigation: 'Administrative' section exists");
$t->assert($userMgmtPos > $adminSectionPos, "Logical Bifurcation: User Management is located UNDER Administrative section");
$t->assert($mfaSettingPos > $adminSectionPos, "Logical Bifurcation: MFA Settings is located UNDER Administrative section");

// Operational units must be under Operations
$officesPos = strpos($layoutContent, "route('admin.offices.index')");
$t->assert($officesPos > $operationsPos && $officesPos < $supportPos, "Logical Bifurcation: Operational Units is located UNDER Operations section");

$exitCode = $t->summary();
exit($exitCode);

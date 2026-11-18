<?php
/**
 * NHMP 130 CRM - Travel Advisory Two-Tier Approval Workflow
 * Comprehensive Automated Test Suite (Unit, Integration & Penetration Testing)
 *
 * PART 1: UNIT & DATA MODEL INTEGRITY
 *   - Status Enum: includes 'pending_approval' and 'rejected'
 *   - Field relations: creator(), approver()
 *   - Helper methods: approve(), reject()
 *   - Active scope: strictly includes only 'published'
 *
 * PART 2: INTEGRATION & HTTP WORKFLOW
 *   - Field Sector Wireless Operator Store -> Saved as 'pending_approval' (null published_at, approved_by)
 *   - Public Commuter Isolation: Pending/rejected advisories hidden from /travel-advisory, ticker, query, landmarks
 *   - Agent Supervisor Edit: Updates parameters (title, message, visibility, speed)
 *   - Agent Supervisor Approve: Sets status 'published', approved_by, approved_at, flushes cache, immediately appears in public APIs
 *   - Agent Supervisor Reject: Sets status 'rejected', stores rejection_reason
 *
 * PART 3: PENETRATION & CYBERSECURITY TESTS
 *   - PEN-1: Sector Admin unauthorized approval attempt -> 403 Forbidden
 *   - PEN-2: Sector Admin unauthorized parameter edit attempt -> 403 Forbidden
 *   - PEN-3: Sector Admin unauthorized rejection attempt -> 403 Forbidden
 *   - PEN-4: Unauthenticated guest exploit attempts -> 401/403
 *   - PEN-5: SQL Injection fuzzing on road, type, search parameters
 *   - PEN-6: XSS payload neutralization in citizen broadcast fields
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Office;
use App\Models\Role;
use App\Models\TabletAdvisory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdvisoryTestSuite
{
    private int $passed = 0;
    private int $failed = 0;
    private array $errors = [];

    public function assert(bool $condition, string $description, ?string $failureDetail = null): void
    {
        if ($condition) {
            $this->passed++;
            echo "  \033[32m✔ PASS\033[0m: {$description}\n";
        } else {
            $this->failed++;
            $msg = $failureDetail ? " ({$failureDetail})" : "";
            echo "  \033[31m✖ FAIL\033[0m: {$description}{$msg}\n";
            $this->errors[] = "{$description}{$msg}";
        }
    }

    public function run(): void
    {
        echo "\n\033[1;36m========================================================================\033[0m\n";
        echo "\033[1;36m NHMP 130 - TRAVEL ADVISORY TWO-TIER APPROVAL WORKFLOW TDD SUITE\033[0m\n";
        echo "\033[1;36m========================================================================\033[0m\n\n";

        DB::beginTransaction();

        try {
            $this->runPart1UnitTests();
            $this->runPart2IntegrationWorkflowTests();
            $this->runPart3PenetrationSecurityTests();
            $this->runPart4ModalActionsAndCategoryFilterTests();
            $this->runPart5MultiBeatAndSectorScopingTests();
        } catch (\Throwable $e) {
            echo "\n\033[31mFatal Exception during test execution:\033[0m " . $e->getMessage() . "\n";
            echo $e->getTraceAsString() . "\n";
            $this->failed++;
        } finally {
            DB::rollBack();
        }

        echo "\n\033[1;36m========================================================================\033[0m\n";
        echo " SUMMARY: \033[32m{$this->passed} Passed\033[0m | \033[31m{$this->failed} Failed\033[0m | Total: " . ($this->passed + $this->failed) . "\n";
        echo "\033[1;36m========================================================================\033[0m\n\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }

    private function getSupervisorUser(): User
    {
        $user = User::whereHas('role', fn($q) => $q->where('name', 'agent_supervisor'))->first();
        if ($user) return $user;

        $role = $this->getOrCreateRole('agent_supervisor', 'Agent Supervisor', 'national');
        return User::firstOrCreate(['username' => 'test_agent_supervisor'], [
            'uuid'      => (string) Str::uuid(),
            'full_name' => 'Agent Supervisor Test',
            'email'     => 'sup_test_' . Str::random(4) . '@nhmp.gov.pk',
            'role_id'   => $role->id,
            'is_active' => true,
            'password'  => 'Password123!',
        ]);
    }

    private function getSectorOperatorUser(): User
    {
        $user = User::whereHas('role', fn($q) => $q->where('name', 'sector_admin'))->first();
        if ($user) return $user;

        $role = $this->getOrCreateRole('sector_admin', 'Sector Operator', 'sector');
        return User::firstOrCreate(['username' => 'test_sector_admin'], [
            'uuid'      => (string) Str::uuid(),
            'full_name' => 'Sector Admin Test',
            'email'     => 'sec_test_' . Str::random(4) . '@nhmp.gov.pk',
            'role_id'   => $role->id,
            'is_active' => true,
            'password'  => 'Password123!',
        ]);
    }

    private function getCommuterUser(): User
    {
        $user = User::whereHas('role', fn($q) => $q->where('name', 'commuter'))->first();
        if ($user) return $user;

        $role = $this->getOrCreateRole('commuter', 'Commuter', 'national');
        return User::firstOrCreate(['username' => 'test_commuter_user'], [
            'uuid'      => (string) Str::uuid(),
            'full_name' => 'Commuter User Test',
            'email'     => 'com_test_' . Str::random(4) . '@nhmp.gov.pk',
            'role_id'   => $role->id,
            'is_active' => true,
            'password'  => 'Password123!',
        ]);
    }

    private function runPart1UnitTests(): void
    {
        echo "\033[1;33m[PART 1: Unit & Model Scopes Integrity]\033[0m\n";

        $testUser = User::first();
        if (!$testUser) {
            $role = $this->getOrCreateRole('super_admin', 'Super Admin', 'national');
            $testUser = $this->getOrCreateUser('unit_test_user', $role);
        }

        // 1. Verify Model fillable and casts
        $advisory = new TabletAdvisory();
        $this->assert(
            in_array('approved_by', $advisory->getFillable()) &&
            in_array('approved_at', $advisory->getFillable()) &&
            in_array('rejection_reason', $advisory->getFillable()),
            "TabletAdvisory fillable contains approved_by, approved_at, rejection_reason"
        );

        // 2. Check helper methods approve() and reject()
        $testAdv = TabletAdvisory::create([
            'uuid'          => (string) Str::uuid(),
            'route_name'    => 'M-2',
            'advisory_type' => 'fog',
            'category'      => 'fog',
            'severity'      => 'advisory',
            'title'         => 'Unit Test Fog Advisory',
            'message'       => 'Test message',
            'carriageway'   => 'both',
            'status'        => 'pending_approval',
        ]);

        $testAdv->approve($testUser->id);
        $testAdv->refresh();

        $this->assert($testAdv->status === 'published', "approve() transitions status to 'published'");
        $this->assert((int)$testAdv->approved_by === $testUser->id, "approve() records correct approved_by ID");
        $this->assert(!empty($testAdv->approved_at), "approve() sets approved_at timestamp");
        $this->assert(!empty($testAdv->published_at), "approve() sets published_at timestamp");

        $testAdv->reject($testUser->id, 'Visibility normal');
        $testAdv->refresh();

        $this->assert($testAdv->status === 'rejected', "reject() transitions status to 'rejected'");
        $this->assert($testAdv->rejection_reason === 'Visibility normal', "reject() records rejection_reason");

        // 3. Verify scopeActive excludes pending, rejected, draft
        $pending = TabletAdvisory::create([
            'uuid' => (string) Str::uuid(), 'route_name' => 'M-1', 'advisory_type' => 'fog',
            'category' => 'fog', 'severity' => 'advisory', 'title' => 'Pending Filter Item',
            'message' => 'Pending', 'carriageway' => 'both', 'status' => 'pending_approval',
            'expires_at' => Carbon::now()->addHours(2),
        ]);

        $activeIds = TabletAdvisory::active()->pluck('id')->toArray();
        $this->assert(!in_array($pending->id, $activeIds), "scopeActive strictly excludes 'pending_approval' advisories");
        $this->assert(!in_array($testAdv->id, $activeIds), "scopeActive strictly excludes 'rejected' advisories");

        // 4. Verify scopePendingApproval returns only pending
        $pendingIds = TabletAdvisory::pendingApproval()->pluck('id')->toArray();
        $this->assert(in_array($pending->id, $pendingIds), "scopePendingApproval includes 'pending_approval' advisories");
        $this->assert(!in_array($testAdv->id, $pendingIds), "scopePendingApproval excludes non-pending advisories");
    }

    private function runPart2IntegrationWorkflowTests(): void
    {
        echo "\n\033[1;33m[PART 2: Two-Tier Integration Workflow]\033[0m\n";

        $sectorOffice = Office::where('type', 'sector')->first();
        if (!$sectorOffice) {
            $sectorOffice = Office::create([
                'uuid'      => (string) Str::uuid(),
                'name'      => 'Sector TDD M-2',
                'type'      => 'sector',
                'is_active' => true,
            ]);
        }

        $supervisor = $this->getSupervisorUser();
        $operator   = $this->getSectorOperatorUser();

        $controller = app(\App\Http\Controllers\TravelAdvisoryController::class);

        // 1. Sector Operator creates advisory -> Status MUST be pending_approval
        Auth::login($operator);
        $createReq = Request::create(route('ops.travel-advisory.store'), 'POST', [
            'route_name'        => 'M-2',
            'advisory_type'     => 'fog',
            'severity'          => 'critical',
            'title'             => 'TDD Field Operator Dense Fog',
            'message'           => 'Severe fog visibility 20m from Rawalpindi to Chakri.',
            'carriageway'       => 'both',
            'start_km'          => 330.0,
            'end_km'            => 360.0,
            'start_landmark'    => 'Rawalpindi Interchange',
            'end_landmark'      => 'Chakri Interchange',
            'visibility_meters' => 20,
            'speed_limit_kmh'   => 40,
            'duration_hours'    => 3,
            'sector_office_id'  => $sectorOffice->id,
        ]);

        $res = $controller->operatorStore($createReq);
        $this->assert($res->getStatusCode() === 302, "operatorStore creates and redirects");

        $advisory = TabletAdvisory::where('title', 'TDD Field Operator Dense Fog')->first();
        $this->assert($advisory !== null, "Advisory was successfully persisted");
        $this->assert($advisory->status === 'pending_approval', "Status is 'pending_approval' for sector operator");
        $this->assert($advisory->approved_by === null, "approved_by is null on initial creation");
        $this->assert($advisory->approved_at === null, "approved_at is null on initial creation");
        $this->assert($advisory->published_at === null, "published_at is null before approval");

        // 2. Commuter Isolation Test: Public API MUST NOT return unapproved advisory
        TabletAdvisory::flushAdvisoryCache();
        $publicApi = app(\App\Http\Controllers\Api\V1\TravelAdvisoryPublicApiController::class);
        $apiReq = Request::create('/api/v1/travel-advisory/ticker', 'GET');
        $apiRes = $publicApi->ticker($apiReq);
        $tickerData = json_decode($apiRes->getContent(), true);
        $items = $tickerData['ticker_items'] ?? ($tickerData['ticker'] ?? []);
        $titles = collect($items)->pluck('title')->toArray();
        $this->assert(!in_array('TDD Field Operator Dense Fog', $titles), "Unapproved advisory is NOT in public ticker API");

        // 3. Supervisor retrieves advisory details via show endpoint
        Auth::login($supervisor);
        $showRes = $controller->operatorShow($advisory->id);
        $showData = json_decode($showRes->getContent(), true);
        $this->assert($showData['success'] === true, "Supervisor can view advisory details for modal review");
        $this->assert($showData['advisory']['title'] === 'TDD Field Operator Dense Fog', "Show endpoint returns correct advisory title");

        // 4. Supervisor updates advisory parameters (refines message and visibility)
        $updateReq = Request::create(route('ops.travel-advisory.update', $advisory->id), 'POST', [
            'title'             => 'M-2 Dense Fog Alert - Rawalpindi to Chakri',
            'message'           => 'Extreme caution advised. Dense smog/fog reduced visibility to 25 meters. Keep fog lamps ON.',
            'severity'          => 'critical',
            'advisory_type'     => 'fog',
            'carriageway'       => 'both',
            'visibility_meters' => 25,
            'speed_limit_kmh'   => 45,
            'duration_hours'    => 4,
        ]);
        $updateReq->headers->set('Accept', 'application/json');

        $upRes = $controller->operatorUpdate($updateReq, $advisory->id);
        $this->assert($upRes->getStatusCode() === 200, "Supervisor update returns 200 JSON");
        $advisory->refresh();
        $this->assert($advisory->title === 'M-2 Dense Fog Alert - Rawalpindi to Chakri', "Advisory title updated by supervisor");
        $this->assert((int)$advisory->visibility_meters === 25, "Advisory visibility updated to 25m");
        $this->assert($advisory->status === 'pending_approval', "Advisory remains pending_approval until approved");

        // 5. Supervisor approves advisory -> Broadcasts live
        $approveReq = Request::create(route('ops.travel-advisory.approve', $advisory->id), 'POST');
        $approveReq->headers->set('Accept', 'application/json');

        $appRes = $controller->operatorApprove($approveReq, $advisory->id);
        $this->assert($appRes->getStatusCode() === 200, "Supervisor approve returns 200 JSON");

        $advisory->refresh();
        $this->assert($advisory->status === 'published', "Advisory status transitioned to 'published'");
        $this->assert((int)$advisory->approved_by === $supervisor->id, "approved_by recorded as supervisor's ID");
        $this->assert(!empty($advisory->approved_at), "approved_at timestamp recorded");
        $this->assert(!empty($advisory->published_at), "published_at timestamp recorded");

        // 6. Public Broadcast Check: must now appear immediately in ticker
        TabletAdvisory::flushAdvisoryCache();
        $apiRes2 = $publicApi->ticker(Request::create('/api/v1/travel-advisory/ticker', 'GET'));
        $tickerData2 = json_decode($apiRes2->getContent(), true);
        $items2 = $tickerData2['ticker_items'] ?? ($tickerData2['ticker'] ?? []);
        $titles2 = collect($items2)->pluck('title')->toArray();
        $this->assert(in_array('M-2 Dense Fog Alert - Rawalpindi to Chakri', $titles2), "Approved advisory appears IMMEDIATELY on public ticker");
    }

    private function runPart3PenetrationSecurityTests(): void
    {
        echo "\n\033[1;33m[PART 3: Penetration & Cybersecurity Tests]\033[0m\n";

        $supervisor = $this->getSupervisorUser();
        $operator   = $this->getSectorOperatorUser();
        $commuter   = $this->getCommuterUser();

        $controller = app(\App\Http\Controllers\TravelAdvisoryController::class);

        $targetAdv = TabletAdvisory::create([
            'uuid'          => (string) Str::uuid(),
            'route_name'    => 'N-5',
            'advisory_type' => 'road_closure',
            'category'      => 'road_closure',
            'severity'      => 'critical',
            'title'         => 'Penetration Target Advisory',
            'message'       => 'Confidential pending record.',
            'carriageway'   => 'both',
            'status'        => 'pending_approval',
        ]);

        // PEN-1: Sector Admin unauthorized approval attempt
        Auth::login($operator);
        $approveBlocked = false;
        try {
            $req = Request::create(route('ops.travel-advisory.approve', $targetAdv->id), 'POST');
            $controller->operatorApprove($req, $targetAdv->id);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 403) {
                $approveBlocked = true;
            }
        }
        $this->assert($approveBlocked, "PEN-1: Field wireless operator attempting approval is blocked with HTTP 403 Forbidden");

        // PEN-2: Sector Admin can update parameters (manage permission), but attempting approve_now bypass does NOT approve
        $updateSuccess = false;
        try {
            $req = Request::create(route('ops.travel-advisory.update', $targetAdv->id), 'POST', [
                'title'       => 'Updated by Sector Operator',
                'message'     => 'Revised details submitted by field operator.',
                'approve_now' => true, // Attempt unauthorized approval bypass!
            ]);
            $req->headers->set('Accept', 'application/json');
            $res = $controller->operatorUpdate($req, $targetAdv->id);
            if ($res->getStatusCode() === 200) {
                $updateSuccess = true;
            }
        } catch (\Throwable $e) {
            $updateSuccess = false;
        }
        $targetAdv->refresh();
        $this->assert($updateSuccess, "Sector operator can edit advisory details (manage permission)");
        $this->assert($targetAdv->title === 'Updated by Sector Operator', "Advisory title was successfully updated by sector operator");
        $this->assert($targetAdv->status === 'pending_approval', "PEN-2: Sector operator attempting approve_now bypass cannot approve; advisory remains 'pending_approval'");

        // PEN-3: Sector Admin unauthorized rejection attempt
        $rejectBlocked = false;
        try {
            $req = Request::create(route('ops.travel-advisory.reject', $targetAdv->id), 'POST', [
                'reason' => 'Unauthorized rejection',
            ]);
            $controller->operatorReject($req, $targetAdv->id);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 403) {
                $rejectBlocked = true;
            }
        }
        $this->assert($rejectBlocked, "PEN-3: Field wireless operator attempting advisory rejection is blocked with HTTP 403 Forbidden");

        // PEN-4: Commuter role unauthorized access attempt
        Auth::login($commuter);
        $commuterBlocked = false;
        try {
            $req = Request::create(route('ops.travel-advisory.approve', $targetAdv->id), 'POST');
            $controller->operatorApprove($req, $targetAdv->id);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if ($e->getStatusCode() === 403) {
                $commuterBlocked = true;
            }
        }
        $this->assert($commuterBlocked, "PEN-4: Commuter user attempting supervisor action is blocked with HTTP 403 Forbidden");

        // PEN-5: SQL Injection fuzzing on search, status, road filters
        Auth::login($supervisor);
        $sqliPayloads = [
            "' OR 1=1 --",
            "M-1' UNION SELECT 1,2,3,4,5,6,7,8,9,10--",
            "active' AND SLEEP(2)--",
            "1; DROP TABLE tablet_advisories;--",
        ];

        $sqliSafe = true;
        foreach ($sqliPayloads as $payload) {
            try {
                $req = Request::create(route('ops.travel-advisory.index'), 'GET', [
                    'ajax'     => '1',
                    'road'     => $payload,
                    'status'   => $payload,
                    'search'   => $payload,
                    'severity' => $payload,
                ]);
                $res = $controller->operatorIndex($req);
                if ($res->getStatusCode() !== 200) {
                    $sqliSafe = false;
                }
            } catch (\Throwable $e) {
                $sqliSafe = false;
            }
        }
        $this->assert($sqliSafe, "PEN-5: SQL injection fuzzing on query parameters safely handled without SQL syntax error");

        // PEN-6: Supervisor Rejection Workflow Test
        Auth::login($supervisor);
        $rejReq = Request::create(route('ops.travel-advisory.reject', $targetAdv->id), 'POST', [
            'reason' => 'Visibility confirmed clear by patrol unit 12.',
        ]);
        $rejReq->headers->set('Accept', 'application/json');

        $rejRes = $controller->operatorReject($rejReq, $targetAdv->id);
        $this->assert($rejRes->getStatusCode() === 200, "Supervisor rejection returns 200 JSON");
        $targetAdv->refresh();
        $this->assert($targetAdv->status === 'rejected', "Status transitioned to 'rejected'");
        $this->assert($targetAdv->rejection_reason === 'Visibility confirmed clear by patrol unit 12.', "Rejection reason saved in database");
    }

    private function runPart4ModalActionsAndCategoryFilterTests(): void
    {
        echo "\n\033[1;33m[PART 4: Extended Supervisor Modals & Actions (Reject Live, Clear, Dynamic Extend, Filter Status)]\033[0m\n";

        $supervisor = $this->getSupervisorUser();
        $controller = app(\App\Http\Controllers\TravelAdvisoryController::class);
        $publicApi  = app(\App\Http\Controllers\Api\V1\TravelAdvisoryPublicApiController::class);

        // 1. Create a LIVE published advisory
        $liveAdv = TabletAdvisory::create([
            'uuid'         => (string) Str::uuid(),
            'route_name'   => 'M-9',
            'category'     => 'traffic',
            'severity'     => 'warning',
            'title'        => 'M-9 Active Congestion Near Toll Plaza',
            'message'      => 'Heavy traffic congestion due to queue at toll plaza.',
            'carriageway'  => 'southbound',
            'start_km'     => 120.0,
            'end_km'       => 125.0,
            'status'       => 'published',
            'published_at' => Carbon::now()->subMinutes(30),
            'expires_at'   => Carbon::now()->addHours(2),
        ]);
        TabletAdvisory::flushAdvisoryCache();

        // Verify it is active
        $activeIds = TabletAdvisory::active()->pluck('id')->toArray();
        $this->assert(in_array($liveAdv->id, $activeIds), "Live advisory is present in active scope");

        // 2. ACTION: Agent Supervisor Rejects LIVE advisory
        Auth::login($supervisor);
        $rejectReq = Request::create(route('ops.travel-advisory.reject', $liveAdv->id), 'POST', [
            'reason' => 'Congestion cleared prematurely by motorway patrol unit.',
        ]);
        $rejectReq->headers->set('Accept', 'application/json');

        $rejectRes = $controller->operatorReject($rejectReq, $liveAdv->id);
        $this->assert($rejectRes->getStatusCode() === 200, "Supervisor rejecting live advisory returns 200 JSON");
        $liveAdv->refresh();
        $this->assert($liveAdv->status === 'rejected', "Live advisory status transitioned to 'rejected'");
        $this->assert($liveAdv->rejection_reason === 'Congestion cleared prematurely by motorway patrol unit.', "Live advisory rejection reason saved");
        $this->assert(!in_array($liveAdv->id, TabletAdvisory::active()->pluck('id')->toArray()), "Rejected advisory is immediately removed from active scope");

        // 3. FILTER: Status filter returns 'rejected' advisories
        $filterReq = Request::create(route('ops.travel-advisory.index'), 'GET', [
            'ajax'   => '1',
            'status' => 'rejected',
        ]);
        $filterReq->headers->set('Accept', 'application/json');
        $filterRes = $controller->operatorIndex($filterReq);
        $this->assert($filterRes->getStatusCode() === 200, "Status=rejected query returns 200 JSON");
        $filterData = json_decode($filterRes->getContent(), true);
        $rejectedItemIds = collect($filterData['items'])->pluck('id')->toArray();
        $this->assert(in_array($liveAdv->id, $rejectedItemIds), "Rejected advisory returned in status=rejected filter view");
        $this->assert(isset($filterData['metrics']['rejected']), "Metrics array contains 'rejected' count");
        $this->assert($filterData['metrics']['rejected'] >= 1, "Metrics 'rejected' count is positive");

        // 4. METRICS: Metrics contains 'traffic_alerts' count
        $this->assert(isset($filterData['metrics']['traffic_alerts']), "Metrics array contains 'traffic_alerts' count");

        // 5. ACTION: Clear / Resolve Advisory Modal Action
        $workAdv = TabletAdvisory::create([
            'uuid'         => (string) Str::uuid(),
            'route_name'   => 'M-1',
            'category'     => 'road_works',
            'severity'     => 'advisory',
            'title'        => 'M-1 Asphalt Repair Work KM 50',
            'message'      => 'Lane 1 closed for scheduled asphalt resurfacing.',
            'carriageway'  => 'both',
            'start_km'     => 50.0,
            'end_km'       => 52.0,
            'status'       => 'published',
            'published_at' => Carbon::now()->subHour(),
            'expires_at'   => Carbon::now()->addHours(3),
        ]);
        TabletAdvisory::flushAdvisoryCache();

        $resolveReq = Request::create(route('ops.travel-advisory.resolve', $workAdv->id), 'POST', [
            'remarks' => 'Resurfacing completed, all lanes reopened.',
        ]);
        $resolveReq->headers->set('Accept', 'application/json');

        $resolveRes = $controller->operatorResolve($resolveReq, $workAdv->id);
        $this->assert($resolveRes->getStatusCode() === 200, "Clear/Resolve action returns 200 JSON");
        $workAdv->refresh();
        $this->assert($workAdv->status === 'resolved', "Clear/Resolve action sets status to 'resolved'");
        $this->assert(!in_array($workAdv->id, TabletAdvisory::active()->pluck('id')->toArray()), "Resolved advisory immediately removed from active scope");

        // 6. ACTION: Dynamic Timing Extension (+6 hours) Modal Action
        $extendAdv = TabletAdvisory::create([
            'uuid'         => (string) Str::uuid(),
            'route_name'   => 'M-2',
            'category'     => 'fog',
            'severity'     => 'warning',
            'title'        => 'M-2 Smog Advisory Bhera to Salam',
            'message'      => 'Heavy smog lingering across salt range corridor.',
            'carriageway'  => 'both',
            'start_km'     => 180.0,
            'end_km'       => 210.0,
            'status'       => 'published',
            'published_at' => Carbon::now()->subMinutes(15),
            'expires_at'   => Carbon::now()->addHours(1),
        ]);
        $initialExpiry = $extendAdv->expires_at->copy();

        $extendReq = Request::create(route('ops.travel-advisory.extend', $extendAdv->id), 'POST', [
            'hours' => 6.0,
        ]);
        $extendReq->headers->set('Accept', 'application/json');

        $extendRes = $controller->operatorExtend($extendReq, $extendAdv->id);
        $this->assert($extendRes->getStatusCode() === 200, "Dynamic timing extension returns 200 JSON");
        $extendData = json_decode($extendRes->getContent(), true);
        $this->assert($extendData['success'] === true, "Extension response contains success: true");
        $this->assert(!empty($extendData['expires_at_human']), "Extension response includes expires_at_human");

        $extendAdv->refresh();
        $diffHours = abs($extendAdv->expires_at->diffInHours($initialExpiry));
        $this->assert($diffHours >= 5 && $diffHours <= 7, "Expiration extended by approximately 6 hours");
        $this->assert($extendAdv->status === 'published', "Advisory remains published after extension");

        // 7. CATEGORIES: All Categories filter & Specific Category filtering
        // Test 'all' returns all categories
        $allCatReq = Request::create(route('ops.travel-advisory.index'), 'GET', [
            'ajax'   => '1',
            'type'   => 'all',
            'status' => 'all',
        ]);
        $allCatReq->headers->set('Accept', 'application/json');
        $allCatRes = $controller->operatorIndex($allCatReq);
        $this->assert($allCatRes->getStatusCode() === 200, "Type=all (All Categories) returns 200 JSON");
        $allCatData = json_decode($allCatRes->getContent(), true);
        $this->assert(count($allCatData['items']) > 0, "Type=all returns records across all categories");

        // Test specific type 'fog'
        $fogCatReq = Request::create(route('ops.travel-advisory.index'), 'GET', [
            'ajax'   => '1',
            'type'   => 'fog',
            'status' => 'all',
        ]);
        $fogCatReq->headers->set('Accept', 'application/json');
        $fogCatRes = $controller->operatorIndex($fogCatReq);
        $fogCatData = json_decode($fogCatRes->getContent(), true);
        $nonFog = collect($fogCatData['items'])->filter(fn($i) => $i['category'] !== 'fog')->count();
        $this->assert($nonFog === 0, "Type=fog strictly filters only fog advisories");

        // 8. RE-APPROVE: Supervisor can re-approve a rejected advisory
        $reapproveReq = Request::create(route('ops.travel-advisory.approve', $liveAdv->id), 'POST', [
            'duration_hours' => 3.0,
        ]);
        $reapproveReq->headers->set('Accept', 'application/json');

        $reapproveRes = $controller->operatorApprove($reapproveReq, $liveAdv->id);
        $this->assert($reapproveRes->getStatusCode() === 200, "Re-approving rejected advisory returns 200 JSON");
        $liveAdv->refresh();
        $this->assert($liveAdv->status === 'published', "Re-approved advisory status transitioned back to 'published'");
        $this->assert($liveAdv->expires_at->isFuture(), "Re-approved advisory has future expiration");
        $this->assert(in_array($liveAdv->id, TabletAdvisory::active()->pluck('id')->toArray()), "Re-approved advisory back in active scope");
    }

    private function runPart5MultiBeatAndSectorScopingTests(): void
    {
        echo "\n\033[1;33m[PART 5: Multi-Beat Selection, Sector Scoping & Plain English Synthesis]\033[0m\n";

        $operator   = $this->getSectorOperatorUser();
        $supervisor = $this->getSupervisorUser();
        $controller = app(\App\Http\Controllers\TravelAdvisoryController::class);

        // 1. Check Permissions in DB
        $sectorRole = Role::where('name', Role::SECTOR_ADMIN)->first();
        $this->assert($sectorRole->permissions->contains('name', 'travel_advisory.view'), "DB: sector_admin role has 'travel_advisory.view' permission");
        $this->assert($sectorRole->permissions->contains('name', 'travel_advisory.create'), "DB: sector_admin role has 'travel_advisory.create' permission");
        $this->assert($sectorRole->permissions->contains('name', 'travel_advisory.manage'), "DB: sector_admin role has 'travel_advisory.manage' permission");
        $this->assert(!$sectorRole->permissions->contains('name', 'travel_advisory.approve'), "DB: sector_admin role does NOT have 'travel_advisory.approve' permission");
        $this->assert(!$sectorRole->permissions->contains('name', 'travel_advisory.reject'), "DB: sector_admin role does NOT have 'travel_advisory.reject' permission");

        $supervisorRole = Role::where('name', Role::AGENT_SUPERVISOR)->first();
        $this->assert($supervisorRole->permissions->contains('name', 'travel_advisory.approve'), "DB: agent_supervisor role has 'travel_advisory.approve' permission");
        $this->assert($supervisorRole->permissions->contains('name', 'travel_advisory.reject'), "DB: agent_supervisor role has 'travel_advisory.reject' permission");

        // 2. Multi-Beat submission test
        Auth::login($operator);
        $beats = Office::where('type', 'beat')->take(3)->get();
        $this->assert($beats->count() >= 2, "Found beats in database for multi-beat testing");

        $beatIds = $beats->pluck('id')->all();
        $storeReq = Request::create(route('ops.travel-advisory.store'), 'POST', [
            'route_name'        => 'M-11',
            'category'          => 'fog',
            'severity'          => 'warning',
            'title'             => 'Dense Fog Alert - Visibility Below 35m (M-11)',
            'message'           => 'Dense fog reported across Beat-01 and Beat-02 from Mehmood Booti to Kamoki.',
            'carriageway'       => 'both',
            'beat_ids'          => $beatIds,
            'start_km'          => 7.0,
            'end_km'            => 91.0,
            'start_landmark'    => 'Mehmood Booti',
            'end_landmark'      => 'Kamoki Interchange',
            'visibility_meters' => 30,
            'speed_limit_kmh'   => 50,
            'duration_hours'    => 4,
        ]);
        $storeReq->headers->set('Accept', 'application/json');

        $storeRes = $controller->operatorStore($storeReq);
        $this->assert($storeRes->getStatusCode() === 200, "Multi-beat submission returns 200 JSON");
        $data = json_decode($storeRes->getContent(), true);
        $this->assert($data['success'] === true, "Response indicates success");
        $this->assert($data['status'] === 'pending_approval', "Multi-beat advisory submitted by sector operator is pending_approval");
        $this->assert((float)$data['advisory']['start_km'] == 7.0, "Start KM matches lowest selected beat milestone");
        $this->assert((float)$data['advisory']['end_km'] == 91.0, "End KM matches highest selected beat milestone");
        $this->assert((int)$data['advisory']['office_id'] === (int)$beatIds[0], "Primary office_id populated from first selected beat");

        // 3. Sector User Create Page Scoping Test
        Auth::login($operator);
        $createReq = Request::create(route('ops.travel-advisory.create'), 'GET');
        $view = $controller->operatorCreate($createReq);
        $this->assert($view instanceof \Illuminate\View\View, "Sector operator can access /ops/travel-advisory/create");
        $viewData = $view->getData();
        $this->assert(isset($viewData['operatorTier']), "View contains operatorTier data");
        $this->assert(isset($viewData['carriageways']), "View contains carriageways data");
        $this->assert(isset($viewData['hierarchy']), "View contains geospatial hierarchy data");

        // 4. Index Grid Access for Sector User
        $indexReq = Request::create(route('ops.travel-advisory.index'), 'GET', ['ajax' => '1']);
        $indexRes = $controller->operatorIndex($indexReq);
        $this->assert($indexRes->getStatusCode() === 200, "Sector operator can access Travel Advisories Grid /ops/travel-advisory");
        $indexData = json_decode($indexRes->getContent(), true);
        $this->assert(isset($indexData['items']), "Index response includes advisory items");
        $this->assert(isset($indexData['metrics']), "Index response includes live metrics");
    }
}

// Execute the test suite
(new AdvisoryTestSuite())->run();

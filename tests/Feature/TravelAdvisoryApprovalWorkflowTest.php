<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Role;
use App\Models\TabletAdvisory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class TravelAdvisoryApprovalWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $supervisorUser;
    protected User $sectorAdminUser;
    protected User $commuterUser;
    protected Office $testSector;
    protected Office $testBeat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);

        // 1. Setup Sector and Beat Offices
        $zone = Office::firstOrCreate(
            ['name' => 'Test North Zone'],
            ['type' => 'zone', 'is_active' => true]
        );

        $this->testSector = Office::firstOrCreate(
            ['name' => 'Sector M-2 North'],
            ['type' => 'sector', 'parent_id' => $zone->id, 'is_active' => true]
        );

        $this->testBeat = Office::firstOrCreate(
            ['name' => 'Beat 01 M-2'],
            ['type' => 'beat', 'parent_id' => $this->testSector->id, 'is_active' => true]
        );

        // 2. Setup Agent Supervisor User
        $supervisorRole = Role::firstOrCreate(
            ['name' => 'agent_supervisor'],
            ['display_name' => 'Agent Supervisor', 'scope_level' => 'national', 'is_active' => true]
        );

        $this->supervisorUser = User::firstOrCreate(
            ['username' => 'test_agent_supervisor'],
            [
                'uuid' => (string) Str::uuid(),
                'full_name' => 'Call Center Agent Supervisor',
                'email' => 'supervisor_test_' . Str::random(5) . '@nhmp.gov.pk',
                'role_id' => $supervisorRole->id,
                'is_active' => true,
                'password' => bcrypt('Password123!'),
            ]
        );

        // 3. Setup Sector Wireless Operator (sector_admin)
        $sectorRole = Role::firstOrCreate(
            ['name' => 'sector_admin'],
            ['display_name' => 'Sector Admin / Wireless Operator', 'scope_level' => 'sector', 'is_active' => true]
        );

        $this->sectorAdminUser = User::firstOrCreate(
            ['username' => 'test_sector_operator'],
            [
                'uuid' => (string) Str::uuid(),
                'full_name' => 'Sector Wireless Operator',
                'email' => 'operator_test_' . Str::random(5) . '@nhmp.gov.pk',
                'role_id' => $sectorRole->id,
                'office_id' => $this->testSector->id,
                'is_active' => true,
                'password' => bcrypt('Password123!'),
            ]
        );

        // 4. Setup Commuter User
        $commuterRole = Role::firstOrCreate(
            ['name' => 'commuter'],
            ['display_name' => 'Commuter', 'scope_level' => 'national', 'is_active' => true]
        );

        $this->commuterUser = User::firstOrCreate(
            ['username' => 'test_commuter_citizen'],
            [
                'uuid' => (string) Str::uuid(),
                'full_name' => 'Test Citizen Commuter',
                'email' => 'citizen_test_' . Str::random(5) . '@citizen.pk',
                'role_id' => $commuterRole->id,
                'is_active' => true,
                'password' => bcrypt('Password123!'),
            ]
        );

        // Clear advisory cache before each test
        TabletAdvisory::flushAdvisoryCache();
    }

    /**
     * Test 1: Field sector wireless operator creates an advisory.
     * It MUST be saved with status 'pending_approval' and no approval timestamp.
     */
    public function test_sector_wireless_operator_creation_results_in_pending_approval(): void
    {
        $payload = [
            'route_name'        => 'M-2',
            'advisory_type'     => 'fog',
            'severity'          => 'advisory',
            'title'             => 'M-2 Fog Advisory Kot Abdul Malik',
            'message'           => 'Dense fog reported by patrol units between KM 10 and KM 25.',
            'carriageway'       => 'both',
            'start_km'          => 10.0,
            'end_km'            => 25.0,
            'start_landmark'    => 'Kot Abdul Malik Interchange',
            'end_landmark'      => 'Sheikhupura Interchange',
            'visibility_meters' => 40,
            'speed_limit_kmh'   => 50,
            'duration_hours'    => 4,
            'sector_office_id'  => $this->testSector->id,
        ];

        $response = $this->actingAs($this->sectorAdminUser)
            ->post(route('ops.travel-advisory.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('ops.travel-advisory.create'));

        // Verify record in database
        $advisory = TabletAdvisory::where('title', 'M-2 Fog Advisory Kot Abdul Malik')->first();
        $this->assertNotNull($advisory, 'Advisory record should exist in the database');
        $this->assertEquals('pending_approval', $advisory->status, 'Status must be pending_approval for field operator submissions');
        $this->assertNull($advisory->approved_by, 'approved_by must be null before supervisor review');
        $this->assertNull($advisory->approved_at, 'approved_at must be null before supervisor review');
        $this->assertNull($advisory->published_at, 'published_at must be null before approval');
    }

    /**
     * Test 2: Unapproved advisories (pending_approval, rejected, draft) MUST NOT
     * be returned by public commuter endpoints.
     */
    public function test_unapproved_advisories_are_isolated_from_public_commuter_apis(): void
    {
        // Create an unapproved advisory
        $pendingAdvisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'route_name'        => 'M-1',
            'advisory_type'     => 'fog',
            'category'          => 'fog',
            'severity'          => 'critical',
            'title'             => 'CONFIDENTIAL DRAFT - Heavy Fog M-1',
            'message'           => 'Unverified field log - not ready for public release.',
            'carriageway'       => 'both',
            'start_km'          => 350.0,
            'end_km'            => 380.0,
            'status'            => 'pending_approval',
            'created_by'        => $this->sectorAdminUser->id,
            'office_id'         => $this->testSector->id,
            'expires_at'        => Carbon::now()->addHours(3),
        ]);

        // 1. Check Public Ticker API
        $tickerRes = $this->getJson('/api/v1/travel-advisory/ticker');
        $tickerRes->assertOk();
        $tickerTitles = collect($tickerRes->json('ticker_items') ?? [])->pluck('title')->toArray();
        $this->assertNotContains('CONFIDENTIAL DRAFT - Heavy Fog M-1', $tickerTitles, 'Pending advisory must not leak into public ticker');

        // 2. Check Public Query API
        $queryRes = $this->getJson('/api/v1/travel-advisory/query?road=M-1');
        $queryRes->assertOk();
        $queryAdvisories = collect($queryRes->json('advisories') ?? [])->pluck('title')->toArray();
        $this->assertNotContains('CONFIDENTIAL DRAFT - Heavy Fog M-1', $queryAdvisories, 'Pending advisory must not leak into public query endpoint');

        // 3. The public web portal page was removed; the public APIs above are the only public surface.
        $this->get('/travel-advisory')->assertNotFound();
    }

    /**
     * Test 3: Agent Supervisor can update and refine advisory parameters via AJAX modal endpoint.
     */
    public function test_agent_supervisor_can_update_advisory_parameters(): void
    {
        $advisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'route_name'        => 'M-3',
            'advisory_type'     => 'fog',
            'category'          => 'fog',
            'severity'          => 'advisory',
            'title'             => 'M-3 Fog Raw Log',
            'message'           => 'Draft message from field.',
            'carriageway'       => 'both',
            'start_km'          => 120.0,
            'end_km'            => 150.0,
            'status'            => 'pending_approval',
            'created_by'        => $this->sectorAdminUser->id,
            'office_id'         => $this->testSector->id,
            'expires_at'        => Carbon::now()->addHours(2),
        ]);

        $updatePayload = [
            'title'             => 'M-3 Dense Fog Advisory - Darkhana to Samundri',
            'message'           => 'Visibility down to 30m. Commuters are advised to travel with fog lights and maintain safe distance.',
            'severity'          => 'warning',
            'advisory_type'     => 'fog',
            'visibility_meters' => 30,
            'speed_limit_kmh'   => 40,
            'duration_hours'    => 5,
        ];

        $response = $this->actingAs($this->supervisorUser)
            ->postJson(route('ops.travel-advisory.update', $advisory->id), $updatePayload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $advisory->refresh();
        $this->assertEquals('M-3 Dense Fog Advisory - Darkhana to Samundri', $advisory->title);
        $this->assertEquals(30, $advisory->visibility_meters);
        $this->assertEquals(40, $advisory->speed_limit_kmh);
        $this->assertEquals('warning', $advisory->severity);
        $this->assertEquals('pending_approval', $advisory->status, 'Advisory remains pending until approved');
    }

    /**
     * Test 4: Agent Supervisor approves advisory -> status becomes published,
     * approver recorded, and immediately broadcasts to public ticker and APIs.
     */
    public function test_agent_supervisor_approval_publishes_live_to_public_apis(): void
    {
        $advisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'route_name'        => 'M-5',
            'advisory_type'     => 'fog',
            'category'          => 'fog',
            'severity'          => 'critical',
            'title'             => 'M-5 Multan-Sukkur Zero Visibility Closure',
            'message'           => 'Motorway M-5 closed from Multan to Zahirpir due to zero visibility fog.',
            'carriageway'       => 'both',
            'start_km'          => 770.0,
            'end_km'            => 890.0,
            'status'            => 'pending_approval',
            'created_by'        => $this->sectorAdminUser->id,
            'office_id'         => $this->testSector->id,
            'expires_at'        => Carbon::now()->addHours(4),
        ]);

        // Supervisor approves the advisory
        $response = $this->actingAs($this->supervisorUser)
            ->postJson(route('ops.travel-advisory.approve', $advisory->id));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status'  => 'published',
        ]);

        $advisory->refresh();
        $this->assertEquals('published', $advisory->status);
        $this->assertEquals($this->supervisorUser->id, $advisory->approved_by);
        $this->assertNotNull($advisory->approved_at);
        $this->assertNotNull($advisory->published_at);

        // Immediate Public API Verification: must now appear in ticker and query
        $tickerRes = $this->getJson('/api/v1/travel-advisory/ticker');
        $tickerRes->assertOk();
        $tickerTitles = collect($tickerRes->json('ticker_items') ?? [])->pluck('title')->toArray();
        $this->assertContains('M-5 Multan-Sukkur Zero Visibility Closure', $tickerTitles, 'Approved advisory must immediately broadcast on ticker');
    }

    /**
     * Test 5 (PEN TEST): Field wireless operator or unauthorized role attempting to approve
     * must be strictly denied with HTTP 403 Forbidden.
     */
    public function test_pen_test_sector_operator_cannot_approve_advisory(): void
    {
        $advisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'route_name'        => 'N-5',
            'advisory_type'     => 'road_closure',
            'category'          => 'road_closure',
            'severity'          => 'critical',
            'title'             => 'N-5 Sector Operator Self-Approve Attempt',
            'message'           => 'Pen-test payload trying to bypass approval hierarchy.',
            'carriageway'       => 'both',
            'start_km'          => 100.0,
            'end_km'            => 105.0,
            'status'            => 'pending_approval',
            'created_by'        => $this->sectorAdminUser->id,
            'office_id'         => $this->testSector->id,
        ]);

        // Attempt approve as Sector Admin
        $response = $this->actingAs($this->sectorAdminUser)
            ->postJson(route('ops.travel-advisory.approve', $advisory->id));

        $response->assertStatus(403);

        $advisory->refresh();
        $this->assertEquals('pending_approval', $advisory->status, 'Status must not change on 403 exploit attempt');
        $this->assertNull($advisory->approved_by);
    }

    /**
     * Test 6: Sector wireless operator can update/edit parameters (manage permission),
     * but attempting approve_now bypass strictly leaves status as pending_approval.
     */
    public function test_sector_operator_can_edit_advisory_parameters_but_cannot_bypass_approval(): void
    {
        $advisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'route_name'        => 'M-2',
            'advisory_type'     => 'fog',
            'category'          => 'fog',
            'severity'          => 'advisory',
            'title'             => 'Original M-2 Entry',
            'message'           => 'Legitimate entry.',
            'carriageway'       => 'both',
            'start_km'          => 50.0,
            'end_km'            => 70.0,
            'status'            => 'pending_approval',
            'created_by'        => $this->sectorAdminUser->id,
            'office_id'         => $this->testSector->id,
        ]);

        $response = $this->actingAs($this->sectorAdminUser)
            ->postJson(route('ops.travel-advisory.update', $advisory->id), [
                'title'       => 'Updated by Operator',
                'message'     => 'Revised details by operator.',
                'approve_now' => true,
            ]);

        $response->assertOk();

        $advisory->refresh();
        $this->assertEquals('Updated by Operator', $advisory->title, 'Operator can edit details');
        $this->assertEquals('pending_approval', $advisory->status, 'Operator cannot approve; status remains pending_approval');
    }

    /**
     * Test 6b (PEN TEST): Field wireless operator attempting rejection must be strictly
     * denied with HTTP 403 Forbidden.
     */
    public function test_pen_test_sector_operator_cannot_reject_advisory(): void
    {
        $advisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'route_name'        => 'M-2',
            'advisory_type'     => 'fog',
            'category'          => 'fog',
            'severity'          => 'advisory',
            'title'             => 'Original M-2 Entry',
            'message'           => 'Legitimate entry.',
            'carriageway'       => 'both',
            'status'            => 'pending_approval',
            'created_by'        => $this->sectorAdminUser->id,
            'office_id'         => $this->testSector->id,
        ]);

        $response = $this->actingAs($this->sectorAdminUser)
            ->postJson(route('ops.travel-advisory.reject', $advisory->id), [
                'reason' => 'Unauthorized rejection attempt',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test 7 (PEN TEST): Unauthenticated guest attempting approve/update endpoints
     * must receive HTTP 401 or redirect to login.
     */
    public function test_pen_test_unauthenticated_requests_are_rejected(): void
    {
        $advisory = TabletAdvisory::create([
            'uuid'          => (string) Str::uuid(),
            'route_name'    => 'M-4',
            'advisory_type' => 'fog',
            'category'      => 'fog',
            'severity'      => 'info',
            'title'         => 'M-4 Guest Exploit Probe',
            'message'       => 'Probe.',
            'carriageway'   => 'both',
            'status'        => 'pending_approval',
        ]);

        $resApprove = $this->postJson(route('ops.travel-advisory.approve', $advisory->id));
        $this->assertTrue(in_array($resApprove->status(), [401, 403, 302]), 'Guest must be denied on approve');

        $resUpdate = $this->postJson(route('ops.travel-advisory.update', $advisory->id), ['title' => 'Guest Update']);
        $this->assertTrue(in_array($resUpdate->status(), [401, 403, 302]), 'Guest must be denied on update');
    }

    /**
     * Test 8: Agent Supervisor can reject an advisory with a reason.
     * Record becomes 'rejected' and is never broadcast.
     */
    public function test_agent_supervisor_can_reject_advisory_with_reason(): void
    {
        $advisory = TabletAdvisory::create([
            'uuid'              => (string) Str::uuid(),
            'route_name'        => 'M-9',
            'advisory_type'     => 'traffic',
            'category'          => 'congestion',
            'severity'          => 'info',
            'title'             => 'M-9 Spurious Congestion Report',
            'message'           => 'Fog has cleared. Erroneous submission.',
            'carriageway'       => 'both',
            'status'            => 'pending_approval',
            'created_by'        => $this->sectorAdminUser->id,
            'office_id'         => $this->testSector->id,
        ]);

        $response = $this->actingAs($this->supervisorUser)
            ->postJson(route('ops.travel-advisory.reject', $advisory->id), [
                'reason' => 'Visibility normal. CCTV verification confirmed clear weather.',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status'  => 'rejected',
        ]);

        $advisory->refresh();
        $this->assertEquals('rejected', $advisory->status);
        $this->assertEquals('Visibility normal. CCTV verification confirmed clear weather.', $advisory->rejection_reason);

        // Confirm absent from public ticker
        $tickerRes = $this->getJson('/api/v1/travel-advisory/ticker');
        $tickerTitles = collect($tickerRes->json('ticker_items') ?? [])->pluck('title')->toArray();
        $this->assertNotContains('M-9 Spurious Congestion Report', $tickerTitles);
    }

    private function sectorPayload(float $hours): array
    {
        return [
            'route_name' => 'M-2', 'advisory_type' => 'fog', 'severity' => 'advisory',
            'title' => 'Live alert test advisory', 'message' => 'Dense fog between KM 10 and KM 25.',
            'carriageway' => 'both', 'start_km' => 10.0, 'end_km' => 25.0,
            'start_landmark' => 'A', 'end_landmark' => 'B', 'duration_hours' => $hours,
            'sector_office_id' => $this->testSector->id,
        ];
    }

    public function test_live_alert_events_flow_between_sector_and_supervisor(): void
    {
        $base = $this->actingAs($this->supervisorUser)->getJson(route('ops.travel-advisory.events'))->json('last_seq');
        $this->assertIsInt($base);

        $this->actingAs($this->sectorAdminUser)->postJson(route('ops.travel-advisory.store'), $this->sectorPayload(2))->assertOk();
        $advisory = TabletAdvisory::where('title', 'Live alert test advisory')->latest('id')->firstOrFail();

        $sup = $this->actingAs($this->supervisorUser)->getJson(route('ops.travel-advisory.events', ['after' => $base]))->json();
        $this->assertSame('created', $sup['events'][0]['type'], 'Supervisor must be alerted when a sector inserts an advisory');
        $own = $this->actingAs($this->sectorAdminUser)->getJson(route('ops.travel-advisory.events', ['after' => $base]))->json();
        $this->assertSame([], $own['events'], 'Actor must not be alerted about their own action');

        $mid = $sup['last_seq'];
        $this->actingAs($this->supervisorUser)->postJson(route('ops.travel-advisory.approve', $advisory->id))->assertOk();
        $sector = $this->actingAs($this->sectorAdminUser)->getJson(route('ops.travel-advisory.events', ['after' => $mid]))->json();
        $this->assertSame('approved', $sector['events'][0]['type'], 'Sector must be alerted when a supervisor approves');
        $supAgain = $this->actingAs($this->supervisorUser)->getJson(route('ops.travel-advisory.events', ['after' => $mid]))->json();
        $this->assertSame([], $supAgain['events']);

        $mid2 = $sector['last_seq'];
        $this->actingAs($this->supervisorUser)->postJson(route('ops.travel-advisory.extend', $advisory->id), ['hours' => 3])->assertOk();
        $this->actingAs($this->supervisorUser)->postJson(route('ops.travel-advisory.resolve', $advisory->id))->assertOk();
        $types = collect($this->actingAs($this->sectorAdminUser)->getJson(route('ops.travel-advisory.events', ['after' => $mid2]))->json('events'))->pluck('type')->all();
        $this->assertSame(['extended', 'resolved'], $types);
    }

    public function test_approval_keeps_the_duration_chosen_at_insert_instead_of_fixed_4_hours(): void
    {
        $this->actingAs($this->sectorAdminUser)->postJson(route('ops.travel-advisory.store'), $this->sectorPayload(1))->assertOk();
        $advisory = TabletAdvisory::where('title', 'Live alert test advisory')->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(60, now()->diffInMinutes($advisory->expires_at, false), 2, 'Insert must honour the chosen 1h');

        // Supervisor is late: the 1h window already passed while it waited for approval
        TabletAdvisory::where('id', $advisory->id)->update(['created_at' => now()->subHours(2), 'expires_at' => now()->subHour()]);
        $this->actingAs($this->supervisorUser)->postJson(route('ops.travel-advisory.approve', $advisory->id))->assertOk();

        $minutes = now()->diffInMinutes($advisory->fresh()->expires_at, false);
        $this->assertEqualsWithDelta(60, $minutes, 3, 'Approval must give the operator-chosen 1h from approval time, not a fixed 4h');
    }

    public function test_edit_without_duration_keeps_current_expiry(): void
    {
        $this->actingAs($this->sectorAdminUser)->postJson(route('ops.travel-advisory.store'), $this->sectorPayload(6))->assertOk();
        $advisory = TabletAdvisory::where('title', 'Live alert test advisory')->latest('id')->firstOrFail();
        $before = $advisory->expires_at->getTimestamp();

        $this->actingAs($this->supervisorUser)->postJson(route('ops.travel-advisory.update', $advisory->id), [
            'title' => 'Live alert test advisory', 'message' => 'Edited message', 'severity' => 'advisory',
            'carriageway' => 'both', 'category' => 'fog', 'duration_hours' => '',
        ])->assertOk();

        $this->assertSame($before, $advisory->fresh()->expires_at->getTimestamp(), 'Blank duration must not reset expiry');
    }

    private function seedAdvisory(array $over = []): TabletAdvisory
    {
        $createdAt = $over['created_at'] ?? null;
        unset($over['created_at']);
        $advisory = TabletAdvisory::create(array_merge([
            'uuid' => (string) Str::uuid(),
            'title' => 'Card test ' . Str::random(6),
            'category' => 'fog',
            'severity' => 'advisory',
            'route_name' => 'M-2',
            'carriageway' => 'both',
            'office_id' => $this->testBeat->id,
            'message' => 'Test message',
            'status' => 'published',
            'start_km' => 10,
            'end_km' => 20,
            'expires_at' => now()->addHours(2),
            'published_at' => now(),
            'created_by' => $this->sectorAdminUser->id,
        ], $over));
        if ($createdAt) {
            // created_at is not mass-assignable; set it directly to simulate an older insertion time
            TabletAdvisory::where('id', $advisory->id)->update(['created_at' => $createdAt]);
            $advisory->refresh();
        }
        return $advisory;
    }

    public function test_expired_card_counts_and_lists_expired_advisories_with_timing_fields(): void
    {
        $expired = $this->seedAdvisory(['title' => 'Expired fog one', 'expires_at' => now()->subHour(), 'created_at' => now()->subHours(5)]);
        $live = $this->seedAdvisory(['title' => 'Live fog one']);

        $res = $this->actingAs($this->supervisorUser)->getJson(route('ops.travel-advisory.index', ['ajax' => 1, 'status' => 'expired']))->assertOk();
        $titles = collect($res->json('items'))->pluck('title');
        $this->assertTrue($titles->contains('Expired fog one'));
        $this->assertFalse($titles->contains('Live fog one'));
        $this->assertGreaterThanOrEqual(1, $res->json('metrics.expired'));
        $item = collect($res->json('items'))->firstWhere('id', $expired->id);
        $this->assertSame('Expired', $item['validity_label']);
        $this->assertNotEmpty($item['created_at_display']);
        $this->assertNotEmpty($item['expires_at_display']);
        $this->assertTrue($item['is_expired']);
    }

    public function test_date_range_filters_list_and_all_card_metrics(): void
    {
        $old = $this->seedAdvisory(['title' => 'Old dated advisory', 'category' => 'road_closure', 'created_at' => Carbon::parse('2026-01-10 10:00:00')]);
        $new = $this->seedAdvisory(['title' => 'New dated advisory', 'category' => 'road_closure', 'created_at' => Carbon::parse('2026-02-20 10:00:00')]);

        $res = $this->actingAs($this->supervisorUser)->getJson(route('ops.travel-advisory.index', [
            'ajax' => 1, 'status' => 'all', 'date_from' => '2026-02-01', 'date_to' => '2026-02-28',
        ]))->assertOk();
        $titles = collect($res->json('items'))->pluck('title');
        $this->assertTrue($titles->contains('New dated advisory'));
        $this->assertFalse($titles->contains('Old dated advisory'));
        $this->assertSame(1, $res->json('metrics.road_closures'), 'Category card follows the date range');
        $this->assertSame(1, $res->json('metrics.total_all'));

        // single day boundaries are inclusive
        $res2 = $this->actingAs($this->supervisorUser)->getJson(route('ops.travel-advisory.index', [
            'ajax' => 1, 'status' => 'all', 'date_from' => '2026-01-10', 'date_to' => '2026-01-10',
        ]))->assertOk();
        $this->assertTrue(collect($res2->json('items'))->pluck('title')->contains('Old dated advisory'));

        // invalid dates are ignored (no 500)
        $this->actingAs($this->supervisorUser)->getJson(route('ops.travel-advisory.index', ['ajax' => 1, 'date_from' => 'nonsense', 'date_to' => '2026-13-99']))->assertOk();
    }

    public function test_hierarchy_filters_cascade_zone_sector_beat_for_national_users(): void
    {
        $zone = $this->testSector->parent;
        $otherZone = Office::create(['name' => 'Other Zone ' . Str::random(4), 'type' => 'zone', 'is_active' => true]);
        $otherSector = Office::create(['name' => 'Other Sector ' . Str::random(4), 'type' => 'sector', 'parent_id' => $otherZone->id, 'is_active' => true]);
        $otherBeat = Office::create(['name' => 'Other Beat ' . Str::random(4), 'type' => 'beat', 'parent_id' => $otherSector->id, 'is_active' => true]);
        Office::clearHierarchyCache();

        $mine  = $this->seedAdvisory(['title' => 'Mine in zone', 'office_id' => $this->testBeat->id, 'sector_id' => $this->testSector->id, 'zone_id' => $zone->id]);
        $other = $this->seedAdvisory(['title' => 'Elsewhere', 'office_id' => $otherBeat->id, 'sector_id' => $otherSector->id, 'zone_id' => $otherZone->id]);

        $titles = fn ($q) => collect($this->actingAs($this->supervisorUser)->getJson(route('ops.travel-advisory.index', array_merge(['ajax' => 1, 'status' => 'all'], $q)))->assertOk()->json('items'))->pluck('title');

        $this->assertTrue($titles([])->contains('Elsewhere') && $titles([])->contains('Mine in zone'), 'national sees everything');
        $z = $titles(['zone_id' => $zone->id]);
        $this->assertTrue($z->contains('Mine in zone')); $this->assertFalse($z->contains('Elsewhere'));
        $sx = $titles(['zone_id' => $zone->id, 'sector_id' => $this->testSector->id]);
        $this->assertTrue($sx->contains('Mine in zone')); $this->assertFalse($sx->contains('Elsewhere'));
        $b = $titles(['beat_id' => $otherBeat->id]);
        $this->assertTrue($b->contains('Elsewhere')); $this->assertFalse($b->contains('Mine in zone'));
        // inconsistent chain (sector from another zone) is dropped, not trusted
        $bad = $titles(['zone_id' => $zone->id, 'sector_id' => $otherSector->id]);
        $this->assertTrue($bad->contains('Mine in zone'));

        $page = $this->actingAs($this->supervisorUser)->get(route('ops.travel-advisory.index'))->assertOk();
        $page->assertSee('All Zones', false);
    }

    public function test_sector_operator_is_locked_to_own_zone_and_sector(): void
    {
        $zone = $this->testSector->parent;
        $otherSector = Office::create(['name' => 'Rival Sector ' . Str::random(4), 'type' => 'sector', 'parent_id' => $zone->id, 'is_active' => true]);
        $otherBeat = Office::create(['name' => 'Rival Beat ' . Str::random(4), 'type' => 'beat', 'parent_id' => $otherSector->id, 'is_active' => true]);
        Office::clearHierarchyCache();

        \App\Models\UserScope::create(['user_id' => $this->sectorAdminUser->id, 'office_id' => $this->testSector->id, 'access_level' => 'read_only', 'is_active' => true]);
        $this->seedAdvisory(['title' => 'Own sector item', 'office_id' => $this->testBeat->id, 'sector_id' => $this->testSector->id, 'zone_id' => $zone->id]);
        $this->seedAdvisory(['title' => 'Rival sector item', 'office_id' => $otherBeat->id, 'sector_id' => $otherSector->id, 'zone_id' => $zone->id]);

        $res = $this->actingAs($this->sectorAdminUser)->getJson(route('ops.travel-advisory.index', [
            'ajax' => 1, 'status' => 'all', 'sector_id' => $otherSector->id, 'zone_id' => 0,
        ]))->assertOk();
        $titles = collect($res->json('items'))->pluck('title');
        $this->assertTrue($titles->contains('Own sector item'), 'sector operator must see own sector items');
        $this->assertFalse($titles->contains('Rival sector item'), 'a sector operator cannot override the locked sector');

        $page = $this->actingAs($this->sectorAdminUser)->get(route('ops.travel-advisory.index'))->assertOk();
        // Js::from() encodes quotes as \u0022 inside JSON.parse('...')
        $page->assertSee('\\u0022sector\\u0022:' . $this->testSector->id, false);
        $page->assertSee('\\u0022zone\\u0022:' . $zone->id, false);
    }
}

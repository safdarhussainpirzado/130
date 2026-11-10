<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\SupportRequestAction;
use App\Models\User;
use App\Models\UserScope;
use App\Services\Support\OrganizationalTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HierarchicalTransferAndScopingTest extends TestCase
{
    private OrganizationalTransferService $transferService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transferService = app(OrganizationalTransferService::class);
    }

    public function test_pipeline_intra_sector_creates_two_step_workflow()
    {
        // Zone 1 -> Sector 1 -> Beat 1 & Beat 2
        $zone = Office::firstOrCreate(['name' => 'Test Zone 1', 'type' => 'zone'], ['is_active' => true]);
        $sector = Office::firstOrCreate(['name' => 'Test Sector 1', 'type' => 'sector', 'parent_id' => $zone->id], ['is_active' => true]);
        $beat1 = Office::firstOrCreate(['name' => 'Test Beat 1', 'type' => 'beat', 'parent_id' => $sector->id], ['is_active' => true]);
        $beat2 = Office::firstOrCreate(['name' => 'Test Beat 2', 'type' => 'beat', 'parent_id' => $sector->id], ['is_active' => true]);

        $beat1->load('parent.parent');
        $beat2->load('parent.parent');

        $pipeline = $this->transferService->buildTransferApprovalPipeline($beat1, $beat2);

        $this->assertCount(2, $pipeline);
        $this->assertEquals('source_sector_departure', $pipeline[0]['step_key']);
        $this->assertEquals('dest_beat_arrival', $pipeline[1]['step_key']);
        $this->assertEquals($sector->id, $pipeline[0]['office_id']);
        $this->assertEquals($beat2->id, $pipeline[1]['office_id']);
    }

    public function test_pipeline_intra_zone_creates_four_step_workflow()
    {
        // Zone 1 -> Sector A (Beat A) & Sector B (Beat B)
        $zone = Office::firstOrCreate(['name' => 'Test Common Zone', 'type' => 'zone'], ['is_active' => true]);
        $secA = Office::firstOrCreate(['name' => 'Test Sector A', 'type' => 'sector', 'parent_id' => $zone->id], ['is_active' => true]);
        $secB = Office::firstOrCreate(['name' => 'Test Sector B', 'type' => 'sector', 'parent_id' => $zone->id], ['is_active' => true]);
        $beatA = Office::firstOrCreate(['name' => 'Test Beat A', 'type' => 'beat', 'parent_id' => $secA->id], ['is_active' => true]);
        $beatB = Office::firstOrCreate(['name' => 'Test Beat B', 'type' => 'beat', 'parent_id' => $secB->id], ['is_active' => true]);

        $beatA->load('parent.parent');
        $beatB->load('parent.parent');

        $pipeline = $this->transferService->buildTransferApprovalPipeline($beatA, $beatB);

        $this->assertCount(4, $pipeline);
        $this->assertEquals('source_sector_departure', $pipeline[0]['step_key']);
        $this->assertEquals('common_zone_endorsement', $pipeline[1]['step_key']);
        $this->assertEquals('dest_sector_clearance', $pipeline[2]['step_key']);
        $this->assertEquals('dest_beat_arrival', $pipeline[3]['step_key']);
    }

    public function test_pipeline_inter_zone_creates_six_step_workflow()
    {
        $zone1 = Office::firstOrCreate(['name' => 'Inter Zone 1', 'type' => 'zone'], ['is_active' => true]);
        $zone2 = Office::firstOrCreate(['name' => 'Inter Zone 2', 'type' => 'zone'], ['is_active' => true]);
        $sec1 = Office::firstOrCreate(['name' => 'Inter Sector 1', 'type' => 'sector', 'parent_id' => $zone1->id], ['is_active' => true]);
        $sec2 = Office::firstOrCreate(['name' => 'Inter Sector 2', 'type' => 'sector', 'parent_id' => $zone2->id], ['is_active' => true]);
        $beat1 = Office::firstOrCreate(['name' => 'Inter Beat 1', 'type' => 'beat', 'parent_id' => $sec1->id], ['is_active' => true]);
        $beat2 = Office::firstOrCreate(['name' => 'Inter Beat 2', 'type' => 'beat', 'parent_id' => $sec2->id], ['is_active' => true]);

        $beat1->load('parent.parent');
        $beat2->load('parent.parent');

        $pipeline = $this->transferService->buildTransferApprovalPipeline($beat1, $beat2);

        $this->assertCount(6, $pipeline);
        $this->assertEquals('source_sector_departure', $pipeline[0]['step_key']);
        $this->assertEquals('source_zone_clearance', $pipeline[1]['step_key']);
        $this->assertEquals('national_operations_review', $pipeline[2]['step_key']);
        $this->assertEquals('dest_zone_clearance', $pipeline[3]['step_key']);
        $this->assertEquals('dest_sector_clearance', $pipeline[4]['step_key']);
        $this->assertEquals('dest_beat_arrival', $pipeline[5]['step_key']);
    }

    public function test_sector_admin_can_approve_departure_but_cannot_self_approve()
    {
        $sectorRole = Role::firstOrCreate(['name' => 'sector_admin'], ['display_name' => 'Sector Admin', 'status' => 'active']);
        $beatRole   = Role::firstOrCreate(['name' => 'beat_operator'], ['display_name' => 'Beat Operator', 'status' => 'active']);

        $zone = Office::firstOrCreate(['name' => 'Zone North', 'type' => 'zone'], ['is_active' => true]);
        $sec = Office::firstOrCreate(['name' => 'Sector North 1', 'type' => 'sector', 'parent_id' => $zone->id], ['is_active' => true]);
        $beat = Office::firstOrCreate(['name' => 'Beat North 01', 'type' => 'beat', 'parent_id' => $sec->id], ['is_active' => true]);

        // Applicant officer
        $applicant = User::factory()->create([
            'role_id'  => $beatRole->id,
            'password' => Hash::make('Secret12345!'),
        ]);
        UserScope::create([
            'user_id'   => $applicant->id,
            'office_id' => $beat->id,
            'is_active' => true,
        ]);

        // Sector Admin
        $sectorAdmin = User::factory()->create([
            'role_id'  => $sectorRole->id,
            'password' => Hash::make('Secret12345!'),
        ]);
        UserScope::create([
            'user_id'   => $sectorAdmin->id,
            'office_id' => $sec->id,
            'is_active' => true,
        ]);

        // Unrelated Admin from other zone
        $otherZone = Office::firstOrCreate(['name' => 'Zone South', 'type' => 'zone'], ['is_active' => true]);
        $otherSec = Office::firstOrCreate(['name' => 'Sector South 1', 'type' => 'sector', 'parent_id' => $otherZone->id], ['is_active' => true]);
        $otherAdmin = User::factory()->create([
            'role_id'  => $sectorRole->id,
            'password' => Hash::make('Secret12345!'),
        ]);
        UserScope::create([
            'user_id'   => $otherAdmin->id,
            'office_id' => $otherSec->id,
            'is_active' => true,
        ]);

        $beat->load('parent.parent');
        $otherSec->load('parent.parent');

        // Create support request
        $supportReq = $this->transferService->submitTransferRequest(
            $applicant,
            $otherSec->id,
            'Inter-zone transfer request',
            'Secret12345!'
        );

        // 1. Sector Admin over Source Sector CAN act on Step 0 (Departure)
        $this->assertTrue($this->transferService->canUserActOnCurrentStep($sectorAdmin, $supportReq));

        // 2. Applicant CANNOT act on their own request (Anti-Self-Approval)
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($applicant, $supportReq));

        // 3. Other Admin outside jurisdiction CANNOT act
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($otherAdmin, $supportReq));
    }

    public function test_rejection_at_any_tier_immediately_terminates_transfer()
    {
        $sectorRole = Role::firstOrCreate(['name' => 'sector_admin'], ['display_name' => 'Sector Admin', 'status' => 'active']);
        $beatRole   = Role::firstOrCreate(['name' => 'beat_operator'], ['display_name' => 'Beat Operator', 'status' => 'active']);

        $zone = Office::firstOrCreate(['name' => 'Reject Test Zone', 'type' => 'zone'], ['is_active' => true]);
        $sec = Office::firstOrCreate(['name' => 'Reject Test Sector', 'type' => 'sector', 'parent_id' => $zone->id], ['is_active' => true]);
        $beat1 = Office::firstOrCreate(['name' => 'Reject Beat 1', 'type' => 'beat', 'parent_id' => $sec->id], ['is_active' => true]);
        $beat2 = Office::firstOrCreate(['name' => 'Reject Beat 2', 'type' => 'beat', 'parent_id' => $sec->id], ['is_active' => true]);

        $applicant = User::factory()->create([
            'role_id'  => $beatRole->id,
            'password' => Hash::make('Secret12345!'),
        ]);
        UserScope::create(['user_id' => $applicant->id, 'office_id' => $beat1->id, 'is_active' => true]);

        $sectorAdmin = User::factory()->create([
            'role_id'  => $sectorRole->id,
            'password' => Hash::make('Secret12345!'),
        ]);
        UserScope::create(['user_id' => $sectorAdmin->id, 'office_id' => $sec->id, 'is_active' => true]);

        $supportReq = $this->transferService->submitTransferRequest(
            $applicant,
            $beat2->id,
            'Intra-sector transfer',
            'Secret12345!'
        );

        $this->assertEquals(SupportRequest::STATUS_PENDING_SOURCE_APPROVAL, $supportReq->current_status);

        // Reject by Sector Admin
        $updated = $this->transferService->processPipelineStep(
            $supportReq,
            $sectorAdmin,
            false,
            'Operational personnel shortage at source beat',
            'Secret12345!'
        );

        $this->assertEquals(SupportRequest::STATUS_REJECTED, $updated->current_status);
        $this->assertNotNull($updated->completed_at);
        $this->assertDatabaseHas('support_request_actions', [
            'support_request_id' => $updated->id,
            'actor_id'           => $sectorAdmin->id,
            'action_type'        => SupportRequestAction::ACTION_TRANSFER_REJECTED,
            'new_status'         => SupportRequest::STATUS_REJECTED,
        ]);
    }

    public function test_support_center_scoped_to_sector_lsm_only_shows_sector_requests()
    {
        $sectorRole = Role::firstOrCreate(['name' => 'sector_admin'], ['display_name' => 'Sector Admin', 'status' => 'active']);
        $beatRole   = Role::firstOrCreate(['name' => 'beat_operator'], ['display_name' => 'Beat Operator', 'status' => 'active']);

        $zone = Office::firstOrCreate(['name' => 'LSM Zone', 'type' => 'zone'], ['is_active' => true]);
        $sector = Office::firstOrCreate(['name' => 'LSM Sector', 'type' => 'sector', 'parent_id' => $zone->id], ['is_active' => true]);
        $beat1 = Office::firstOrCreate(['name' => 'LSM Beat 01', 'type' => 'beat', 'parent_id' => $sector->id], ['is_active' => true]);

        // External office not under LSM
        $otherZone = Office::firstOrCreate(['name' => 'Other Zone', 'type' => 'zone'], ['is_active' => true]);
        $otherSector = Office::firstOrCreate(['name' => 'Other Sector', 'type' => 'sector', 'parent_id' => $otherZone->id], ['is_active' => true]);
        $otherBeat = Office::firstOrCreate(['name' => 'Other Beat', 'type' => 'beat', 'parent_id' => $otherSector->id], ['is_active' => true]);

        /** @var User $sectorLsm */
        $sectorLsm = User::factory()->create([
            'username' => 'test_sector_lsm_' . uniqid(),
            'role_id'  => $sectorRole->id,
            'password' => Hash::make('Secret12345!'),
        ]);
        UserScope::create([
            'user_id'   => $sectorLsm->id,
            'office_id' => $sector->id,
            'is_active' => true,
        ]);

        $beatUser = User::factory()->create([
            'username' => 'test_beat_usr_' . uniqid(),
            'role_id'  => $beatRole->id,
            'password' => Hash::make('Secret12345!'),
        ]);
        UserScope::create([
            'user_id'   => $beatUser->id,
            'office_id' => $beat1->id,
            'is_active' => true,
        ]);

        $otherUser = User::factory()->create([
            'username' => 'test_other_usr_' . uniqid(),
            'role_id'  => $beatRole->id,
            'password' => Hash::make('Secret12345!'),
        ]);
        UserScope::create([
            'user_id'   => $otherUser->id,
            'office_id' => $otherBeat->id,
            'is_active' => true,
        ]);

        // Create LSM in-scope transfer request
        $this->transferService->submitTransferRequest(
            $beatUser,
            $otherBeat->id,
            'Transfer from LSM beat',
            'Secret12345!'
        );

        // Create completely outside request
        $this->transferService->submitTransferRequest(
            $otherUser,
            $otherSector->id,
            'Outside transfer',
            'Secret12345!'
        );

        $response = $this->actingAs($sectorLsm)->getJson('/mgmt/support');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data',
            'kpis',
            'pagination',
        ]);

        $this->assertTrue($response->json('success'));
        $data = $response->json('data');

        // Sector LSM allowed office IDs: Sector and its descendant beats
        $allowedOfficeIds = Office::getDescendantIdsFor($sector->id, true);

        $this->assertNotEmpty($data);
        foreach ($data as $req) {
            $sourceId = SupportRequest::where('public_id', $req['public_id'])->value('source_office_id');
            $destId   = SupportRequest::where('public_id', $req['public_id'])->value('destination_office_id');
            $reqId    = $req['requester']['id'] ?? null;

            $isInScope = in_array($sourceId, $allowedOfficeIds, true)
                || in_array($destId, $allowedOfficeIds, true)
                || $reqId === $sectorLsm->id;

            $this->assertTrue($isInScope, "Request {$req['public_id']} must belong to Sector LSM jurisdiction.");
        }
    }

    public function test_intra_sector_transfer_strictly_enforces_related_office_hierarchy()
    {
        $zoneRole   = Role::firstOrCreate(['name' => 'zone_admin'], ['display_name' => 'Zone Admin', 'status' => 'active']);
        $sectorRole = Role::firstOrCreate(['name' => 'sector_admin'], ['display_name' => 'Sector Admin', 'status' => 'active']);
        $beatRole   = Role::firstOrCreate(['name' => 'beat_operator'], ['display_name' => 'Beat Operator', 'status' => 'active']);
        $natRole    = Role::firstOrCreate(['name' => 'operation_admin'], ['display_name' => 'Operation Admin', 'status' => 'active']);

        $zone   = Office::firstOrCreate(['name' => 'Hierarchy Zone', 'type' => 'zone'], ['is_active' => true]);
        $sector = Office::firstOrCreate(['name' => 'Hierarchy Sector', 'type' => 'sector', 'parent_id' => $zone->id], ['is_active' => true]);
        $beat1  = Office::firstOrCreate(['name' => 'Hierarchy Beat 1', 'type' => 'beat', 'parent_id' => $sector->id], ['is_active' => true]);
        $beat2  = Office::firstOrCreate(['name' => 'Hierarchy Beat 2', 'type' => 'beat', 'parent_id' => $sector->id], ['is_active' => true]);

        $beat1->load('parent.parent');
        $beat2->load('parent.parent');

        // 1. Applicant at Beat 1
        /** @var User $applicant */
        $applicant = User::factory()->create(['role_id' => $beatRole->id, 'password' => Hash::make('Secret12345!')]);
        UserScope::create(['user_id' => $applicant->id, 'office_id' => $beat1->id, 'is_active' => true]);

        // 2. Zone Admin at Zone
        /** @var User $zoneAdmin */
        $zoneAdmin = User::factory()->create(['role_id' => $zoneRole->id, 'password' => Hash::make('Secret12345!')]);
        UserScope::create(['user_id' => $zoneAdmin->id, 'office_id' => $zone->id, 'is_active' => true]);

        // 3. Operation Admin (National)
        /** @var User $opAdmin */
        $opAdmin = User::factory()->create(['role_id' => $natRole->id, 'password' => Hash::make('Secret12345!')]);

        // 4. Sector Admin at Sector
        /** @var User $sectorAdmin */
        $sectorAdmin = User::factory()->create(['role_id' => $sectorRole->id, 'password' => Hash::make('Secret12345!')]);
        UserScope::create(['user_id' => $sectorAdmin->id, 'office_id' => $sector->id, 'is_active' => true]);

        // 5. Destination Beat Operator at Beat 2
        /** @var User $destOperator */
        $destOperator = User::factory()->create(['role_id' => $beatRole->id, 'password' => Hash::make('Secret12345!')]);
        UserScope::create(['user_id' => $destOperator->id, 'office_id' => $beat2->id, 'is_active' => true]);

        // Submit Intra-Sector Transfer
        $supportReq = $this->transferService->submitTransferRequest(
            $applicant,
            $beat2->id,
            'Intra-sector rotation order',
            'Secret12345!'
        );

        // STEP 0: Source Sector Departure Approval
        // - Sector Admin CAN approve
        $this->assertTrue($this->transferService->canUserActOnCurrentStep($sectorAdmin, $supportReq));
        // - Zone Admin CANNOT approve
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($zoneAdmin, $supportReq), 'Zone Admin must NOT approve sector departure!');
        // - Operation Admin CANNOT approve
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($opAdmin, $supportReq), 'Operation Admin must NOT approve sector departure!');
        // - Destination Beat Operator CANNOT approve
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($destOperator, $supportReq), 'Destination Beat Operator must NOT approve sector departure!');
        // - Applicant CANNOT self-approve
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($applicant, $supportReq), 'Applicant cannot self-approve!');

        // HTTP Enforcement: Zone Admin and Op Admin get 403 on Departure
        $this->actingAs($zoneAdmin)
            ->postJson("/mgmt/support/requests/{$supportReq->public_id}/approve-source", [
                'remarks'  => 'Unauthorized Zone attempt',
                'password' => 'Secret12345!',
            ])->assertStatus(403);

        $this->actingAs($opAdmin)
            ->postJson("/mgmt/support/requests/{$supportReq->public_id}/approve-source", [
                'remarks'  => 'Unauthorized Operation Admin attempt',
                'password' => 'Secret12345!',
            ])->assertStatus(403);

        // Sector Admin approves departure
        $step1Req = $this->transferService->processPipelineStep(
            $supportReq,
            $sectorAdmin,
            true,
            'Source Sector Departure verified',
            'Secret12345!'
        );

        $this->assertEquals(SupportRequest::STATUS_PENDING_DESTINATION_CONFIRMATION, $step1Req->current_status);

        // STEP 1: Destination Beat Arrival Confirmation
        // - Destination Beat Operator CAN confirm arrival
        $this->assertTrue($this->transferService->canUserActOnCurrentStep($destOperator, $step1Req));
        // - Sector Admin CANNOT confirm arrival (belongs strictly to destination beat)
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($sectorAdmin, $step1Req), 'Sector Admin must NOT confirm beat arrival!');
        // - Zone Admin CANNOT confirm arrival
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($zoneAdmin, $step1Req), 'Zone Admin must NOT confirm beat arrival!');
        // - Operation Admin CANNOT confirm arrival
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($opAdmin, $step1Req), 'Operation Admin must NOT confirm beat arrival!');
        // - Applicant CANNOT self-confirm arrival
        $this->assertFalse($this->transferService->canUserActOnCurrentStep($applicant, $step1Req), 'Applicant cannot self-confirm arrival!');

        // HTTP Enforcement: Zone Admin and Sector Admin get 403 on Arrival
        $this->actingAs($zoneAdmin)
            ->postJson("/mgmt/support/requests/{$supportReq->public_id}/confirm-destination", [
                'remarks'  => 'Unauthorized Zone arrival attempt',
                'password' => 'Secret12345!',
            ])->assertStatus(403);

        $this->actingAs($sectorAdmin)
            ->postJson("/mgmt/support/requests/{$supportReq->public_id}/confirm-destination", [
                'remarks'  => 'Unauthorized Sector arrival attempt',
                'password' => 'Secret12345!',
            ])->assertStatus(403);

        // Destination Beat Operator confirms arrival
        $completedReq = $this->transferService->processPipelineStep(
            $step1Req,
            $destOperator,
            true,
            'Destination Beat Arrival confirmed',
            'Secret12345!'
        );

        $this->assertEquals(SupportRequest::STATUS_COMPLETED, $completedReq->current_status);
        $this->assertNotNull($completedReq->completed_at);
    }
}


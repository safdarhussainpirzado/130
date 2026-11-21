<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\CallSubType;
use App\Models\CallType;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class Drop1RequirementsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([ValidateCsrfToken::class]);
    }

    private function getSuperAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => 'super_admin'], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'is_active' => true,
        ]);

        return User::firstOrCreate(['username' => 'drop1_super_admin'], [
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Drop 1 Super Admin',
            'email' => 'drop1_super_admin@nhmp.gov.pk',
            'mobile_no' => '03009990001',
            'cnic' => '33333-3333333-1',
            'role_id' => $role->id,
            'password' => 'Secret12345!',
            'is_active' => true,
            'is_blocked' => false,
        ]);
    }

    /**
     * A1: Support Request Requester profile, eager loading, and filtering.
     */
    public function test_support_request_requester_filter_and_profile_data(): void
    {
        $admin = $this->getSuperAdmin();

        $zone = Office::firstOrCreate(['name' => 'Central Zone'], ['uuid' => (string) Str::uuid(), 'type' => 'zone', 'is_active' => true]);
        $sector = Office::firstOrCreate(['name' => 'Sector M-3'], ['uuid' => (string) Str::uuid(), 'type' => 'sector', 'parent_id' => $zone->id, 'is_active' => true]);
        $beat = Office::firstOrCreate(['name' => 'Beat 10'], ['uuid' => (string) Str::uuid(), 'type' => 'beat', 'parent_id' => $sector->id, 'is_active' => true]);

        $requesterRole = Role::firstOrCreate(['name' => 'beat_operator'], [
            'display_name' => 'Beat Operator',
            'scope_level' => 'beat',
            'is_active' => true,
        ]);

        $requester = User::firstOrCreate(['username' => 'requester_officer_99'], [
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Officer Ahmad Raza',
            'email' => 'ahmad.raza99@nhmp.gov.pk',
            'mobile_no' => '03009990002',
            'cnic' => '33333-3333333-2',
            'role_id' => $requesterRole->id,
            'password' => 'Secret12345!',
            'is_active' => true,
            'is_blocked' => false,
        ]);

        UserScope::firstOrCreate([
            'user_id' => $requester->id,
            'office_id' => $beat->id,
        ], [
            'uuid' => (string) Str::uuid(),
            'access_level' => 'read_write',
            'is_active' => true,
        ]);

        $req = SupportRequest::create([
            'uuid' => (string) Str::uuid(),
            'tracking_number' => 'REQ-TEST-'.strtoupper(Str::random(6)),
            'requester_id' => $requester->id,
            'request_type' => 'material',
            'priority' => 'high',
            'status' => 'pending',
            'subject' => 'Emergency Cones Request',
            'description' => 'Need 20 safety cones for diversion protocol.',
            'destination_office_id' => $sector->id,
        ]);

        // 1. Verify index API / view loads requester relations
        $response = $this->actingAs($admin)->get('/mgmt/support?requester=requester_officer_99');
        $response->assertStatus(200);
        $response->assertSee('Officer Ahmad Raza');
        $response->assertSee('Beat 10');

        // 2. Verify show page loads requester details
        $showResponse = $this->actingAs($admin)->get("/mgmt/support/requests/{$req->public_id}");
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Officer Ahmad Raza');
        $showResponse->assertSee('Beat 10');

        // 3. Verify legacy row without requester displays fallback
        $legacyReq = SupportRequest::create([
            'uuid' => (string) Str::uuid(),
            'tracking_number' => 'REQ-LEGACY-'.strtoupper(Str::random(6)),
            'requester_id' => $requester->id,
            'request_type' => 'technical',
            'priority' => 'normal',
            'status' => 'pending',
            'subject' => 'Legacy System Ticket',
            'description' => 'Created prior to requester tracking.',
            'destination_office_id' => $sector->id,
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('support_requests')->where('id', $legacyReq->id)->update(['requester_id' => 999999]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $legacyResponse = $this->actingAs($admin)->get("/mgmt/support/requests/{$legacyReq->public_id}");
        $legacyResponse->assertStatus(200);
        $legacyResponse->assertSee('System / Unknown Requester');
    }

    /**
     * A4: Call reminder notification payload category, call_type, and call_sub_type.
     */
    public function test_call_notification_poll_payload_includes_full_category(): void
    {
        $admin = $this->getSuperAdmin();

        $zone = Office::firstOrCreate(['name' => 'Motorway North'], ['uuid' => (string) Str::uuid(), 'type' => 'zone', 'is_active' => true]);
        $sector = Office::firstOrCreate(['name' => 'Sector M-1'], ['uuid' => (string) Str::uuid(), 'type' => 'sector', 'parent_id' => $zone->id, 'is_active' => true]);
        $beat = Office::firstOrCreate(['name' => 'Beat 01 M-1'], ['uuid' => (string) Str::uuid(), 'type' => 'beat', 'parent_id' => $sector->id, 'is_active' => true]);

        $beatRole = Role::firstOrCreate(['name' => 'beat_operator'], [
            'display_name' => 'Beat Operator',
            'scope_level' => 'beat',
            'is_active' => true,
        ]);

        $perm = Permission::firstOrCreate(['name' => 'calls.api_lookup'], [
            'display_name' => 'API Lookup',
            'module' => 'calls',
        ]);
        if (! $beatRole->permissions()->where('permissions.id', $perm->id)->exists()) {
            $beatRole->permissions()->attach($perm->id);
        }

        $operator = User::firstOrCreate(['username' => 'beat_op_test_99'], [
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Beat Operator 99',
            'email' => 'beat_op_test_99@nhmp.gov.pk',
            'mobile_no' => '03009990099',
            'cnic' => '33333-3333333-9',
            'role_id' => $beatRole->id,
            'password' => 'Secret12345!',
            'is_active' => true,
            'is_blocked' => false,
        ]);

        UserScope::firstOrCreate([
            'user_id' => $operator->id,
            'office_id' => $beat->id,
        ], [
            'uuid' => (string) Str::uuid(),
            'access_level' => 'read_write',
            'is_active' => true,
        ]);

        $callType = CallType::firstOrCreate(['name' => 'Emergency Response'], ['category' => 'emergency', 'is_active' => true]);
        $callSubType = CallSubType::firstOrCreate(['name' => 'Fatal Collision', 'call_type_id' => $callType->id], ['is_active' => true, 'priority' => 3]);

        $call = Call::create([
            'uuid' => (string) Str::uuid(),
            'call_number' => 'CRM-2026-99001',
            'agent_id' => $admin->id,
            'caller_name' => 'Test Commuter',
            'caller_number' => '03001239999',
            'call_type_id' => $callType->id,
            'call_sub_type_id' => $callSubType->id,
            'office_id' => $beat->id,
            'status' => 'pending',
            'priority' => 3,
            'created_at' => now(),
            'last_reminder_at' => now(),
        ]);

        $pollResponse = $this->actingAs($operator)->getJson('/api/notifications/poll?since='.urlencode(now()->subMinutes(10)->toIso8601String()));
        $pollResponse->assertStatus(200);

        $newCalls = collect($pollResponse->json('new_calls'));
        $polledCall = $newCalls->firstWhere('id', $call->id);
        $this->assertNotNull($polledCall, 'Created call must be returned in notifications poll');
        $this->assertEquals('Emergency Response', $polledCall['call_type']['name']);
        $this->assertEquals('Fatal Collision', $polledCall['call_sub_type']['name']);
        $this->assertEquals('Emergency Response / Fatal Collision', $polledCall['category']);
    }
}

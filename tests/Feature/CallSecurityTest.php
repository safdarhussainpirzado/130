<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\CallSubType;
use App\Models\CallType;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Services\CallService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    private function getPrerequisites(): array
    {
        $role = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'is_active' => true,
        ]);

        $user = User::firstOrCreate(['username' => 'security_test_admin'], [
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Security Test Admin',
            'email' => 'security_admin@nhmp.gov.pk',
            'mobile_no' => '03009998877',
            'cnic' => '99999-9999999-9',
            'role_id' => $role->id,
            'password' => 'Secret12345!',
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $callType = CallType::firstOrCreate(['name' => 'Emergency Highway Rescue'], [
            'category' => 'emergency',
            'is_active' => true,
        ]);

        $subType = CallSubType::firstOrCreate(['name' => 'Road Accident Rapid', 'call_type_id' => $callType->id], [
            'is_active' => true,
            'priority' => 1,
        ]);

        $zone = Office::firstOrCreate(['name' => 'Security Test Zone', 'type' => 'zone'], [
            'uuid' => (string) Str::uuid(),
            'is_active' => true,
        ]);
        $sector = Office::firstOrCreate(['name' => 'Security Test Sector', 'type' => 'sector', 'parent_id' => $zone->id], [
            'uuid' => (string) Str::uuid(),
            'is_active' => true,
        ]);
        $beat = Office::firstOrCreate(['name' => 'Security Test Beat', 'type' => 'beat', 'parent_id' => $sector->id], [
            'uuid' => (string) Str::uuid(),
            'is_active' => true,
        ]);

        $callService = app(CallService::class);
        $callNumber = $callService->generateCallNumber();

        $call = Call::create([
            'uuid' => (string) Str::uuid(),
            'call_number' => $callNumber,
            'agent_id' => $user->id,
            'caller_number' => '03009998877',
            'caller_name' => 'Citizen In Need',
            'call_type_id' => $callType->id,
            'call_sub_type_id' => $subType->id,
            'office_id' => $beat->id,
            'status' => 'pending',
            'priority' => 1,
        ]);

        return [$user, $call];
    }

    /**
     * Test that sequential integer ID access redirects with 301 to UUIDv7 canonical URL (Anti-IDOR / Anti-Enumeration).
     */
    public function test_legacy_integer_id_permanently_redirects_to_uuid_canonical_url(): void
    {
        [$user, $call] = $this->getPrerequisites();

        $response = $this->actingAs($user)->get("/calls/{$call->id}");

        $response->assertStatus(301);
        $response->assertRedirect("/calls/{$call->uuid}");
    }

    /**
     * Test that accessing canonical /calls/{uuid} succeeds with 200 OK for authenticated user.
     */
    public function test_canonical_uuid_access_returns_success_for_authorized_user(): void
    {
        [$user, $call] = $this->getPrerequisites();

        $response = $this->actingAs($user)->get("/calls/{$call->uuid}");
        $response->assertStatus(200);
        $response->assertSee($call->call_number);
    }

    /**
     * Test that unauthenticated guests cannot access call details.
     */
    public function test_unauthenticated_guests_are_redirected_to_login(): void
    {
        [$user, $call] = $this->getPrerequisites();

        $response = $this->get("/calls/{$call->uuid}");
        $response->assertRedirect('/login');
    }

    /**
     * Test status transition works via legacy integer ID (e.g., POST /calls/{id}/status).
     */
    public function test_status_transition_works_with_legacy_integer_id(): void
    {
        [$user, $call] = $this->getPrerequisites();

        $response = $this->actingAs($user)->postJson("/calls/{$call->id}/status", [
            'status' => 'in_progress',
            'remarks' => 'Patrol officer dispatched to location',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'in_progress');

        $this->assertEquals('in_progress', $call->fresh()->status);
    }

    /**
     * Test status transition works via canonical UUID (e.g., POST /calls/{uuid}/status).
     */
    public function test_status_transition_works_with_canonical_uuid(): void
    {
        [$user, $call] = $this->getPrerequisites();

        // Advance to in_progress first to adhere to state machine lifecycle (pending -> in_progress -> completed)
        $call->update(['status' => 'in_progress', 'inprogress_at' => now()]);

        $response = $this->actingAs($user)->postJson("/calls/{$call->uuid}/status", [
            'status' => 'completed',
            'remarks' => 'Assistance successfully rendered',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'completed');

        $this->assertEquals('completed', $call->fresh()->status);
    }

    /**
     * Test reminder API endpoint functions seamlessly with both integer ID and canonical UUID.
     */
    public function test_call_reminder_works_with_both_id_and_uuid(): void
    {
        Event::fake();
        [$user, $call] = $this->getPrerequisites();

        // 1. Integer ID
        $responseId = $this->actingAs($user)->postJson("/api/calls/{$call->id}/reminder");
        $responseId->assertStatus(200);
        $responseId->assertJsonPath('success', true);
        $responseId->assertJsonPath('call.call_reminder_count', 1);

        // 2. Canonical UUID
        $responseUuid = $this->actingAs($user)->postJson("/api/calls/{$call->uuid}/reminder");
        $responseUuid->assertStatus(200);
        $responseUuid->assertJsonPath('success', true);
        $responseUuid->assertJsonPath('call.call_reminder_count', 2);
    }
}

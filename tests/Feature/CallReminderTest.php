<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Call;
use App\Models\CallType;
use App\Models\CallSubType;
use App\Models\Office;
use Illuminate\Support\Facades\Event;
use App\Events\CallLogged;
use App\Services\CallService;
use Illuminate\Support\Str;

class CallReminderTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    private function getPrerequisites(): array
    {
        $role = \App\Models\Role::firstOrCreate(['name' => 'super_admin'], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'is_active' => true,
        ]);

        $user = User::firstOrCreate(['username' => 'test_call_agent'], [
            'uuid' => (string) Str::uuid(),
            'full_name' => 'Test Call Agent',
            'email' => 'test_call_agent@nhmp.gov.pk',
            'mobile_no' => '03001234500',
            'cnic' => '11111-1111111-1',
            'role_id' => $role->id,
            'password' => 'Secret12345!',
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $callType = CallType::firstOrCreate(['name' => 'Emergency Help'], [
            'category' => 'emergency',
            'is_active' => true,
        ]);

        $subType = CallSubType::firstOrCreate(['name' => 'Accident Response', 'call_type_id' => $callType->id], [
            'is_active' => true,
            'priority' => 3,
        ]);

        $zone = Office::firstOrCreate(['name' => 'Test Zone', 'type' => 'zone'], ['uuid' => (string) Str::uuid(), 'is_active' => true]);
        $sector = Office::firstOrCreate(['name' => 'Test Sector', 'type' => 'sector', 'parent_id' => $zone->id], ['uuid' => (string) Str::uuid(), 'is_active' => true]);
        $beat = Office::firstOrCreate(['name' => 'Test Beat', 'type' => 'beat', 'parent_id' => $sector->id], ['uuid' => (string) Str::uuid(), 'is_active' => true]);

        return [$user, $callType, $subType, $beat];
    }

    public function test_can_save_call_with_location_details(): void
    {
        [$user, $callType, $subType, $beat] = $this->getPrerequisites();

        $response = $this->actingAs($user)->post('/calls', [
            'caller_number' => '03001234567',
            'caller_name' => 'Test Caller',
            'call_type_id' => $callType->id,
            'call_sub_type_id' => $subType->id,
            'beat_id' => $beat->id,
            'km_marker_text' => '120 NB',
            'location_details' => 'Near Rehman Masjid',
            'priority' => 3,
        ]);

        if ($response->exception) {
            $this->fail("Call creation threw exception: " . $response->exception->getMessage());
        }

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('calls', [
            'location_details' => 'Near Rehman Masjid',
        ]);

        $savedCall = Call::where('location_details', 'Near Rehman Masjid')->latest()->first();
        $this->assertNotNull($savedCall);
        $this->assertEquals('03001234567', $savedCall->caller_number);
    }

    public function test_can_send_reminder_via_api(): void
    {
        Event::fake();

        [$user, $callType, $subType, $beat] = $this->getPrerequisites();

        $callService = app(CallService::class);
        $callNumber = $callService->generateCallNumber();

        $call = Call::create([
            'uuid' => (string) Str::uuid(),
            'call_number' => $callNumber,
            'agent_id' => $user->id,
            'caller_number' => '03001234567',
            'call_type_id' => $callType->id,
            'call_sub_type_id' => $subType->id,
            'office_id' => $beat->id,
            'status' => 'pending',
            'priority' => 3,
        ]);

        $response = $this->actingAs($user)->postJson("/api/calls/{$call->id}/reminder");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('call.call_reminder_count', 1);

        $this->assertDatabaseHas('calls', [
            'id' => $call->id,
            'call_reminder_count' => 1,
        ]);

        $this->assertNotNull($call->fresh()->last_reminder_at);

        Event::assertDispatched(CallLogged::class);
    }
}

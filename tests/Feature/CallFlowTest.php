<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Call;
use App\Models\CallType;
use App\Models\CallSubType;
use App\Models\Office;
use Illuminate\Support\Facades\DB;
use App\Events\CallLogged;
use Illuminate\Support\Facades\Event;

class CallFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    private function getAuthorizedUser(): User
    {
        return User::whereHas('role', fn($q) => $q->where('name', 'agent'))->first()
            ?: User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))->first()
            ?: User::first();
    }

    public function test_crm_agent_can_view_dashboard(): void
    {
        $user = $this->getAuthorizedUser();
        
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Command Center Analytics');
    }

    public function test_crm_agent_can_create_call_and_trigger_event(): void
    {
        Event::fake();

        $user = $this->getAuthorizedUser();
        $callType = CallType::first();
        $subType = CallSubType::where('call_type_id', $callType->id)->first();
        $beat = Office::where('type', 'beat')->first() ?: Office::first();
        
        $response = $this->actingAs($user)->post('/calls', [
            'caller_number' => '03001234567',
            'call_type_id' => $callType->id,
            'call_sub_type_id' => $subType->id,
            'beat_id' => $beat->id,
            'km_marker_text' => '120 NB',
            'caller_name' => 'John Doe',
            'details' => 'Vehicle broken down on M2',
        ]);

        $response->assertRedirect(route('calls.create'));
        $response->assertSessionHas('success');

        // Assert database values
        $call = Call::latest('id')->first();
        $this->assertNotNull($call);
        $this->assertEquals('03001234567', $call->caller_number);
        $this->assertEquals('pending', $call->status);
        $this->assertEquals('120 NB', $call->km_marker_text);
        $this->assertDatabaseHas('call_status_history', ['call_id' => $call->id]);

        // Assert broadcasting system deployed events securely 
        Event::assertDispatched(CallLogged::class, function ($event) use ($call) {
            return $event->callNumber === $call->call_number;
        });
    }

    public function test_agent_can_create_call_without_km_marker(): void
    {
        Event::fake();

        $user = $this->getAuthorizedUser();
        $callType = CallType::first();
        $subType = CallSubType::where('call_type_id', $callType->id)->first();
        $beat = Office::where('type', 'beat')->first() ?: Office::first();

        $response = $this->actingAs($user)->post('/calls', [
            'caller_number' => '03009876543',
            'call_type_id' => $callType->id,
            'call_sub_type_id' => $subType->id,
            'beat_id' => $beat->id,
            'km_marker_text' => null,
            'caller_name' => 'Jane Doe',
            'details' => 'No KM marker provided by caller',
        ]);

        $response->assertRedirect(route('calls.create'));
        $response->assertSessionHas('success');

        $call = Call::latest('id')->first();
        $this->assertNotNull($call);
        $this->assertEquals('03009876543', $call->caller_number);
        $this->assertEquals('pending', $call->status);
        $this->assertNull($call->km_marker_text);
    }

    public function test_invalid_km_format_is_rejected(): void
    {
        $user = $this->getAuthorizedUser();
        $callType = CallType::first();
        $subType = CallSubType::where('call_type_id', $callType->id)->first();
        $beat = Office::where('type', 'beat')->first() ?: Office::first();

        $response = $this->actingAs($user)->post('/calls', [
            'caller_number' => '03009876543',
            'call_type_id' => $callType->id,
            'call_sub_type_id' => $subType->id,
            'beat_id' => $beat->id,
            'km_marker_text' => 'invalid-marker-!@#$',
            'caller_name' => 'Jane Doe',
            'details' => 'Invalid KM format test',
        ]);

        $response->assertSessionHasErrors(['km_marker_text']);
    }
}

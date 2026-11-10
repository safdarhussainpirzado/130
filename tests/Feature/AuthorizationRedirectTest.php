<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class AuthorizationRedirectTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_agent_is_redirected_from_dashboard_to_calls_create(): void
    {
        $agent = User::whereHas('role', fn($q) => $q->where('name', 'agent'))->first();
        if (!$agent) {
            $this->markTestSkipped('No agent user found in database.');
        }
        
        $response = $this->actingAs($agent)->get('/dashboard');
        
        $response->assertRedirect(route('calls.create'));
        $response->assertSessionHas('error', 'Unauthorized access. You have been redirected to your authorized workspace.');
    }

    public function test_supervisor_is_redirected_from_calls_create_to_calls_index(): void
    {
        $supervisor = User::whereHas('role', fn($q) => $q->where('name', 'agent_supervisor'))->first();
        if (!$supervisor) {
            $this->markTestSkipped('No supervisor user found in database.');
        }
        
        $response = $this->actingAs($supervisor)->get(route('calls.create'));
        
        $response->assertRedirect(route('calls.index'));
        $response->assertSessionHas('error', 'Unauthorized access. You have been redirected to your authorized workspace.');
    }

    public function test_supervisor_cannot_submit_call_creation_form(): void
    {
        $supervisor = User::whereHas('role', fn($q) => $q->where('name', 'agent_supervisor'))->first();
        if (!$supervisor) {
            $this->markTestSkipped('No supervisor user found in database.');
        }
        $callType = DB::table('call_types')->first();
        
        $response = $this->actingAs($supervisor)->post('/calls', [
            'caller_number' => '03009998877',
            'call_type_id' => $callType->id,
            'caller_name' => 'John Doe',
        ]);
        
        // Unauthorized state-changing mutations must be rejected with 403 Forbidden
        $response->assertStatus(403);
        
        $this->assertDatabaseMissing('calls', [
            'caller_number' => '03009998877',
        ]);
    }
}

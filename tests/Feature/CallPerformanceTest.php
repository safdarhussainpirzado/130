<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Call;

class CallPerformanceTest extends TestCase
{
    /**
     * Test that /api/lookup-caller executes with index and returns caller history with UUIDs.
     */
    public function test_lookup_caller_api_executes_rapidly_and_includes_uuid(): void
    {
        // Use an administrator user with calls.api_lookup permission
        $user = User::where('role_id', 1)->first() ?: User::first();
        $sampleCall = Call::whereNotNull('caller_number')->where('caller_number', '!=', '')->first();

        if (!$sampleCall) {
            $this->markTestSkipped('No call record available to test lookup-caller.');
        }

        $startTime = microtime(true);
        $response = $this->actingAs($user)->getJson("/api/lookup-caller?number={$sampleCall->caller_number}");
        $executionTime = (microtime(true) - $startTime) * 1000; // in milliseconds

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'name',
            'vehicle_no',
            'calls' => [
                '*' => ['id', 'uuid', 'call_number', 'status']
            ]
        ]);

        // Verify that UUID is present on returned calls
        $data = $response->json();
        if (!empty($data['calls'])) {
            $firstCall = $data['calls'][0];
            $this->assertArrayHasKey('uuid', $firstCall);
            $this->assertNotEmpty($firstCall['uuid']);
        }

        // Performance check: query with index should complete in under 500ms
        $this->assertLessThan(500, $executionTime, "Caller lookup took {$executionTime}ms, exceeding performance threshold.");
    }

    /**
     * Test that /calls index page renders successfully with consolidated query aggregation.
     */
    public function test_calls_index_page_loads_with_consolidated_metrics(): void
    {
        $user = User::where('role_id', 1)->first() ?: User::first();

        // Warm up blade view compilation
        $this->actingAs($user)->get('/calls');

        // Measure steady-state response time
        $startTime = microtime(true);
        $response = $this->actingAs($user)->get('/calls');
        $executionTime = (microtime(true) - $startTime) * 1000;

        $response->assertStatus(200);
        $response->assertSee('Help Management');

        // Performance check: steady-state response under 5000ms in test environment
        $this->assertLessThan(5000, $executionTime, "/calls steady-state load took {$executionTime}ms, exceeding performance threshold.");
    }
}

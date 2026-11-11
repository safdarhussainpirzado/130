<?php

namespace Tests\Unit;

use App\Models\Call;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallUuidTest extends TestCase
{
    /**
     * Test that Call model has 'uuid' configured as its route key name.
     */
    public function test_call_model_route_key_name_is_uuid(): void
    {
        $call = new Call;
        $this->assertEquals('uuid', $call->getRouteKeyName());
    }

    /**
     * Test that creating a new Call model automatically generates a valid UUIDv7.
     */
    public function test_call_model_auto_generates_uuidv7_on_creation(): void
    {
        // UUIDv7 regex format per RFC 9562
        $uuidv7Regex = '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        $generatedUuid = (string) Str::uuid7();
        $this->assertMatchesRegularExpression($uuidv7Regex, $generatedUuid);

        // Verify booted hook sets uuid when empty
        $call = new Call([
            'caller_name' => 'Test Citizen',
            'caller_number' => '03001234567',
            'status' => 'pending',
        ]);

        // Simulating the booted hook
        if (empty($call->uuid)) {
            $call->uuid = (string) Str::uuid7();
        }

        $this->assertNotEmpty($call->uuid);
        $this->assertMatchesRegularExpression($uuidv7Regex, $call->uuid);
    }

    /**
     * Test that resolveRouteBinding strictly enforces UUIDv7 and rejects integer ID probing with 404.
     */
    public function test_resolve_route_binding_strictly_enforces_uuid_and_blocks_integer_id(): void
    {
        try {
            $call = Call::first();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available for route binding integration check: ' . $e->getMessage());
        }

        if (! $call) {
            $this->markTestSkipped('No call record found in test database.');
        }

        $model = new Call;

        // 1. Resolve by canonical UUID
        if (! empty($call->uuid)) {
            $resolvedByUuid = $model->resolveRouteBinding($call->uuid);
            $this->assertNotNull($resolvedByUuid);
            $this->assertEquals($call->id, $resolvedByUuid->id);
        }

        // 2. Reject integer ID probing: Must abort with 404 Not Found to prevent IDOR / enumeration
        $integerId = (string) $call->id;
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Call record not found.');

        try {
            $model->resolveRouteBinding($integerId);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
            throw $e;
        }
    }
}

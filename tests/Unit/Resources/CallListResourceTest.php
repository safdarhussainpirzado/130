<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\CallListResource;
use App\Models\CallType;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\Support\CallFixtureFactory;
use Tests\TestCase;

/**
 * Positive and Negative Unit Tests for CallListResource.
 * Verifies strict data minimization, field exposure shielding, and preview truncation.
 */
class CallListResourceTest extends TestCase
{
    /**
     * Positive Scenario: CallListResource transforms all permitted fields correctly.
     */
    public function test_call_list_resource_includes_only_permitted_columns(): void
    {
        $call = CallFixtureFactory::make([
            'id' => 42,
            'call_number' => 'CRM-2026-00042',
            'caller_name' => 'Citizen Imran',
            'caller_number' => '03001234567',
            'status' => 'pending',
            'priority' => 1,
            'details' => str_repeat('A', 250), // 250 chars
        ]);

        $resource = new CallListResource($call);
        $result = $resource->toArray(Request::create('/calls'));

        // Permitted fields
        $this->assertEquals(42, $result['id']);
        $this->assertEquals('CRM-2026-00042', $result['call_number']);
        $this->assertEquals('Citizen Imran', $result['caller_name']);
        $this->assertEquals('03001234567', $result['caller_number']);
        $this->assertEquals('pending', $result['status']);
        $this->assertEquals(1, $result['priority']);

        // Positive check: details must be truncated to preview (<= 123 chars with ellipsis)
        $this->assertLessThanOrEqual(123, strlen($result['details']));
        $this->assertStringEndsWith('...', $result['details']);
    }

    /**
     * Negative Scenario: CallListResource strictly excludes internal sensitive fields.
     */
    public function test_call_list_resource_strictly_excludes_sensitive_fields(): void
    {
        $call = CallFixtureFactory::make();
        // Artificially inject sensitive internal fields
        $call->setAttribute('agent_sip_password', 'SuperSecretPass123!');
        $call->setAttribute('caller_ip', '192.168.1.50');
        $call->setAttribute('server_token', 'token_xyz_secret');
        $call->setAttribute('raw_payload', '{"debug": true}');

        $resource = new CallListResource($call);
        $result = $resource->toArray(Request::create('/calls'));

        $this->assertArrayNotHasKey('agent_sip_password', $result);
        $this->assertArrayNotHasKey('caller_ip', $result);
        $this->assertArrayNotHasKey('server_token', $result);
        $this->assertArrayNotHasKey('raw_payload', $result);
        $this->assertArrayNotHasKey('created_by_ip', $result);
    }

    /**
     * Negative Scenario: CallListResource safely handles unloaded relations without exceptions.
     */
    public function test_call_list_resource_handles_null_and_unloaded_relations(): void
    {
        $call = CallFixtureFactory::make([
            'office_id' => null,
            'agent_id' => null,
            'call_type_id' => null,
            'call_sub_type_id' => null,
            'vehicle_type_id' => null,
            'details' => null,
        ]);

        $resource = new CallListResource($call);
        $result = $resource->toArray(Request::create('/calls'));

        $this->assertEquals('OFF-GRID', $result['location']);
        $this->assertNull($result['office_id']);
        $this->assertNull($result['agent']);
        $this->assertNull($result['call_type']);
        $this->assertNull($result['call_sub_type']);
        $this->assertNull($result['vehicle_type']);
        $this->assertNull($result['details']);
    }

    /**
     * Positive Scenario: CallListResource formats loaded relations with selective attributes.
     */
    public function test_call_list_resource_formats_loaded_relations(): void
    {
        $call = CallFixtureFactory::make();

        $callType = new CallType;
        $callType->id = 1;
        $callType->name = 'Emergency';
        $callType->icon = 'fa-bell';
        $call->setRelation('callType', $callType);

        $agent = new User;
        $agent->id = 99;
        $agent->full_name = 'Officer Asim';
        $agent->username = 'asim99';
        $call->setRelation('agent', $agent);

        $resource = new CallListResource($call);
        $result = $resource->toArray(Request::create('/calls'));

        $this->assertEquals(['name' => 'Emergency'], $result['call_type']);
        $this->assertEquals(['id' => 99, 'name' => 'Officer Asim', 'full_name' => 'Officer Asim'], $result['agent']);
    }
}

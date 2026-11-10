<?php

namespace Tests\Unit\Resources;

use App\Http\Resources\CallShowResource;
use App\Models\Call;
use App\Models\CallSubType;
use App\Models\CallType;
use App\Models\Carriageway;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tests\TestCase;

class CallShowResourceTest extends TestCase
{
    public function test_call_show_resource_renders_cleanly_with_null_relations(): void
    {
        $call = new Call([
            'call_number' => 'CRM-2026-000101',
            'caller_name' => 'Citizen Test',
            'caller_number' => '03001234567',
            'status' => 'pending',
            'priority' => 2,
            'details' => 'Emergency breakdown on motorway',
            'location_details' => 'Southbound near toll',
            'caller_lat' => 33.6844,
            'caller_lng' => 73.0479,
        ]);
        $call->id = 101;

        $request = Request::create('/api/calls/101', 'GET');
        $resource = new CallShowResource($call);
        $data = $resource->toArray($request);

        $this->assertEquals(101, $data['id']);
        $this->assertEquals('CRM-2026-000101', $data['call_number']);
        $this->assertEquals('Citizen Test', $data['caller_name']);
        $this->assertEquals('03001234567', $data['caller_number']);
        $this->assertEquals('pending', $data['status']);
        $this->assertEquals(2, $data['priority']);
        $this->assertNull($data['call_type']);
        $this->assertNull($data['office']);
        $this->assertNull($data['agent']);
        $this->assertNull($data['carriageway']);
        $this->assertNull($data['vehicle_type']);
    }

    public function test_call_show_resource_eager_loads_office_hierarchy(): void
    {
        $zone = new Office(['name' => 'North Zone', 'type' => 'zone']);
        $zone->id = 1;
        $sector = new Office(['name' => 'Sector M-2 North', 'type' => 'sector']);
        $sector->id = 2;
        $sector->setRelation('parent', $zone);

        $beat = new Office(['name' => 'Beat 01', 'type' => 'beat']);
        $beat->id = 3;
        $beat->setRelation('parent', $sector);

        $call = new Call([
            'call_number' => 'CRM-2026-000102',
            'status' => 'in_progress',
            'office_id' => 3,
        ]);
        $call->id = 102;
        $call->setRelation('office', $beat);

        $request = Request::create('/api/calls/102', 'GET');
        $resource = new CallShowResource($call);
        $data = $resource->toArray($request);

        $this->assertNotNull($data['office']);
        $this->assertEquals('Beat 01', $data['office']['beat']);
        $this->assertEquals('Sector M-2 North', $data['office']['sector']);
        $this->assertEquals('North Zone', $data['office']['zone']);
    }

    public function test_call_show_resource_never_leaks_sensitive_security_attributes(): void
    {
        $agentRole = new Role(['id' => 5, 'name' => 'agent', 'display_name' => 'Call Center Agent']);
        $agent = new User([
            'full_name' => 'Agent Ahmad',
            'username' => 'ahmad_agent',
            'password' => '$2y$10$encryptedSecretHashExample',
            'two_factor_secret' => 'SECRET_TOTP_SEED_KEY',
            'two_factor_recovery_codes' => '["CODE-1234","CODE-5678"]',
            'totp_last_step' => 12345,
            'ip_address' => '10.0.0.99',
        ]);
        $agent->id = 42;
        $agent->setRelation('role', $agentRole);

        $call = new Call([
            'call_number' => 'CRM-2026-000103',
            'status' => 'completed',
            'agent_id' => 42,
        ]);
        $call->id = 103;
        $call->setRelation('agent', $agent);

        $request = Request::create('/api/calls/103', 'GET');
        $resource = new CallShowResource($call);
        $data = $resource->toArray($request);

        $serializedJson = json_encode($data);

        // Assert strictly zero leakage of sensitive security fields
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('two_factor_secret', $data);
        $this->assertArrayNotHasKey('two_factor_recovery_codes', $data);
        $this->assertArrayNotHasKey('totp_last_step', $data);

        $this->assertStringNotContainsString('SECRET_TOTP_SEED_KEY', $serializedJson);
        $this->assertStringNotContainsString('encryptedSecretHashExample', $serializedJson);
        $this->assertStringNotContainsString('CODE-1234', $serializedJson);
    }
}

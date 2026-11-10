<?php

namespace Tests\Feature;

use App\Http\Resources\CallListResource;
use App\Http\Resources\CallShowResource;
use App\Http\Resources\UserListResource;
use App\Http\Resources\UserShowResource;
use App\Models\Call;
use App\Models\CallType;
use App\Models\CallSubType;
use App\Models\Designation;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class DataMinimizationAndSecurityTest extends TestCase
{
    /**
     * Test that CallListResource strictly omits sensitive agent credentials,
     * MFA secrets, and security telemetry.
     */
    public function test_call_list_resource_strictly_protects_sensitive_data(): void
    {
        $agent = new User([
            'id' => 9991,
            'username' => 'test_agent_alpha',
            'full_name' => 'Agent Alpha',
            'email' => 'agent.alpha@example.gov.pk',
            'mobile_no' => '03001234567',
            'cnic' => '35201-1234567-1',
            'password' => 'secret_hash_value',
            'remember_token' => 'remember_token_secret',
            'totp_secret' => 'encrypted_totp_secret_string',
            'failed_attempts' => 2,
            'locked_until' => now()->addMinutes(15),
            'last_login_ip' => '10.0.0.45',
            'security_version' => 3,
            'is_active' => true,
        ]);
        $agent->id = 9991;

        $callType = new CallType([
            'id' => 1,
            'name' => 'Emergency',
            'icon' => 'fa-phone-volume',
        ]);
        $callType->id = 1;

        $callSubType = new CallSubType([
            'id' => 10,
            'name' => 'Accident With Injury',
            'icon' => 'fa-car-burst',
        ]);
        $callSubType->id = 10;

        $call = new Call([
            'id' => 8881,
            'call_number' => 'CRM-2026-0008881',
            'caller_name' => 'Citizen Test',
            'caller_number' => '03009876543',
            'status' => 'pending',
            'priority' => 1,
            'agent_id' => 9991,
            'call_type_id' => 1,
            'call_sub_type_id' => 10,
            'details' => 'A very long confidential detailed description of an incident on the motorway that exceeds one hundred characters for data minimization testing purposes.',
            'pending_remarks' => 'Internal sensitive dispatch note',
            'inprogress_remarks' => 'Patrol allocated',
        ]);
        $call->id = 8881;
        $call->setRelation('agent', $agent);
        $call->setRelation('callType', $callType);
        $call->setRelation('callSubType', $callSubType);
        $call->setRelation('office', null);
        $call->setRelation('vehicleType', null);

        $request = Request::create('/api/calls', 'GET');
        $resource = (new CallListResource($call))->toArray($request);

        // Positive assertions: Required visible fields are present
        $this->assertEquals(8881, $resource['id']);
        $this->assertEquals('CRM-2026-0008881', $resource['call_number']);
        $this->assertEquals('Citizen Test', $resource['caller_name']);
        $this->assertEquals('03009876543', $resource['caller_number']);
        $this->assertEquals('pending', $resource['status']);
        $this->assertEquals(1, $resource['priority']);
        $this->assertEquals('Emergency', $resource['call_type']['name']);
        $this->assertEquals('Accident With Injury', $resource['call_sub_type']['name']);

        // Agent sanitization: Only id and full_name permitted
        $this->assertEquals(9991, $resource['agent']['id']);
        $this->assertEquals('Agent Alpha', $resource['agent']['name']);
        $this->assertArrayNotHasKey('email', $resource['agent']);
        $this->assertArrayNotHasKey('password', $resource['agent']);
        $this->assertArrayNotHasKey('totp_secret', $resource['agent']);
        $this->assertArrayNotHasKey('remember_token', $resource['agent']);
        $this->assertArrayNotHasKey('last_login_ip', $resource['agent']);
        $this->assertArrayNotHasKey('failed_attempts', $resource['agent']);
        $this->assertArrayNotHasKey('locked_until', $resource['agent']);
        $this->assertArrayNotHasKey('security_version', $resource['agent']);

        // Description preview is truncated to <= 100 chars (no unbounded large text)
        $this->assertNotNull($resource['details_preview']);
        $this->assertLessThanOrEqual(103, strlen($resource['details_preview'])); // 100 + '...'

        // Internal remarks must NOT be present in list resource
        $this->assertArrayNotHasKey('pending_remarks', $resource);
        $this->assertArrayNotHasKey('inprogress_remarks', $resource);
        $this->assertArrayNotHasKey('tiger_reached_remarks', $resource);
        $this->assertArrayNotHasKey('completed_remarks', $resource);
    }

    /**
     * Test that UserListResource strictly omits sensitive credentials,
     * password hashes, MFA recovery data, and login telemetry.
     */
    public function test_user_list_resource_strictly_protects_credentials(): void
    {
        $role = new Role([
            'id' => 2,
            'name' => 'beat_operator',
            'display_name' => 'Beat Operator',
        ]);
        $role->id = 2;

        $designation = new Designation([
            'id' => 5,
            'name' => 'Sub Inspector',
            'short_code' => 'SI',
            'bps' => 14,
        ]);
        $designation->id = 5;

        $user = new User([
            'id' => 7771,
            'username' => 'officer_7771',
            'full_name' => 'Muhammad Aslam',
            'email' => 'm.aslam@nhmp.gov.pk',
            'mobile_no' => '03005551234',
            'cnic' => '35202-7654321-1',
            'password' => '$2y$10$hashedPasswordThatMustNeverLeakToClientEndpoints',
            'remember_token' => 'secret_remember_token_string',
            'totp_secret' => 'encrypted_base32_totp_secret_key',
            'totp_last_step' => 58941023,
            'security_version' => 4,
            'failed_attempts' => 1,
            'locked_until' => null,
            'last_login_ip' => '192.168.10.88',
            'is_active' => true,
        ]);
        $user->id = 7771;
        $user->setRelation('role', $role);
        $user->setRelation('designation', $designation);
        $user->setRelation('activeScopes', collect([]));

        $request = Request::create('/mgmt/users', 'GET');
        $resource = (new UserListResource($user))->toArray($request);

        // Positive assertions
        $this->assertEquals(7771, $resource['id']);
        $this->assertEquals('officer_7771', $resource['username']);
        $this->assertEquals('Muhammad Aslam', $resource['full_name']);
        $this->assertEquals('m.aslam@nhmp.gov.pk', $resource['email']);
        $this->assertEquals('03005551234', $resource['mobile_no']);
        $this->assertEquals('35202-7654321-1', $resource['cnic']);
        $this->assertTrue($resource['is_active']);
        $this->assertEquals('Beat Operator', $resource['role']['display_name']);
        $this->assertEquals('Sub Inspector', $resource['designation']['name']);
        $this->assertEquals(14, $resource['designation']['bps']);

        // Zero Sensitive Information assertions
        $this->assertArrayNotHasKey('password', $resource);
        $this->assertArrayNotHasKey('remember_token', $resource);
        $this->assertArrayNotHasKey('totp_secret', $resource);
        $this->assertArrayNotHasKey('totp_last_step', $resource);
        $this->assertArrayNotHasKey('security_version', $resource);
        $this->assertArrayNotHasKey('failed_attempts', $resource);
        $this->assertArrayNotHasKey('locked_until', $resource);
        $this->assertArrayNotHasKey('last_login_ip', $resource);
    }

    /**
     * Test that User model default serialization hides security fields
     * even if accidentally cast to array or JSON.
     */
    public function test_user_model_hidden_attributes_protect_against_accidental_serialization(): void
    {
        $user = new User([
            'id' => 5551,
            'username' => 'test_user_leak_check',
            'full_name' => 'Safety Test User',
            'email' => 'safety@example.gov.pk',
            'mobile_no' => '03001112233',
            'cnic' => '35201-1112233-1',
            'password' => 'super_secret_hash',
            'remember_token' => 'token_secret',
            'totp_secret' => 'totp_secret_val',
            'totp_last_step' => 123456,
            'security_version' => 2,
            'failed_attempts' => 3,
            'locked_until' => now()->addMinutes(30),
            'last_login_ip' => '172.16.0.99',
            'is_active' => true,
        ]);

        $serialized = $user->toArray();

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
        $this->assertArrayNotHasKey('totp_secret', $serialized);
        $this->assertArrayNotHasKey('totp_last_step', $serialized);
        $this->assertArrayNotHasKey('security_version', $serialized);
        $this->assertArrayNotHasKey('failed_attempts', $serialized);
        $this->assertArrayNotHasKey('locked_until', $serialized);
        $this->assertArrayNotHasKey('last_login_ip', $serialized);
    }

    /**
     * Test that CallShowResource reveals full details and status history
     * on demand, while redacting administrative agent fields for unprivileged users.
     */
    public function test_call_show_resource_loads_details_with_role_based_redaction(): void
    {
        $agent = new User([
            'id' => 4441,
            'username' => 'agent_secret_agent',
            'full_name' => 'Agent Tariq',
            'password' => 'secret_hash',
            'totp_secret' => 'totp_secret_val',
        ]);
        $agent->id = 4441;

        $call = new Call([
            'id' => 3331,
            'call_number' => 'CRM-2026-0003331',
            'caller_name' => 'Test Citizen',
            'caller_number' => '03001239876',
            'status' => 'in_progress',
            'priority' => 2,
            'details' => 'Full incident narrative with detailed caller report.',
            'inprogress_remarks' => 'Allocated to Tiger-3',
            'tiger_id' => 'T3',
            'agent_id' => 4441,
        ]);
        $call->id = 3331;
        $call->setRelation('agent', $agent);
        $call->setRelation('callType', null);
        $call->setRelation('callSubType', null);
        $call->setRelation('office', null);
        $call->setRelation('carriageway', null);
        $call->setRelation('vehicleType', null);

        // 1. Regular beat_operator request (non-privileged)
        $beatOperator = new User(['id' => 101, 'role_id' => 3]);
        $beatRole = new Role(['id' => 3, 'name' => 'beat_operator']);
        $beatOperator->setRelation('role', $beatRole);

        $request = Request::create('/calls/3331', 'GET');
        $request->setUserResolver(fn() => $beatOperator);

        $showResource = (new CallShowResource($call))->toArray($request);

        $this->assertEquals('Full incident narrative with detailed caller report.', $showResource['details']);
        $this->assertEquals('Allocated to Tiger-3', $showResource['inprogress_remarks']);
        $this->assertEquals('Agent Tariq', $showResource['agent']['full_name']);
        // Non-privileged: agent username is redacted
        $this->assertNull($showResource['agent']['username']);
        // Credentials never present
        $this->assertArrayNotHasKey('password', $showResource['agent']);
        $this->assertArrayNotHasKey('totp_secret', $showResource['agent']);

        // 2. Privileged admin request
        $adminUser = new User(['id' => 1, 'role_id' => 1]);
        $adminRole = new Role(['id' => 1, 'name' => 'super_admin']);
        $adminUser->setRelation('role', $adminRole);

        $adminRequest = Request::create('/calls/3331', 'GET');
        $adminRequest->setUserResolver(fn() => $adminUser);

        $adminShowResource = (new CallShowResource($call))->toArray($adminRequest);
        $this->assertEquals('agent_secret_agent', $adminShowResource['agent']['username']);
        $this->assertArrayNotHasKey('password', $adminShowResource['agent']);
    }
}

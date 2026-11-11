<?php

namespace Tests\Unit;

use App\Models\Call;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use App\Policies\CallPolicy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CallAuthorizationPolicyTest extends TestCase
{
    private CallPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CallPolicy();
        Office::clearHierarchyCache();

        // Setup mock organizational tree:
        // Region 1 -> Zone 10 -> Sector 11 -> Beat 12, Beat 13
        // Region 1 -> Zone 20 -> Sector 21 -> Beat 22
        $mockTree = [
            1 => [
                ['id' => 10, 'parent_id' => 1, 'is_active' => true],
                ['id' => 20, 'parent_id' => 1, 'is_active' => true],
            ],
            10 => [
                ['id' => 11, 'parent_id' => 10, 'is_active' => true],
            ],
            11 => [
                ['id' => 12, 'parent_id' => 11, 'is_active' => true],
                ['id' => 13, 'parent_id' => 11, 'is_active' => true],
            ],
            20 => [
                ['id' => 21, 'parent_id' => 20, 'is_active' => true],
            ],
            21 => [
                ['id' => 22, 'parent_id' => 21, 'is_active' => true],
            ],
        ];

        Cache::put('crm:all_offices_tree_v1', $mockTree, 3600);
    }

    protected function tearDown(): void
    {
        Office::clearHierarchyCache();
        parent::tearDown();
    }

    private function makeUserWithRoleAndScope(
        string $roleName,
        ?int $scopedOfficeId = null,
        array $permissions = ['calls.view'],
        string $accessLevel = 'read_write',
        bool $isActive = true,
        string $roleStatus = 'active'
    ): User {
        $scopeLevel = in_array($roleName, [Role::SUPER_ADMIN, Role::IT_ADMIN], true)
            ? 'national'
            : (in_array($roleName, [Role::AGENT_SUPERVISOR, Role::AGENT], true) ? 'call_centre' : 'zone');

        $role = new Role([
            'name' => $roleName,
            'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
            'scope_level' => $scopeLevel,
            'status' => $roleStatus,
        ]);
        $permModels = collect($permissions)->map(fn($p) => new Permission(['name' => $p]));
        $role->setRelation('permissions', $permModels);

        $user = new User([
            'username' => 'test_' . $roleName,
            'is_active' => $isActive,
        ]);
        $user->id = rand(100, 999);
        $user->setRelation('role', $role);

        if ($scopedOfficeId !== null) {
            $scope = new UserScope([
                'office_id' => $scopedOfficeId,
                'access_level' => $accessLevel,
                'is_active' => true,
            ]);
            $user->setRelation('activeScopes', collect([$scope]));
        } else {
            $user->setRelation('activeScopes', collect());
        }

        return $user;
    }

    public function test_inactive_or_suspended_users_fail_closed(): void
    {
        // 1. Inactive user account
        $inactiveAdmin = $this->makeUserWithRoleAndScope(Role::SUPER_ADMIN, null, ['calls.view', 'calls.create'], 'full', false);
        $call = new Call(['office_id' => 12]);

        $this->assertFalse($this->policy->viewAny($inactiveAdmin));
        $this->assertFalse($this->policy->create($inactiveAdmin));
        $this->assertFalse($this->policy->view($inactiveAdmin, $call));
        $this->assertFalse($this->policy->update($inactiveAdmin, $call));
        $this->assertFalse($this->policy->delete($inactiveAdmin, $call));
        $this->assertFalse($this->policy->export($inactiveAdmin));

        // 2. Inactive role status
        $inactiveRoleUser = $this->makeUserWithRoleAndScope(Role::SUPER_ADMIN, null, ['calls.view'], 'full', true, 'inactive');
        $this->assertFalse($this->policy->viewAny($inactiveRoleUser));
        $this->assertFalse($this->policy->view($inactiveRoleUser, $call));
    }

    public function test_missing_permission_fails_closed_even_for_privileged_roles(): void
    {
        // Operation admin WITHOUT calls.view permission
        $opAdminNoView = $this->makeUserWithRoleAndScope('operation_admin', null, ['users.view']);
        $call = new Call(['office_id' => 12]);

        $this->assertFalse($this->policy->viewAny($opAdminNoView), 'Operation admin without calls.view must fail closed');
        $this->assertFalse($this->policy->view($opAdminNoView, $call), 'Operation admin without calls.view must fail closed');

        // Operation admin WITH calls.view but WITHOUT calls.delete
        $opAdminViewOnly = $this->makeUserWithRoleAndScope('operation_admin', null, ['calls.view']);
        $this->assertTrue($this->policy->view($opAdminViewOnly, $call));
        $this->assertFalse($this->policy->delete($opAdminViewOnly, $call), 'Must fail closed on delete without calls.delete');
    }

    public function test_null_or_invalid_scope_office_id_fails_closed(): void
    {
        // Operational user whose scope row has office_id = null (invalid / unassigned scope)
        $userNullOffice = $this->makeUserWithRoleAndScope('zone_admin', null);
        $invalidScope = new UserScope(['office_id' => null, 'access_level' => 'read_write', 'is_active' => true]);
        $userNullOffice->setRelation('activeScopes', collect([$invalidScope]));

        $call = new Call(['office_id' => 12]);
        $this->assertFalse($this->policy->view($userNullOffice, $call), 'Null scope office_id must fail closed, never return true');

        // Empty active scopes
        $userNoScopes = $this->makeUserWithRoleAndScope('zone_admin', null);
        $this->assertFalse($this->policy->view($userNoScopes, $call), 'Empty scopes must fail closed');
    }

    public function test_scope_write_and_delete_capability_enforcement(): void
    {
        // 1. User with read_only access level on Beat 12
        $readOnlyUser = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.view', 'calls.update', 'calls.delete'], 'read_only');
        $call = new Call(['office_id' => 12]);

        $this->assertTrue($this->policy->view($readOnlyUser, $call));
        $this->assertFalse($this->policy->update($readOnlyUser, $call), 'read_only scope must not permit update');
        $this->assertFalse($this->policy->delete($readOnlyUser, $call), 'read_only scope must not permit delete');

        // 2. User with read_write access level on Beat 12
        $readWriteUser = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.view', 'calls.update', 'calls.delete'], 'read_write');
        $this->assertTrue($this->policy->view($readWriteUser, $call));
        $this->assertTrue($this->policy->update($readWriteUser, $call));
        $this->assertFalse($this->policy->delete($readWriteUser, $call), 'read_write scope must not permit delete');

        // 3. User with full access level on Beat 12
        $fullUser = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.view', 'calls.update', 'calls.delete'], 'full');
        $this->assertTrue($this->policy->delete($fullUser, $call), 'full scope must permit delete');
    }

    public function test_super_admin_has_universal_access_with_permission(): void
    {
        $superAdmin = $this->makeUserWithRoleAndScope(Role::SUPER_ADMIN, null, ['calls.view', 'calls.update', 'calls.delete', 'calls.export']);

        $callInBeat12 = new Call(['office_id' => 12, 'agent_id' => 99]);
        $unassignedCall = new Call(['office_id' => null, 'agent_id' => null]);

        $this->assertTrue($this->policy->view($superAdmin, $callInBeat12));
        $this->assertTrue($this->policy->view($superAdmin, $unassignedCall));
        $this->assertTrue($this->policy->update($superAdmin, $callInBeat12));
        $this->assertTrue($this->policy->delete($superAdmin, $callInBeat12));
        $this->assertTrue($this->policy->export($superAdmin));
    }

    public function test_agent_can_only_view_own_calls(): void
    {
        $agent = $this->makeUserWithRoleAndScope('agent', null, ['calls.view']);
        $agent->id = 42;

        $ownCall = new Call(['office_id' => 12, 'agent_id' => 42]);
        $otherCall = new Call(['office_id' => 12, 'agent_id' => 99]);
        $unassignedCall = new Call(['office_id' => null, 'agent_id' => null]);

        $this->assertTrue($this->policy->view($agent, $ownCall));
        $this->assertFalse($this->policy->view($agent, $otherCall), 'Agent cannot access other agents calls');
        $this->assertFalse($this->policy->view($agent, $unassignedCall), 'Agent cannot access unassigned calls');
    }

    public function test_zone_admin_can_view_descendant_units_but_not_cross_zone(): void
    {
        // User scoped to Zone 10
        $zoneAdmin = $this->makeUserWithRoleAndScope('zone_admin', 10, ['calls.view']);

        $callInZone10 = new Call(['office_id' => 10]);
        $callInSector11 = new Call(['office_id' => 11]);
        $callInBeat12 = new Call(['office_id' => 12]);
        $callInBeat13 = new Call(['office_id' => 13]);

        // Calls inside Zone 10 tree
        $this->assertTrue($this->policy->view($zoneAdmin, $callInZone10));
        $this->assertTrue($this->policy->view($zoneAdmin, $callInSector11));
        $this->assertTrue($this->policy->view($zoneAdmin, $callInBeat12));
        $this->assertTrue($this->policy->view($zoneAdmin, $callInBeat13));

        // Calls inside Zone 20 (Cross-zone: MUST BE DENIED)
        $callInZone20 = new Call(['office_id' => 20]);
        $callInBeat22 = new Call(['office_id' => 22]);
        $this->assertFalse($this->policy->view($zoneAdmin, $callInZone20));
        $this->assertFalse($this->policy->view($zoneAdmin, $callInBeat22));
    }

    public function test_sector_admin_can_view_own_sector_and_beats_only(): void
    {
        // Scoped to Sector 11
        $sectorAdmin = $this->makeUserWithRoleAndScope('sector_admin', 11, ['calls.view']);

        $callInSector11 = new Call(['office_id' => 11]);
        $callInBeat12 = new Call(['office_id' => 12]);
        $callInBeat13 = new Call(['office_id' => 13]);

        $this->assertTrue($this->policy->view($sectorAdmin, $callInSector11));
        $this->assertTrue($this->policy->view($sectorAdmin, $callInBeat12));
        $this->assertTrue($this->policy->view($sectorAdmin, $callInBeat13));

        // Calls in parent Zone 10 or Sector 21 (Cross-sector: MUST BE DENIED)
        $callInZone10 = new Call(['office_id' => 10]);
        $callInSector21 = new Call(['office_id' => 21]);
        $this->assertFalse($this->policy->view($sectorAdmin, $callInZone10));
        $this->assertFalse($this->policy->view($sectorAdmin, $callInSector21));
    }

    public function test_beat_operator_can_only_view_assigned_beat(): void
    {
        // Scoped to Beat 12
        $beatOperator = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.view']);

        $callInBeat12 = new Call(['office_id' => 12]);
        $callInBeat13 = new Call(['office_id' => 13]);
        $callInSector11 = new Call(['office_id' => 11]);

        $this->assertTrue($this->policy->view($beatOperator, $callInBeat12));
        $this->assertFalse($this->policy->view($beatOperator, $callInBeat13));
        $this->assertFalse($this->policy->view($beatOperator, $callInSector11));
    }

    public function test_operational_scoped_users_cannot_access_unassigned_calls(): void
    {
        $zoneAdmin = $this->makeUserWithRoleAndScope('zone_admin', 10, ['calls.view']);
        $sectorAdmin = $this->makeUserWithRoleAndScope('sector_admin', 11, ['calls.view']);
        $beatOperator = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.view']);

        $unassignedCall1 = new Call(['office_id' => null]);
        $unassignedCall2 = new Call(['office_id' => 0]);

        $this->assertFalse($this->policy->view($zoneAdmin, $unassignedCall1));
        $this->assertFalse($this->policy->view($zoneAdmin, $unassignedCall2));

        $this->assertFalse($this->policy->view($sectorAdmin, $unassignedCall1));
        $this->assertFalse($this->policy->view($sectorAdmin, $unassignedCall2));

        $this->assertFalse($this->policy->view($beatOperator, $unassignedCall1));
        $this->assertFalse($this->policy->view($beatOperator, $unassignedCall2));
    }

    /**
     * Benchmark authorization latency: 1,000 policy checks through in-memory cached tree.
     */
    public function test_authorization_latency_benchmark(): void
    {
        $zoneAdmin = $this->makeUserWithRoleAndScope('zone_admin', 10, ['calls.view']);
        $call = new Call(['office_id' => 12]);

        $latencies = [];
        for ($i = 0; $i < 1000; $i++) {
            $t0 = microtime(true);
            $authorized = $this->policy->view($zoneAdmin, $call);
            $latencies[] = (microtime(true) - $t0) * 1000000; // microseconds
            $this->assertTrue($authorized);
        }

        sort($latencies);
        $p50 = $latencies[499];
        $p95 = $latencies[949];
        $p99 = $latencies[989];

        // Ensure sub-millisecond evaluation (< 250 microseconds per check)
        $this->assertLessThan(250, $p50, "p50 authorization latency should be < 250 microseconds");
        $this->assertLessThan(500, $p95, "p95 authorization latency should be < 500 microseconds");
    }

    /**
     * Test real Laravel authorization mechanisms via Gate and $user->can().
     */
    public function test_laravel_gate_integration_permits_and_denies(): void
    {
        $zoneAdmin = $this->makeUserWithRoleAndScope('zone_admin', 10, ['calls.view', 'calls.update'], 'read_write');
        $callInZone = new Call(['office_id' => 12]);
        $callCrossZone = new Call(['office_id' => 22]);

        // Permitted access through Gate
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($zoneAdmin)->allows('view', $callInZone));
        $this->assertTrue(\Illuminate\Support\Facades\Gate::forUser($zoneAdmin)->allows('update', $callInZone));

        // Denied access through Gate
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($zoneAdmin)->allows('view', $callCrossZone));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($zoneAdmin)->allows('update', $callCrossZone));
        $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($zoneAdmin)->allows('delete', $callInZone));
    }

    /**
     * Test manageStatus requires explicit calls.manage_status permission and respects organizational boundaries.
     */
    public function test_manage_status_requires_permission_and_scope(): void
    {
        $callInBeat12 = new Call(['office_id' => 12]);
        $callInBeat22 = new Call(['office_id' => 22]);

        // 1. Beat operator with calls.manage_status on Beat 12
        $beatOperator = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.manage_status'], 'read_write');
        $this->assertTrue($this->policy->manageStatus($beatOperator, $callInBeat12));
        $this->assertFalse($this->policy->manageStatus($beatOperator, $callInBeat22), 'Cross-beat status change must be denied');

        // 2. User with calls.update but WITHOUT calls.manage_status cannot manage status
        $userUpdateOnly = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.update'], 'read_write');
        $this->assertFalse($this->policy->manageStatus($userUpdateOnly, $callInBeat12), 'calls.update alone must not permit status management');

        // 3. User with calls.manage_status but read_only access_level cannot manage status
        $readOnlyUser = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.manage_status'], 'read_only');
        $this->assertFalse($this->policy->manageStatus($readOnlyUser, $callInBeat12), 'read_only scope must not permit status updates');
    }

    /**
     * Test create call requires active user and calls.create permission.
     */
    public function test_create_call_authorization(): void
    {
        $agentWithCreate = $this->makeUserWithRoleAndScope('agent', null, ['calls.create']);
        $agentWithoutCreate = $this->makeUserWithRoleAndScope('agent', null, ['calls.view']);
        $inactiveAgent = $this->makeUserWithRoleAndScope('agent', null, ['calls.create'], 'read_write', false);

        $this->assertTrue($this->policy->create($agentWithCreate));
        $this->assertFalse($this->policy->create($agentWithoutCreate));
        $this->assertFalse($this->policy->create($inactiveAgent));
    }

    /**
     * Test export requires calls.export and valid organizational scope.
     */
    public function test_export_requires_permission_and_valid_scope(): void
    {
        // 1. Beat operator with calls.export and active scope on Beat 12
        $beatOpWithExport = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.export']);
        $this->assertTrue($this->policy->export($beatOpWithExport));

        // 2. Beat operator without calls.export
        $beatOpNoExport = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.view']);
        $this->assertFalse($this->policy->export($beatOpNoExport));

        // 3. Operational user with calls.export but no active scopes
        $userNoScopes = $this->makeUserWithRoleAndScope('sector_admin', null, ['calls.export']);
        $this->assertFalse($this->policy->export($userNoScopes), 'Export without valid active scope must fail closed');
    }

    /**
     * Test API resources do not leak sensitive user fields (passwords, tokens, TOTP secrets).
     */
    public function test_json_responses_do_not_expose_sensitive_fields(): void
    {
        $user = new User([
            'username' => 'test_officer',
            'full_name' => 'Officer Aslam',
            'password' => 'secret_hash_value',
            'remember_token' => 'remember_token_secret',
            'totp_secret' => 'TOTPSECRET123',
            'is_active' => true,
        ]);
        $user->id = 77;

        $call = new Call([
            'id' => 1,
            'call_number' => 'CRM-2026-TEST',
            'caller_name' => 'Caller Citizen',
            'caller_number' => '03001234567',
            'status' => 'pending',
            'priority' => 1,
            'office_id' => 12,
            'agent_id' => 77,
        ]);
        $call->setRelation('agent', $user);

        $resource = new \App\Http\Resources\CallListResource($call);
        $serialized = $resource->toArray(request());

        $this->assertArrayNotHasKey('password', $serialized);
        $this->assertArrayNotHasKey('remember_token', $serialized);
        $this->assertArrayNotHasKey('totp_secret', $serialized);
        $this->assertArrayNotHasKey('failed_attempts', $serialized);

        if (isset($serialized['agent'])) {
            $this->assertArrayNotHasKey('password', $serialized['agent']);
            $this->assertArrayNotHasKey('remember_token', $serialized['agent']);
            $this->assertArrayNotHasKey('totp_secret', $serialized['agent']);
        }
    }

    /**
     * Regression Test: Agent Supervisors must NOT receive implicit nationwide write/delete access.
     */
    public function test_agent_supervisor_cannot_write_or_delete_nationwide_calls_without_explicit_scope(): void
    {
        // Agent supervisor has calls.view, calls.update, calls.delete permissions, but NO operational office scope
        $supervisor = $this->makeUserWithRoleAndScope(Role::AGENT_SUPERVISOR, null, ['calls.view', 'calls.update', 'calls.delete']);
        $call = new Call(['office_id' => 12]);

        // Supervisor has nationwide read oversight
        $this->assertTrue($this->policy->view($supervisor, $call), 'Supervisor must have read oversight');

        // Supervisor must NOT have implicit nationwide write or delete access
        $this->assertFalse($this->policy->update($supervisor, $call), 'Supervisor must NOT receive implicit nationwide write access');
        $this->assertFalse($this->policy->delete($supervisor, $call), 'Supervisor must NOT receive implicit nationwide delete access');
        $this->assertFalse($this->policy->manageStatus($supervisor, $call), 'Supervisor must NOT receive implicit nationwide status management access');

        // If supervisor has an explicit scoped office, write/delete operates strictly within that scope
        $supervisorWithScope = $this->makeUserWithRoleAndScope(Role::AGENT_SUPERVISOR, 12, ['calls.view', 'calls.update', 'calls.delete'], 'full');
        $this->assertTrue($this->policy->update($supervisorWithScope, $call), 'Supervisor with explicit full scope can update scoped call');
        $this->assertTrue($this->policy->delete($supervisorWithScope, $call), 'Supervisor with explicit full scope can delete scoped call');

        $crossCall = new Call(['office_id' => 22]);
        $this->assertFalse($this->policy->update($supervisorWithScope, $crossCall), 'Supervisor cannot write outside assigned scope');
        $this->assertFalse($this->policy->delete($supervisorWithScope, $crossCall), 'Supervisor cannot delete outside assigned scope');
    }

    /**
     * Regression Test: Deactivated scope rows must fail closed in both UserScope model and CallPolicy traversal.
     */
    public function test_deactivated_scope_fails_closed_in_policy_and_scope_model(): void
    {
        // 1. UserScope model methods directly
        $activeScope = new UserScope(['office_id' => 12, 'access_level' => 'full', 'is_active' => true]);
        $this->assertTrue($activeScope->canWrite());
        $this->assertTrue($activeScope->canDelete());

        $inactiveScope = new UserScope(['office_id' => 12, 'access_level' => 'full', 'is_active' => false]);
        $this->assertFalse($inactiveScope->canWrite(), 'Inactive scope must return false for canWrite()');
        $this->assertFalse($inactiveScope->canDelete(), 'Inactive scope must return false for canDelete()');

        // 2. Policy traversal with inactive scope
        $user = $this->makeUserWithRoleAndScope('beat_operator', null, ['calls.view', 'calls.update', 'calls.delete']);
        $user->setRelation('activeScopes', collect([$inactiveScope]));

        $call = new Call(['office_id' => 12]);
        $this->assertFalse($this->policy->view($user, $call), 'Inactive scope must fail closed on view');
        $this->assertFalse($this->policy->update($user, $call), 'Inactive scope must fail closed on update');
        $this->assertFalse($this->policy->delete($user, $call), 'Inactive scope must fail closed on delete');
    }

    /**
     * Regression Test: A missing canWrite() or canDelete() implementation must never result in permission granted.
     */
    public function test_scope_missing_capability_methods_fails_closed(): void
    {
        // Standard class or mock object lacking canWrite / canDelete methods
        $dummyScope = new class {
            public int $office_id = 12;
            public bool $is_active = true;
            public string $access_level = 'full';
        };

        $user = $this->makeUserWithRoleAndScope('beat_operator', null, ['calls.view', 'calls.update', 'calls.delete']);
        $user->setRelation('activeScopes', collect([$dummyScope]));

        $call = new Call(['office_id' => 12]);
        $this->assertTrue($this->policy->view($user, $call), 'View does not require canWrite/canDelete');
        $this->assertFalse($this->policy->update($user, $call), 'Missing canWrite() must fail closed');
        $this->assertFalse($this->policy->delete($user, $call), 'Missing canDelete() must fail closed');
    }

    /**
     * Test reminder policy authorizes agents, supervisors, and properly scoped operators.
     */
    public function test_reminder_policy_authorizes_agents_supervisors_and_scoped_operators(): void
    {
        User::clearPermissionCache();
        $call = new Call(['office_id' => 12, 'agent_id' => 200]);

        // 1. Agent with calls.api_lookup can send reminder
        $agent = $this->makeUserWithRoleAndScope('agent', null, ['calls.api_lookup', 'calls.create', 'calls.view']);
        $agent->id = 219;
        $this->assertTrue($this->policy->reminder($agent, $call), 'Agent with calls.api_lookup must be authorized to send reminder');

        // 2. Agent without permissions fails closed
        $unpermittedAgent = $this->makeUserWithRoleAndScope('agent', null, ['profile.update']);
        $unpermittedAgent->id = 220;
        $this->assertFalse($this->policy->reminder($unpermittedAgent, $call), 'Agent without call permissions must fail closed');

        // 3. Supervisor can send reminder
        $supervisor = $this->makeUserWithRoleAndScope(Role::AGENT_SUPERVISOR, null, ['calls.view', 'calls.export']);
        $supervisor->id = 221;
        $this->assertTrue($this->policy->reminder($supervisor, $call), 'Supervisor must be authorized to send reminder');

        // 4. Inactive agent fails closed
        $inactiveAgent = $this->makeUserWithRoleAndScope('agent', null, ['calls.api_lookup', 'calls.create'], 'full', false);
        $inactiveAgent->id = 222;
        $this->assertFalse($this->policy->reminder($inactiveAgent, $call), 'Inactive agent must fail closed');

        // 5. Scoped beat operator can send reminder within their jurisdiction
        $beatOperator = $this->makeUserWithRoleAndScope('beat_operator', 12, ['calls.view', 'calls.manage_status'], 'read_write');
        $beatOperator->id = 223;
        $this->assertTrue($this->policy->reminder($beatOperator, $call), 'Beat operator on matching beat can send reminder');

        // 6. Out-of-scope beat operator fails closed
        $otherBeatOperator = $this->makeUserWithRoleAndScope('beat_operator', 22, ['calls.view', 'calls.manage_status'], 'read_write');
        $otherBeatOperator->id = 224;
        $this->assertFalse($this->policy->reminder($otherBeatOperator, $call), 'Out-of-scope operator must fail closed');
    }
}

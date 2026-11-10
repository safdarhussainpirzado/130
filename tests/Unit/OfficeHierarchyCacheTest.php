<?php

namespace Tests\Unit;

use App\Models\Call;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use App\Policies\CallPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OfficeHierarchyCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Office::clearHierarchyCache();
    }

    protected function tearDown(): void
    {
        Office::clearHierarchyCache();
        parent::tearDown();
    }

    /**
     * Test cycle detection: Graph cycle A -> B -> A must terminate safely without infinite recursion or memory exhaustion.
     */
    public function test_cycle_detection_terminates_safely(): void
    {
        // Setup cyclic graph: 100 -> 101 -> 100
        $cyclicTree = [
            100 => [
                ['id' => 101, 'parent_id' => 100, 'is_active' => true],
            ],
            101 => [
                ['id' => 100, 'parent_id' => 101, 'is_active' => true],
            ],
        ];

        Cache::put('crm:all_offices_tree_v1', $cyclicTree, 60);

        $startTime = microtime(true);
        $descendants = Office::getDescendantIdsFor(100, true);
        $elapsedMs = (microtime(true) - $startTime) * 1000;

        // Must finish instantly (< 10ms), not hang
        $this->assertLessThan(50, $elapsedMs, "Cycle traversal should terminate quickly without infinite loop.");
        $this->assertCount(2, $descendants);
        $this->assertContains(100, $descendants);
        $this->assertContains(101, $descendants);
    }

    /**
     * Test self-referencing parent: A node with parent_id == id must not cause infinite loop.
     */
    public function test_self_referencing_parent_does_not_loop(): void
    {
        $selfRefTree = [
            50 => [
                ['id' => 50, 'parent_id' => 50, 'is_active' => true],
                ['id' => 51, 'parent_id' => 50, 'is_active' => true],
            ],
        ];

        Cache::put('crm:all_offices_tree_v1', $selfRefTree, 60);

        $descendants = Office::getDescendantIdsFor(50, true);
        $this->assertEquals([50, 51], $descendants);

        $descendantsWithoutSelf = Office::getDescendantIdsFor(50, false);
        $this->assertEquals([51], $descendantsWithoutSelf);
    }

    /**
     * Test orphan handling: Node querying for children when it has no descendants.
     */
    public function test_orphan_node_or_leaf_returns_expected_ids(): void
    {
        $tree = [
            10 => [
                ['id' => 20, 'parent_id' => 10, 'is_active' => true],
            ],
        ];

        Cache::put('crm:all_offices_tree_v1', $tree, 60);

        // Leaf node 20 has no children in tree
        $this->assertEquals([20], Office::getDescendantIdsFor(20, true));
        $this->assertEquals([], Office::getDescendantIdsFor(20, false));

        // Non-existent node 999
        $this->assertEquals([999], Office::getDescendantIdsFor(999, true));
        $this->assertEquals([], Office::getDescendantIdsFor(999, false));
    }

    /**
     * Test duplicate relationships do not produce duplicate IDs.
     */
    public function test_duplicate_relationships_in_tree_do_not_produce_duplicates(): void
    {
        $treeWithDuplicates = [
            10 => [
                ['id' => 11, 'parent_id' => 10, 'is_active' => true],
                ['id' => 11, 'parent_id' => 10, 'is_active' => true], // duplicate entry in DB
                ['id' => 12, 'parent_id' => 10, 'is_active' => true],
            ],
            11 => [
                ['id' => 13, 'parent_id' => 11, 'is_active' => true],
                ['id' => 13, 'parent_id' => 11, 'is_active' => true],
            ],
        ];

        Cache::put('crm:all_offices_tree_v1', $treeWithDuplicates, 60);

        $descendants = Office::getDescendantIdsFor(10, true);
        $this->assertEquals([10, 11, 12, 13], $descendants);
        $this->assertCount(4, $descendants, "Descendants must have exactly 4 unique IDs.");
    }

    /**
     * Test large organizational trees (500+ offices nationwide across 4 tiers).
     */
    public function test_large_organizational_tree_performance(): void
    {
        // 1 Region (1) -> 10 Zones (100-109) -> 50 Sectors (1000-1049) -> 400 Beats (10000-10399)
        $largeTree = [];

        // Region 1 has 10 zones
        for ($z = 100; $z < 110; $z++) {
            $largeTree[1][] = ['id' => $z, 'parent_id' => 1, 'is_active' => true];
        }

        // Each zone has 5 sectors (50 total)
        for ($s = 1000; $s < 1050; $s++) {
            $parentZone = 100 + ($s % 10);
            $largeTree[$parentZone][] = ['id' => $s, 'parent_id' => $parentZone, 'is_active' => true];
        }

        // Each sector has 8 beats (400 total)
        for ($b = 10000; $b < 10400; $b++) {
            $parentSector = 1000 + ($b % 50);
            $largeTree[$parentSector][] = ['id' => $b, 'parent_id' => $parentSector, 'is_active' => true];
        }

        Cache::put('crm:all_offices_tree_v1', $largeTree, 60);

        $t0 = microtime(true);
        $allDescendants = Office::getDescendantIdsFor(1, true);
        $elapsedMs = (microtime(true) - $t0) * 1000;

        // Total nodes: 1 + 10 + 50 + 400 = 461 nodes
        $this->assertCount(461, $allDescendants);
        $this->assertLessThan(15, $elapsedMs, "Traversing 461-node tree must take < 15ms in memory");
    }

    /**
     * Test cache failure resilience: When Cache throws exception, it falls back to DB query safely without fatal error.
     */
    public function test_cache_failure_falls_back_gracefully(): void
    {
        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        Cache::shouldReceive('remember')
            ->once()
            ->andThrow(new \RuntimeException('Redis connection failed'));

        $tree = Office::getHierarchyTree();
        $this->assertIsArray($tree);
    }

    /**
     * Test that infrastructure failure fails closed and does NOT broaden authorization.
     */
    public function test_infrastructure_failure_fails_closed_in_authorization(): void
    {
        // Simulate completely empty hierarchy tree (Cache down, DB down)
        Cache::put('crm:all_offices_tree_v1', [], 60);

        $policy = new CallPolicy();

        $role = new Role(['name' => 'zone_admin', 'display_name' => 'Zone Admin', 'scope_level' => 'zone', 'status' => 'active']);
        $role->setRelation('permissions', collect([new Permission(['name' => 'calls.view'])]));

        $user = new User(['id' => 101, 'username' => 'test_zone_admin', 'is_active' => true]);
        $user->setRelation('role', $role);
        $scope = new UserScope(['office_id' => 10, 'access_level' => 'read_write', 'is_active' => true]);
        $user->setRelation('activeScopes', collect([$scope]));

        // Call in another unit (11)
        $callOutside = new Call(['office_id' => 11]);
        $unassignedCall = new Call(['office_id' => null]);

        // When hierarchy fails to resolve sub-units, user must NOT be granted access to other units or unassigned calls
        $this->assertFalse($policy->view($user, $callOutside), 'Failed hierarchy must fail closed, denying sub-unit access');
        $this->assertFalse($policy->view($user, $unassignedCall), 'Failed hierarchy must fail closed on unassigned calls');

        // User can only match their exact scoped office ID (10)
        $callSelf = new Call(['office_id' => 10]);
        $this->assertTrue($policy->view($user, $callSelf));
    }

    /**
     * Test clearHierarchyCache removes cached entry.
     */
    public function test_clear_hierarchy_cache_invalidates_key(): void
    {
        Cache::put('crm:all_offices_tree_v1', [1 => [['id' => 2]]], 60);
        $this->assertTrue(Cache::has('crm:all_offices_tree_v1'));

        Office::clearHierarchyCache();
        $this->assertFalse(Cache::has('crm:all_offices_tree_v1'));
    }
}

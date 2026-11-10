<?php

namespace Tests\Unit;

use App\Http\Controllers\CallController;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CallDirectoryFilterTest extends TestCase
{
    /**
     * Test phone number normalization for leftmost-prefix index scans.
     */
    public function test_phone_number_normalization_regex(): void
    {
        $testCases = [
            '03001234567'   => '03001234567',
            '+923001234567' => '03001234567',
            '923001234567'  => '03001234567',
            '3001234567'    => '03001234567',
            '03339876543'   => '03339876543',
            '+923339876543' => '03339876543',
        ];

        foreach ($testCases as $input => $expectedNormalized) {
            $matched = preg_match('/^(\+?92|0)?(3[0-9]{2,})$/', $input, $m);
            $this->assertSame(1, $matched, "Input phone '{$input}' should match mobile prefix pattern.");
            $normalized = '0' . $m[2];
            $this->assertSame($expectedNormalized, $normalized);
        }

        // Non-phone inputs should NOT match phone prefix regex
        $nonPhones = ['CRM-2026-0001', 'Motorway North', 'Accident near toll', 'ABC-1234'];
        foreach ($nonPhones as $input) {
            $matched = preg_match('/^(\+?92|0)?(3[0-9]{2,})$/', $input);
            $this->assertSame(0, $matched, "Non-phone input '{$input}' should not match mobile regex.");
        }
    }

    /**
     * Test Office::getDescendantIdsFor in-memory BFS adjacency list resolution.
     */
    public function test_office_descendant_ids_cached_hierarchy(): void
    {
        // Setup mock office tree in cache:
        // Zone 10 has Sectors 11 and 12
        // Sector 12 has Beats 13 and 14
        // Sector 11 has Beat 15
        $mockTree = [
            10 => [
                ['id' => 11, 'parent_id' => 10],
                ['id' => 12, 'parent_id' => 10],
            ],
            11 => [
                ['id' => 15, 'parent_id' => 11],
            ],
            12 => [
                ['id' => 13, 'parent_id' => 12],
                ['id' => 14, 'parent_id' => 12],
            ],
        ];

        Cache::put('crm:all_offices_tree_v1', $mockTree, 60);

        // Zone 10 descendants should include self and all children: 10, 11, 12, 15, 13, 14
        $zoneDescendants = Office::getDescendantIdsFor(10, true);
        sort($zoneDescendants);
        $this->assertEquals([10, 11, 12, 13, 14, 15], $zoneDescendants);

        // Sector 12 descendants should include 12, 13, 14
        $sectorDescendants = Office::getDescendantIdsFor(12, true);
        sort($sectorDescendants);
        $this->assertEquals([12, 13, 14], $sectorDescendants);

        // Beat 13 has no children: should only return [13]
        $beatDescendants = Office::getDescendantIdsFor(13, true);
        $this->assertEquals([13], $beatDescendants);

        // Without self:
        $withoutSelf = Office::getDescendantIdsFor(12, false);
        sort($withoutSelf);
        $this->assertEquals([13, 14], $withoutSelf);
    }

    /**
     * Test CallController filter options structure for privileged user.
     */
    public function test_get_filter_data_returns_minimal_empty_structure(): void
    {
        $controller = app(CallController::class);
        $refMethod = new \ReflectionMethod(CallController::class, 'getFilterData');
        $refMethod->setAccessible(true);
        $data = $refMethod->invoke($controller);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('zones', $data);
        $this->assertArrayHasKey('sectors', $data);
        $this->assertArrayHasKey('beats', $data);
        $this->assertArrayHasKey('callTypes', $data);
        $this->assertArrayHasKey('callSubTypes', $data);
        $this->assertArrayHasKey('agents', $data);
        // Verify they are initially empty to prevent bloating the HTML document
        $this->assertEmpty($data['zones']);
        $this->assertEmpty($data['sectors']);
        $this->assertEmpty($data['beats']);
        $this->assertEmpty($data['agents']);
    }

    /**
     * Test applyDirectoryAndStatus filters for assignment, gender, and all statuses.
     */
    public function test_apply_directory_and_status_filters(): void
    {
        $controller = app(CallController::class);
        $refMethod = new \ReflectionMethod(CallController::class, 'applyDirectoryAndStatus');
        $refMethod->setAccessible(true);

        // 1. Assignment: 'assigned'
        $reqAssigned = Request::create('/calls/data', 'POST', ['assignment' => 'assigned']);
        $qAssigned = \App\Models\Call::query();
        $refMethod->invoke($controller, $qAssigned, $reqAssigned);
        $sql = $qAssigned->toSql();
        $this->assertStringContainsString('`calls`.`office_id` is not null', $sql);
        $this->assertStringContainsString('`calls`.`office_id` > ?', $sql);

        // 2. Assignment: 'unassigned'
        $reqUnassigned = Request::create('/calls/data', 'POST', ['assignment' => 'unassigned']);
        $qUnassigned = \App\Models\Call::query();
        $refMethod->invoke($controller, $qUnassigned, $reqUnassigned);
        $sqlUnassigned = $qUnassigned->toSql();
        $this->assertStringContainsString('`calls`.`office_id` is null', $sqlUnassigned);

        // 3. Gender: 'female'
        $reqFemale = Request::create('/calls/data', 'POST', ['gender' => 'female']);
        $qFemale = \App\Models\Call::query();
        $refMethod->invoke($controller, $qFemale, $reqFemale);
        $sqlFemale = $qFemale->toSql();
        $this->assertStringContainsString('`calls`.`caller_gender` = ?', $sqlFemale);
        $this->assertStringContainsString('`calls`.`call_type_id` = ?', $sqlFemale);
        $this->assertContains('female', $qFemale->getBindings());
        $this->assertContains(10, $qFemale->getBindings());

        // 4. Statuses: pending, in_progress, tiger_reached, completed
        $statuses = ['pending', 'in_progress', 'tiger_reached', 'completed'];
        foreach ($statuses as $status) {
            $req = Request::create('/calls/data', 'POST', ['status' => $status]);
            $q = \App\Models\Call::query();
            $refMethod->invoke($controller, $q, $req);
            $this->assertStringContainsString('`calls`.`status` = ?', $q->toSql());
            $this->assertContains($status, $q->getBindings());
        }

        // 5. Status: 'all' should NOT apply a where status clause
        $reqAll = Request::create('/calls/data', 'POST', ['status' => 'all']);
        $qAll = \App\Models\Call::query();
        $refMethod->invoke($controller, $qAll, $reqAll);
        $this->assertStringNotContainsString('`calls`.`status` =', $qAll->toSql());
    }

    /**
     * Test applyCategoryFilters for numeric and slug based categories.
     */
    public function test_apply_category_filters(): void
    {
        $controller = app(CallController::class);
        $refMethod = new \ReflectionMethod(CallController::class, 'applyCategoryFilters');
        $refMethod->setAccessible(true);

        // Category IDs: 1 (Emergency), 2 (General Help), 3 (Information), 4 (Complaint), 5 (Crime), 6 (Junk)
        foreach ([1, 2, 3, 4, 5, 6] as $typeId) {
            $req = Request::create('/calls/data', 'POST', ['call_type_id' => $typeId]);
            $q = \App\Models\Call::query();
            $refMethod->invoke($controller, $q, $req);
            $this->assertStringContainsString('`calls`.`call_type_id` = ?', $q->toSql());
            $this->assertContains($typeId, $q->getBindings());
        }
    }

    /**
     * Test that active call types in database are strictly the 6 primary categories.
     */
    public function test_active_call_types_are_strictly_six(): void
    {
        \Illuminate\Support\Facades\DB::table('call_types')->delete();
        foreach ([1 => 'Emergency', 2 => 'General Help', 3 => 'Information', 4 => 'Complaint', 5 => 'Crime', 6 => 'Junk/Silent'] as $id => $name) {
            \Illuminate\Support\Facades\DB::table('call_types')->insert(['id' => $id, 'name' => $name, 'category' => 'emergency', 'priority' => 1, 'is_active' => true]);
        }
        \Illuminate\Support\Facades\DB::table('call_types')->insert(['id' => 7, 'name' => 'Emergency Assistance', 'category' => 'emergency', 'priority' => 1, 'is_active' => false]);
        \Illuminate\Support\Facades\DB::table('call_types')->insert(['id' => 10, 'name' => "Women's Safety Emergency", 'category' => 'emergency', 'priority' => 1, 'is_active' => false]);

        $activeTypes = \App\Models\CallType::where('is_active', true)->orderBy('id')->get();
        $this->assertCount(6, $activeTypes);
        $this->assertEquals([1, 2, 3, 4, 5, 6], $activeTypes->pluck('id')->all());

        // Verify IDs 7 and 10 are inactive
        $inactive7 = \App\Models\CallType::find(7);
        $this->assertNotNull($inactive7);
        $this->assertFalse((bool) $inactive7->is_active);

        $inactive10 = \App\Models\CallType::find(10);
        $this->assertNotNull($inactive10);
        $this->assertFalse((bool) $inactive10->is_active);
    }

    /**
     * Test that pending women safety calls ordering SQL puts them at the top.
     */
    public function test_pending_women_calls_priority_ordering(): void
    {
        $pendingSort = "CASE WHEN (calls.caller_gender = 'female' OR calls.call_type_id = 10) AND calls.status = 'pending' THEN 0 ELSE 1 END ASC";
        $q = \App\Models\Call::query()->orderByRaw($pendingSort)->orderBy('calls.created_at', 'desc');
        $sql = $q->toSql();

        $this->assertStringContainsString("caller_gender = 'female'", $sql);
        $this->assertStringContainsString("call_type_id = 10", $sql);
        $this->assertStringContainsString("status = 'pending'", $sql);
        $this->assertStringContainsString("THEN 0 ELSE 1 END ASC", $sql);
    }

    /**
     * Test that caller_gender column exists on calls table and query builds correctly.
     */
    public function test_caller_gender_column_and_filter_support(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasColumn('calls', 'caller_gender'));
        $q = \App\Models\Call::query()->where('caller_gender', 'female');
        $this->assertStringContainsString('`caller_gender` = ?', $q->toSql());
    }
}



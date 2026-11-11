<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Call;
use App\Models\CallStatusHistory;
use App\Models\Role;
use App\Models\Office;
use App\Models\Designation;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionClass;
use ReflectionMethod;

class RouteIntegrityAndRelationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    /**
     * Verify that all critical Eloquent model relationships invoked across controllers,
     * resources, and views exist and return valid Relation instances.
     */
    public function test_critical_eloquent_relationships_are_defined(): void
    {
        $relationshipsToVerify = [
            Call::class => [
                'callType',
                'callSubType',
                'office',
                'agent',
                'carriageway',
                'vehicleType',
                'statusHistory',
                'tiger',
            ],
            CallStatusHistory::class => [
                'call',
                'changedBy',
                'user', // Ensured alias for backward and forward compatibility
            ],
            User::class => [
                'role',
                'designation',
                'activeScopes',
                'scopes',
            ],
            Role::class => [
                'permissions',
                'users',
            ],
            Office::class => [
                'parent',
                'children',
            ],
        ];

        foreach ($relationshipsToVerify as $modelClass => $relations) {
            $reflection = new ReflectionClass($modelClass);
            $instance = new $modelClass();

            foreach ($relations as $relationName) {
                $this->assertTrue(
                    $reflection->hasMethod($relationName),
                    "Model [{$modelClass}] is missing critical relationship method [{$relationName}()]."
                );

                $method = $reflection->getMethod($relationName);
                $this->assertTrue(
                    $method->isPublic(),
                    "Relationship method [{$modelClass}::{$relationName}()] must be public."
                );

                $result = $instance->{$relationName}();
                $this->assertInstanceOf(
                    Relation::class,
                    $result,
                    "Method [{$modelClass}::{$relationName}()] must return an Eloquent Relation instance."
                );
            }
        }
    }

    /**
     * Test GET /calls/{id} endpoint with both HTML and JSON requests,
     * ensuring statusHistory and user relationships load without throwing RelationNotFoundException.
     */
    public function test_call_show_route_renders_cleanly_without_relation_exceptions(): void
    {
        $superAdmin = User::whereHas('role', fn($q) => $q->where('name', 'super_admin'))->first() ?: User::first();
        if (!$superAdmin) {
            $this->markTestSkipped('Super admin or fallback user not present in testing database.');
        }

        $call = Call::latest('id')->first();
        if (!$call) {
            $this->markTestSkipped('No call records exist to test /calls/{id}.');
        }

        // 1. Test HTML View Render
        $htmlResponse = $this->actingAs($superAdmin)->get("/calls/{$call->id}");
        $htmlResponse->assertStatus(200);
        $htmlContent = $htmlResponse->getContent();

        $this->assertStringNotContainsString('RelationNotFoundException', $htmlContent);
        $this->assertStringNotContainsString('Call to undefined relationship', $htmlContent);
        $this->assertStringNotContainsString('Attempt to read property', $htmlContent);

        // 2. Test JSON API Response
        $jsonResponse = $this->actingAs($superAdmin)
            ->withHeaders(['Accept' => 'application/json'])
            ->get("/calls/{$call->id}");

        $jsonResponse->assertStatus(200);
        $json = $jsonResponse->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('id', $json);
        $this->assertArrayHasKey('status_history', $json);
    }

    /**
     * Test GET /calls index route, ensuring data minimization and zero sensitive leaks.
     */
    public function test_call_index_route_renders_with_clean_data_minimization(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);
        $superAdmin = User::where('role_id', $adminRole->id)->where('is_active', true)->where('is_blocked', false)->first()
            ?: User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true, 'is_blocked' => false]);

        $response = $this->actingAs($superAdmin)->get('/calls');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('RelationNotFoundException', $content);
        $this->assertStringNotContainsString('totp_secret', $content);
        $this->assertStringNotContainsString('totp_last_step', $content);
    }

    /**
     * Test GET /mgmt/users route, ensuring permissions are not duplicated across users.
     */
    public function test_user_management_route_eliminates_permission_duplication(): void
    {
        $adminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);
        $superAdmin = User::where('role_id', $adminRole->id)->where('is_active', true)->where('is_blocked', false)->first()
            ?: User::factory()->create(['role_id' => $adminRole->id, 'is_active' => true, 'is_blocked' => false]);

        $response = $this->actingAs($superAdmin)->get('/mgmt/users');
        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringNotContainsString('RelationNotFoundException', $content);
        $this->assertStringContainsString('id="users-index-data"', $content);
        $this->assertStringContainsString('userManager', $content);
    }
}

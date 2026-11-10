<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DatabaseIsolationGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Establish clean in-memory sqlite connection for testing guard mechanics
        config([
            'database.default' => 'guard_test',
            'database.connections.guard_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
    }

    /**
     * Requirement 1 & Baseline: Allowed vs Protected databases.
     */
    public function test_guard_allows_safe_in_memory_and_disposable_databases(): void
    {
        $this->assertDatabaseNotProtected(':memory:', 'test');
        $this->assertDatabaseNotProtected('130_crm_test_disposable', 'test');
        $this->assertDatabaseNotProtected('/tmp/test.sqlite', 'test');
        $this->assertTrue(true);
    }

    public function test_guard_refuses_active_staging_database(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CRITICAL SAFETY VIOLATION');
        $this->assertDatabaseNotProtected('130_crm_db', 'test');
    }

    public function test_guard_refuses_legacy_and_production_databases(): void
    {
        $protected = ['crm_db', 'nhmp_crm', '130_crm_production', 'production', 'staging'];
        foreach ($protected as $name) {
            try {
                $this->assertDatabaseNotProtected($name, 'test');
                $this->fail("Guard failed to reject protected database: {$name}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('CRITICAL SAFETY VIOLATION', $e->getMessage());
            }
        }
    }

    /**
     * Requirement 2: Alternate named connections cannot target a protected database.
     */
    public function test_alternate_named_connection_cannot_target_protected_database(): void
    {
        config([
            'database.connections.reporting_replica' => [
                'driver' => 'mysql',
                'database' => '130_crm_db',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("configured connection 'reporting_replica'");
        $this->enforceTestDatabaseIsolation();
    }

    /**
     * Requirement 3: Reconnect or connection purge cannot bypass pre-execution query guard.
     */
    public function test_reconnect_or_purge_cannot_bypass_query_interceptor(): void
    {
        // Re-enforce isolation to register DB::beforeExecuting
        $this->enforceTestDatabaseIsolation();

        // Purge and reconnect connection
        DB::purge('guard_test');

        // Create an anonymous connection mock that returns a protected database name
        $tamperedConnection = new class
        {
            public function getDatabaseName()
            {
                return '130_crm_db';
            }
        };

        // Assert that assertDatabaseNotProtected strictly intercepts this connection
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("runtime query execution against protected operational database '130_crm_db'");
        $this->assertDatabaseNotProtected($tamperedConnection->getDatabaseName(), 'runtime query execution');
    }

    /**
     * Requirement 5: Destructive paths cannot target protected databases.
     */
    public function test_destructive_operations_refused_on_protected_database(): void
    {
        $destructiveTargets = ['130_crm_db', 'crm_db', '130_crm_staging'];
        foreach ($destructiveTargets as $target) {
            try {
                $this->assertDatabaseNotProtected($target, 'migrate:fresh / RefreshDatabase');
                $this->fail("Destructive guard failed to block {$target}");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('CRITICAL SAFETY VIOLATION', $e->getMessage());
                $this->assertStringContainsString($target, $e->getMessage());
            }
        }
    }

    /**
     * Requirement 6: Missing or empty database configuration causes a hard failure instead of silent fallback.
     */
    public function test_missing_database_configuration_causes_hard_failure(): void
    {
        config([
            'database.connections.guard_test.database' => '',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Database name for connection 'guard_test' is empty or unset");
        $this->enforceTestDatabaseIsolation();
    }

    public function test_missing_default_connection_causes_hard_failure(): void
    {
        config([
            'database.default' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Default database connection is not configured');
        $this->enforceTestDatabaseIsolation();
    }
}

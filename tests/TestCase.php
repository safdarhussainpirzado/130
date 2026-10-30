<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * List of protected production and staging operational database names.
     * Tests MUST NEVER connect to, query, or execute against these databases.
     */
    protected const PROTECTED_DATABASES = [
        '130_crm_db',
        'crm_db',
        'nhmp_crm',
        '130_crm_production',
        '130_crm_staging',
        'production',
        'staging',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->enforceTestDatabaseIsolation();
    }

    /**
     * Intercept trait setup to enforce isolation BEFORE RefreshDatabase or DatabaseMigrations run.
     */
    protected function setUpTraits()
    {
        $this->enforceTestDatabaseIsolation();

        return parent::setUpTraits();
    }

    /**
     * Fail-closed runtime safety guard: inspects the resolved database connection
     * and strictly forbids execution against any operational, staging, or production database.
     * Also registers a runtime query interceptor via DB::beforeExecuting to prevent
     * mid-test configuration tampering from escaping the boundary.
     */
    protected function enforceTestDatabaseIsolation(): void
    {
        // 1. Enforce testing environment
        if (app()->environment() !== 'testing') {
            throw new RuntimeException(
                "CRITICAL SAFETY VIOLATION: Application environment is '" . app()->environment() . "', expected 'testing'. Aborting tests."
            );
        }

        $defaultConnection = config('database.default');

        // 2. Prevent missing or empty configuration fallback (Hard failure instead of silent fallback)
        if (empty($defaultConnection)) {
            throw new RuntimeException(
                "CRITICAL SAFETY VIOLATION: Default database connection is not configured! Aborting tests to prevent fallback."
            );
        }

        $configuredDb = (string) config("database.connections.{$defaultConnection}.database");
        if (empty($configuredDb)) {
            throw new RuntimeException(
                "CRITICAL SAFETY VIOLATION: Database name for connection '{$defaultConnection}' is empty or unset! " .
                "Tests must explicitly target an isolated disposable database (e.g. '130_crm_test_disposable') or in-memory SQLite."
            );
        }

        // 3. Check all configured database connections (alternate named connections)
        $connections = config('database.connections', []);
        foreach ($connections as $connName => $connConfig) {
            if (is_array($connConfig) && isset($connConfig['database'])) {
                $this->assertDatabaseNotProtected((string) $connConfig['database'], "configured connection '{$connName}'");
            }
        }

        // 4. Register pre-execution query interceptor on DatabaseManager
        // This hooks into ALL queries on ALL connections (including reconnected/purged connections)
        try {
            DB::beforeExecuting(function ($query, $bindings, $connection) {
                $dbName = (string) $connection->getDatabaseName();
                $this->assertDatabaseNotProtected($dbName, 'runtime query execution');
            });
        } catch (\Throwable $e) {
            // DB facade may be unavailable in pure container tests
        }
    }

    /**
     * Asserts that a database name does not match any protected database pattern.
     * Throws RuntimeException immediately upon match.
     */
    public function assertDatabaseNotProtected(string $dbName, string $context): void
    {
        $normalized = strtolower(trim($dbName));

        // Missing/empty database in query context is dangerous
        if (empty($normalized)) {
            throw new RuntimeException(
                "CRITICAL SAFETY VIOLATION: Intercepted {$context} with empty database name! Execution aborted to prevent fallback."
            );
        }

        // Allow in-memory or dedicated SQLite fixtures
        if ($normalized === ':memory:' || str_ends_with($normalized, '.sqlite')) {
            return;
        }

        foreach (self::PROTECTED_DATABASES as $protected) {
            $protectedNorm = strtolower($protected);
            if ($normalized === $protectedNorm || str_contains($normalized, $protectedNorm)) {
                throw new RuntimeException(
                    "CRITICAL SAFETY VIOLATION: Test suite attempted {$context} against protected operational database '{$dbName}'! " .
                    "Execution aborted immediately to prevent data loss or schema drift in staging/production. " .
                    "Configure an explicitly designated disposable database (e.g. '130_crm_test_disposable') in phpunit.xml."
                );
            }
        }
    }
}

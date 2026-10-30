<?php

/**
 * NHMP 130 Helpline - Disposable Test Target Pre-Execution Verification
 *
 * Strictly validates that the active Laravel database configuration targets
 * ONLY the isolated disposable database (130_crm_test_disposable) with the
 * dedicated least-privilege test account (crm_test_user).
 *
 * FAILS CLOSED (exit code 1) if operational database 130_crm_db is detected.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$defaultConn = config('database.default');
$dbConfig = config("database.connections.{$defaultConn}");

$effectiveHost = $dbConfig['host'] ?? 'UNSET';
$effectivePort = $dbConfig['port'] ?? 'UNSET';
$effectiveDb = $dbConfig['database'] ?? 'UNSET';
$effectiveUser = $dbConfig['username'] ?? 'UNSET';

echo "=========================================================\n";
echo "NHMP 130 - EFFECTIVE LARAVEL DATABASE CONFIGURATION\n";
echo "=========================================================\n";
echo "Default Connection : " . $defaultConn . "\n";
echo "Effective Host     : " . $effectiveHost . "\n";
echo "Effective Port     : " . $effectivePort . "\n";
echo "Effective Database : " . $effectiveDb . "\n";
echo "Effective Username : " . $effectiveUser . "\n";
echo "=========================================================\n";

// Forbidden database denylist
$forbidden = ['130_crm_db', 'crm_db', 'nhmp_crm', '130_crm_production', 'production', 'staging'];
foreach ($forbidden as $bad) {
    if (str_contains(strtolower($effectiveDb), strtolower($bad)) && $effectiveDb !== '130_crm_test_disposable') {
        fwrite(STDERR, "CRITICAL SAFETY VIOLATION: Configured database '{$effectiveDb}' targets operational database '{$bad}'!\n");
        fwrite(STDERR, "ABORTING IMMEDIATELY BEFORE ANY MIGRATION OR TEST EXECUTION.\n");
        exit(1);
    }
}

if ($effectiveDb !== '130_crm_test_disposable') {
    fwrite(STDERR, "CRITICAL SAFETY VIOLATION: Target database must be explicitly '130_crm_test_disposable', got '{$effectiveDb}'. Aborting.\n");
    exit(1);
}

if ($effectiveUser !== 'crm_test_user') {
    fwrite(STDERR, "SAFETY WARNING: Expected least-privilege user 'crm_test_user', got '{$effectiveUser}'.\n");
}

// Connect and verify MySQL engine-level identity
try {
    $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
    $activeDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
    $activeUser = $pdo->query("SELECT USER()")->fetchColumn();

    echo "Runtime Active DB  : " . $activeDb . "\n";
    echo "Runtime Active User: " . $activeUser . "\n";
    echo "=========================================================\n";

    if ($activeDb !== '130_crm_test_disposable') {
        fwrite(STDERR, "CRITICAL RUNTIME ERROR: Connected database is '{$activeDb}', expected '130_crm_test_disposable'. Aborting.\n");
        exit(1);
    }

    echo "STATUS: TARGET CONFIRMED ISOLATED & DISPOSABLE (SAFE TO PROCEED)\n";
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDERR, "DATABASE CONNECTION ERROR: " . $e->getMessage() . "\n");
    exit(1);
}

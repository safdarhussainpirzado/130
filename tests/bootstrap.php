<?php

/**
 * NHMP 130 Helpline - PHPUnit Test Suite Bootstrap
 *
 * Enforces pre-boot process environment isolation before Laravel or PHPUnit runs.
 * Prevents process-level inherited environment variables from targeting operational databases.
 */

require __DIR__ . '/../vendor/autoload.php';

// Denylist of protected databases that must NEVER be in process environment during tests
$protectedDatabases = [
    '130_crm_db',
    'crm_db',
    'nhmp_crm',
    '130_crm_production',
    '130_crm_staging',
    'production',
    'staging',
];

$processDb = strtolower(trim((string) (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? ''))));

foreach ($protectedDatabases as $protected) {
    if ($processDb === $protected || ($processDb !== '' && str_contains($processDb, $protected))) {
        // Strip protected database from process memory immediately
        putenv('DB_DATABASE=130_crm_test_disposable');
        $_ENV['DB_DATABASE'] = '130_crm_test_disposable';
        $_SERVER['DB_DATABASE'] = '130_crm_test_disposable';
        break;
    }
}

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

// Unconditionally force testing database to disposable fixture database
putenv('DB_DATABASE=130_crm_test_disposable');
$_ENV['DB_DATABASE'] = '130_crm_test_disposable';
$_SERVER['DB_DATABASE'] = '130_crm_test_disposable';


// Force application environment to testing for all test suite runs
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

// Prevent production cached configuration and routes from poisoning testing environment
putenv('APP_CONFIG_CACHE=' . __DIR__ . '/../bootstrap/cache/config.testing_disabled.php');
$_ENV['APP_CONFIG_CACHE'] = __DIR__ . '/../bootstrap/cache/config.testing_disabled.php';
$_SERVER['APP_CONFIG_CACHE'] = __DIR__ . '/../bootstrap/cache/config.testing_disabled.php';

putenv('APP_ROUTES_CACHE=' . __DIR__ . '/../bootstrap/cache/routes.testing_disabled.php');
$_ENV['APP_ROUTES_CACHE'] = __DIR__ . '/../bootstrap/cache/routes.testing_disabled.php';
$_SERVER['APP_ROUTES_CACHE'] = __DIR__ . '/../bootstrap/cache/routes.testing_disabled.php';

putenv('TRUSTED_PROXIES=10.1.2.62,172.16.0.0/12');
$_ENV['TRUSTED_PROXIES'] = '10.1.2.62,172.16.0.0/12';
$_SERVER['TRUSTED_PROXIES'] = '10.1.2.62,172.16.0.0/12';


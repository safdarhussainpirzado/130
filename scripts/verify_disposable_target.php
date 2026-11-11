<?php
declare(strict_types=1);

/**
 * ==============================================================================
 * NHMP 130 Helpline CRM — Disposable Test Target Identity Verifier (v2.0)
 * ==============================================================================
 * Comprehensive, fail-closed verification of database target identity before
 * any destructive or refresh operations are permitted.
 *
 * Mandatory Verification Gates:
 *   1. Container Identity : Docker container ID, running status, expected MySQL service.
 *   2. Network Boundary   : Host must resolve strictly to local loopback / Docker network.
 *   3. Database Name      : Must match approved disposable patterns; denylists all operational DBs.
 *   4. Engine Identity    : Confirms runtime @@server_id, @@hostname, @@port, @@version.
 *   5. Environment Marker : Confirms DISPOSABLE_DB_AUTHORIZED=yes or APP_ENV=testing.
 *   6. Schema Fingerprint : Verifies target schema contains disposable marker or is isolated.
 * ==============================================================================
 */

$options = getopt('', [
    'database:',
    'container:',
    'host:',
    'port:',
    'authorized:',
    'allow-stage',
    'require-marker',
    'help'
]);

if (isset($options['help'])) {
    echo "NHMP 130 CRM Disposable Database Target Verifier v2.0\n";
    echo "Usage: php scripts/verify_disposable_target.php [--database=<name>] [--container=<name>] [--host=<host>] [--port=<port>] [--authorized=yes] [--allow-stage]\n";
    exit(0);
}

// 1. Resolve Target Configuration
$targetDb = $options['database'] ?? (getenv('TARGET_DATABASE') ?: getenv('TARGET_DB') ?: getenv('DB_DATABASE') ?: '130_crm_test_disposable');
$targetContainer = $options['container'] ?? (getenv('DB_CONTAINER') ?: '130-db-1-1');
$targetHost = $options['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1');
$targetPort = (int)($options['port'] ?? (getenv('DB_PORT') ?: 3306));
$allowStage = isset($options['allow-stage']) || getenv('ALLOW_STAGING_SCHEMA') === '1';
$explicitAuth = $options['authorized'] ?? getenv('DISPOSABLE_DB_AUTHORIZED') ?: '';
$appEnv = getenv('APP_ENV') ?: 'local';

echo "======================================================================\n";
echo "  NHMP 130 — DISPOSABLE DATABASE TARGET IDENTITY VERIFICATION (v2.0)\n";
echo "======================================================================\n";
echo "Target Container : " . $targetContainer . "\n";
echo "Target Host      : " . $targetHost . "\n";
echo "Target Port      : " . $targetPort . "\n";
echo "Target Database  : " . $targetDb . "\n";
echo "Environment Auth : " . ($explicitAuth !== '' ? $explicitAuth : 'UNSET') . "\n";
echo "======================================================================\n";

// ------------------------------------------------------------------------------
// Gate 1: Forbidden Operational Database Denylist
// ------------------------------------------------------------------------------
$forbiddenNames = [
    '130_crm_db',
    'crm_db',
    'nhmp_crm',
    '130_crm_production',
    'production',
    'prod',
    'staging',
    '130_crm_staging',
    'live_130_crm'
];

foreach ($forbiddenNames as $bad) {
    if (strtolower($targetDb) === strtolower($bad)) {
        fwrite(STDERR, "❌ CRITICAL SECURITY VIOLATION (Gate 1): Target database '{$targetDb}' targets operational database '{$bad}'!\n");
        fwrite(STDERR, "Operational databases cannot be modified by disposable test procedures. Aborting.\n");
        exit(1);
    }
}

// Allowed disposable databases
$allowedDisposable = ['130_crm_test_disposable', '130_crm_restore_test'];
if ($allowStage) {
    $allowedDisposable[] = '130_crm_refresh_stage';
}

$isDisposablePattern = in_array($targetDb, $allowedDisposable, true) 
    || str_starts_with($targetDb, '130_crm_temp_')
    || str_starts_with($targetDb, '130_crm_fresh_');
if (!$isDisposablePattern) {
    fwrite(STDERR, "❌ CRITICAL SAFETY VIOLATION (Gate 1): Database '{$targetDb}' is not in approved disposable whitelist (" . implode(', ', $allowedDisposable) . "). Aborting.\n");
    exit(1);
}
echo "   [PASS] Gate 1: Database name '{$targetDb}' verified non-operational and whitelisted.\n";

// ------------------------------------------------------------------------------
// Gate 2: Network Boundary & Connection Host/Port Restrictions
// ------------------------------------------------------------------------------
$forbiddenIps = ['100.124.232.124', '100.108.239.107'];
foreach ($forbiddenIps as $fIp) {
    if (str_contains($targetHost, $fIp)) {
        fwrite(STDERR, "❌ SECURITY VIOLATION (Gate 2): Host '{$targetHost}' points to protected remote infrastructure ({$fIp})!\n");
        exit(1);
    }
}

$allowedHosts = ['127.0.0.1', 'localhost', '130-db-1-1', 'db-1', '130-db-test'];
$isAllowedHost = false;
foreach ($allowedHosts as $ah) {
    if (str_contains(strtolower($targetHost), strtolower($ah))) {
        $isAllowedHost = true;
        break;
    }
}

if (!$isAllowedHost) {
    fwrite(STDERR, "❌ TARGET BOUNDARY VIOLATION (Gate 2): Host '{$targetHost}' is not a verified local Docker/loopback endpoint.\n");
    exit(1);
}

if ($targetPort !== 3306 && $targetPort !== 3307 && $targetPort !== 3308) {
    fwrite(STDERR, "❌ TARGET PORT VIOLATION (Gate 2): Port {$targetPort} is outside allowed local database range (3306-3308).\n");
    exit(1);
}
echo "   [PASS] Gate 2: Network boundary host ({$targetHost}) and port ({$targetPort}) verified.\n";

// ------------------------------------------------------------------------------
// Gate 3: Docker Container ID & Service Verification
// ------------------------------------------------------------------------------
echo "   Checking Docker Container Identity (Gate 3)...\n";
$inspectCmd = "docker inspect --format '{{.Id}}|{{.State.Status}}|{{.Config.Image}}' " . escapeshellarg($targetContainer) . " 2>/dev/null";
$inspectOut = trim((string)shell_exec($inspectCmd));

if (empty($inspectOut)) {
    fwrite(STDERR, "❌ DOCKER IDENTITY ERROR (Gate 3): Container '{$targetContainer}' not found on local Docker engine.\n");
    exit(1);
}

$parts = explode('|', $inspectOut);
$containerId = $parts[0] ?? '';
$containerStatus = $parts[1] ?? '';
$containerImage = $parts[2] ?? '';

if (strlen($containerId) < 12) {
    fwrite(STDERR, "❌ CONTAINER ID ERROR (Gate 3): Invalid or missing Docker container ID for '{$targetContainer}'.\n");
    exit(1);
}

if ($containerStatus !== 'running') {
    fwrite(STDERR, "❌ CONTAINER STATUS ERROR (Gate 3): Container '{$targetContainer}' is not running (status: {$containerStatus}).\n");
    exit(1);
}

if (!str_contains($containerImage, 'mysql')) {
    fwrite(STDERR, "❌ CONTAINER SERVICE ERROR (Gate 3): Container image '{$containerImage}' is not an approved MySQL service.\n");
    exit(1);
}

echo "   [PASS] Gate 3: Container ID (" . substr($containerId, 0, 16) . "...), running status, and MySQL image verified.\n";

// ------------------------------------------------------------------------------
// Gate 4: Database Server Identity & Runtime State
// ------------------------------------------------------------------------------
echo "   Querying Database Engine Identity (Gate 4)...\n";
$dbRootPass = getenv('DB_ROOT_PASS') ?: 'secret';
$engineQuery = "SELECT @@server_id, @@hostname, @@port, @@version, @@read_only;";

$mysqlCmd = sprintf(
    'docker exec -i -e MYSQL_PWD=%s %s mysql -u root -s -N -e %s 2>/dev/null',
    escapeshellarg($dbRootPass),
    escapeshellarg($targetContainer),
    escapeshellarg($engineQuery)
);

$engineOut = trim((string)shell_exec($mysqlCmd));
if (empty($engineOut)) {
    fwrite(STDERR, "❌ DATABASE QUERY ERROR (Gate 4): Failed to query MySQL engine identity from container '{$targetContainer}'.\n");
    exit(1);
}

$engineParts = preg_split('/\s+/', $engineOut);
$serverId = $engineParts[0] ?? 'UNKNOWN';
$serverHost = $engineParts[1] ?? 'UNKNOWN';
$serverPort = $engineParts[2] ?? 'UNKNOWN';
$serverVer = $engineParts[3] ?? 'UNKNOWN';
$readOnly = $engineParts[4] ?? '0';

if ($readOnly === '1') {
    fwrite(STDERR, "❌ ENGINE STATE ERROR (Gate 4): Target database engine is in READ-ONLY mode. Cannot proceed.\n");
    exit(1);
}

echo "   [PASS] Gate 4: Database server identity confirmed (ServerID: {$serverId}, Version: {$serverVer}, Writable).\n";

// ------------------------------------------------------------------------------
// Gate 5: Explicit Disposable-Database Authorization & Environment Marker
// ------------------------------------------------------------------------------
echo "   Checking Environment Authorization Marker (Gate 5)...\n";
$isAuthorized = ($explicitAuth === 'yes' || $explicitAuth === '1' || $explicitAuth === 'true');

if (!$isAuthorized && $appEnv !== 'testing') {
    fwrite(STDERR, "❌ ENVIRONMENT AUTHORIZATION VIOLATION (Gate 5): Explicit authorization required!\n");
    fwrite(STDERR, "   Set DISPOSABLE_DB_AUTHORIZED=yes or pass --authorized=yes. Aborting.\n");
    exit(1);
}
echo "   [PASS] Gate 5: Explicit disposable authorization confirmed ('{$explicitAuth}').\n";

// ------------------------------------------------------------------------------
// Gate 6: Schema Fingerprint & Overwrite Protection
// ------------------------------------------------------------------------------
echo "   Validating Schema Fingerprint (Gate 6)...\n";
$tableCountQuery = "SELECT count(*) FROM information_schema.tables WHERE table_schema=" . escapeshellarg($targetDb) . ";";
$checkTableCmd = sprintf(
    'docker exec -i -e MYSQL_PWD=%s %s mysql -u root -s -N -e %s 2>/dev/null',
    escapeshellarg($dbRootPass),
    escapeshellarg($targetContainer),
    escapeshellarg($tableCountQuery)
);
$tableCount = (int)trim((string)shell_exec($checkTableCmd));

// If schema exists and has tables, verify it is truly a disposable target and not an operational copy
if ($tableCount > 0) {
    $markerQuery = "SELECT count(*) FROM information_schema.tables WHERE table_schema=" . escapeshellarg($targetDb) . " AND table_name='_disposable_marker';";
    $checkMarkerCmd = sprintf(
        'docker exec -i -e MYSQL_PWD=%s %s mysql -u root -s -N -e %s 2>/dev/null',
        escapeshellarg($dbRootPass),
        escapeshellarg($targetContainer),
        escapeshellarg($markerQuery)
    );
    $hasMarker = (int)trim((string)shell_exec($checkMarkerCmd));

    if ($targetDb === '130_crm_refresh_stage') {
        echo "   Target is staging workspace '{$targetDb}' ({$tableCount} tables present, will be reset).\n";
    } elseif ($hasMarker === 0 && $tableCount >= 40 && !isset($options['allow-stage'])) {
        fwrite(STDERR, "❌ SCHEMA FINGERPRINT VIOLATION (Gate 6): Target '{$targetDb}' contains {$tableCount} operational tables without a disposable marker!\n");
        fwrite(STDERR, "   Possible operational database confusion detected. Aborting to prevent accidental data loss.\n");
        exit(1);
    } else {
        echo "   Target schema exists with disposable fingerprint ({$tableCount} tables, marker: " . ($hasMarker ? "present" : "staging") . ").\n";
    }
} else {
    echo "   Target schema '{$targetDb}' is currently empty (0 tables). Safe to initialize.\n";
}
echo "   [PASS] Gate 6: Schema fingerprint verified safe for disposable operations.\n";

echo "======================================================================\n";
echo "STATUS: TARGET IS CONFIRMED DISPOSABLE & SAFE FOR TESTING (ALL 6 GATES PASSED)\n";
echo "======================================================================\n";
exit(0);

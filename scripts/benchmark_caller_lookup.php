<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Call;
use Illuminate\Support\Facades\DB;

$number = '03999999999';

// Warm up
Call::where('caller_number', $number)->orderBy('created_at', 'desc')->limit(5)->get();

// Benchmark Default
$iterations = 5;
$defaultTimes = [];
for ($i = 0; $i < $iterations; $i++) {
    $t0 = microtime(true);
    Call::where('caller_number', $number)->orderBy('created_at', 'desc')->limit(5)->get();
    $defaultTimes[] = (microtime(true) - $t0) * 1000;
}
$avgDefault = array_sum($defaultTimes) / count($defaultTimes);

// Benchmark with USE INDEX
$useIndexTimes = [];
for ($i = 0; $i < $iterations; $i++) {
    $t0 = microtime(true);
    Call::from(DB::raw('calls USE INDEX (idx_calls_caller_created)'))
        ->where('caller_number', $number)
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    $useIndexTimes[] = (microtime(true) - $t0) * 1000;
}
$avgUseIndex = array_sum($useIndexTimes) / count($useIndexTimes);

// Benchmark with ORDER BY id DESC
$orderByIdTimes = [];
for ($i = 0; $i < $iterations; $i++) {
    $t0 = microtime(true);
    Call::where('caller_number', $number)->orderBy('id', 'desc')->limit(5)->get();
    $orderByIdTimes[] = (microtime(true) - $t0) * 1000;
}
$avgOrderById = array_sum($orderByIdTimes) / count($orderByIdTimes);

echo json_encode([
    'avg_default_ms' => round($avgDefault, 2),
    'avg_use_index_ms' => round($avgUseIndex, 2),
    'avg_order_by_id_ms' => round($avgOrderById, 2),
    'speedup_use_index' => round($avgDefault / max($avgUseIndex, 0.001), 1) . 'x',
    'speedup_order_by_id' => round($avgDefault / max($avgOrderById, 0.001), 1) . 'x',
], JSON_PRETTY_PRINT) . PHP_EOL;

<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CallController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Admin\MfaSettingController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\OfficeController;
use App\Http\Controllers\CarriagewayController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\TtsScriptController;
use App\Http\Controllers\GeospatialMarkerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(auth()->user()->getLandingPageRoute());
    }
    return redirect()->route('login');
});

// Health check for HAProxy with bounded Database and Cache probes
Route::get('/up', function () {
    try {
        // 1. Bounded MySQL Probe: 2-second connection timeout and 2000ms statement deadline
        $dbConn = config('database.default', 'mysql');
        $dbConfig = config("database.connections.{$dbConn}", []);
        if (($dbConfig['driver'] ?? '') === 'mysql') {
            $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
            $options = [
                \PDO::ATTR_TIMEOUT => 2,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION max_execution_time=2000',
            ];
            $pdo = new \PDO($dsn, $dbConfig['username'] ?? 'root', $dbConfig['password'] ?? '', $options);
            $stmt = $pdo->query('SELECT 1');
            if ($stmt === false) {
                throw new \RuntimeException('Database health probe query failed');
            }
        } else {
            \Illuminate\Support\Facades\DB::select('SELECT 1');
        }

        // 2. Bounded Redis Probe: 2.0s connect timeout and 2.0s read timeout
        $redisHost = (string) config('database.redis.default.host', '127.0.0.1');
        $redisPort = (int) config('database.redis.default.port', 6379);
        $redisPass = config('database.redis.default.password');

        if (!class_exists('Redis') && !class_exists('Predis\\Client')) {
            throw new \RuntimeException('Required Redis support is absent in PHP runtime');
        }

        if (class_exists('Redis')) {
            $redis = new \Redis();
            if (!@$redis->connect($redisHost, $redisPort, 2.0, null, 0, 2.0)) {
                throw new \RuntimeException('Unable to connect to Redis within 2s timeout');
            }
            if (!empty($redisPass)) {
                if (!$redis->auth($redisPass)) {
                    throw new \RuntimeException('Redis authentication failed');
                }
            }
            $pong = $redis->ping();
            if ($pong !== true && $pong !== 'PONG' && $pong !== '+PONG') {
                throw new \RuntimeException('Redis ping returned invalid response');
            }
            $redis->close();
        } else {
            \Illuminate\Support\Facades\Redis::connection()->ping();
        }

        return response()->noContent();
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Health check /up failed', [
            'error' => $e->getMessage(),
            'class' => get_class($e),
        ]);
        return response('Service Unavailable', 503)->header('Content-Type', 'text/plain');
    }
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard')->middleware('can:dashboard.view');
    Route::get('/dashboard/chart-data', [DashboardController::class, 'chartData'])->name('dashboard.chart-data')->middleware('can:dashboard.view');
    Route::get('/auth/heartbeat', function(\Illuminate\Http\Request $request) {
        if ($request->boolean('active')) {
            $request->session()->put('last_activity_at', now()->timestamp);
        }
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['status' => 'ok']);
        }
        return response()->noContent();
    })->name('auth.heartbeat');
    
    Route::get('/calls/pending', [CallController::class, 'pending'])->name('calls.pending');
    Route::get('/calls/inprogress', [CallController::class, 'inprogress'])->name('calls.inprogress');
    Route::get('/calls/tiger-reached', [CallController::class, 'tigerReached'])->name('calls.tiger_reached');
    Route::get('/calls/completed', [CallController::class, 'completed'])->name('calls.completed');
    Route::post('/calls/{call}/status', [CallController::class, 'updateStatus'])->name('calls.updateStatus');
    Route::get('/calls/export', [CallController::class, 'exportCsv'])->name('calls.export');
    Route::resource('calls', CallController::class);

    // Advanced Operational AJAX
    Route::get('/api/lookup-caller', [CallController::class, 'searchCallerHistory'])->name('api.lookupCaller');
    Route::get('/api/beat-resources', [CallController::class, 'getBeatResources'])->name('api.beatResources');
    Route::get('/ajax/geospatial-lookup', [CallController::class, 'geospatialLookup'])->name('api.geospatialLookup');
    Route::get('/ajax/spatial-contacts', [CallController::class, 'spatialContacts'])->name('api.spatialContacts');
    Route::get('/api/notifications/poll', [CallController::class, 'getNotificationsPoll'])->name('api.notifications.poll');
    Route::post('/api/dispatch/ping', [CallController::class, 'sendReminder'])->name('api.calls.reminder');
    Route::post('/api/calls/{call}/reminder', [CallController::class, 'sendReminder'])->name('api.calls.reminder.call');
    
    // Administrative Modules
    Route::prefix('mgmt')->name('admin.')->group(function() {
        Route::middleware('can:users.view')->group(function() {
            Route::resource('users', UserController::class);
            Route::post('users/{user}/toggle-status',  [UserController::class, 'toggleStatus'])->name('users.toggleStatus')->middleware('can:users.manage_status');
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.resetPassword')->middleware('can:users.manage_password');

            // Password reset requests management
            Route::get('password-requests',                        [UserController::class, 'getPasswordRequests'])->name('users.passwordRequests.index');
            Route::post('password-requests/{resetRequest}/reject', [UserController::class, 'rejectPasswordRequest'])->name('users.passwordRequests.reject');

            // Dynamic multi-scope management
            Route::get('users/{user}/scopes',                    [UserController::class, 'getScopes'])->name('users.scopes.index')->middleware('can:users.manage_scopes');
            Route::post('users/{user}/scopes',                   [UserController::class, 'addScope'])->name('users.scopes.add')->middleware('can:users.manage_scopes');
            Route::patch('users/{user}/scopes/{scope}',          [UserController::class, 'updateScope'])->name('users.scopes.update')->middleware('can:users.manage_scopes');
            Route::delete('users/{user}/scopes/{scope}',         [UserController::class, 'removeScope'])->name('users.scopes.remove')->middleware('can:users.manage_scopes');
        });

        Route::middleware('can:roles.view')->group(function() {
            Route::resource('roles', RoleController::class);
            Route::post('roles/{role}/toggle-status', [RoleController::class, 'toggleStatus'])->name('roles.toggleStatus');
            Route::resource('permissions', \App\Http\Controllers\PermissionController::class)->middleware('can:permissions.view');
            Route::post('permissions/{permission}/toggle-status', [\App\Http\Controllers\PermissionController::class, 'toggleStatus'])->name('permissions.toggleStatus');
            Route::get('audit', [AuditController::class, 'index'])->name('audit.index')->middleware('can:system.audit_view');
            Route::resource('tts-scripts', TtsScriptController::class)->except(['show', 'edit', 'update'])->middleware('can:system.tts_scripts.manage');
        });

        // MFA & Security Settings (Restricted to Super Admin & Operations Manager)
        Route::middleware('can:security.mfa_roles.view')->group(function() {
            Route::get('mfa-settings', [MfaSettingController::class, 'index'])->name('mfa-settings.index');
            Route::get('settings', [MfaSettingController::class, 'index'])->name('settings.index');
            Route::get('settings/mfa-roles', [MfaSettingController::class, 'index'])->name('settings.mfa-roles.index');

            Route::post('mfa-settings/roles/{role}/toggle', [MfaSettingController::class, 'toggleRole'])
                ->middleware('can:security.mfa_roles.manage')
                ->name('mfa-settings.roles.toggle');
            Route::post('settings/mfa-roles/{role}/toggle', [MfaSettingController::class, 'toggleRole'])
                ->middleware('can:security.mfa_roles.manage')
                ->name('settings.mfa-roles.toggle');

            Route::post('mfa-settings/users/{user}/reset', [MfaSettingController::class, 'resetUser'])
                ->middleware('can:security.mfa_roles.manage')
                ->name('mfa-settings.users.reset');

            Route::post('mfa-settings/users/{user}/revoke', [MfaSettingController::class, 'revokeUser'])
                ->middleware('can:security.mfa_roles.manage')
                ->name('mfa-settings.users.revoke');

            Route::post('mfa-settings/users/{user}/enforce', [MfaSettingController::class, 'enforceUser'])
                ->middleware('can:security.mfa_roles.manage')
                ->name('mfa-settings.users.enforce');
        });

        Route::middleware('can:geography.offices.view')->group(function() {
            Route::get('offices', [OfficeController::class, 'index'])->name('offices.index');
            
            Route::post('offices', [OfficeController::class, 'store'])->name('offices.store')->middleware('can:geography.offices.create');
            Route::patch('offices/{office}', [OfficeController::class, 'update'])->name('offices.update')->middleware('can:geography.offices.update');
            Route::delete('offices/{office}', [OfficeController::class, 'destroy'])->name('offices.destroy')->middleware('can:geography.offices.delete');
            Route::post('offices/{office}/toggle-status', [OfficeController::class, 'toggleStatus'])->name('offices.toggleStatus')->middleware('can:geography.offices.update');
            
            Route::resource('carriageways', CarriagewayController::class);
            Route::post('carriageways/{carriageway}/toggle-status', [CarriagewayController::class, 'toggleStatus'])->name('carriageways.toggleStatus')->middleware('can:geography.carriageways.manage');

            Route::resource('geospatial-markers', GeospatialMarkerController::class)->middleware('can:geography.geospatial.view');
            Route::post('geospatial-markers/{geospatialMarker}/toggle-status', [GeospatialMarkerController::class, 'toggleStatus'])->name('admin.geospatial-markers.toggleStatus')->middleware('can:geography.geospatial.manage');
        });
    });
    
    
    // Reports Module
    Route::prefix('reports')->name('reports.')->group(function() {
        Route::get('/call-type-summary', [ReportController::class, 'callTypeSummary'])->name('call-type-summary')->middleware('can:reports.call_type_summary');
        Route::get('/beat-wise', [ReportController::class, 'beatWise'])->name('beat-wise')->middleware('can:reports.beat_wise');
        Route::get('/agent-wise', [ReportController::class, 'agentWise'])->name('agent-wise')->middleware('can:reports.agent_wise');
        Route::get('/sla-compliance', [ReportController::class, 'slaCompliance'])->name('sla-compliance')->middleware('can:reports.sla_compliance');
        Route::get('/max-response-time', [ReportController::class, 'maxResponseTime'])->name('max-response-time')->middleware('can:reports.max_response_time');
        Route::get('/predictive-analysis', [ReportController::class, 'predictiveAnalysis'])->name('predictive-analysis')->middleware('can:reports.predictive_analysis');
        Route::get('/category-analysis', [ReportController::class, 'categoryAnalysis'])->name('category-analysis')->middleware('can:reports.view');
        Route::get('/junk-calls-frequency', [ReportController::class, 'junkCallsFrequency'])->name('junk-calls-frequency')->middleware('can:reports.view');
        Route::get('/help-response-time', [ReportController::class, 'helpResponseTime'])->name('help-response-time')->middleware('can:reports.view');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

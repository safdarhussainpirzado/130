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
use App\Http\Controllers\GeospatialLandmarkController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

Route::get('/', function () {
    if (Auth::check()) {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        return redirect($user->getLandingPageRoute());
    }
    return redirect()->route('login');
});

// Health check for HAProxy with bounded Database and Cache probes
Route::get('/up', function () {
    try {
        // 1. Bounded MySQL Probe
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
        $stmt = $pdo->query('SELECT 1');
        if ($stmt === false) {
            throw new \RuntimeException('Database health probe query failed');
        }

        // 2. Bounded Redis Probe
        \Illuminate\Support\Facades\Redis::connection()->ping();

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
    Route::get('/dashboard/metrics', [DashboardController::class, 'getMetrics'])->name('dashboard.metrics')->middleware('can:dashboard.view');
    Route::get('/dashboard/chart-data', [DashboardController::class, 'chartData'])->name('dashboard.chart-data')->middleware('can:dashboard.view');
    
    // Three Independent Ultra-Low-Latency Dashboard Endpoints
    Route::get('/api/dashboard/live', [\App\Http\Controllers\Api\DashboardApiController::class, 'live'])->name('api.dashboard.live')->middleware('can:dashboard.view');
    Route::get('/api/dashboard/analytics', [\App\Http\Controllers\Api\DashboardApiController::class, 'analytics'])->name('api.dashboard.analytics')->middleware('can:dashboard.view');
    Route::get('/api/dashboard/reporting', [\App\Http\Controllers\Api\DashboardApiController::class, 'reporting'])->name('api.dashboard.reporting')->middleware('can:dashboard.view');
    Route::get('/auth/heartbeat', function(\Illuminate\Http\Request $request) {
        if ($request->boolean('active')) {
            $request->session()->put('last_activity_at', now()->timestamp);
        }
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['status' => 'ok']);
        }
        return response()->noContent();
    })->name('auth.heartbeat');
    
    Route::get('/calls/assigned', [CallController::class, 'assigned'])->name('calls.assigned');
    Route::get('/calls/unassigned', [CallController::class, 'unassigned'])->name('calls.unassigned');
    Route::get('/calls/pending', [CallController::class, 'pending'])->name('calls.pending');
    Route::get('/calls/inprogress', [CallController::class, 'inprogress'])->name('calls.inprogress');
    Route::get('/calls/tiger-reached', [CallController::class, 'tigerReached'])->name('calls.tiger_reached');
    Route::get('/calls/completed', [CallController::class, 'completed'])->name('calls.completed');
    Route::post('/calls/{call}/status', [CallController::class, 'updateStatus'])->name('calls.updateStatus');
    Route::get('/calls/export', [CallController::class, 'exportCsv'])->name('calls.export');
    Route::get('/calls/filter-options', [CallController::class, 'getFilterOptions'])->name('calls.filter_options');
    Route::post('/calls/data', [CallController::class, 'index'])->name('calls.data');
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
            Route::post('users/{user}/clear-lockout',       [UserController::class, 'clearLockout'])->name('users.clearLockout')->middleware('can:users.manage_status');
            Route::post('users/{user}/flush-rate-limits',   [UserController::class, 'flushRateLimits'])->name('users.flushRateLimits')->middleware('can:users.manage_status');
            Route::post('users/{user}/reset-password',     [UserController::class, 'resetPassword'])->name('users.resetPassword')->middleware('can:users.manage_password');

            // Password reset requests management
            Route::get('password-requests',                        [UserController::class, 'getPasswordRequests'])->name('users.passwordRequests.index');
            Route::post('password-requests/{resetRequest}/reject', [UserController::class, 'rejectPasswordRequest'])->name('users.passwordRequests.reject');

            // Dynamic multi-scope management
            Route::get('users/{user}/scopes',                    [UserController::class, 'getScopes'])->name('users.scopes.index')->middleware('can:users.manage_scopes');
            Route::post('users/{user}/scopes',                   [UserController::class, 'addScope'])->name('users.scopes.add')->middleware('can:users.manage_scopes');
            Route::patch('users/{user}/scopes/{scope}',          [UserController::class, 'updateScope'])->name('users.scopes.update')->middleware('can:users.manage_scopes');
            Route::delete('users/{user}/scopes/{scope}',         [UserController::class, 'removeScope'])->name('users.scopes.remove')->middleware('can:users.manage_scopes');
            Route::get('users/{user}/audit',                     [UserController::class, 'audit'])->name('users.audit');
        });

        Route::middleware('can:roles.view')->group(function() {
            Route::resource('roles', RoleController::class);
            Route::post('roles/{role}/toggle-status', [RoleController::class, 'toggleStatus'])->name('roles.toggleStatus');
            Route::resource('permissions', \App\Http\Controllers\PermissionController::class)->middleware('can:permissions.view');
            Route::post('permissions/{permission}/toggle-status', [\App\Http\Controllers\PermissionController::class, 'toggleStatus'])->name('permissions.toggleStatus');
            Route::get('audit', [AuditController::class, 'index'])->name('audit.index')->middleware('can:system.audit_view');
            Route::get('audit/{log}', [AuditController::class, 'show'])->name('audit.show')->middleware('can:system.audit_view');
            Route::get('geo-access/report', [\App\Http\Controllers\Admin\GeoAccessReportController::class, 'index'])->name('geo-access.report')->middleware('can:system.audit_view');
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

            // Geospatial Landmarks (Caller Location Helper: 1,042 Physical Reference Points)
            Route::resource('geospatial-landmarks', GeospatialLandmarkController::class)->middleware('can:geography.geospatial.view');
            Route::post('geospatial-landmarks/{geospatialLandmark}/toggle-status', [GeospatialLandmarkController::class, 'toggleStatus'])->name('geospatial-landmarks.toggleStatus')->middleware('can:geography.geospatial.manage');

            // Geospatial Markers (Highway Grid Coordinates: 97,479 Meter-by-Meter Coordinates)
            Route::resource('geospatial-markers', GeospatialMarkerController::class)->middleware('can:geography.geospatial.view');
            Route::post('geospatial-markers/{geospatialMarker}/toggle-status', [GeospatialMarkerController::class, 'toggleStatus'])->name('geospatial-markers.toggleStatus')->middleware('can:geography.geospatial.manage');
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

    // User Self-Service Support Portal
    Route::prefix('portal/support')->name('portal.support.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Portal\SupportCenterController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\Portal\SupportCenterController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\Portal\SupportCenterController::class, 'store'])->name('store')->middleware('throttle:10,1');
        Route::get('/requests/{supportRequest:public_id}', [\App\Http\Controllers\Portal\SupportCenterController::class, 'show'])->name('show');
        Route::post('/requests/{supportRequest:public_id}/cancel', [\App\Http\Controllers\Portal\SupportCenterController::class, 'cancel'])->name('cancel')->middleware('throttle:10,1');
        Route::post('/lock-my-account', [\App\Http\Controllers\Portal\SupportCenterController::class, 'lockAccount'])->name('lock-account')->middleware('throttle:5,1');
    });

    // Support Management Module (Unified Single-Page Support Center)
    Route::prefix('mgmt/support')->name('admin.support.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Admin\SupportManagementController::class, 'index'])->name('index');
        Route::get('/dashboard', fn() => redirect()->route('admin.support.index'))->name('dashboard');
        Route::get('/requests', fn() => redirect()->route('admin.support.index'))->name('requests.index');
        Route::get('/requests/password', fn() => redirect()->route('admin.support.index', ['type' => 'password_recovery']))->name('requests.password');
        Route::get('/requests/mfa', fn() => redirect()->route('admin.support.index', ['type' => 'mfa_support']))->name('requests.mfa');
        Route::get('/requests/blocking', fn() => redirect()->route('admin.support.index', ['type' => 'account_block']))->name('requests.blocking');
        Route::get('/requests/transfers', fn() => redirect()->route('admin.support.index', ['type' => 'org_transfer']))->name('requests.transfers');
        Route::get('/requests/other', fn() => redirect()->route('admin.support.index', ['type' => 'other_account']))->name('requests.other');
        Route::get('/requests/history', fn() => redirect()->route('admin.support.index', ['status' => 'completed']))->name('requests.history');
        Route::get('/analytics', fn() => redirect()->route('admin.support.index'))->name('analytics');
        Route::get('/pending-count', [\App\Http\Controllers\Admin\SupportManagementController::class, 'pendingCount'])->name('pendingCount');

        Route::get('/requests/{supportRequest:public_id}', [\App\Http\Controllers\Admin\SupportManagementController::class, 'show'])->name('requests.show');
        Route::post('/requests/{supportRequest:public_id}/approve-source', [\App\Http\Controllers\Admin\SupportManagementController::class, 'approveSource'])->name('requests.approveSource')->middleware('throttle:10,1');
        Route::post('/requests/{supportRequest:public_id}/confirm-destination', [\App\Http\Controllers\Admin\SupportManagementController::class, 'confirmDestination'])->name('requests.confirmDestination')->middleware('throttle:10,1');
        Route::post('/requests/{supportRequest:public_id}/reject', [\App\Http\Controllers\Admin\SupportManagementController::class, 'reject'])->name('requests.reject')->middleware('throttle:10,1');
        Route::post('/requests/{supportRequest:public_id}/unlock', [\App\Http\Controllers\Admin\SupportManagementController::class, 'unlockAccount'])->name('requests.unlock')->middleware('throttle:10,1');
        Route::post('/requests/{supportRequest:public_id}/issue-password-token', [\App\Http\Controllers\Admin\SupportManagementController::class, 'issuePasswordToken'])->name('requests.issuePasswordToken')->middleware('throttle:10,1');
        Route::post('/requests/{supportRequest:public_id}/resolve-general', [\App\Http\Controllers\Admin\SupportManagementController::class, 'resolveGeneral'])->name('requests.resolveGeneral')->middleware('throttle:10,1');
    });

    // Public / Operations route alias: /operations/support/requests/{supportRequest:public_id}
    Route::get('/operations/support/requests/{supportRequest:public_id}', [\App\Http\Controllers\Admin\SupportManagementController::class, 'show'])
        ->name('operations.support.requests.show');

    // Wireless Operator Rapid Incident Data Entry Hub & Management Grid
    Route::get('/ops/travel-advisory', [\App\Http\Controllers\TravelAdvisoryController::class, 'operatorIndex'])->name('ops.travel-advisory.index');
    Route::get('/ops/travel-advisory/create', [\App\Http\Controllers\TravelAdvisoryController::class, 'operatorCreate'])->name('ops.travel-advisory.create');
    Route::post('/ops/travel-advisory', [\App\Http\Controllers\TravelAdvisoryController::class, 'operatorStore'])->name('ops.travel-advisory.store');
    Route::post('/ops/travel-advisory/{id}/resolve', [\App\Http\Controllers\TravelAdvisoryController::class, 'operatorResolve'])->name('ops.travel-advisory.resolve');
    Route::post('/ops/travel-advisory/{id}/extend', [\App\Http\Controllers\TravelAdvisoryController::class, 'operatorExtend'])->name('ops.travel-advisory.extend');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Public Commuter Travel Advisory Portal (nhmp.gov.pk/travel-advisory style)
Route::get('/travel-advisory', [\App\Http\Controllers\TravelAdvisoryController::class, 'publicPortal'])
    ->name('public.travel-advisory');

require __DIR__.'/auth.php';

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CallController;
use App\Models\Carriageway;

/*
|--------------------------------------------------------------------------
| API Routes — ALL routes require authentication (C-4 fix)
|--------------------------------------------------------------------------
| Rate limiting applied both here AND at Nginx level (defense-in-depth).
*/

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    Route::get('/user', fn(Request $request) => new \App\Http\Resources\UserShowResource($request->user()));

    // Beat resource lookup (tigers, officers) — served by CallController
    Route::get('/beat-resources', [CallController::class, 'getBeatResources']);

    // Unified search — tighter rate limit (geospatial OSINT reduction)
    Route::middleware('throttle:30,1')
        ->get('/unified-search', function (Request $request) {
            $q = $request->string('q')->trim();
            if ($q->length() < 2) {
                return response()->json(['results' => []]);
            }

            $carriageways = Carriageway::where(function ($query) use ($q) {
                $query->where('road_name', 'like', "%{$q}%")
                      ->orWhere('road_short', 'like', "%{$q}%")
                      ->orWhere('road', 'like', "%{$q}%");
            })->limit(5)->get(['id', 'road_name', 'road_short', 'type']);

            $beats = \App\Models\Office::beats()->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%");
            })->with(['parent.parent'])
              ->limit(5)
              ->get(['id', 'name', 'parent_id', 'type']);

            $formattedBeats = $beats->map(function($b) {
                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'sector_name' => $b->parent?->name,
                    'zone_name' => $b->parent?->parent?->name,
                ];
            });

            return response()->json([
                'carriageways' => $carriageways,
                'beats'        => $formattedBeats,
            ]);
        })->name('api.unified-search');

    // Tablet Advisory Administrative Management
    // Advisory creation is web-only (approval workflow); the API cannot create advisories.
    Route::patch('/v1/advisories/{uuid}/status', [\App\Http\Controllers\Api\TabletAdvisoryController::class, 'updateStatus'])
        ->name('api.advisories.update-status');
});

// Real-Time SSE Stream & Fast Emergency Delta for Operations
Route::middleware('throttle:60000,1')->group(function () {
    Route::get('/v1/realtime/stream', [\App\Http\Controllers\Api\RealtimeStreamController::class, 'stream'])
        ->name('api.realtime.stream');
    Route::get('/v1/realtime/emergency-check', [\App\Http\Controllers\Api\RealtimeStreamController::class, 'checkEmergency'])
        ->name('api.realtime.check-emergency');
});

// ==============================================================================
// PUBLIC TABLET ADVISORY PLATFORM ENDPOINTS (High Concurrency, ETag 304, Redis Tagged Cache)
// ==============================================================================
Route::middleware('throttle:60000,1')->prefix('v1/advisories')->group(function () {
    Route::get('/', [\App\Http\Controllers\Api\TabletAdvisoryController::class, 'index'])
        ->name('api.advisories.index');
    Route::get('/emergency-alerts', [\App\Http\Controllers\Api\TabletAdvisoryController::class, 'emergencyAlerts'])
        ->name('api.advisories.emergency-alerts');
    Route::get('/{uuid}', [\App\Http\Controllers\Api\TabletAdvisoryController::class, 'show'])
        ->name('api.advisories.show');
});

// ==============================================================================
// PUBLIC HUMSAFAR TRAVEL ADVISORY PORTAL APIS (For nhmp.gov.pk/travel-advisory)
// ==============================================================================
Route::middleware('throttle:60000,1')->prefix('v1/travel-advisory')->group(function () {
    Route::get('/routes', [\App\Http\Controllers\Api\V1\TravelAdvisoryPublicApiController::class, 'routes'])
        ->name('api.v1.travel-advisory.routes');
    Route::get('/query', [\App\Http\Controllers\Api\V1\TravelAdvisoryPublicApiController::class, 'query'])
        ->name('api.v1.travel-advisory.query');
    Route::get('/ticker', [\App\Http\Controllers\Api\V1\TravelAdvisoryPublicApiController::class, 'ticker'])
        ->name('api.v1.travel-advisory.ticker');
    Route::get('/geojson', [\App\Http\Controllers\Api\V1\TravelAdvisoryPublicApiController::class, 'geojson'])
        ->name('api.v1.travel-advisory.geojson');
    Route::get('/landmarks', [\App\Http\Controllers\Api\V1\TravelAdvisoryPublicApiController::class, 'landmarks'])
        ->name('api.v1.travel-advisory.landmarks');
});

// ==============================================================================
// HAMSAFAR ECOSYSTEM V1 MOBILE APIS (Public Auth Endpoints)
// ==============================================================================
Route::prefix('v1/auth')->group(function () {
    // Commuter 1-Tap Google Sign-In & Standard Registration
    Route::middleware('throttle:30,1')->post('/commuter/google', [\App\Http\Controllers\Api\V1\CommuterAuthController::class, 'google'])
        ->name('api.v1.auth.commuter.google');
    Route::middleware('throttle:15,1')->post('/commuter/register', [\App\Http\Controllers\Api\V1\CommuterAuthController::class, 'register'])
        ->name('api.v1.auth.commuter.register');
    Route::middleware('throttle:15,1')->post('/commuter/login', [\App\Http\Controllers\Api\V1\CommuterAuthController::class, 'login'])
        ->name('api.v1.auth.commuter.login');

    // Uniformed Patrol Officer Tactical Login
    Route::middleware('throttle:10,1')->post('/patrol/login', [\App\Http\Controllers\Api\V1\PatrolAuthController::class, 'login'])
        ->name('api.v1.auth.patrol.login');

    // Authenticated Logout Endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/commuter/logout', [\App\Http\Controllers\Api\V1\CommuterAuthController::class, 'logout'])
            ->name('api.v1.auth.commuter.logout');
        Route::post('/patrol/logout', [\App\Http\Controllers\Api\V1\PatrolAuthController::class, 'logout'])
            ->name('api.v1.auth.patrol.logout');
    });
});

// ==============================================================================
// HAMSAFAR ECOSYSTEM V1 AUTHENTICATED MOBILE APIS (Bearer Token Protected)
// ==============================================================================
Route::middleware(['auth:sanctum', 'throttle:120,1'])->prefix('v1')->group(function () {
    // Commuter Profile Management
    Route::prefix('commuter')->group(function () {
        Route::get('/profile', [\App\Http\Controllers\Api\V1\CommuterAuthController::class, 'profile'])
            ->name('api.v1.commuter.profile');
        Route::put('/profile', [\App\Http\Controllers\Api\V1\CommuterAuthController::class, 'updateProfile'])
            ->name('api.v1.commuter.profile.update');
        Route::post('/logout', [\App\Http\Controllers\Api\V1\CommuterAuthController::class, 'logout'])
            ->name('api.v1.commuter.logout');
    });

    // Patrol Officer Identity & Screen 02 Duty Setup
    Route::prefix('patrol')->middleware('hamsafar.access:field')->group(function () {
        Route::get('/me', [\App\Http\Controllers\Api\V1\PatrolAuthController::class, 'me'])
            ->name('api.v1.patrol.me');
        Route::post('/logout', [\App\Http\Controllers\Api\V1\PatrolAuthController::class, 'logout'])
            ->name('api.v1.patrol.logout');

        Route::prefix('duty')->group(function () {
            Route::get('/roster-context', [\App\Http\Controllers\Api\V1\PatrolDutyController::class, 'getRosterContext'])
                ->name('api.v1.patrol.duty.roster-context');
            Route::post('/start', [\App\Http\Controllers\Api\V1\PatrolDutyController::class, 'startDuty'])
                ->name('api.v1.patrol.duty.start');
            Route::post('/end', [\App\Http\Controllers\Api\V1\PatrolDutyController::class, 'endDuty'])
                ->name('api.v1.patrol.duty.end');
            Route::get('/status', [\App\Http\Controllers\Api\V1\PatrolDutyController::class, 'status'])
                ->name('api.v1.patrol.duty.status');
            Route::put('/status', [\App\Http\Controllers\Api\V1\PatrolDutyController::class, 'updateStatus'])
                ->name('api.v1.patrol.duty.status.update');
            Route::middleware('throttle:600,1')->post('/telemetry', [\App\Http\Controllers\Api\V1\PatrolDutyController::class, 'telemetry'])
                ->name('api.v1.patrol.duty.telemetry');
        });

        // Screen 04/05: Patrol Assigned Case Review
        Route::get('/cases/assigned', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'assigned'])
            ->name('api.v1.patrol.cases.assigned');

        // Humsafar Track B: surveys, presence heartbeat + availability
        Route::prefix('surveys')->group(function () {
            Route::get('/categories', [\App\Http\Controllers\Api\V1\PatrolSurveyController::class, 'categories'])->name('api.v1.patrol.surveys.categories');
            Route::get('/', [\App\Http\Controllers\Api\V1\PatrolSurveyController::class, 'index'])->name('api.v1.patrol.surveys.index');
            Route::post('/', [\App\Http\Controllers\Api\V1\PatrolSurveyController::class, 'store'])->name('api.v1.patrol.surveys.store');
            Route::get('/{uuid}', [\App\Http\Controllers\Api\V1\PatrolSurveyController::class, 'show'])->name('api.v1.patrol.surveys.show');
            Route::put('/{uuid}', [\App\Http\Controllers\Api\V1\PatrolSurveyController::class, 'update'])->name('api.v1.patrol.surveys.update');
        });
        Route::middleware('throttle:90,1')->post('/heartbeat', [\App\Http\Controllers\Api\V1\OfficerPresenceController::class, 'heartbeat'])->name('api.v1.patrol.heartbeat');
        Route::get('/availability', [\App\Http\Controllers\Api\V1\OfficerPresenceController::class, 'status'])->name('api.v1.patrol.availability');
        Route::put('/availability', [\App\Http\Controllers\Api\V1\OfficerPresenceController::class, 'setAvailability'])->name('api.v1.patrol.availability.set');
        Route::post('/availability/return-to-duty', [\App\Http\Controllers\Api\V1\OfficerPresenceController::class, 'returnToDuty'])->name('api.v1.patrol.availability.return');
    });

    // ==========================================================================
    // SHARED INCIDENT LIFECYCLE (Case H-1042 / Patrol P-12 Screens 04-16)
    // ==========================================================================
    Route::prefix('cases')->group(function () {
        Route::get('/{uuid}', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'show'])
            ->middleware('hamsafar.access:field')
            ->name('api.v1.cases.show');
        Route::post('/{uuid}/acknowledge', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'acknowledge'])
            ->middleware('hamsafar.access:field')
            ->name('api.v1.cases.acknowledge');
        Route::post('/{uuid}/cannot-respond', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'cannotRespond'])
            ->middleware('hamsafar.access:field')
            ->name('api.v1.cases.cannot-respond');
        Route::post('/{uuid}/start-response', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'startResponse'])
            ->middleware('hamsafar.access:field')
            ->name('api.v1.cases.start-response');
        Route::post('/{uuid}/mark-arrived', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'markArrived'])
            ->middleware('hamsafar.access:field')
            ->name('api.v1.cases.mark-arrived');
        Route::post('/{uuid}/request-support', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'requestSupport'])
            ->middleware('hamsafar.access:field')
            ->name('api.v1.cases.request-support');
        Route::post('/{uuid}/complete-assistance', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'completeAssistance'])
            ->middleware('hamsafar.access:field')
            ->name('api.v1.cases.complete-assistance');
        Route::post('/{uuid}/evidence', [\App\Http\Controllers\Api\V1\CallEvidenceController::class, 'store'])
            ->middleware(['hamsafar.access:field', 'throttle:20,1'])
            ->name('api.v1.cases.evidence.store');
        Route::post('/{uuid}/feedback', [\App\Http\Controllers\Api\V1\HelpFeedbackController::class, 'store'])
            ->name('api.v1.cases.feedback.store');
        Route::post('/{uuid}/close-and-release', [\App\Http\Controllers\Api\V1\PatrolCaseController::class, 'closeAndRelease'])
            ->middleware('hamsafar.access:field')
            ->name('api.v1.cases.close-and-release');

        // Screen 08/09: Live Assigned Patrol Tracking
        Route::get('/{uuid}/tracking', [\App\Http\Controllers\Api\V1\CommuterServicesController::class, 'tracking'])
            ->name('api.v1.cases.tracking');

        // Screen 09/10: Bidirectional Case Messaging
        Route::get('/{uuid}/messages', [\App\Http\Controllers\Api\V1\CaseChatController::class, 'index'])
            ->name('api.v1.cases.messages.index');
        Route::post('/{uuid}/messages', [\App\Http\Controllers\Api\V1\CaseChatController::class, 'store'])
            ->name('api.v1.cases.messages.store');
    });

    // ==========================================================================
    // COMMUTER ROAD USER EMERGENCY & ASSISTANCE SERVICES (Views 01-14)
    // ==========================================================================
    Route::prefix('commuter')->group(function () {
        Route::get('/nearby-locations', \App\Http\Controllers\Api\V1\NearbyLocationsController::class)->middleware('throttle:30,1')->name('api.v1.commuter.nearby-locations');
        Route::post('/presence', \App\Http\Controllers\Api\V1\CommuterPresenceController::class)->middleware('throttle:30,1')->name('api.v1.commuter.presence');
        Route::post('/sos', [\App\Http\Controllers\Api\V1\CommuterServicesController::class, 'triggerSos'])
            ->name('api.v1.commuter.sos');
        Route::get('/cases/active', [\App\Http\Controllers\Api\V1\CommuterServicesController::class, 'activeCase'])
            ->name('api.v1.commuter.cases.active');
        Route::get('/cases/history', [\App\Http\Controllers\Api\V1\CommuterServicesController::class, 'history'])
            ->name('api.v1.commuter.cases.history');
    });

    // ==========================================================================
    // WOMEN'S JOURNEY TRANSIT SAFETY MODULE (WJ-204 Screens W01-W14)
    // ==========================================================================
    Route::prefix('womens-journey')->group(function () {
        Route::post('/register', [\App\Http\Controllers\Api\V1\WomensJourneyController::class, 'register'])
            ->name('api.v1.womens-journey.register');
        Route::get('/active', [\App\Http\Controllers\Api\V1\WomensJourneyController::class, 'active'])
            ->name('api.v1.womens-journey.active');
        Route::post('/{uuid}/updates', [\App\Http\Controllers\Api\V1\WomensJourneyController::class, 'postUpdate'])
            ->name('api.v1.womens-journey.updates');
        Route::post('/{uuid}/alert', [\App\Http\Controllers\Api\V1\WomensJourneyController::class, 'triggerSilentAlert'])
            ->name('api.v1.womens-journey.alert');
        Route::post('/{uuid}/safe-arrival', [\App\Http\Controllers\Api\V1\WomensJourneyController::class, 'confirmSafeArrival'])
            ->name('api.v1.womens-journey.safe-arrival');
        Route::get('/desk-queue', [\App\Http\Controllers\Api\V1\WomensJourneyController::class, 'deskQueue'])
            ->middleware('hamsafar.access:desk')
            ->name('api.v1.womens-journey.desk-queue');
    });
});


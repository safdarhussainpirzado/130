<?php

/**
 * NHMP 130 Helpline — Production RoadRunner Long-Lived Worker
 *
 * Implements strict request lifecycle boundary isolation:
 * 1. Request, session, and facade instance forgetting.
 * 2. Complete Auth guard, gate, and user session context clearing (zero cross-user leakage).
 * 3. Scope and organizational hierarchy cache flushing (zero zone/sector/beat cross-talk).
 * 4. Dangling database transaction rollback (unwinds nested transactions).
 * 5. Connection liveness verification and stale connection recovery (MySQL & Redis).
 * 6. Read/write PDO detachment (reverts sticky write-PDO pinned by prior requests).
 * 7. Active garbage collection cycle execution to bound worker memory.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$app->instance('request', Request::create('/'));
$kernel->bootstrap();

if (class_exists('Spiral\RoadRunner\Worker') && class_exists('Nyholm\Psr7\Factory\Psr17Factory')) {
    $psr17Factory = new Psr17Factory();
    $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    $httpFoundationFactory = new HttpFoundationFactory();

    $worker = \Spiral\RoadRunner\Worker::create();
    $psr7 = new \Spiral\RoadRunner\Http\PSR7Worker($worker, $psr17Factory, $psr17Factory, $psr17Factory);

    while ($req = $psr7->waitRequest()) {
        $symfonyRequest = null;
        $laravelRequest = null;
        $response = null;
        $psr7Response = null;

        try {
            // 1. Verify DB liveness prior to handling request
            try {
                DB::connection()->getPdo();
            } catch (\Throwable $dbErr) {
                try {
                    DB::purge();
                    DB::reconnect();
                } catch (\Throwable $recErr) {
                    Log::error('RoadRunner Worker: DB reconnection failed: ' . $recErr->getMessage());
                }
            }

            // 2. Convert PSR-7 request to Symfony & Laravel Request
            $symfonyRequest = $httpFoundationFactory->createRequest($req);
            $laravelRequest = Request::createFromBase($symfonyRequest);

            $app->instance('request', $laravelRequest);
            Facade::clearResolvedInstance('request');

            // 3. Handle request through Laravel Kernel
            $response = $kernel->handle($laravelRequest);

            // 4. Send response back to RoadRunner
            $psr7Response = $psrHttpFactory->createResponse($response)->withHeader('X-Worker-Pid', (string)getmypid());
            $psr7->respond($psr7Response);

            // 5. Terminate response lifecycle
            $kernel->terminate($laravelRequest, $response);
        } catch (\Throwable $e) {
            Log::error('RoadRunner Worker Exception: ' . $e->getMessage(), [
                'exception' => $e,
                'uri' => $laravelRequest ? $laravelRequest->getRequestUri() : ($req ? (string)$req->getUri() : 'unknown'),
            ]);
            try {
                // Return structured 500 error response instead of breaking worker pipe
                $errorResponse = new \Symfony\Component\HttpFoundation\Response(
                    json_encode([
                        'error' => 'INTERNAL_SERVER_ERROR',
                        'message' => 'An error occurred while processing the request.',
                        'status' => 500,
                    ]),
                    500,
                    ['Content-Type' => 'application/json']
                );
                $psr7Response = $psrHttpFactory->createResponse($errorResponse)->withHeader('X-Worker-Pid', (string)getmypid());
                $psr7->respond($psr7Response);
            } catch (\Throwable $sendErr) {
                $psr7->getWorker()->error((string)$e);
            }
        } finally {
            // =========================================================================
            // MANDATORY LONG-RUNNING WORKER STATE & MEMORY ISOLATION GATES
            // =========================================================================

            // GATE A: Clear all session state and credentials
            if ($app->resolved('session')) {
                try {
                    $sessionDriver = $app->make('session')->driver();
                    $sessionDriver->flush();
                    $sessionDriver->regenerate();
                } catch (\Throwable $t) {}
            }

            // GATE B: Clear all Auth guards, gates, and session credentials
            if ($app->bound('auth.driver')) {
                $app->forgetInstance('auth.driver');
            }
            if ($app->bound('auth')) {
                try {
                    Auth::forgetGuards();
                } catch (\Throwable $t) {}
            }

            // GATE C: Reset user permission cache
            if (class_exists('App\Models\User')) {
                try {
                    \App\Models\User::clearPermissionCache();
                } catch (\Throwable $t) {}
            }

            // GATE D: Clear request and request session store
            $app->forgetInstance('request');
            if ($app->bound('session.store')) {
                $app->forgetInstance('session.store');
            }

            // GATE D: Clear Facades cache
            Facade::clearResolvedInstances();

            // GATE E: Flush organizational scope, user permissions & office hierarchy caches
            if (class_exists('App\Models\Office')) {
                \App\Models\Office::clearHierarchyCache();
            }
            if (class_exists('App\Models\User')) {
                \App\Models\User::clearPermissionCache();
            }

            // GATE F: Rollback dangling DB transactions (unwind any depth)
            try {
                while (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            } catch (\Throwable $trxErr) {}

            // GATE G: Reset Read/Write PDO state (detach write-PDO stickiness)
            try {
                if (method_exists(DB::connection('mysql'), 'useDefaultPdo')) {
                    DB::connection('mysql')->useDefaultPdo();
                }
            } catch (\Throwable $pdoErr) {}

            // GATE H: Verify Redis connection liveness
            if ($app->bound('redis')) {
                try {
                    $app['redis']->connection()->ping();
                } catch (\Throwable $redErr) {
                    try {
                        $app['redis']->purge();
                    } catch (\Throwable $t) {}
                }
            }

            // GATE I: Garbage collection cycle to bound worker memory growth
            unset($symfonyRequest, $laravelRequest, $response, $psr7Response);
            gc_collect_cycles();
        }
    }
}

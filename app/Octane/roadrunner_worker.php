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

// Pre-warm Pakistan geo datasets, Tor nodes, and ASN tables into read-only memory once per worker
\App\Services\Geo\GeoLookup::warmup();

$baseConfig = $app['config']->all();
$baseLocale = $app->getLocale();

if (class_exists('Spiral\RoadRunner\Worker') && class_exists('Nyholm\Psr7\Factory\Psr17Factory')) {
    $psr17Factory = new Psr17Factory();
    $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);
    $httpFoundationFactory = new HttpFoundationFactory();

    $worker = \Spiral\RoadRunner\Worker::create();
    $psr7 = new \Spiral\RoadRunner\Http\PSR7Worker($worker, $psr17Factory, $psr17Factory, $psr17Factory);

    $recycle = false;
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
            // MANDATORY STATE-ISOLATION GATES. A gate that throws is NEVER silent: it is logged and the worker is
            // marked poisoned and recycled after this request, so leftover state can never serve another user.
            // =========================================================================
            $poisoned = ! \App\Octane\RequestStateReset::run($app, $baseConfig, $baseLocale);

            unset($symfonyRequest, $laravelRequest, $response, $psr7Response);
            gc_collect_cycles();

            $recycle = $poisoned;
        }

        if ($recycle) {
            // Leave the loop: RoadRunner respawns a clean worker. The current response was already sent.
            break;
        }
    }
}

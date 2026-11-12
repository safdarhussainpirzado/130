<?php

namespace App\Octane;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Log;

/**
 * Per-request state isolation for the long-lived RoadRunner worker. run() returns false when any gate failed
 * (already logged at CRITICAL); the worker must then be recycled.
 */
class RequestStateReset
{
    public static function run($app, array $baseConfig, string $baseLocale): bool
    {
        $poisoned = false;
        $gate = function (string $name, callable $fn) use (&$poisoned): void {
                try {
                    $fn();
                } catch (\Throwable $t) {
                    $poisoned = true;
                    Log::critical("RoadRunner isolation gate FAILED: {$name}: " . $t->getMessage());
                }
            };

            $gate('session', function () use ($app) {
                if ($app->resolved('session')) {
                    $driver = $app->make('session')->driver();
                    $driver->flush();
                    $driver->regenerate();
                }
            });
            $gate('auth', function () use ($app) {
                if ($app->bound('auth.driver')) { $app->forgetInstance('auth.driver'); }
                if ($app->bound('auth')) { Auth::forgetGuards(); }
            });
            $gate('permission-cache', fn () => \App\Models\User::clearPermissionCache());
            $gate('office-cache', fn () => \App\Models\Office::clearHierarchyCache());
            $gate('request-session-instances', function () use ($app) {
                $app->forgetInstance('request');
                if ($app->bound('session.store')) { $app->forgetInstance('session.store'); }
            });
            $gate('queued-cookies', function () use ($app) {
                if ($app->resolved('cookie')) { $app->make('cookie')->flushQueuedCookies(); }
            });
            $gate('config-and-locale', function () use ($app, $baseConfig, $baseLocale) {
                $app['config']->set($baseConfig);
                $app->setLocale($baseLocale);
            });
            $gate('facades', fn () => Facade::clearResolvedInstances());
            $gate('db-transactions', function () {
                while (DB::transactionLevel() > 0) { DB::rollBack(); }
            });
            $gate('db-pdo', function () {
                $c = DB::connection('mysql');
                if (method_exists($c, 'useDefaultPdo')) { $c->useDefaultPdo(); }
            });
            $gate('redis', function () use ($app) {
                if ($app->bound('redis')) {
                    try { $app['redis']->connection()->ping(); } catch (\Throwable $e) { $app['redis']->purge(); }
                }
            });
            // Verification: after the reset, no guard may still hold a user.
            $gate('verify-no-user', function () use ($app) {
                if ($app->bound('auth') && Auth::guard('web')->hasUser()) {
                    throw new \RuntimeException('web guard still holds a user after reset');
                }
            });

        return ! $poisoned;
    }
}

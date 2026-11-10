<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\Database\ReplicaLagManager;

class EnsureReadWriteConsistency
{
    /**
     * Handle an incoming request.
     * Ensures consistency across separate HTTP requests when user has recent writes
     * or when accessing critical emergency call/dispatch endpoints.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Force write PDO for consistency-sensitive emergency routes
        if ($request->is('api/v1/calls*', 'api/v1/dispatch*', 'api/v1/telephony*')) {
            ReplicaLagManager::requireFreshData();
        }

        // 2. Check if this session recently executed a write
        if (ReplicaLagManager::hasActiveWriteStickyRequirement()) {
            ReplicaLagManager::requireFreshData();
        }

        $response = $next($request);

        // 3. If this request was a mutating HTTP method on CRM data, mark session as sticky
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            ReplicaLagManager::markWriteActivity(3);
        }

        return $response;
    }
}

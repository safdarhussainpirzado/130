<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role/permission gate for the Hamsafar bearer-token APIs.
 *
 * Sanctum only proves "some valid token". Without this, a commuter token (anyone can self-register)
 * could call patrol endpoints and the 130 Control women's-journey desk queue (passenger PII).
 *
 * Usage:  hamsafar.access:field   -> patrol/field roles only
 *         hamsafar.access:desk    -> users holding womens_journey.desk.view
 */
class EnsureHamsafarAccess
{
    /** Same roles allowed to sign in to the patrol app (PatrolAuthController::login). */
    private const FIELD_ROLES = ['patrol_officer', 'beat_operator', 'operation_admin', 'super_admin'];

    public function handle(Request $request, Closure $next, string $mode): Response
    {
        $user = $request->user();

        $allowed = match ($mode) {
            'field' => $user && $user->is_active && in_array($user->role?->name, self::FIELD_ROLES, true),
            'desk'  => $user && $user->is_active && $user->hasPermission('womens_journey.desk.view'),
            default => false, // unknown mode fails closed
        };

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorised to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}

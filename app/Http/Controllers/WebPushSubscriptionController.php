<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class WebPushSubscriptionController extends Controller
{
    private const MAX_SUBSCRIPTIONS_PER_USER = 10;

    /**
     * Return the public RFC 8292 VAPID key to authenticated clients.
     */
    public function getPublicKey(): JsonResponse
    {
        $publicKey = config('services.webpush.public_key');

        return response()->json([
            'publicKey' => $publicKey,
        ]);
    }

    /**
     * Subscribe client browser to Web Push notifications.
     * Hardened against SSRF, IDOR, parameter tampering, and key abuse.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'endpoint' => ['required', 'string', 'url', 'max:1000'],
            'keys.p256dh' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9\-_=]+$/'],
            'keys.auth' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9\-_=]+$/'],
        ], [
            'endpoint.url' => 'The push service endpoint must be a valid HTTPS URL.',
            'keys.p256dh.regex' => 'The P-256 ECDH key must be base64url encoded.',
            'keys.auth.regex' => 'The authentication secret must be base64url encoded.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $endpoint = (string) $request->input('endpoint');
        $parsedUrl = parse_url($endpoint);
        $scheme = strtolower($parsedUrl['scheme'] ?? '');
        $host = strtolower($parsedUrl['host'] ?? '');

        // 1. SSRF Guard: Scheme must strictly be HTTPS
        if ($scheme !== 'https') {
            return response()->json(['error' => 'The push endpoint must use HTTPS.'], 422);
        }

        // 2. SSRF Guard: Host must match legitimate browser push vendor gateway allow-list
        if (! $this->isAllowedPushHost($host)) {
            return response()->json([
                'error' => 'The push endpoint host is not an authorized Web Push gateway vendor.',
            ], 422);
        }

        // 3. User Subscription Cap: Max 10 active endpoints per user
        $activeSubs = PushSubscription::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($activeSubs->count() >= self::MAX_SUBSCRIPTIONS_PER_USER) {
            // Deactivate the oldest active subscription to stay within quota
            $activeSubs->first()?->update(['is_active' => false]);
        }

        // 4. Register or update subscription
        $subscription = PushSubscription::registerSubscription([
            'user_id' => $user->id,
            'office_id' => $user->office?->id,
            'device_type' => 'web_crm',
            'provider' => 'webpush',
            'endpoint' => $endpoint,
            'public_key' => $request->input('keys.p256dh'),
            'auth_token' => $request->input('keys.auth'),
            'user_agent' => $request->userAgent() ?? 'Web Browser',
        ]);

        return response()->json([
            'status' => 'subscribed',
            'subscription' => [
                'id' => $subscription->id,
                'created_at' => $subscription->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Unsubscribe client browser from Web Push notifications.
     * Enforces Anti-IDOR: Users may only deactivate their own registered subscriptions.
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        $request->validate([
            'endpoint' => ['required', 'string', 'url'],
        ]);

        $endpoint = (string) $request->input('endpoint');
        $hash = hash('sha256', $endpoint);

        $subscription = PushSubscription::where('token_hash', $hash)->first();

        if ($subscription) {
            // Anti-IDOR check: Prevent a user from deactivating another user's device endpoint
            if ((int) $subscription->user_id !== (int) $user->id) {
                return response()->json(['error' => 'Unauthorized device endpoint operation.'], 403);
            }

            $subscription->update(['is_active' => false]);
        }

        return response()->json([
            'status' => 'unsubscribed',
        ]);
    }

    /**
     * SSRF allow-list validation for Web Push gateway endpoints.
     */
    public function isAllowedPushHost(string $host): bool
    {
        $host = strtolower(trim($host));

        $exactAllowed = [
            'fcm.googleapis.com',
            'updates.push.services.mozilla.com',
            'notify.windows.com',
            'web.push.apple.com',
        ];

        if (in_array($host, $exactAllowed, true)) {
            return true;
        }

        // Allow wildcard subdomains for Windows Notification Service and Apple Push Service
        if (str_ends_with($host, '.notify.windows.com') || str_ends_with($host, '.push.apple.com')) {
            return true;
        }

        return false;
    }
}

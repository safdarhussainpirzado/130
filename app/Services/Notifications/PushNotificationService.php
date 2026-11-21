<?php

namespace App\Services\Notifications;

use App\Models\Call;
use App\Models\Office;
use App\Models\PushSubscription;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\TabletAdvisory;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Throwable;

class PushNotificationService
{
    public const MAX_TITLE_LENGTH = 80;

    public const MAX_BODY_LENGTH = 200;

    public const DEDUP_TTL_SECONDS = 600; // 10 minutes

    public const USER_RATE_LIMIT_PER_MINUTE = 30;

    public function __construct(
        protected WebPushService $webPushService,
        protected FcmService $fcmService
    ) {}

    /**
     * Notify desk users of a newly inserted pending help call.
     *
     * Recipient Matrix:
     * - Beat & Sector operators whose active UserScope covers the call's beat / sector / zone.
     * - Agent supervisors whose scope or role covers that zone.
     * - Operation Admins (IT_ADMIN) & Super Admins for every pending help (all priorities).
     * - Strictly NO patrol officer or commuter profile queries.
     *
     * @return array{recipients: int, webpush: array, fcm: array}
     */
    public function notifyHelpPending(Call $call): array
    {
        if ($call->status !== 'pending') {
            return ['recipients' => 0, 'webpush' => ['sent' => 0, 'failed' => 0], 'fcm' => ['sent' => 0, 'failed' => 0]];
        }

        $recipientUserIds = $this->resolveDeskRecipientsForHelp($call);
        if (empty($recipientUserIds)) {
            return ['recipients' => 0, 'webpush' => ['sent' => 0, 'failed' => 0], 'fcm' => ['sent' => 0, 'failed' => 0]];
        }

        $priorityNum = (int) ($call->priority ?: 3);
        $isP1 = $call->isP1();
        $isP1OrP2 = $call->isP1OrP2();
        $priorityLabel = $call->getPriorityLabel();

        // Payload hygiene: strictly NO caller name, phone number, CNIC, or victim details
        $beatName = $call->office?->name ?? 'Unassigned Beat';
        $typeName = $call->callType?->name ?? 'Help Request';

        $title = $this->sanitizeText("🚨 NHMP 130 [{$priorityLabel}]: Pending Help {$call->call_number}", self::MAX_TITLE_LENGTH);
        $body = $this->sanitizeText("Beat: {$beatName} | Type: {$typeName} | Requires immediate operational dispatch.", self::MAX_BODY_LENGTH);
        $url = $this->resolveCallUrl($call);

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/images/nhmp-logo.png',
            'badge' => '/images/nhmp-badge.png',
            'tag' => "call-pending-{$call->id}",
            'data' => [
                'type' => 'help_pending',
                'call_id' => $call->id,
                'call_number' => $call->call_number,
                'priority' => $priorityNum,
                'url' => $url,
                'requireInteraction' => $isP1OrP2,
            ],
        ];

        return $this->dispatchToSubscriptions($recipientUserIds, $payload, 'help_pending', $call->id);
    }

    /**
     * Notify desk users of Travel Advisory lifecycle events.
     *
     * Recipient Matrix:
     * - Sector Operator creates/updates -> Notify Supervisors covering that zone.
     * - Supervisor approves/rejects/extends/clears -> Notify originating sector operators and beat operators.
     *
     * @param  string  $eventType  'created'|'approved'|'rejected'|'updated'|'extended'|'resolved'
     * @return array{recipients: int, webpush: array, fcm: array}
     */
    public function notifyAdvisory(TabletAdvisory $advisory, string $eventType, array $extra = []): array
    {
        $recipientUserIds = $this->resolveDeskRecipientsForAdvisory($advisory, $eventType);
        if (empty($recipientUserIds)) {
            return ['recipients' => 0, 'webpush' => ['sent' => 0, 'failed' => 0], 'fcm' => ['sent' => 0, 'failed' => 0]];
        }

        $eventLabel = match ($eventType) {
            'approved' => 'Approved & Live',
            'rejected' => 'Rejected',
            'updated' => 'Updated',
            'extended' => 'Extended',
            'resolved' => 'Cleared / Resolved',
            default => 'New Advisory Submitted',
        };

        $sectorName = $advisory->sector?->name ?? $advisory->route_name ?? 'National Highways';
        $routeName = $advisory->route_name ?? 'Highway';
        $category = ucfirst((string) ($advisory->category ?? 'Alert'));

        $title = $this->sanitizeText("⚠️ Advisory {$eventLabel}: {$routeName}", self::MAX_TITLE_LENGTH);
        $body = $this->sanitizeText("{$sectorName} | {$category}: ".($advisory->title ?? 'Travel Advisory Update'), self::MAX_BODY_LENGTH);
        $url = $this->resolveAdvisoryUrl($advisory, $eventType);

        $payload = [
            'title' => $title,
            'body' => $body,
            'icon' => '/images/nhmp-logo.png',
            'badge' => '/images/nhmp-badge.png',
            'tag' => "advisory-{$advisory->id}-{$eventType}",
            'data' => [
                'type' => 'travel_advisory',
                'advisory_id' => $advisory->id,
                'event_type' => $eventType,
                'url' => $url,
                'requireInteraction' => false,
            ],
        ];

        return $this->dispatchToSubscriptions($recipientUserIds, $payload, "advisory_{$eventType}", $advisory->id);
    }

    /**
     * Notify desk users of Support Desk ticket events.
     *
     * Recipient Matrix:
     * - Ticket created -> Operation Admins and assigned office operators.
     * - Action taken -> Requester (portal view) and assigned approvers (admin view).
     *
     * @return array{recipients: int, webpush: array, fcm: array}
     */
    public function notifySupport(SupportRequest $request, string $actionType): array
    {
        $recipientUserIds = $this->resolveDeskRecipientsForSupport($request, $actionType);
        if (empty($recipientUserIds)) {
            return ['recipients' => 0, 'webpush' => ['sent' => 0, 'failed' => 0], 'fcm' => ['sent' => 0, 'failed' => 0]];
        }

        $statusLabel = ucwords(str_replace('_', ' ', (string) $request->current_status));
        $actionLabel = ucwords(str_replace('_', ' ', (string) $actionType));

        $title = $this->sanitizeText("🎫 Support Desk: {$actionLabel}", self::MAX_TITLE_LENGTH);
        $body = $this->sanitizeText("Ticket #{$request->id} ({$statusLabel}) | Subject: {$request->subject}", self::MAX_BODY_LENGTH);

        if ($actionType === 'created' || $actionType === SupportRequest::STATUS_SUBMITTED) {
            $adminUrl = $this->resolveSupportAdminUrl($request);
            $payload = [
                'title' => $title,
                'body' => $body,
                'icon' => '/images/nhmp-logo.png',
                'badge' => '/images/nhmp-badge.png',
                'tag' => "support-{$request->id}-{$actionType}",
                'data' => [
                    'type' => 'support_request',
                    'support_request_id' => $request->id,
                    'public_id' => $request->public_id,
                    'action_type' => $actionType,
                    'url' => $adminUrl,
                    'requireInteraction' => false,
                ],
            ];

            return $this->dispatchToSubscriptions($recipientUserIds, $payload, "support_{$actionType}", $request->id);
        }

        // Action taken: Requester views via portal route, approver views via admin route
        $requesterId = (int) $request->requester_id;
        $approverId = (int) $request->assigned_approver_id;

        $results = ['recipients' => 0, 'webpush' => ['sent' => 0, 'failed' => 0], 'fcm' => ['sent' => 0, 'failed' => 0]];

        if ($requesterId > 0 && in_array($requesterId, $recipientUserIds, true)) {
            $portalPayload = [
                'title' => $title,
                'body' => $body,
                'icon' => '/images/nhmp-logo.png',
                'badge' => '/images/nhmp-badge.png',
                'tag' => "support-{$request->id}-{$actionType}",
                'data' => [
                    'type' => 'support_request',
                    'support_request_id' => $request->id,
                    'public_id' => $request->public_id,
                    'action_type' => $actionType,
                    'url' => $this->resolveSupportRequesterUrl($request),
                    'requireInteraction' => false,
                ],
            ];
            $r = $this->dispatchToSubscriptions([$requesterId], $portalPayload, "support_{$actionType}_req", $request->id);
            $results['recipients'] += $r['recipients'];
            $results['webpush']['sent'] += $r['webpush']['sent'] ?? 0;
            $results['webpush']['failed'] += $r['webpush']['failed'] ?? 0;
            $results['fcm']['sent'] += $r['fcm']['sent'] ?? 0;
            $results['fcm']['failed'] += $r['fcm']['failed'] ?? 0;
        }

        $adminRecipients = array_diff($recipientUserIds, [$requesterId]);
        if (! empty($adminRecipients)) {
            $adminPayload = [
                'title' => $title,
                'body' => $body,
                'icon' => '/images/nhmp-logo.png',
                'badge' => '/images/nhmp-badge.png',
                'tag' => "support-{$request->id}-{$actionType}",
                'data' => [
                    'type' => 'support_request',
                    'support_request_id' => $request->id,
                    'public_id' => $request->public_id,
                    'action_type' => $actionType,
                    'url' => $this->resolveSupportAdminUrl($request),
                    'requireInteraction' => false,
                ],
            ];
            $r = $this->dispatchToSubscriptions($adminRecipients, $adminPayload, "support_{$actionType}_adm", $request->id);
            $results['recipients'] += $r['recipients'];
            $results['webpush']['sent'] += $r['webpush']['sent'] ?? 0;
            $results['webpush']['failed'] += $r['webpush']['failed'] ?? 0;
            $results['fcm']['sent'] += $r['fcm']['sent'] ?? 0;
            $results['fcm']['failed'] += $r['fcm']['failed'] ?? 0;
        }

        return $results;
    }

    /**
     * Dispatch a harmless test notification to a specific desk user's active subscriptions.
     *
     * @return array{recipients: int, webpush: array, fcm: array}
     */
    public function notifyTest(User $user): array
    {
        $dashboardUrl = Route::has('dashboard') ? route('dashboard', [], false) : '/dashboard';

        $payload = [
            'title' => '🚨 NHMP 130: Test Notification',
            'body' => 'Desk push notifications are active and functioning correctly.',
            'icon' => '/favicon-192x192.png',
            'badge' => '/favicon-32x32.png',
            'tag' => "test-notification-{$user->id}-".time(),
            'data' => [
                'type' => 'test_notification',
                'user_id' => $user->id,
                'url' => $dashboardUrl,
                'requireInteraction' => false,
            ],
        ];

        return $this->dispatchToSubscriptions([$user->id], $payload, 'test_ping', (string) time());
    }

    /**
     * Dispatch notification payload to active subscriptions for the given users.
     * Handles deduplication, rate-limiting, and channel routing.
     *
     * @param  array<int>  $userIds
     * @return array{recipients: int, webpush: array, fcm: array}
     */
    protected function dispatchToSubscriptions(array $userIds, array $payload, string $channel, string|int $eventId): array
    {
        $filteredUserIds = [];

        foreach ($userIds as $userId) {
            $userId = (int) $userId;

            // 1. Deduplication per (channel, eventId, userId) with 10-minute window
            $dedupKey = "push:dedup:{$channel}:{$eventId}:{$userId}";
            if (! Cache::add($dedupKey, true, self::DEDUP_TTL_SECONDS)) {
                continue;
            }

            // 2. Per-user rate limiting (max 30/minute)
            $rateKey = "push:rate:{$userId}";
            if (RateLimiter::tooManyAttempts($rateKey, self::USER_RATE_LIMIT_PER_MINUTE)) {
                $this->logRateLimited('warning', "Push rate limit exceeded for user {$userId}. Notification suppressed.");

                continue;
            }
            RateLimiter::hit($rateKey, 60);

            $filteredUserIds[] = $userId;
        }

        if (empty($filteredUserIds)) {
            return ['recipients' => 0, 'webpush' => ['sent' => 0, 'failed' => 0], 'fcm' => ['sent' => 0, 'failed' => 0]];
        }

        try {
            $subscriptions = PushSubscription::active()
                ->whereIn('user_id', $filteredUserIds)
                ->get();
        } catch (Throwable $e) {
            $subscriptions = collect();
            $this->logRateLimited('error', 'PushSubscription query failed: '.$e->getMessage());
        }

        $webPushSubs = $subscriptions->where('provider', 'webpush');
        $fcmSubs = $subscriptions->where('provider', 'fcm');

        $webPushResult = ['sent' => 0, 'failed' => 0];
        if ($webPushSubs->isNotEmpty()) {
            $webPushResult = $this->webPushService->send($webPushSubs, $payload);
        }

        $fcmResult = ['sent' => 0, 'failed' => 0];
        if ($fcmSubs->isNotEmpty()) {
            $fcmTokens = $fcmSubs->pluck('endpoint')->filter()->values()->toArray();
            if (! empty($fcmTokens)) {
                $fcmNotification = [
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                ];
                $fcmResult = $this->fcmService->sendToTokens($fcmTokens, $fcmNotification, $payload['data'] ?? []);
            }
        }

        return [
            'recipients' => count($filteredUserIds),
            'webpush' => $webPushResult,
            'fcm' => $fcmResult,
        ];
    }

    /**
     * Resolve eligible desk users for Help Pending notification.
     *
     * @return array<int>
     */
    protected function resolveDeskRecipientsForHelp(Call $call): array
    {
        $officeIds = [];
        if ($call->office_id) {
            $officeIds[] = (int) $call->office_id;
            // Also include parent sector/zone if office is a beat
            $office = $call->office ?: Office::find($call->office_id);
            if ($office && $office->parent_id) {
                $officeIds[] = (int) $office->parent_id;
                $parent = $office->parent ?: Office::find($office->parent_id);
                if ($parent && $parent->parent_id) {
                    $officeIds[] = (int) $parent->parent_id;
                }
            }
        }

        $userIds = [];

        // 1. Scoped users covering the call's beat / sector / zone
        if (! empty($officeIds)) {
            $scopedUsers = UserScope::whereIn('office_id', $officeIds)
                ->where('is_active', true)
                ->pluck('user_id')
                ->toArray();
            $userIds = array_merge($userIds, $scopedUsers);
        }

        // 2. Agent Supervisors
        $supervisors = User::whereHas('role', function ($q) {
            $q->whereIn('name', [Role::AGENT_SUPERVISOR, Role::ZONE_ADMIN]);
        })
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->pluck('id')
            ->toArray();

        $userIds = array_merge($userIds, $supervisors);

        // 3. Operation Admins & Super Admins receive EVERY pending help (owner decision 2026-11-21).
        $admins = User::whereHas('role', function ($q) {
            $q->whereIn('name', [Role::IT_ADMIN, Role::SUPER_ADMIN]);
        })
            ->where('is_active', true)
            ->where('is_blocked', false)
            ->limit(500)
            ->pluck('id')
            ->toArray();

        $userIds = array_merge($userIds, $admins);

        $userIds = array_values(array_unique(array_filter($userIds)));
        if (count($userIds) > 500) {
            $this->logRateLimited('warning', 'Push recipient count for call exceeded 500 ('.count($userIds).'). Capped to first 500 recipients.');
            $userIds = array_slice($userIds, 0, 500);
        }

        return $userIds;
    }

    /**
     * Resolve eligible desk users for Travel Advisory lifecycle.
     *
     * @return array<int>
     */
    protected function resolveDeskRecipientsForAdvisory(TabletAdvisory $advisory, string $eventType): array
    {
        $userIds = [];

        if (in_array($eventType, ['created', 'updated'], true)) {
            // Operator submitted or modified -> Alert Supervisors & Zone Admins
            $supervisors = User::whereHas('role', function ($q) {
                $q->whereIn('name', [Role::AGENT_SUPERVISOR, Role::ZONE_ADMIN, Role::SUPER_ADMIN]);
            })
                ->where('is_active', true)
                ->where('is_blocked', false)
                ->limit(500)
                ->pluck('id')
                ->toArray();

            $userIds = array_merge($userIds, $supervisors);
        } else {
            // Supervisor approved, rejected, extended, or cleared -> Alert Originating Sector & Beat operators
            if ($advisory->created_by) {
                $userIds[] = (int) $advisory->created_by;
            }

            $officeIds = array_filter([(int) $advisory->office_id, (int) $advisory->sector_id]);
            if (! empty($officeIds)) {
                $sectorOperators = UserScope::whereIn('office_id', $officeIds)
                    ->where('is_active', true)
                    ->limit(500)
                    ->pluck('user_id')
                    ->toArray();
                $userIds = array_merge($userIds, $sectorOperators);
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        if (count($userIds) > 500) {
            $this->logRateLimited('warning', 'Push recipient count for advisory exceeded 500 ('.count($userIds).'). Capped to first 500 recipients.');
            $userIds = array_slice($userIds, 0, 500);
        }

        return $userIds;
    }

    /**
     * Resolve eligible desk users for Support Request ticket lifecycle.
     *
     * @return array<int>
     */
    protected function resolveDeskRecipientsForSupport(SupportRequest $request, string $actionType): array
    {
        $userIds = [];

        if ($actionType === 'created' || $actionType === SupportRequest::STATUS_SUBMITTED) {
            // Alert Operation Admins and destination office
            $admins = User::whereHas('role', function ($q) {
                $q->whereIn('name', [Role::IT_ADMIN, Role::SUPER_ADMIN]);
            })
                ->where('is_active', true)
                ->where('is_blocked', false)
                ->limit(500)
                ->pluck('id')
                ->toArray();

            $userIds = array_merge($userIds, $admins);

            if ($request->destination_office_id) {
                $destUsers = UserScope::where('office_id', $request->destination_office_id)
                    ->where('is_active', true)
                    ->limit(500)
                    ->pluck('user_id')
                    ->toArray();
                $userIds = array_merge($userIds, $destUsers);
            }
        } else {
            // Action taken: alert the ticket requester and assigned approver
            if ($request->requester_id) {
                $userIds[] = (int) $request->requester_id;
            }
            if ($request->assigned_approver_id) {
                $userIds[] = (int) $request->assigned_approver_id;
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));
        if (count($userIds) > 500) {
            $this->logRateLimited('warning', 'Push recipient count for support exceeded 500 ('.count($userIds).'). Capped to first 500 recipients.');
            $userIds = array_slice($userIds, 0, 500);
        }

        return $userIds;
    }

    /**
     * Dynamically resolve relative URL for Call record.
     */
    public function resolveCallUrl(Call $call): string
    {
        try {
            if (Route::has('calls.show')) {
                $param = ! empty($call->uuid) ? $call->uuid : $call->id;

                return $this->sanitizeRelativeUrl(route('calls.show', ['call' => $param], false));
            }
        } catch (Throwable) {
        }

        return $this->sanitizeRelativeUrl("/calls/{$call->id}");
    }

    /**
     * Dynamically resolve relative URL for Travel Advisory event.
     */
    public function resolveAdvisoryUrl(TabletAdvisory $advisory, string $eventType): string
    {
        try {
            if (Route::has('ops.travel-advisory.index')) {
                $params = in_array($eventType, ['created', 'updated'], true)
                    ? ['status' => 'pending_approval']
                    : [];

                return $this->sanitizeRelativeUrl(route('ops.travel-advisory.index', $params, false));
            }
        } catch (Throwable) {
        }

        return $this->sanitizeRelativeUrl(
            in_array($eventType, ['created', 'updated'], true)
                ? '/ops/travel-advisory?status=pending_approval'
                : '/ops/travel-advisory'
        );
    }

    /**
     * Dynamically resolve relative URL for Support Request admin management.
     */
    public function resolveSupportAdminUrl(SupportRequest $request): string
    {
        try {
            if (Route::has('admin.support.requests.show')) {
                return $this->sanitizeRelativeUrl(route('admin.support.requests.show', ['supportRequest' => $request->public_id], false));
            }
        } catch (Throwable) {
        }

        return $this->sanitizeRelativeUrl("/mgmt/support/requests/{$request->public_id}");
    }

    /**
     * Dynamically resolve relative URL for Support Request requester portal.
     */
    public function resolveSupportRequesterUrl(SupportRequest $request): string
    {
        try {
            if (Route::has('portal.support.show')) {
                return $this->sanitizeRelativeUrl(route('portal.support.show', ['supportRequest' => $request->public_id], false));
            }
        } catch (Throwable) {
        }

        return $this->sanitizeRelativeUrl("/portal/support/requests/{$request->public_id}");
    }

    /**
     * Sanitize and length-bound text strings for push payloads.
     */
    public function sanitizeText(string $text, int $maxChars): string
    {
        $clean = strip_tags($text);
        $clean = preg_replace('/\s+/', ' ', $clean);
        $clean = trim((string) $clean);

        return mb_substr($clean, 0, $maxChars);
    }

    /**
     * Strictly validate and enforce same-origin relative URL.
     * Rejects any external or protocol-relative schemes.
     */
    public function sanitizeRelativeUrl(string $url): string
    {
        $url = trim($url);

        // Must start with '/' and must not start with '//' (protocol-relative)
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return '/';
        }

        // Must not contain scheme delimiter
        if (str_contains($url, '://')) {
            return '/';
        }

        return $url;
    }

    /**
     * Rate-limited logger to prevent log flooding.
     */
    private function logRateLimited(string $level, string $message): void
    {
        $logKey = 'push:service:log_ratelimit:'.md5($message);
        if (Cache::add($logKey, true, 3600)) {
            Log::log($level, $message);
        }
    }
}

<?php

namespace App\Services\Notifications;

use App\Jobs\SendAdvisoryPush;
use App\Jobs\SendHelpPendingPush;
use App\Jobs\SendSupportPush;
use App\Models\Call;
use App\Models\SupportRequest;
use App\Models\TabletAdvisory;
use Illuminate\Support\Facades\Log;

class PushDispatcher
{
    /**
     * Check if push notifications are enabled globally.
     */
    public static function isEnabled(): bool
    {
        return (bool) config('services.push.enabled', false);
    }

    /**
     * Dispatch help pending push only if enabled.
     */
    public static function dispatchHelpPending(Call $call): void
    {
        if (! self::isEnabled()) {
            return;
        }

        try {
            SendHelpPendingPush::dispatch($call)->afterCommit();
        } catch (\Throwable $e) {
            Log::warning("Push dispatch failed on call {$call->id}: ".$e->getMessage());
        }
    }

    /**
     * Dispatch advisory push only if enabled.
     */
    public static function dispatchAdvisory(TabletAdvisory $advisory, string $eventType, array $extra = []): void
    {
        if (! self::isEnabled()) {
            return;
        }

        try {
            SendAdvisoryPush::dispatch($advisory, $eventType, $extra)->afterCommit();
        } catch (\Throwable $e) {
            Log::warning("Advisory push dispatch failed on advisory {$advisory->id}: ".$e->getMessage());
        }
    }

    /**
     * Dispatch support push only if enabled.
     */
    public static function dispatchSupport(SupportRequest $request, string $actionType): void
    {
        if (! self::isEnabled()) {
            return;
        }

        try {
            SendSupportPush::dispatch($request, $actionType)->afterCommit();
        } catch (\Throwable $e) {
            Log::warning("Support push dispatch failed for request {$request->id}: ".$e->getMessage());
        }
    }
}

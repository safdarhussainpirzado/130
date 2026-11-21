<?php

namespace App\Jobs;

use App\Models\Call;
use App\Services\Notifications\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendHelpPendingPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $timeout = 30;

    public function __construct(public Call $call, public int $repeat = 0)
    {
        $this->onQueue(config('services.push.queue', 'push'));
    }

    public function handle(PushNotificationService $pushService): void
    {
        try {
            // Re-read the status: once the help leaves 'pending' the reminders stop immediately.
            $fresh = $this->call->exists ? Call::query()->find($this->call->id) : $this->call;
            if (! $fresh || $fresh->status !== 'pending') {
                return;
            }
            $pushService->notifyHelpPending($fresh, $this->repeat);
        } catch (Throwable $e) {
            Log::error("SendHelpPendingPush job failed for call {$this->call->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}

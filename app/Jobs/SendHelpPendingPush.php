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

    public function __construct(public Call $call)
    {
        $this->onQueue(config('services.push.queue', 'push'));
    }

    public function handle(PushNotificationService $pushService): void
    {
        try {
            $pushService->notifyHelpPending($this->call);
        } catch (Throwable $e) {
            Log::error("SendHelpPendingPush job failed for call {$this->call->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}

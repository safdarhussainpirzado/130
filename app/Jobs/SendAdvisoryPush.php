<?php

namespace App\Jobs;

use App\Models\TabletAdvisory;
use App\Services\Notifications\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendAdvisoryPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $timeout = 30;

    public function __construct(
        public TabletAdvisory $advisory,
        public string $eventType,
        public array $extra = []
    ) {
        $this->onQueue(config('services.push.queue', 'push'));
    }

    public function handle(PushNotificationService $pushService): void
    {
        try {
            $pushService->notifyAdvisory($this->advisory, $this->eventType, $this->extra);
        } catch (Throwable $e) {
            Log::error("SendAdvisoryPush job failed for advisory {$this->advisory->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}

<?php

namespace App\Jobs;

use App\Models\SupportRequest;
use App\Services\Notifications\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendSupportPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $timeout = 30;

    public function __construct(
        public SupportRequest $request,
        public string $actionType
    ) {
        $this->onQueue(config('services.push.queue', 'push'));
    }

    public function handle(PushNotificationService $pushService): void
    {
        try {
            $pushService->notifySupport($this->request, $this->actionType);
        } catch (Throwable $e) {
            Log::error("SendSupportPush job failed for request {$this->request->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}

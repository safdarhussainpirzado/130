<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTestPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $timeout = 30;

    public function __construct(public User $user)
    {
        $this->onQueue(config('services.push.queue', 'push'));
    }

    public function handle(PushNotificationService $pushService): void
    {
        try {
            $pushService->notifyTest($this->user);
        } catch (Throwable $e) {
            Log::error("SendTestPush job failed for user {$this->user->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);
            throw $e;
        }
    }
}

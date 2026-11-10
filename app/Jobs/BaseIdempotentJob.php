<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

abstract class BaseIdempotentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Unique key representing the idempotent operation.
     */
    abstract public function idempotencyKey(): string;

    /**
     * The business logic to execute.
     */
    abstract public function handleIdempotent(): void;

    /**
     * Execute the job with distributed lock protection.
     */
    public function handle(): void
    {
        $key = 'nhmp:job:idempotency:' . $this->idempotencyKey();
        
        // 1. Check if job was already processed to completion (TTL 24 hours)
        $completedKey = $key . ':completed';
        if (Redis::get($completedKey)) {
            Log::info('BaseIdempotentJob: Skipping duplicate job execution', [
                'job' => static::class,
                'key' => $this->idempotencyKey()
            ]);
            return;
        }

        // 2. Acquire atomic execution lock (lease for timeout + 10s)
        $lockKey = $key . ':lock';
        $acquired = Redis::set($lockKey, 'locked', 'EX', $this->timeout + 10, 'NX');

        if (!$acquired) {
            Log::warning('BaseIdempotentJob: Job is currently locked by another worker process, releasing back to queue', [
                'job' => static::class,
                'key' => $this->idempotencyKey()
            ]);
            $this->release(3);
            return;
        }

        try {
            $this->handleIdempotent();
            
            // 3. Mark job as successfully completed
            Redis::set($completedKey, '1', 'EX', 86400);
        } finally {
            Redis::del($lockKey);
        }
    }
}

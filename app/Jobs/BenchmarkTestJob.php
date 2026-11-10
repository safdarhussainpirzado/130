<?php

namespace App\Jobs;

class BenchmarkTestJob extends BaseIdempotentJob
{
    public string $queueName;
    public int $taskId;

    public function __construct(string $queueName, int $taskId)
    {
        $this->queueName = $queueName;
        $this->taskId = $taskId;
        $this->onQueue($queueName);
    }

    public function idempotencyKey(): string
    {
        return "bench_{$this->queueName}_{$this->taskId}";
    }

    public function handleIdempotent(): void
    {
        // Simulate real processing workload (10ms)
        usleep(10000);
    }
}

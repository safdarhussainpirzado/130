<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CrmTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $markerPath;

    public function __construct(string $markerPath = '/tmp/crm_queue_worker_test.marker')
    {
        $this->markerPath = $markerPath;
    }

    public function handle(): void
    {
        file_put_contents($this->markerPath, 'PROCESSED_AT_' . date('c'));
    }
}

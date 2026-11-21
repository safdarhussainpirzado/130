<?php

namespace App\Console\Commands;

use App\Jobs\SendHelpPendingPush;
use App\Models\Call;
use App\Services\Notifications\PushDispatcher;
use Illuminate\Console\Command;

/**
 * Every minute, re-alert the related beat/sector/zone staff for each help that is STILL pending.
 * Stops by itself the moment the status leaves 'pending' (initiated, assigned, completed...).
 */
class PushRepeatPendingHelpCommand extends Command
{
    protected $signature = 'push:repeat-pending-help {--max-age-hours=24 : Do not remind about helps older than this}';

    protected $description = 'Send a reminder push every minute for helps that are still pending';

    public function handle(): int
    {
        if (! PushDispatcher::isEnabled()) {
            return self::SUCCESS;
        }

        $now = now();
        $sent = 0;
        Call::query()
            ->where('status', 'pending')
            ->whereNotNull('office_id')
            ->where('created_at', '<=', $now->copy()->subSeconds(60))
            ->where('created_at', '>=', $now->copy()->subHours((int) $this->option('max-age-hours')))
            ->select(['id', 'uuid', 'call_number', 'status', 'priority', 'office_id', 'call_type_id', 'created_at'])
            ->orderBy('id')
            ->chunkById(200, function ($calls) use ($now, &$sent) {
                foreach ($calls as $call) {
                    $repeat = (int) floor($call->created_at->diffInSeconds($now) / 60);
                    if ($repeat >= 1) {
                        SendHelpPendingPush::dispatch($call, $repeat);
                        $sent++;
                    }
                }
            });

        $this->info("Queued {$sent} pending-help reminder(s).");

        return self::SUCCESS;
    }
}

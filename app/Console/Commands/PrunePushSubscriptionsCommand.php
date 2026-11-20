<?php

namespace App\Console\Commands;

use App\Models\PushSubscription;
use Illuminate\Console\Command;

class PrunePushSubscriptionsCommand extends Command
{
    protected $signature = 'push:prune-inactive {--days=30 : Number of days of inactivity before an inactive subscription is pruned}';

    protected $description = 'Prune deactivated push subscriptions older than the specified threshold';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        if ($days < 1) {
            $this->error('The --days option must be at least 1.');

            return 1;
        }

        $this->info("Pruning deactivated push subscriptions older than {$days} days...");

        $pruned = PushSubscription::pruneInactive($days);

        $this->info("✔ Pruned {$pruned} inactive push subscription(s).");

        return 0;
    }
}

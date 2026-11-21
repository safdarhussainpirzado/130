<?php

namespace App\Console\Commands;

use App\Jobs\SendTestPush;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\Notifications\PushDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class PushTestCommand extends Command
{
    protected $signature = 'push:test {--user= : Desk user ID to receive the test push notification}';

    protected $description = 'Dispatch a single queued test push notification to a user registered desk devices';

    public function handle(): int
    {
        if (! PushDispatcher::isEnabled()) {
            $this->error('Push notifications are disabled (PUSH_ENABLED=false). Refusing to send test push.');

            return 1;
        }

        $userId = $this->option('user');
        if (! $userId) {
            $this->error('The --user option is required. Example: php artisan push:test --user=1');

            return 1;
        }

        $user = User::find($userId);
        if (! $user) {
            $this->error("User with ID [{$userId}] not found.");

            return 1;
        }

        $subCount = 0;
        try {
            if (Schema::hasTable('push_subscriptions')) {
                $subCount = PushSubscription::active()->where('user_id', $user->id)->count();
            }
        } catch (\Throwable) {
            $subCount = 0;
        }

        SendTestPush::dispatch($user);

        $queue = config('services.push.queue', 'push');
        $this->info("✔ Queued test push notification for {$user->name} (User #{$user->id}) on queue [{$queue}].");

        if ($subCount === 0) {
            $this->warn("Note: User #{$user->id} currently has 0 active push subscriptions. The job was queued, but no browser or device will display a notification until the user subscribes via the desk UI.");
        } else {
            $this->line("Targeting {$subCount} active subscription(s) across registered browser/FCM endpoints.");
        }

        $this->line('Ensure your push-worker is running to process the queued job: php artisan queue:work --queue='.$queue);

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserSearchToken;
use Illuminate\Console\Command;

class BackfillUserBlindIndexesCommand extends Command
{
    protected $signature = 'users:backfill-blind-indexes 
                            {--chunk=100 : Number of user records per chunk}
                            {--dry-run : Simulate execution without writing to database}
                            {--from-id=0 : Start processing from a specific user ID (for resuming)}';

    protected $description = 'Backfill email_bidx, username_bidx, and user_search_tokens for encrypted user search';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $fromId = max(0, (int) $this->option('from-id'));

        $this->info('Starting User Blind Index Backfill...');
        if ($dryRun) {
            $this->warn('DRY-RUN MODE ENABLED: No changes will be persisted to the database.');
        }

        $query = User::where('id', '>=', $fromId)->orderBy('id');
        $totalToProcess = (clone $query)->count();

        $this->line("Found {$totalToProcess} users to process starting from ID {$fromId} (chunk size: {$chunkSize}).");

        $processedCount = 0;
        $updatedUsers = 0;
        $tokenRowsCreated = 0;

        $query->chunkById($chunkSize, function ($users) use ($dryRun, &$processedCount, &$updatedUsers, &$tokenRowsCreated) {
            foreach ($users as $user) {
                $processedCount++;
                $userId = $user->id;

                $emailBidx = ! empty($user->email) && function_exists('email_blind_index')
                    ? email_blind_index((string) $user->email)
                    : null;

                $usernameBidx = ! empty($user->username) && function_exists('username_blind_index')
                    ? username_blind_index((string) $user->username)
                    : null;

                $tokens = ! empty($user->full_name) && function_exists('name_search_tokens')
                    ? name_search_tokens((string) $user->full_name)
                    : [];

                $needsUserUpdate = ($user->email_bidx !== $emailBidx) || ($user->username_bidx !== $usernameBidx);

                if (! $dryRun) {
                    if ($needsUserUpdate) {
                        User::where('id', $userId)->update([
                            'email_bidx' => $emailBidx,
                            'username_bidx' => $usernameBidx,
                        ]);
                        $updatedUsers++;
                    }

                    // Sync tokens idempotently
                    UserSearchToken::where('user_id', $userId)->delete();
                    if (! empty($tokens)) {
                        $now = now();
                        $insertData = [];
                        foreach ($tokens as $tokenBidx) {
                            $insertData[] = [
                                'user_id' => $userId,
                                'token_bidx' => $tokenBidx,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                        UserSearchToken::insert($insertData);
                        $tokenRowsCreated += count($insertData);
                    }
                } else {
                    if ($needsUserUpdate) {
                        $updatedUsers++;
                    }
                    $tokenRowsCreated += count($tokens);
                }
            }

            $this->line("Processed up to ID {$users->last()->id} ({$processedCount} total)...");
        });

        $this->info('User blind index backfill completed successfully.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Users Processed', $processedCount],
                ['User Records Updated (or needing update)', $updatedUsers],
                ['Token Rows Generated', $tokenRowsCreated],
            ]
        );

        return 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Services\CallSearchTokens;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCallSearchTokensCommand extends Command
{
    protected $signature = 'calls:backfill-search-tokens {--from-id=0} {--chunk=1000} {--sleep-ms=40} {--dry-run}';

    protected $description = 'Build blind-index search tokens (name / phone fragments) for existing calls. Idempotent, resumable, throttled.';

    public function handle(): int
    {
        $from = (int) $this->option('from-id');
        $chunk = max(100, (int) $this->option('chunk'));
        $sleep = (int) $this->option('sleep-ms');
        $dry = (bool) $this->option('dry-run');
        $calls = 0;
        $tokens = 0;
        $started = microtime(true);

        Call::query()->where('id', '>', $from)->select(['id', 'caller_name', 'caller_number'])->orderBy('id')
            ->chunkById($chunk, function ($rows) use (&$calls, &$tokens, $dry, $sleep) {
                $batch = [];
                foreach ($rows as $call) {
                    foreach (CallSearchTokens::forCall($call) as $h) {
                        $batch[] = ['token_bidx' => $h, 'call_id' => $call->id];
                    }
                    $calls++;
                }
                $tokens += count($batch);
                if (! $dry && $batch) {
                    foreach (array_chunk($batch, 5000) as $part) {
                        DB::table('call_search_tokens')->insertOrIgnore($part);
                    }
                }
                if ($calls % 20000 < 1000) {
                    $this->line("processed {$calls} calls, last id {$rows->last()->id}, tokens {$tokens}");
                }
                if ($sleep > 0) {
                    usleep($sleep * 1000);
                }
            });

        $this->info(sprintf('%s %d calls, %d tokens in %.1fs', $dry ? 'Would index' : 'Indexed', $calls, $tokens, microtime(true) - $started));

        return self::SUCCESS;
    }
}

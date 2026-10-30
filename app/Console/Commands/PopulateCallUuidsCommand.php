<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PopulateCallUuidsCommand extends Command
{
    protected $signature = 'calls:populate-uuids {--chunk=2000 : Chunk size per batch}';
    protected $description = 'Populate missing UUIDv7 identifiers for existing calls';

    public function handle(): int
    {
        $this->info("Scanning for calls with missing UUIDv7...");
        
        $totalMissing = DB::table('calls')->whereNull('uuid')->count();
        if ($totalMissing === 0) {
            $this->info("All calls already have UUIDs assigned. Nothing to do.");
            return Command::SUCCESS;
        }

        $this->info("Found {$totalMissing} calls requiring UUIDv7 assignment.");
        $chunkSize = (int) $this->option('chunk');
        $bar = $this->output->createProgressBar($totalMissing);

        while (true) {
            $calls = DB::table('calls')
                ->whereNull('uuid')
                ->select('id')
                ->limit($chunkSize)
                ->get();

            if ($calls->isEmpty()) {
                break;
            }

            $cases = [];
            $ids = [];
            foreach ($calls as $call) {
                $uuid = (string) Str::uuid7();
                $cases[] = "WHEN id = {$call->id} THEN '{$uuid}'";
                $ids[] = $call->id;
            }

            $casesSql = implode(' ', $cases);
            $idsSql = implode(',', $ids);

            DB::statement("UPDATE calls SET uuid = CASE {$casesSql} END WHERE id IN ({$idsSql})");
            $bar->advance($calls->count());
        }

        $bar->finish();
        $this->newLine();
        $this->info("Successfully assigned UUIDv7 to all calls!");

        return Command::SUCCESS;
    }
}

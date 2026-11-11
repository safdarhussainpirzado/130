<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Call;
use App\Models\User;
use App\Models\CommuterProfile;
use App\Models\WomensJourney;
use Illuminate\Support\Facades\DB;

class MigrateEncryptedFieldsToGcm extends Command
{
    protected $signature = 'crypto:migrate-gcm {--batch=100 : Number of records per batch} {--dry-run : Simulate migration without writing to database}';
    protected $description = 'Idempotently migrates all legacy encrypted and plaintext sensitive fields to authenticated AES-256-GCM with AAD';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("🔐 Starting NHMP 130 Cryptographic Migration to AES-256-GCM (NIST SP 800-38D)...");
        if ($dryRun) {
            $this->warn("⚠️  DRY-RUN MODE ENABLED — No changes will be written to the database.");
        }

        $targets = [
            Call::class => ['caller_number', 'caller_name'],
            User::class => ['cnic', 'mobile_no'],
            CommuterProfile::class => ['emergency_contacts'],
            WomensJourney::class => ['emergency_contacts'],
        ];

        $totalMigrated = 0;
        $totalErrors = 0;

        foreach ($targets as $modelClass => $fields) {
            $this->line("\nProcessing model: <fg=cyan>{$modelClass}</> [fields: " . implode(', ', $fields) . "]");
            $count = $modelClass::count();
            if ($count === 0) {
                $this->line("  No records found.");
                continue;
            }

            $bar = $this->output->createProgressBar($count);
            $bar->start();

            $modelClass::chunkById($batchSize, function ($records) use ($fields, $dryRun, &$totalMigrated, &$totalErrors, $bar) {
                foreach ($records as $record) {
                    $needsUpdate = false;
                    foreach ($fields as $field) {
                        $rawVal = $record->getRawOriginal($field);
                        if (empty($rawVal)) {
                            continue;
                        }

                        // Check if already in v2 format
                        $isV2 = false;
                        if (str_starts_with($rawVal, 'eyJ')) {
                            $decoded = json_decode(base64_decode($rawVal, true) ?: '', true);
                            if (is_array($decoded) && isset($decoded['v']) && (int) $decoded['v'] === 2) {
                                $isV2 = true;
                            }
                        }

                        if (!$isV2) {
                            // Touch attribute to trigger GracefulEncrypted::set()
                            $currentVal = $record->{$field};
                            $record->{$field} = $currentVal;
                            $needsUpdate = true;
                        }
                    }

                    if ($needsUpdate) {
                        if (!$dryRun) {
                            try {
                                $record->save();
                                $totalMigrated++;
                            } catch (\Throwable $e) {
                                $totalErrors++;
                                $this->error("\nFailed to migrate record #{$record->getKey()}: " . $e->getMessage());
                            }
                        } else {
                            $totalMigrated++;
                        }
                    }

                    $bar->advance();
                }
            });

            $bar->finish();
            $this->line("");
        }

        $this->info("\n======================================================================");
        $this->info("✅ Cryptographic Migration Summary:");
        $this->info("   Total Records Upgraded to AES-256-GCM: {$totalMigrated}");
        $this->info("   Total Migration Errors:                {$totalErrors}");
        $this->info("======================================================================");

        return $totalErrors > 0 ? 1 : 0;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;

class VerifyAuditIntegrity extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:verify-integrity {--limit=1000 : Maximum number of records to audit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cryptographically verify the integrity of the audit log hash chain (tamper-evident check)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $this->info("🔒 Auditing cryptographic hash chain integrity (limit: {$limit})...");

        $result = AuditLog::verifyIntegrity($limit);

        if ($result['status'] === 'empty') {
            $this->warn("⚠️  No audit logs with cryptographic hash signatures found.");
            return Command::SUCCESS;
        }

        if ($result['status'] === 'intact') {
            $this->info("✅ AUDIT INTEGRITY INTACT: All {$result['verified_count']} records cryptographically verified.");
            return Command::SUCCESS;
        }

        \Illuminate\Support\Facades\Log::warning('audit chain verification found issues', [
            'count' => count($result['errors']),
            'ids'   => array_slice(array_column($result['errors'], 'id'), 0, 20),
        ]);
        $this->error("🚨 TAMPERING DETECTED! Audit log integrity check failed!");
        foreach ($result['errors'] as $err) {
            $this->error("   [ID: {$err['id']}] {$err['error']} (Expected: {$err['expected']}, Got: {$err['actual']})");
        }

        return Command::FAILURE;
    }
}

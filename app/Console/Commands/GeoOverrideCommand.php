<?php

namespace App\Console\Commands;

use App\Services\Geo\EmergencyOverrideService;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class GeoOverrideCommand extends Command
{
    protected $signature = 'geo:override
                            {--enable : Turn on emergency override (bypass geo enforcement)}
                            {--disable : Turn off emergency override}
                            {--status : Show current emergency override status}
                            {--hours=24 : Duration in hours for the override (max 24)}
                            {--reason= : Mandatory reason for audit tracking}';

    protected $description = 'Manage the time-limited, audited emergency override for Pakistan geo enforcement';

    public function handle(EmergencyOverrideService $overrideService): int
    {
        $enable = (bool) $this->option('enable');
        $disable = (bool) $this->option('disable');
        $showStatus = (bool) $this->option('status');

        if (!$enable && !$disable) {
            $showStatus = true;
        }

        if ($showStatus && !$enable && !$disable) {
            $this->displayStatus($overrideService);
            return SymfonyCommand::SUCCESS;
        }

        if ($enable) {
            $hours = (int) $this->option('hours');
            if ($hours < 1 || $hours > 24) {
                $this->error('Override duration must be between 1 and 24 hours.');
                return SymfonyCommand::FAILURE;
            }

            $reason = (string) $this->option('reason');
            if (empty(trim($reason))) {
                $reason = (string) $this->ask('Please enter the mandatory operational justification / reason for this override');
                if (empty(trim($reason))) {
                    $this->error('A valid operational reason is required for forensic audit logging.');
                    return SymfonyCommand::FAILURE;
                }
            }

            $state = $overrideService->enable('artisan_cli:super_admin', $hours, $reason);

            $this->info("Emergency override ENABLED successfully.");
            $this->line("Expires at: {$state['expires_at']} (in {$hours} hours)");
            $this->line("Audit row written to audit_logs table.");

            return SymfonyCommand::SUCCESS;
        }

        if ($disable) {
            $reason = (string) ($this->option('reason') ?: 'Manual deactivation from Artisan CLI');
            $overrideService->disable('artisan_cli:super_admin', $reason);

            $this->info("Emergency override DISABLED. Normal geo access enforcement resumed.");
            return SymfonyCommand::SUCCESS;
        }

        return SymfonyCommand::SUCCESS;
    }

    private function displayStatus(EmergencyOverrideService $overrideService): void
    {
        $status = $overrideService->getStatus();

        $this->info('NHMP 130 Helpline — Geo Enforcement Emergency Override Status');
        $this->table(
            ['Property', 'Value'],
            [
                ['Override Active', $status['is_active'] ? '<fg=green;options=bold>YES (BYPASS ACTIVE)</>' : '<fg=yellow>NO (ENFORCEMENT ACTIVE)</>'],
                ['Source', $status['source']],
                ['Enabled At', $status['enabled_at'] ?? 'N/A'],
                ['Expires At', $status['expires_at'] ?? 'N/A'],
                ['Remaining Minutes', $status['remaining_minutes'] >= 0 ? "{$status['remaining_minutes']} min" : 'Permanent (ENV)'],
                ['Enabled By', $status['enabled_by'] ?? 'N/A'],
                ['Reason', $status['reason'] ?? 'N/A'],
            ]
        );
    }
}

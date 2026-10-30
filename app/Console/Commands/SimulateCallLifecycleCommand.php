<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Call;
use App\Models\CallSubType;
use App\Models\CallType;
use App\Models\Carriageway;
use App\Models\Office;
use App\Models\User;
use App\Services\CallService;
use Illuminate\Console\Command;

class SimulateCallLifecycleCommand extends Command
{
    protected $signature = 'nhmp:simulate-call-lifecycle {--calls=3 : Number of full lifecycle calls to simulate}';
    protected $description = 'Simulate full multi-user call lifecycle: Agent intake -> Beat dispatch -> Scene arrival -> Resolution';

    public function handle(CallService $callService): int
    {
        $callsCount = (int) $this->option('calls');

        $this->info('============================================================');
        $this->info('  NHMP 130 CRM — Full End-to-End Multi-User Call Lifecycle ');
        $this->info('============================================================');

        // Retrieve the simulated actors
        $agent = User::where('username', 'nhmp_agent_01')->first();
        $beatOperator = User::where('username', 'nhmp_beat_01')->first();
        $sectorAdmin = User::where('username', 'nhmp_sector_01')->first();

        if (!$agent || !$beatOperator) {
            $this->error('Simulated users not found. Run [php artisan nhmp:simulate-org-users] first.');
            return self::FAILURE;
        }

        $this->line("Participating Actors:");
        $this->line("  1. Agent:         [#{$agent->id}] {$agent->full_name} ({$agent->username})");
        $this->line("  2. Beat Operator: [#{$beatOperator->id}] {$beatOperator->full_name} ({$beatOperator->username})");
        if ($sectorAdmin) {
            $this->line("  3. Sector Admin:  [#{$sectorAdmin->id}] {$sectorAdmin->full_name} ({$sectorAdmin->username})");
        }
        $this->newLine();

        // Retrieve reference locations and categories
        $carriageway = Carriageway::first();
        $beat = Office::where('type', 'beat')->first();
        $callType = CallType::first();
        $callSubType = CallSubType::first();

        $this->line("Incident Reference Metadata:");
        $this->line("  Carriageway: [#{$carriageway?->id}] {$carriageway?->name}");
        $this->line("  Beat Office: [#{$beat?->id}] {$beat?->name}");
        $this->line("  Call Type:   [#{$callType?->id}] {$callType?->name} -> {$callSubType?->name}");
        $this->newLine();

        $tableData = [];

        for ($i = 1; $i <= $callsCount; $i++) {
            $this->line("--- Executing Call Simulation #{$i} of {$callsCount} ---");

            // Step 1: Agent Call Intake
            $phoneSuffix = str_pad((string)$i, 4, '0', STR_PAD_LEFT);
            $callerNumber = "0300123" . $phoneSuffix;
            $callerName = "Citizen Driver " . chr(64 + $i);

            $callData = [
                'caller_number'    => $callerNumber,
                'caller_name'      => $callerName,
                'call_type_id'     => $callType->id,
                'call_sub_type_id' => $callSubType->id,
                'details'          => "Simulated emergency breakdown assistance requested at KM " . (20 + $i * 5) . " Northbound.",
                'carriageway_id'   => $carriageway?->id,
                'office_id'        => $beat?->id,
                'beat_id'          => $beat?->id,
                'vehicle_no'       => 'ABC-' . (1000 + $i),
                'km_marker_text'   => (string)(20 + $i * 5),
                'priority'         => 1,
            ];

            $call = $callService->createCall($callData, $agent);
            $this->info("  [1/4] Created by Agent: Call #{$call->call_number} (Status: {$call->status})");

            // Step 2: Beat Operator Dispatches Patrol Unit (in_progress)
            $call = $callService->updateStatus($call, 'in_progress', $beatOperator, [
                'tiger_id'          => 'TIGER-' . (10 + $i),
                'patrol_officer_id' => 'PO Tariq Mehmood',
                'remarks'           => "Beat Operator dispatched Mobile Patrol Unit TIGER-" . (10 + $i),
            ]);
            $this->info("  [2/4] Dispatched by Beat: TIGER-" . (10 + $i) . " (Status: {$call->status})");

            // Step 3: Patrol Mobile Reaches Scene (tiger_reached)
            $call = $callService->updateStatus($call, 'tiger_reached', $beatOperator, [
                'remarks' => "Patrol Mobile reached citizen vehicle at KM " . (20 + $i * 5) . ". Providing mechanical support.",
            ]);
            $this->info("  [3/4] On Scene: Patrol Mobile arrived (Status: {$call->status})");

            // Step 4: Resolution & Completion (completed)
            $call = $callService->updateStatus($call, 'completed', $beatOperator, [
                'remarks' => "Tire replacement completed. Citizen resumed journey safely. Highway lane fully clear.",
            ]);
            $this->info("  [4/4] Resolved by Beat Operator: (Status: {$call->status})");

            $tableData[] = [
                $call->call_number,
                $call->caller_name,
                $call->caller_number,
                $carriageway?->name ?? 'N/A',
                $beat?->name ?? 'N/A',
                $call->status,
                'Agent: ' . $agent->username,
                'Beat: ' . $beatOperator->username,
            ];
            $this->newLine();
        }

        $this->table(
            ['Call Number', 'Caller Name', 'Phone', 'Carriageway', 'Beat', 'Final Status', 'Intake Actor', 'Resolver Actor'],
            $tableData
        );

        $this->info("✓ Full multi-user call lifecycle simulation executed successfully!");
        $this->info('============================================================');

        return self::SUCCESS;
    }
}

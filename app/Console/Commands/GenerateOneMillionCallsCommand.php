<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\CallSubType;
use App\Models\CallType;
use App\Models\Carriageway;
use App\Models\Office;
use App\Models\User;
use App\Models\VehicleType;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateOneMillionCallsCommand extends Command
{
    protected $signature = 'nhmp:seed-one-million-calls {--total=1000000 : Total records to generate} {--batch=1500 : Batch size per transaction (max 1500)}';
    protected $description = 'High-speed bulk generator for 1,000,000 completed call records created by Agent and resolved by Beat Operator';

    public function handle(): int
    {
        $totalRecords = (int) $this->option('total');
        $batchSize = min(1500, max(100, (int) $this->option('batch')));

        $this->info('========================================================================');
        $this->info("  NHMP 130 CRM — High-Speed Bulk Seeder: {$totalRecords} Completed Calls");
        $this->info('========================================================================');

        // Locate actors
        $agent = User::where('username', 'nhmp_agent_01')->first() ?? User::where('role_id', 7)->first() ?? User::first();
        $beatOperator = User::where('username', 'nhmp_beat_01')->first() ?? User::where('role_id', 5)->first() ?? User::first();

        $agentId = $agent->id;
        $beatOperatorId = $beatOperator->id;

        $this->line("Intake Actor:   [#{$agentId}] {$agent->full_name} ({$agent->username})");
        $this->line("Resolver Actor: [#{$beatOperatorId}] {$beatOperator->full_name} ({$beatOperator->username})");

        // Load reference caches
        $beatIds = Office::where('type', 'beat')->pluck('id')->all();
        if (empty($beatIds)) {
            $beatIds = Office::pluck('id')->all();
        }
        $carriagewayIds = Carriageway::pluck('id')->all() ?: [1];
        $callTypeIds = CallType::pluck('id')->all() ?: [1];
        $callSubTypeIds = CallSubType::pluck('id')->all() ?: [1];
        $vehicleTypeIds = VehicleType::pluck('id')->all() ?: [1];

        $totalBeats = count($beatIds);
        $totalCarriageways = count($carriagewayIds);
        $totalTypes = count($callTypeIds);
        $totalSubTypes = count($callSubTypeIds);
        $totalVehicleTypes = count($vehicleTypeIds);

        $this->line("Loaded Metadata References: {$totalBeats} Beats, {$totalCarriageways} Carriageways, {$totalTypes} Call Types.");
        $this->newLine();

        $pdo = DB::connection()->getPdo();

        $this->warn("Clearing existing calls in test database...");
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::table('calls')->truncate();
        DB::table('call_status_history')->truncate();
        DB::table('call_sequences')->updateOrInsert(['year' => 2026], ['current_seq' => $totalRecords]);
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        $startTime = microtime(true);
        $insertedSoFar = 0;
        $totalBatches = (int) ceil($totalRecords / $batchSize);

        $firstNames = ['Muhammad', 'Ali', 'Ahmed', 'Tariq', 'Usman', 'Imran', 'Bilal', 'Hamza', 'Rashid', 'Zahid', 'Farhan', 'Kamran', 'Kashif', 'Arslan', 'Sajid', 'Nasir', 'Waseem', 'Shahid', 'Naveed', 'Asif'];
        $lastNames = ['Khan', 'Ahmed', 'Ali', 'Malik', 'Chaudhry', 'Bhatti', 'Raza', 'Shah', 'Qureshi', 'Siddiqui', 'Mughal', 'Gondal', 'Warraich', 'Cheema', 'Ansari', 'Awan', 'Rehman', 'Hussain', 'Iqbal', 'Mirza'];
        $numFirstNames = count($firstNames);
        $numLastNames = count($lastNames);

        $cityCodes = ['LEA', 'ICT', 'RWP', 'KHI', 'FSD', 'LHR', 'PES', 'QTA', 'MUL', 'GUJ'];
        $numCityCodes = count($cityCodes);

        $tigerUnits = ['TIGER-01', 'TIGER-02', 'TIGER-03', 'TIGER-04', 'TIGER-05', 'TIGER-06', 'TIGER-07', 'TIGER-08', 'TIGER-09', 'TIGER-10', 'TIGER-11', 'TIGER-12', 'TIGER-14', 'TIGER-15'];
        $numTigerUnits = count($tigerUnits);

        // Pre-construct SQL template with 30 columns
        $columns = [
            'call_number', 'agent_id', 'caller_number', 'caller_name', 'call_type_id',
            'call_sub_type_id', 'carriageway_id', 'office_id', 'status', 'priority',
            'vehicle_type_id', 'vehicle_no', 'km_marker_text', 'tiger_id', 'patrol_officer_id',
            'details', 'location_details', 'response_time_sec', 'tiger_response_time_sec',
            'assistance_duration_sec', 'resolution_time_sec',
            'inprogress_status_by', 'inprogress_remarks', 'inprogress_at',
            'tiger_reached_status_by', 'tiger_reached_remarks', 'tiger_reached_at',
            'completed_status_by', 'completed_remarks', 'completed_at',
            'created_at', 'updated_at'
        ];
        $colList = implode(', ', $columns);

        $rowPlaceholder = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';

        $baseDate = Carbon::now()->subDays(120);

        for ($batch = 1; $batch <= $totalBatches; $batch++) {
            $currentBatchSize = min($batchSize, $totalRecords - $insertedSoFar);
            if ($currentBatchSize <= 0) break;

            $params = [];
            $placeholders = [];

            for ($i = 0; $i < $currentBatchSize; $i++) {
                $seq = $insertedSoFar + $i + 1;
                $callNumber = sprintf('CRM-2026-%07d', $seq);

                $callerName = $firstNames[($seq + $i) % $numFirstNames] . ' ' . $lastNames[($seq * 3 + $i) % $numLastNames];
                $phone = '03' . sprintf('%09d', (100000000 + ($seq * 17) % 900000000));
                $vehicleNo = $cityCodes[($seq + $i) % $numCityCodes] . '-' . (1000 + ($seq % 9000));
                $tiger = $tigerUnits[($seq + $i) % $numTigerUnits];

                $beatId = $beatIds[$seq % $totalBeats];
                $carriagewayId = $carriagewayIds[$seq % $totalCarriageways];
                $callTypeId = $callTypeIds[$seq % $totalTypes];
                $subTypeId = $callSubTypeIds[$seq % $totalSubTypes];
                $vehicleTypeId = $vehicleTypeIds[$seq % $totalVehicleTypes];
                $priority = 1 + ($seq % 4);

                // Realistic progressive timestamps
                $secondsOffset = (int) (($seq / $totalRecords) * (120 * 86400));
                $createdAt = (clone $baseDate)->addSeconds($secondsOffset);
                $respSec = 60 + ($seq % 240);       // 1 - 5 mins
                $tigerSec = 300 + ($seq % 420);     // 5 - 12 mins
                $assistSec = 600 + ($seq % 1200);   // 10 - 30 mins
                $resSec = $respSec + $tigerSec + $assistSec;

                $inProgressAt = (clone $createdAt)->addSeconds($respSec);
                $tigerReachedAt = (clone $inProgressAt)->addSeconds($tigerSec);
                $completedAt = (clone $tigerReachedAt)->addSeconds($assistSec);

                $km = (string)(10 + ($seq % 350));

                $params[] = $callNumber;
                $params[] = $agentId;
                $params[] = $phone;
                $params[] = $callerName;
                $params[] = $callTypeId;
                $params[] = $subTypeId;
                $params[] = $carriagewayId;
                $params[] = $beatId;
                $params[] = 'completed';
                $params[] = $priority;
                $params[] = $vehicleTypeId;
                $params[] = $vehicleNo;
                $params[] = $km;
                $params[] = $tiger;
                $params[] = "PO Tariq Mehmood";
                $params[] = "Emergency highway assistance near KM {$km} completed.";
                $params[] = "KM {$km} Northbound";
                $params[] = $respSec;
                $params[] = $tigerSec;
                $params[] = $assistSec;
                $params[] = $resSec;
                $params[] = $beatOperatorId;
                $params[] = "Beat Operator dispatched Mobile Unit {$tiger}";
                $params[] = $inProgressAt->toDateTimeString();
                $params[] = $beatOperatorId;
                $params[] = "Patrol Unit reached citizen at KM {$km}";
                $params[] = $tigerReachedAt->toDateTimeString();
                $params[] = $beatOperatorId;
                $params[] = "Assistance completed. Highway lane clear and safe.";
                $params[] = $completedAt->toDateTimeString();
                $params[] = $createdAt->toDateTimeString();
                $params[] = $completedAt->toDateTimeString();

                $placeholders[] = $rowPlaceholder;
            }

            $sql = "INSERT INTO calls ({$colList}) VALUES " . implode(',', $placeholders);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $pdo->commit();

            $insertedSoFar += $currentBatchSize;

            if ($batch % 50 === 0 || $batch === $totalBatches) {
                $elapsed = microtime(true) - $startTime;
                $rate = $elapsed > 0 ? (int) ($insertedSoFar / $elapsed) : 0;
                $percent = round(($insertedSoFar / $totalRecords) * 100, 1);
                $this->line("  [Batch {$batch}/{$totalBatches}] Inserted {$insertedSoFar} / {$totalRecords} ({$percent}%) at {$rate} rows/sec.");
            }

            unset($params, $placeholders, $sql, $stmt);
            if ($batch % 20 === 0) {
                gc_collect_cycles();
            }
        }

        $totalElapsed = round(microtime(true) - $startTime, 2);
        $avgRate = (int) ($totalRecords / max($totalElapsed, 0.01));

        $this->newLine();
        $this->info("========================================================================");
        $this->info("  ✓ SUCCESS: 1,000,000 CALL RECORDS GENERATED IN {$totalElapsed}s");
        $this->info("  Throughput: {$avgRate} records/second");
        $this->info("  Status: 100% Completed by Beat Wireless Operators");
        $this->info("  Sequence Counter for 2026 set to: 1000000");
        $this->info("========================================================================");

        return self::SUCCESS;
    }
}

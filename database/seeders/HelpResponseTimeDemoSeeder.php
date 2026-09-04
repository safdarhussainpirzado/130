<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Call;
use App\Models\CallStatusHistory;
use App\Models\Office;
use App\Models\CallType;
use App\Models\CallSubType;
use App\Models\User;
use Carbon\Carbon;

class HelpResponseTimeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $agent = User::first();
        if (!$agent) {
            $this->command->error('No users found to assign calls.');
            return;
        }

        // Get Available Beats
        $beats = Office::beats()->pluck('id', 'name');
        $defaultBeatId = $beats->first() ?? Office::first()?->id;

        // Get Available Call Types & SubTypes
        $emergencyType = CallType::where('category', 'emergency')->first() ?? CallType::first();
        $generalHelpType = CallType::where('category', 'general_help')->first() ?? $emergencyType;

        $accidentSub = CallSubType::where('name', 'like', '%accident%')->first() ?? CallSubType::first();
        $breakdownSub = CallSubType::where('name', 'like', '%breakdown%')->first() ?? $accidentSub;
        $medicalSub = CallSubType::where('name', 'like', '%medical%')->first() ?? $accidentSub;

        // Target Date: Today
        $today = Carbon::today();

        $entries = [
            [
                'call_number' => 'CRM-2026-88001',
                'caller_name' => 'Kamran Shah',
                'caller_number' => '0300-1122334',
                'vehicle_no' => 'LEE-21-4509',
                'details' => 'Two-car collision on fast lane at KM 124, commuters safe.',
                'beat_name' => 'Beat-01',
                'call_type_id' => $emergencyType?->id,
                'call_sub_type_id' => $accidentSub?->id,
                'priority' => 1,
                'tiger_id' => 'T-12',
                'patrol_officer_id' => 'SI Tariq Mehmood',
                'created_time' => '07:15:00',
                'dispatched_time' => '07:18:00',
                'reached_time' => '07:27:00', // 9m response
                'completed_time' => '07:52:00', // 25m assistance, 37m total
                'status' => 'completed',
                'dispatch_remarks' => 'Tiger unit dispatched with emergency cone markers',
                'reached_remarks' => 'On site at KM 124, first-aid administered, lane cleared',
                'completed_remarks' => 'Vehicles moved to service area, all commuters safe',
            ],
            [
                'call_number' => 'CRM-2026-88002',
                'caller_name' => 'Zainab Bibi',
                'caller_number' => '0312-5566778',
                'vehicle_no' => 'ICT-20-8812',
                'details' => 'Alternator failure, family vehicle stalled on shoulder at KM 162.',
                'beat_name' => 'Beat-02',
                'call_type_id' => $generalHelpType?->id,
                'call_sub_type_id' => $breakdownSub?->id,
                'priority' => 2,
                'tiger_id' => 'T-15',
                'patrol_officer_id' => 'ASI Muhammad Irfan',
                'created_time' => '07:45:00',
                'dispatched_time' => '07:49:00',
                'reached_time' => '08:01:00', // 12m response
                'completed_time' => '08:23:00', // 22m assistance, 38m total
                'status' => 'completed',
                'dispatch_remarks' => 'Patrolling Tiger redirected from routine surveillance',
                'reached_remarks' => 'Reached stranded family, warning flashers set',
                'completed_remarks' => 'Battery jump-started and escorted to nearest workshop',
            ],
            [
                'call_number' => 'CRM-2026-88003',
                'caller_name' => 'Farhan Qureshi',
                'caller_number' => '0333-9988112',
                'vehicle_no' => 'LXZ-19-3321',
                'details' => 'Rear left tire burst on motorway at KM 190, need mechanical aid.',
                'beat_name' => 'Beat-03',
                'call_type_id' => $generalHelpType?->id,
                'call_sub_type_id' => $breakdownSub?->id,
                'priority' => 2,
                'tiger_id' => 'T-18',
                'patrol_officer_id' => 'IP Rashid Minhas',
                'created_time' => '08:10:00',
                'dispatched_time' => '08:14:00',
                'reached_time' => '08:22:00', // 8m response
                'completed_time' => '08:39:00', // 17m assistance, 29m total
                'status' => 'completed',
                'dispatch_remarks' => 'Beat operator alerted nearest patrolling unit',
                'reached_remarks' => 'Tiger arrived on spot, hydraulic jack provided',
                'completed_remarks' => 'Spare wheel fitted and torque checked, motorist resumed journey',
            ],
            [
                'call_number' => 'CRM-2026-88004',
                'caller_name' => 'Dr. Asim Raza',
                'caller_number' => '0321-4433221',
                'vehicle_no' => 'BK-18-9901',
                'details' => 'Passenger complaining of acute chest pain, urgent medical care needed.',
                'beat_name' => 'Beat-09',
                'call_type_id' => $emergencyType?->id,
                'call_sub_type_id' => $medicalSub?->id,
                'priority' => 1,
                'tiger_id' => 'ST-02',
                'patrol_officer_id' => 'SI Noman Akhtar',
                'created_time' => '08:30:00',
                'dispatched_time' => '08:33:00',
                'reached_time' => '08:40:00', // 7m response
                'completed_time' => '09:12:00', // 32m assistance, 42m total
                'status' => 'completed',
                'dispatch_remarks' => 'Special Tiger unit dispatched with paramedic kit',
                'reached_remarks' => 'Tiger reached, initial vitals taken, oxygen support given',
                'completed_remarks' => 'Escorted with emergency siren to THQ Hospital emergency ward',
            ],
            [
                'call_number' => 'CRM-2026-88005',
                'caller_name' => 'Bilal Ahmed',
                'caller_number' => '0302-8877665',
                'vehicle_no' => 'MN-22-1144',
                'details' => 'Ran out of fuel between interchanges at KM 245.',
                'beat_name' => 'Beat-10',
                'call_type_id' => $generalHelpType?->id,
                'call_sub_type_id' => $breakdownSub?->id,
                'priority' => 3,
                'tiger_id' => 'T-24',
                'patrol_officer_id' => 'ASI Waqas Ali',
                'created_time' => '09:05:00',
                'dispatched_time' => '09:08:00',
                'reached_time' => '09:21:00', // 13m response
                'completed_time' => '09:35:00', // 14m assistance, 30m total
                'status' => 'completed',
                'dispatch_remarks' => 'Tiger dispatched with emergency petrol canister',
                'reached_remarks' => 'On site, fuel transferred safely using funnel',
                'completed_remarks' => 'Engine started, verified driver has enough fuel to reach pump',
            ],
            [
                'call_number' => 'CRM-2026-88006',
                'caller_name' => 'Naveed Jameel',
                'caller_number' => '0345-7766554',
                'vehicle_no' => 'KHI-17-7722',
                'details' => 'Engine overheating with steam coming from radiator.',
                'beat_name' => 'Beat-01',
                'call_type_id' => $generalHelpType?->id,
                'call_sub_type_id' => $breakdownSub?->id,
                'priority' => 2,
                'tiger_id' => 'T-10',
                'patrol_officer_id' => 'SI Tariq Mehmood',
                'created_time' => '09:30:00',
                'dispatched_time' => '09:34:00',
                'reached_time' => '09:49:00', // 15m response
                'completed_time' => '10:14:00', // 25m assistance, 44m total
                'status' => 'completed',
                'dispatch_remarks' => 'Dispatched Tiger 10 with water and coolant reserve',
                'reached_remarks' => 'Engine cooled down, radiator checked for leaks',
                'completed_remarks' => 'Coolant replenished, fan function verified. Motorist all clear.',
            ],
            [
                'call_number' => 'CRM-2026-88007',
                'caller_name' => 'Sajid Mahmood',
                'caller_number' => '0301-3344556',
                'vehicle_no' => 'RWP-21-9988',
                'details' => 'Heavy wooden pallet fallen from commercial truck blocking middle lane.',
                'beat_name' => 'Beat-02',
                'call_type_id' => $emergencyType?->id,
                'call_sub_type_id' => $accidentSub?->id,
                'priority' => 1,
                'tiger_id' => 'T-14',
                'patrol_officer_id' => 'IP Ghulam Abbas',
                'created_time' => '10:00:00',
                'dispatched_time' => '10:03:00',
                'reached_time' => '10:14:00', // 11m response
                'completed_time' => '10:48:00', // 34m assistance, 48m total
                'status' => 'completed',
                'dispatch_remarks' => 'Urgent highway obstruction alert sent to patrol',
                'reached_remarks' => 'Tiger arrived, cones placed 100m upstream for lane diversion',
                'completed_remarks' => 'Heavy pallet cleared off roadway, traffic flow completely restored',
            ],
            [
                'call_number' => 'CRM-2026-88008',
                'caller_name' => 'Maryam Fatima',
                'caller_number' => '0336-1239874',
                'vehicle_no' => 'ISB-23-4411',
                'details' => 'Dead battery after parking at rest stop, cannot crank engine.',
                'beat_name' => 'Beat-03',
                'call_type_id' => $generalHelpType?->id,
                'call_sub_type_id' => $breakdownSub?->id,
                'priority' => 3,
                'tiger_id' => 'T-16',
                'patrol_officer_id' => 'ASI Muhammad Irfan',
                'created_time' => '10:20:00',
                'dispatched_time' => '10:24:00',
                'reached_time' => '10:34:00', // 10m response
                'completed_time' => '10:49:00', // 15m assistance, 29m total
                'status' => 'completed',
                'dispatch_remarks' => 'Nearest patrol instructed to assist at service plaza',
                'reached_remarks' => 'Tiger on scene, inspecting terminals and voltage',
                'completed_remarks' => 'Jump start successful, advised driver to keep engine running',
            ],
            [
                // ACTIVE: TIGER REACHED (Assistance ongoing)
                'call_number' => 'CRM-2026-88009',
                'caller_name' => 'Haris Sheikh',
                'caller_number' => '0308-5544332',
                'vehicle_no' => 'KHI-22-3301',
                'details' => 'Side-mirror clipped by passing vehicle, driver feeling shaken.',
                'beat_name' => 'Beat-09',
                'call_type_id' => $emergencyType?->id,
                'call_sub_type_id' => $accidentSub?->id,
                'priority' => 2,
                'tiger_id' => 'ST-05',
                'patrol_officer_id' => 'SI Noman Akhtar',
                'created_time' => '10:35:00',
                'dispatched_time' => '10:38:00',
                'reached_time' => '10:49:00', // 11m response
                'completed_time' => null,
                'status' => 'tiger_reached',
                'dispatch_remarks' => 'Tiger unit dispatched with traffic management gear',
                'reached_remarks' => 'Patrolling Tiger reached commuter at KM 238, motorist located safely. Assistance in progress.',
                'completed_remarks' => null,
            ],
            [
                // ACTIVE: TIGER REACHED (Assistance ongoing)
                'call_number' => 'CRM-2026-88010',
                'caller_name' => 'Usman Ghani',
                'caller_number' => '0315-9988776',
                'vehicle_no' => 'LHR-24-5566',
                'details' => 'Electronic parking brake locked, vehicle unable to move.',
                'beat_name' => 'Beat-10',
                'call_type_id' => $generalHelpType?->id,
                'call_sub_type_id' => $breakdownSub?->id,
                'priority' => 2,
                'tiger_id' => 'T-22',
                'patrol_officer_id' => 'ASI Waqas Ali',
                'created_time' => '10:45:00',
                'dispatched_time' => '10:49:00',
                'reached_time' => '11:02:00', // 13m response
                'completed_time' => null,
                'status' => 'tiger_reached',
                'dispatch_remarks' => 'Dispatched Tiger 22 with mechanical tool set',
                'reached_remarks' => 'Tiger reached location at KM 289, diagnostic OBD tool connected.',
                'completed_remarks' => null,
            ],
            [
                // ACTIVE: IN PROGRESS / DISPATCHED (Tiger en-route)
                'call_number' => 'CRM-2026-88011',
                'caller_name' => 'Shahbaz Sharif',
                'caller_number' => '0300-4455667',
                'vehicle_no' => 'FD-20-1122',
                'details' => 'Need fuel guidance and assistance at toll approach.',
                'beat_name' => 'Beat-01',
                'call_type_id' => $generalHelpType?->id,
                'call_sub_type_id' => $breakdownSub?->id,
                'priority' => 3,
                'tiger_id' => 'T-11',
                'patrol_officer_id' => 'SI Tariq Mehmood',
                'created_time' => '10:55:00',
                'dispatched_time' => '10:58:00',
                'reached_time' => null,
                'completed_time' => null,
                'status' => 'in_progress',
                'dispatch_remarks' => 'Patrolling Tiger 11 acknowledged dispatch and is en route.',
                'reached_remarks' => null,
                'completed_remarks' => null,
            ],
            [
                // ACTIVE: IN PROGRESS / DISPATCHED (Tiger en-route)
                'call_number' => 'CRM-2026-88012',
                'caller_name' => 'Ayesha Siddiqui',
                'caller_number' => '0333-8877661',
                'vehicle_no' => 'ISB-19-6633',
                'details' => 'White smoke noticed near wheel well, vehicle parked on emergency lane.',
                'beat_name' => 'Beat-02',
                'call_type_id' => $emergencyType?->id,
                'call_sub_type_id' => $accidentSub?->id,
                'priority' => 1,
                'tiger_id' => 'T-17',
                'patrol_officer_id' => 'IP Rashid Minhas',
                'created_time' => '11:02:00',
                'dispatched_time' => '11:05:00',
                'reached_time' => null,
                'completed_time' => null,
                'status' => 'in_progress',
                'dispatch_remarks' => 'Emergency response dispatched with dry chemical powder extinguisher.',
                'reached_remarks' => null,
                'completed_remarks' => null,
            ],
        ];

        $createdCount = 0;
        foreach ($entries as $e) {
            // Check if already seeded
            $existing = Call::where('call_number', $e['call_number'])->first();
            if ($existing) {
                CallStatusHistory::where('call_id', $existing->id)->delete();
                $existing->delete();
            }

            $beatId = $beats[$e['beat_name']] ?? $defaultBeatId;

            $cAt = Carbon::parse($today->toDateString() . ' ' . $e['created_time']);
            $dAt = Carbon::parse($today->toDateString() . ' ' . $e['dispatched_time']);
            $rAt = $e['reached_time'] ? Carbon::parse($today->toDateString() . ' ' . $e['reached_time']) : null;
            $cpAt = $e['completed_time'] ? Carbon::parse($today->toDateString() . ' ' . $e['completed_time']) : null;

            $tigerRespSec = ($rAt && $dAt) ? (int) $dAt->diffInSeconds($rAt) : null;
            $assistSec = ($cpAt && $rAt) ? (int) $rAt->diffInSeconds($cpAt) : null;
            $resSec = ($cpAt && $cAt) ? (int) $cAt->diffInSeconds($cpAt) : null;

            $call = Call::create([
                'call_number' => $e['call_number'],
                'caller_name' => $e['caller_name'],
                'caller_number' => $e['caller_number'],
                'vehicle_no' => $e['vehicle_no'],
                'details' => $e['details'],
                'call_type_id' => $e['call_type_id'],
                'call_sub_type_id' => $e['call_sub_type_id'],
                'office_id' => $beatId,
                'priority' => $e['priority'],
                'agent_id' => $agent->id,
                'status' => $e['status'],
                'tiger_id' => $e['tiger_id'],
                'patrol_officer_id' => $e['patrol_officer_id'],
                'created_at' => $cAt,
                'call_start_time' => $cAt,
                'inprogress_at' => $dAt,
                'inprogress_status_by' => $agent->id,
                'inprogress_remarks' => $e['dispatch_remarks'] . " [Allocated Asset: {$e['tiger_id']}] [Patrol Officer: {$e['patrol_officer_id']}]",
                'tiger_reached_at' => $rAt,
                'tiger_reached_status_by' => $rAt ? $agent->id : null,
                'tiger_reached_remarks' => $e['reached_remarks'],
                'completed_at' => $cpAt,
                'completed_status_by' => $cpAt ? $agent->id : null,
                'completed_remarks' => $e['completed_remarks'],
                'tiger_response_time_sec' => $tigerRespSec,
                'assistance_duration_sec' => $assistSec,
                'resolution_time_sec' => $resSec,
            ]);

            // Milestone 1: Call Created
            CallStatusHistory::create([
                'call_id' => $call->id,
                'old_status' => null,
                'new_status' => 'pending',
                'changed_by' => $agent->id,
                'remarks' => 'Help Created by Call Center Agent: ' . $call->details,
                'created_at' => $cAt,
            ]);

            // Milestone 2: Help Initiated / Dispatched
            CallStatusHistory::create([
                'call_id' => $call->id,
                'old_status' => 'pending',
                'new_status' => 'in_progress',
                'changed_by' => $agent->id,
                'remarks' => $call->inprogress_remarks,
                'created_at' => $dAt,
            ]);

            // Milestone 3: Tiger Reached (if reached)
            if ($rAt) {
                CallStatusHistory::create([
                    'call_id' => $call->id,
                    'old_status' => 'in_progress',
                    'new_status' => 'tiger_reached',
                    'changed_by' => $agent->id,
                    'remarks' => $call->tiger_reached_remarks,
                    'created_at' => $rAt,
                ]);
            }

            // Milestone 4: Help Completed (if completed)
            if ($cpAt) {
                CallStatusHistory::create([
                    'call_id' => $call->id,
                    'old_status' => 'tiger_reached',
                    'new_status' => 'completed',
                    'changed_by' => $agent->id,
                    'remarks' => $call->completed_remarks,
                    'created_at' => $cpAt,
                ]);
            }

            $createdCount++;
        }

        $this->command->info("Successfully seeded {$createdCount} Help Response-Time demo entries with complete milestones!");
    }
}

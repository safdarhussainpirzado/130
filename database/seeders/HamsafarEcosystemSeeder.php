<?php

namespace Database\Seeders;

use App\Models\Call;
use App\Models\CallType;
use App\Models\Carriageway;
use App\Models\CaseMessage;
use App\Models\CommuterProfile;
use App\Models\Office;
use App\Models\PatrolDutySession;
use App\Models\PatrolOfficerProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TabletAdvisory;
use App\Models\User;
use App\Models\WomensJourney;
use App\Models\WomensJourneyUpdate;
use App\Services\PatrolTelemetryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class HamsafarEcosystemSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // =========================================================================
        // 1. GRANULAR PERMISSIONS DEFINITION
        // =========================================================================
        $newPermissions = [
            // Commuter Module
            [
                'name'         => 'commuter.profile.view',
                'display_name' => 'View Commuter Profile',
                'module'       => 'commuter',
                'status'       => 'active',
                'description'  => 'Access and view personal road user safety profile.',
            ],
            [
                'name'         => 'commuter.profile.update',
                'display_name' => 'Update Commuter Profile',
                'module'       => 'commuter',
                'status'       => 'active',
                'description'  => 'Modify CNIC, next-of-kin emergency contacts, and vehicle profiles.',
            ],
            [
                'name'         => 'commuter.sos.trigger',
                'display_name' => 'Trigger Emergency SOS',
                'module'       => 'commuter',
                'status'       => 'active',
                'description'  => 'Transmit roadside distress alerts with GPS coordinates to 130 Control.',
            ],
            [
                'name'         => 'commuter.cases.view',
                'display_name' => 'View Assistance Cases',
                'module'       => 'commuter',
                'status'       => 'active',
                'description'  => 'Track active and historical assistance cases requested by commuter.',
            ],
            [
                'name'         => 'commuter.tracking.view',
                'display_name' => 'Track Assigned Patrol Live',
                'module'       => 'commuter',
                'status'       => 'active',
                'description'  => 'Access real-time sub-second GPS radar of approaching patrol vehicle.',
            ],
            [
                'name'         => 'commuter.chat.participate',
                'display_name' => 'Participate in Shared Case Chat',
                'module'       => 'commuter',
                'status'       => 'active',
                'description'  => 'Exchange text and location messages with assigned tactical patrol.',
            ],
            [
                'name'         => 'commuter.womens_journey.manage',
                'display_name' => 'Manage Monitored Women Journey',
                'module'       => 'commuter',
                'status'       => 'active',
                'description'  => 'Register PSV transit trips, log milestones, and trigger silent alerts.',
            ],

            // Patrol Officer Tactical Module
            [
                'name'         => 'patrol.duty.manage',
                'display_name' => 'Manage Duty Shift & Callsign',
                'module'       => 'patrol',
                'status'       => 'active',
                'description'  => 'Start/End tactical duty shift, bind vehicle callsign (e.g. P-12), and toggle readiness.',
            ],
            [
                'name'         => 'patrol.telemetry.send',
                'display_name' => 'Transmit Live GPS Telemetry',
                'module'       => 'patrol',
                'status'       => 'active',
                'description'  => 'Stream device coordinates, speed, heading, and battery to Redis buffer.',
            ],
            [
                'name'         => 'patrol.cases.view',
                'display_name' => 'View Assigned Tactical Cases',
                'module'       => 'patrol',
                'status'       => 'active',
                'description'  => 'Review details of emergency incidents assigned by 130 Control Room.',
            ],
            [
                'name'         => 'patrol.cases.respond',
                'display_name' => 'Acknowledge & Respond to Cases',
                'module'       => 'patrol',
                'status'       => 'active',
                'description'  => 'Acknowledge assignment, report tactical refusal, and mark en-route movement.',
            ],
            [
                'name'         => 'patrol.cases.arrive',
                'display_name' => 'Confirm Arrival On Scene',
                'module'       => 'patrol',
                'status'       => 'active',
                'description'  => 'Advance case status to tiger_reached upon arrival at commuter coordinates.',
            ],
            [
                'name'         => 'patrol.cases.report',
                'display_name' => 'Submit Assistance Completion Report',
                'module'       => 'patrol',
                'status'       => 'active',
                'description'  => 'Log outcome, actions taken, and remarks before submitting for Control closure.',
            ],
            [
                'name'         => 'patrol.support.request',
                'display_name' => 'Request Multi-Agency Support',
                'module'       => 'patrol',
                'status'       => 'active',
                'description'  => 'Request heavy lifter crane, 1122 ambulance, fire brigade, or local police.',
            ],
            [
                'name'         => 'patrol.chat.participate',
                'display_name' => 'Tactical Case Messaging',
                'module'       => 'patrol',
                'status'       => 'active',
                'description'  => 'Send instructions to commuter and internal coordination notes to Control.',
            ],

            // Travel Advisories Module
            [
                'name'         => 'advisories.view',
                'display_name' => 'View Travel Advisories',
                'module'       => 'advisories',
                'status'       => 'active',
                'description'  => 'Access active public travel advisories and high-priority emergency alerts.',
            ],
            [
                'name'         => 'advisories.manage',
                'display_name' => 'Manage Travel Advisories',
                'module'       => 'advisories',
                'status'       => 'active',
                'description'  => 'Create, edit, verify, publish, and expire official highway travel advisories.',
            ],

            // Women\'s Safety Desk Module
            [
                'name'         => 'womens_journey.desk.view',
                'display_name' => 'Monitor 130 Female Safety Desk',
                'module'       => 'womens_journey',
                'status'       => 'active',
                'description'  => 'Access dedicated queue of active monitored PSV journeys and silent alerts.',
            ],
        ];

        foreach ($newPermissions as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $perm['name']],
                array_merge($perm, ['created_at' => $now, 'updated_at' => $now])
            );
        }

        // =========================================================================
        // 2. ASSIGN PERMISSIONS TO ROLES
        // =========================================================================
        $commuterRole = Role::where('name', 'commuter')->first();
        $patrolRole   = Role::where('name', 'patrol_officer')->first();
        $superAdmin   = Role::where('name', 'super_admin')->first();
        $opAdmin      = Role::where('name', 'operation_admin')->first();
        $zoneAdmin    = Role::where('name', 'zone_admin')->first();
        $sectorAdmin  = Role::where('name', 'sector_admin')->first();
        $beatOp       = Role::where('name', 'beat_operator')->first();
        $supervisor   = Role::where('name', 'agent_supervisor')->first();
        $agent        = Role::where('name', 'agent')->first();

        // Commuter Role Permissions
        $commuterPermSlugs = [
            'commuter.profile.view',
            'commuter.profile.update',
            'commuter.sos.trigger',
            'commuter.cases.view',
            'commuter.tracking.view',
            'commuter.chat.participate',
            'commuter.womens_journey.manage',
            'advisories.view',
        ];
        $this->attachPermsToRole($commuterRole, $commuterPermSlugs);

        // Patrol Officer Role Permissions
        $patrolPermSlugs = [
            'patrol.duty.manage',
            'patrol.telemetry.send',
            'patrol.cases.view',
            'patrol.cases.respond',
            'patrol.cases.arrive',
            'patrol.cases.report',
            'patrol.support.request',
            'patrol.chat.participate',
            'advisories.view',
        ];
        $this->attachPermsToRole($patrolRole, $patrolPermSlugs);

        // Control Room & Operational Roles Permissions
        $controlPermSlugs = [
            'advisories.manage',
            'advisories.view',
            'womens_journey.desk.view',
        ];
        $this->attachPermsToRole($superAdmin, $controlPermSlugs);
        $this->attachPermsToRole($opAdmin, $controlPermSlugs);
        $this->attachPermsToRole($zoneAdmin, $controlPermSlugs);
        $this->attachPermsToRole($sectorAdmin, $controlPermSlugs);
        $this->attachPermsToRole($beatOp, $controlPermSlugs);
        $this->attachPermsToRole($supervisor, $controlPermSlugs);
        $this->attachPermsToRole($agent, ['advisories.view']);

        // =========================================================================
        // 3. SEED REALISTIC COMMUTER USERS & PROFILES
        // =========================================================================
        // Commuter 1: Ayesha Malik
        $commuter1 = User::where('email', 'ayesha.malik@gmail.com')
            ->orWhere('mobile_no', '03001234567')
            ->first();
        if (! $commuter1) {
            $commuter1 = new User();
            $commuter1->uuid = (string) Str::uuid();
        }
        $commuter1->email = 'ayesha.malik@gmail.com';
        $commuter1->full_name = 'Ayesha Malik';
        $commuter1->username = 'ayesha_malik';
        $commuter1->mobile_no = '03001234567';
        $commuter1->password = Hash::make('CommuterSecure123!');
        $commuter1->role_id = $commuterRole->id;
        $commuter1->is_active = 1;
        $commuter1->cnic = '37405-1234567-2';
        $commuter1->save();

        CommuterProfile::updateOrCreate(
            ['user_id' => $commuter1->id],
            [
                'gender'              => 'female',
                'blood_group'         => 'B+',
                'preferred_language'  => 'ur',
                'emergency_contacts'  => [
                    ['name' => 'Tariq Malik (Father)', 'phone' => '03007654321', 'relationship' => 'parent'],
                    ['name' => 'Sadia Malik (Sister)', 'phone' => '03019876543', 'relationship' => 'sibling'],
                ],
                'vehicle_profiles'    => [
                    [
                        'registration_no' => 'ICT-LE-921',
                        'make_model'      => 'Toyota Corolla GLi',
                        'color'           => 'White',
                        'type'            => 'car',
                    ],
                ],
                'is_cnic_verified'    => true,
            ]
        );

        // Commuter 2: Bilal Ahmed
        $commuter2 = User::where('email', 'bilal.ahmed@gmail.com')
            ->orWhere('mobile_no', '03219876543')
            ->first();
        if (! $commuter2) {
            $commuter2 = new User();
            $commuter2->uuid = (string) Str::uuid();
        }
        $commuter2->email = 'bilal.ahmed@gmail.com';
        $commuter2->full_name = 'Bilal Ahmed';
        $commuter2->username = 'bilal_ahmed';
        $commuter2->mobile_no = '03219876543';
        $commuter2->password = Hash::make('CommuterSecure123!');
        $commuter2->role_id = $commuterRole->id;
        $commuter2->is_active = 1;
        $commuter2->cnic = '37405-7654321-1';
        $commuter2->save();

        CommuterProfile::updateOrCreate(
            ['user_id' => $commuter2->id],
            [
                'gender'              => 'male',
                'blood_group'         => 'O+',
                'preferred_language'  => 'en',
                'emergency_contacts'  => [
                    ['name' => 'Farhan Ahmed (Brother)', 'phone' => '03211234567', 'relationship' => 'sibling'],
                ],
                'vehicle_profiles'    => [
                    [
                        'registration_no' => 'ICT-MN-450',
                        'make_model'      => 'Honda Civic Oriel',
                        'color'           => 'Silver',
                        'type'            => 'car',
                    ],
                ],
                'is_cnic_verified'    => true,
            ]
        );

        // Commuter 3: Zainab Bibi (for Women\'s Journey Module)
        $commuter3 = User::where('email', 'zainab.bibi@example.com')
            ->orWhere('mobile_no', '03331122334')
            ->first();
        if (! $commuter3) {
            $commuter3 = new User();
            $commuter3->uuid = (string) Str::uuid();
        }
        $commuter3->email = 'zainab.bibi@example.com';
        $commuter3->full_name = 'Zainab Bibi';
        $commuter3->username = 'zainab_bibi';
        $commuter3->mobile_no = '03331122334';
        $commuter3->password = Hash::make('CommuterSecure123!');
        $commuter3->role_id = $commuterRole->id;
        $commuter3->is_active = 1;
        $commuter3->cnic = '37405-9988776-4';
        $commuter3->save();

        CommuterProfile::updateOrCreate(
            ['user_id' => $commuter3->id],
            [
                'gender'              => 'female',
                'blood_group'         => 'A+',
                'preferred_language'  => 'ur',
                'emergency_contacts'  => [
                    ['name' => 'Mother', 'phone' => '03339988776', 'relationship' => 'parent'],
                ],
                'is_cnic_verified'    => true,
            ]
        );

        // =========================================================================
        // 4. SEED REALISTIC PATROL OFFICERS
        // =========================================================================
        $beatOffice = Office::find(48) ?? Office::where('name', 'like', '%Beat%')->first() ?? Office::first();

        // Officer 1: SPO Tahir Mehmood
        $officer1 = User::where('username', 'spo_tahir')
            ->orWhere('email', 'tahir.mehmood@nhmp.gov.pk')
            ->first();
        if (! $officer1) {
            $officer1 = new User();
            $officer1->uuid = (string) Str::uuid();
        }
        $officer1->username = 'spo_tahir';
        $officer1->full_name = 'Tahir Mehmood';
        $officer1->email = 'tahir.mehmood@nhmp.gov.pk';
        $officer1->mobile_no = '03125550412';
        $officer1->password = Hash::make('PatrolTactical999#');
        $officer1->role_id = $patrolRole->id;
        $officer1->designation_id = 11; // Senior Patrol Officer
        $officer1->is_active = 1;
        $officer1->cnic = '37405-1122334-1';
        $officer1->save();

        // Officer 2: PO Muhammad Khan (Assisting Partner)
        $officer2 = User::where('username', 'po_khan')
            ->orWhere('email', 'muhammad.khan@nhmp.gov.pk')
            ->first();
        if (! $officer2) {
            $officer2 = new User();
            $officer2->uuid = (string) Str::uuid();
        }
        $officer2->username = 'po_khan';
        $officer2->full_name = 'Muhammad Khan';
        $officer2->email = 'muhammad.khan@nhmp.gov.pk';
        $officer2->mobile_no = '03125550882';
        $officer2->password = Hash::make('PatrolTactical999#');
        $officer2->role_id = $patrolRole->id;
        $officer2->designation_id = 12; // Patrol Officer
        $officer2->is_active = 1;
        $officer2->cnic = '37405-2233445-3';
        $officer2->save();

        PatrolOfficerProfile::updateOrCreate(
            ['user_id' => $officer2->id],
            [
                'badge_number'         => 'PO-882',
                'assigned_beat_id'     => $beatOffice->id,
                'is_on_duty'           => false,
            ]
        );

        // =========================================================================
        // 5. SEED ACTIVE TACTICAL DUTY SESSION (Patrol P-12)
        // =========================================================================
        $dutySession = PatrolDutySession::updateOrCreate(
            ['vehicle_callsign' => 'P-12', 'status' => 'BUSY'],
            [
                'officer_user_id'           => $officer1->id,
                'assisting_officer_user_id' => $officer2->id,
                'beat_office_id'            => $beatOffice->id,
                'vehicle_reg_no'            => 'NHMP-1042',
                'started_at'                => $now->copy()->subHours(2),
                'last_latitude'             => 32.79150000,
                'last_longitude'            => 72.71420000,
                'last_speed_kph'            => 62.5,
                'last_bearing_degrees'      => 185.0,
                'last_telemetry_at'         => $now,
            ]
        );

        PatrolOfficerProfile::updateOrCreate(
            ['user_id' => $officer1->id],
            [
                'badge_number'            => 'SPO-412',
                'assigned_beat_id'        => $beatOffice->id,
                'is_on_duty'              => true,
                'current_duty_session_id' => $dutySession->uuid,
            ]
        );

        // Push initial live telemetry into Redis buffer for P-12
        try {
            app(PatrolTelemetryService::class)->recordTelemetry($dutySession, [
                'latitude'         => 32.79150000,
                'longitude'        => 72.71420000,
                'speed_kph'        => 62.5,
                'bearing_degrees'  => 185.0,
                'accuracy_meters'  => 3.8,
            ]);
        } catch (\Throwable $e) {
            // Redis fallback
        }

        // =========================================================================
        // 6. SEED REALISTIC SIMULATED EMERGENCY CASES IN CRM
        // =========================================================================
        $m2Carriageway = Carriageway::where('road', 'M2')->orWhere('road_short', 'M-2')->first() ?? Carriageway::first();
        $emergencyType = CallType::firstOrCreate(
            ['name' => 'Emergency Assistance'],
            ['description' => 'Commuter Emergency SOS Alert']
        );
        $adminAgent = User::where('role_id', 1)->first() ?? $officer1;

        // CASE 1: H-1042 — PENDING / UNASSIGNED (Visible to 130 Call Center / Beat Operator to Assign)
        $casePending = Call::updateOrCreate(
            ['call_number' => 'H-1042'],
            [
                'agent_id'         => $adminAgent->id,
                'commuter_user_id' => $commuter1->id,
                'caller_name'      => $commuter1->full_name,
                'caller_number'    => $commuter1->mobile_no,
                'call_type_id'     => $emergencyType->id,
                'carriageway_id'   => $m2Carriageway->id,
                'vehicle_no'       => 'ICT-LE-921',
                'caller_lat'       => 32.78451000,
                'caller_lng'       => 72.70892000,
                'km_marker_text'   => 'KM 228 Southbound',
                'location_details' => 'Salt Range steep descent slope near Kallar Kahar, M-2 Southbound.',
                'details'          => 'Emergency SOS: Severe engine overheating, burst coolant hose with thick smoke rising. Stranded on emergency shoulder.',
                'status'           => 'pending',
                'priority'         => 1, // Critical
                'office_id'        => null, // Unassigned!
                'patrol_duty_session_id' => null,
                'call_start_time'  => $now->copy()->subMinutes(8),
                'call_pickup_time' => $now->copy()->subMinutes(8),
            ]
        );

        // CASE 2: H-1043 — ASSIGNED & EN-ROUTE TO PATROL P-12 (Testing Patrol App & Commuter Tracking)
        $caseActive = Call::updateOrCreate(
            ['call_number' => 'H-1043'],
            [
                'agent_id'               => $adminAgent->id,
                'commuter_user_id'       => $commuter2->id,
                'caller_name'            => $commuter2->full_name,
                'caller_number'          => $commuter2->mobile_no,
                'call_type_id'           => $emergencyType->id,
                'carriageway_id'         => $m2Carriageway->id,
                'vehicle_no'             => 'ICT-MN-450',
                'caller_lat'             => 32.79150000,
                'caller_lng'             => 72.71420000,
                'km_marker_text'         => 'KM 234 Northbound',
                'location_details'       => 'Near Bhera Interchange, M-2 Northbound.',
                'details'                => 'Rear high-speed tire blowout. Commuter pulled onto shoulder; requested mechanical and safety assistance.',
                'status'                 => 'in_progress',
                'priority'               => 1,
                'office_id'              => $beatOffice->id,
                'tiger_id'               => 'P-12',
                'patrol_duty_session_id' => $dutySession->id,
                'patrol_officer_id'      => (string) $officer1->id,
                'assigned_at'            => $now->copy()->subMinutes(12),
                'acknowledged_at'        => $now->copy()->subMinutes(11),
                'acknowledged_by'        => $officer1->id,
                'inprogress_at'          => $now->copy()->subMinutes(9),
                'call_start_time'        => $now->copy()->subMinutes(15),
                'call_pickup_time'       => $now->copy()->subMinutes(14),
            ]
        );

        // Case Messages for H-1043
        CaseMessage::updateOrCreate(
            ['call_id' => $caseActive->id, 'sender_user_id' => $officer1->id],
            [
                'sender_type'  => 'patrol_officer',
                'audience'     => 'shared_case',
                'message_type' => 'text',
                'content'      => 'Assalam-o-Alaikum Bilal. Stay inside your vehicle behind the safety guardrail with hazard lights flashing. Patrol P-12 is 2.5 km away approaching your location.',
                'metadata'     => ['officer_callsign' => 'P-12'],
                'created_at'   => $now->copy()->subMinutes(7),
            ]
        );

        CaseMessage::updateOrCreate(
            ['call_id' => $caseActive->id, 'sender_user_id' => $commuter2->id],
            [
                'sender_type'  => 'commuter',
                'audience'     => 'shared_case',
                'message_type' => 'text',
                'content'      => 'Thank you Officer Tahir! Hazards are on, and we are standing safely behind the guardrail waiting for P-12.',
                'created_at'   => $now->copy()->subMinutes(5),
            ]
        );

        // =========================================================================
        // 7. SEED WOMEN'S SAFETY JOURNEY (WJ-204)
        // =========================================================================
        $womensJourney = WomensJourney::updateOrCreate(
            ['journey_code' => 'WJ-204'],
            [
                'user_id'             => $commuter3->id,
                'origin_city'         => 'Rawalpindi',
                'origin_stop'         => 'Faizabad Terminal',
                'destination_city'    => 'Lahore',
                'destination_stop'    => 'Kalma Chowk Terminal',
                'psv_company_name'    => 'Daewoo Express',
                'psv_reg_no'          => 'LZX-8822',
                'psv_seat_no'         => '14-B',
                'status'              => 'active',
                'last_latitude'       => 32.1485000,
                'last_longitude'      => 73.1842000,
                'last_check_in_at'    => $now->copy()->subMinutes(25),
                'notes'               => 'Bus traveling on M-2 Southbound. Periodic check-in frequency 30 minutes.',
            ]
        );

        WomensJourneyUpdate::updateOrCreate(
            ['womens_journey_id' => $womensJourney->id, 'update_type' => 'check_in'],
            [
                'latitude'       => 32.1485000,
                'longitude'      => 73.1842000,
                'location_name'  => 'Crossed Bhera Service Area KM 230 Southbound',
                'content'        => 'Bus stopped for 15 minutes refreshments. Transit is on schedule and peaceful.',
                'created_at'     => $now->copy()->subMinutes(25),
            ]
        );

        // =========================================================================
        // 8. SEED REALISTIC TRAVEL ADVISORIES
        // =========================================================================
        TabletAdvisory::updateOrCreate(
            ['title' => 'M-2 Salt Range Fog & Visibility Advisory'],
            [
                'category'     => 'fog',
                'severity'     => 'warning',
                'route_name'   => 'M-2',
                'carriageway'  => 'both',
                'start_km'     => 210.00,
                'end_km'       => 240.00,
                'message'      => 'Dense fog with visibility reduced to under 40 meters between Balkasar (KM 210) and Kallar Kahar (KM 240). Motorists are advised to keep low beams on, maintain double safety distance, and avoid abrupt braking.',
                'status'       => 'published',
                'is_emergency' => false,
                'published_at' => $now->copy()->subHours(3),
                'expires_at'   => $now->copy()->addHours(6),
                'created_by'   => $adminAgent->id,
            ]
        );

        TabletAdvisory::updateOrCreate(
            ['title' => 'EMERGENCY: Diesel Spill & Single Lane Closure KM 222'],
            [
                'category'     => 'road_closure',
                'severity'     => 'critical',
                'route_name'   => 'M-2',
                'carriageway'  => 'southbound',
                'start_km'     => 221.50,
                'end_km'       => 223.00,
                'message'      => 'CRITICAL ALERT: Heavy oil/diesel spill on Southbound slow lane at KM 222. NHMP Mobile chemical team and sand bowsers actively responding. Extreme skid hazard; speed limit strictly reduced to 30 km/h.',
                'status'       => 'published',
                'is_emergency' => true,
                'published_at' => $now->copy()->subMinutes(40),
                'expires_at'   => $now->copy()->addHours(2),
                'created_by'   => $adminAgent->id,
            ]
        );
    }

    private function attachPermsToRole(?Role $role, array $permissionSlugs): void
    {
        if (! $role) {
            return;
        }

        $permissionIds = Permission::whereIn('name', $permissionSlugs)->pluck('id')->toArray();
        $role->permissions()->syncWithoutDetaching($permissionIds);
    }
}

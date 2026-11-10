<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class SimulateOrgUsersCommand extends Command
{
    protected $signature = 'nhmp:simulate-org-users {--password=TestPass123! : Default password for simulated users}';
    protected $description = 'Provision standard simulated NHMP organizational hierarchy users (Agent, Beat Operator, Sector Admin, Zone Admin)';

    public function handle(): int
    {
        $password = (string) $this->option('password');
        $sharedTotpSecret = 'JBSWY3DPEHPK3PXP';

        $this->info('============================================================');
        $this->info('  NHMP 130 CRM — Organizational Hierarchy User Provisioning');
        $this->info('============================================================');

        // Locate key target offices (Motorway North hierarchy)
        $zoneOffice = Office::where('type', 'zone')
            ->where(function ($q) {
                $q->where('name', 'LIKE', '%North%')->orWhere('name', 'LIKE', '%Central%');
            })
            ->first() ?? Office::where('type', 'zone')->first();

        $sectorOffice = Office::where('type', 'sector')
            ->where('parent_id', $zoneOffice?->id)
            ->first() ?? Office::where('type', 'sector')->first();

        $beatOffice = Office::where('type', 'beat')
            ->where('parent_id', $sectorOffice?->id)
            ->first() ?? Office::where('type', 'beat')->first();

        $this->line("Target Hierarchy:");
        $this->line("  Zone:   [#{$zoneOffice?->id}] {$zoneOffice?->name}");
        $this->line("  Sector: [#{$sectorOffice?->id}] {$sectorOffice?->name}");
        $this->line("  Beat:   [#{$beatOffice?->id}] {$beatOffice?->name}");
        $this->newLine();

        $usersToCreate = [
            [
                'username'       => 'nhmp_agent_01',
                'full_name'      => 'Call Center Agent Muhammad Rizwan',
                'email'          => 'agent01@nhmp.synthetic',
                'mobile_no'      => '03001234501',
                'cnic'           => '35201-1111111-1',
                'designation_id' => 15, // Junior Patrol Officer
                'role_id'        => 7,  // agent
                'scope_office'   => null,
                'role_title'     => 'Call Center Agent',
            ],
            [
                'username'       => 'nhmp_beat_01',
                'full_name'      => 'Beat Wireless Operator Tariq Mehmood',
                'email'          => 'beat01@nhmp.synthetic',
                'mobile_no'      => '03001234502',
                'cnic'           => '35201-2222222-2',
                'designation_id' => 14, // Assistant Patrol Officer
                'role_id'        => 5,  // beat_operator
                'scope_office'   => $beatOffice?->id,
                'role_title'     => 'Beat Operator (Beat-01)',
            ],
            [
                'username'       => 'nhmp_sector_01',
                'full_name'      => 'Sector Administrator Farhan Ali',
                'email'          => 'sector01@nhmp.synthetic',
                'mobile_no'      => '03001234503',
                'cnic'           => '35201-3333333-3',
                'designation_id' => 11, // Senior Patrol Officer
                'role_id'        => 4,  // sector_admin
                'scope_office'   => $sectorOffice?->id,
                'role_title'     => 'Sector Admin (M-1)',
            ],
            [
                'username'       => 'nhmp_zone_01',
                'full_name'      => 'Zone Administrator DIG Khalid Khan',
                'email'          => 'zone01@nhmp.synthetic',
                'mobile_no'      => '03001234504',
                'cnic'           => '35201-4444444-4',
                'designation_id' => 3,  // Deputy Inspector General
                'role_id'        => 3,  // zone_admin
                'scope_office'   => $zoneOffice?->id,
                'role_title'     => 'Zone Admin (North)',
            ],
        ];

        foreach ($usersToCreate as $u) {
            /** @var User $user */
            $user = User::updateOrCreate(
                ['username' => $u['username']],
                [
                    'full_name'           => $u['full_name'],
                    'email'               => $u['email'],
                    'mobile_no'           => $u['mobile_no'],
                    'cnic'                => $u['cnic'],
                    'designation_id'      => $u['designation_id'],
                    'role_id'             => $u['role_id'],
                    'password'            => Hash::make($password),
                    'password_changed_at' => now(),
                    'totp_secret'         => Crypt::encryptString($sharedTotpSecret),
                    'totp_last_step'      => null,
                    'mfa_required'        => true,
                    'mfa_enrolled_at'     => now(),
                    'is_active'           => true,
                ]
            );

            // Assign scope if applicable
            if ($u['scope_office']) {
                UserScope::updateOrCreate(
                    [
                        'user_id'   => $user->id,
                        'office_id' => $u['scope_office'],
                    ],
                    [
                        'access_level' => 'full',
                        'label'        => $u['role_title'],
                        'is_active'    => true,
                    ]
                );
            }

            // Create audit log for compliance
            AuditLog::create([
                'user_id'    => 352, // Super Admin
                'action'     => 'user_provisioned_cli',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => [
                    'username' => $user->username,
                    'role'     => $u['role_title'],
                    'scope'    => $u['scope_office'],
                ],
                'ip_address' => '127.0.0.1 (CLI)',
                'user_agent' => 'Artisan SimulateOrgUsersCommand',
            ]);

            $this->info("  ✓ Provisioned: [{$u['username']}] - {$u['role_title']} (ID: {$user->id})");
        }

        $this->newLine();
        $this->info('All organizational hierarchy users provisioned successfully.');
        $this->line("Default Password:  {$password}");
        $this->line("Shared 2FA Secret: {$sharedTotpSecret}");
        $this->info('============================================================');

        return self::SUCCESS;
    }
}

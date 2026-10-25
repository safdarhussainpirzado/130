<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\TwoFactor\EnrollmentAuthorizationService;
use Illuminate\Console\Command;

class BootstrapSuperAdminMfa extends Command
{
    protected $signature = 'admin:mfa-bootstrap {user? : Username or email of the Super Administrator}';
    protected $description = 'One-time secure bootstrap procedure for the initial Super Administrator MFA setup';

    public function handle(EnrollmentAuthorizationService $authService): int
    {
        $identifier = $this->argument('user') ?? '130_crm_admin';

        /** @var User|null $admin */
        $admin = User::where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (!$admin) {
            $admin = User::where('role_id', 1)->first();
        }

        if (!$admin) {
            $this->error("No administrator account found matching [{$identifier}].");
            return self::FAILURE;
        }

        // Safety Barrier: If already enrolled, refuse bootstrap
        if ($admin->hasMfaEnrolled()) {
            $this->error("Account [{$admin->username}] already has active MFA configured. Bootstrap is disabled for already-enrolled accounts.");
            $this->line("Use the standard recovery dual-control process or authenticated replacement.");
            return self::FAILURE;
        }

        $result = $authService->issueAuthorization($admin, $admin, 'Initial System Bootstrap via Server CLI');
        $token = $result['plain_token'];

        AuditLog::create([
            'user_id'    => $admin->id,
            'action'     => 'super_admin_mfa_bootstrap',
            'table_name' => 'users',
            'record_id'  => $admin->id,
            'new_values' => ['action' => 'Server CLI One-Time MFA Bootstrap Triggered'],
            'ip_address' => '127.0.0.1 (CLI)',
            'user_agent' => 'Artisan BootstrapSuperAdminMfa',
        ]);

        $enrollUrl = url("/2fa/enroll?token={$token}");

        $this->newLine();
        $this->info("===============================================================================");
        $this->info("   NHMP 130 HELPLINE CRM — INITIAL SUPER ADMIN MFA BOOTSTRAP                   ");
        $this->info("===============================================================================");
        $this->line("Target Account: [{$admin->full_name}] ({$admin->username} / {$admin->email})");
        $this->line("Single-Use Authorization Token (Expires in 10 minutes):");
        $this->newLine();
        $this->warn("  {$token}");
        $this->newLine();
        $this->line("Direct Enrollment URL:");
        $this->line("  {$enrollUrl}");
        $this->newLine();
        $this->line("Next Steps:");
        $this->line("  1. Navigate to the enrollment URL in an authorized browser.");
        $this->line("  2. Scan the locally rendered QR code with your authenticator device (or register WebAuthn).");
        $this->line("  3. Prove possession with a valid 6-digit code.");
        $this->line("  4. Securely store your 10 single-use emergency recovery codes.");
        $this->info("===============================================================================");

        return self::SUCCESS;
    }
}

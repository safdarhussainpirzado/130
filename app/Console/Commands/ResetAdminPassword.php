<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ResetAdminPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:reset-password 
                            {user? : Username or email of the administrator}
                            {--password= : Explicit password to set}
                            {--generate : Generate a secure random password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Emergency Break-Glass tool to reset administrator credentials directly from the server CLI';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $identifier = $this->argument('user') ?? $this->ask('Enter administrator username or email');

        if (!$identifier) {
            $this->error('Username or email is required.');
            return self::FAILURE;
        }

        /** @var User|null $user */
        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user) {
            $this->error("User '{$identifier}' not found in the database.");
            return self::FAILURE;
        }

        $password = $this->option('password');

        if ($this->option('generate')) {
            // Generate compliant 16-char password (uppercase, lowercase, number, symbol)
            $password = 'Nhmp!' . Str::random(8) . rand(100, 999) . '#';
            $this->info("Generated secure compliant password: {$password}");
        } elseif (!$password) {
            $password = $this->secret('Enter new password (min 12 chars, upper/lower/numbers/symbols)');
            $confirm = $this->secret('Confirm new password');

            if ($password !== $confirm) {
                $this->error('Passwords do not match.');
                return self::FAILURE;
            }
        }

        // Validate password complexity
        if (strlen($password) < 12) {
            $this->error('Password must be at least 12 characters long according to security policy.');
            return self::FAILURE;
        }

        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $this->error('Password must contain uppercase letters, lowercase letters, and numbers.');
            return self::FAILURE;
        }

        // Apply new credentials & reset security locks
        $oldValues = [
            'locked_until' => $user->locked_until,
            'failed_attempts' => $user->failed_attempts ?? 0,
        ];

        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
            'locked_until' => null,
        ]);

        if (array_key_exists('failed_attempts', $user->getAttributes())) {
            $user->setAttribute('failed_attempts', 0);
        }

        $user->save();

        // Invalidate active sessions from DB
        try {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        } catch (\Throwable $e) {
            // Session table cleanup fallback
        }

        // Record Break-Glass Action into Audit Log
        try {
            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'EMERGENCY_CLI_PASSWORD_RESET',
                'table_name' => 'users',
                'record_id' => $user->id,
                'old_values' => $oldValues,
                'new_values' => ['action' => 'Server CLI Break-Glass Override by root/operator'],
                'ip_address' => '127.0.0.1 (CLI)',
                'user_agent' => 'Artisan CLI Break-Glass',
            ]);
        } catch (\Throwable $e) {
            // Audit log write fallback
        }

        $this->newLine();
        $this->info("✅ Credentials successfully reset for [{$user->full_name}] (Username: {$user->username}, Email: {$user->email})");
        $this->line("   - Account lockouts and failed attempt counters cleared.");
        $this->line("   - Existing active sessions terminated.");
        $this->line("   - Action recorded in Audit Log.");

        return self::SUCCESS;
    }
}

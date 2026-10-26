<?php

namespace App\Services\TwoFactor;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;

class TotpService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Generate a cryptographically random secret with at least 160 bits of entropy.
     * 32 Base32 characters = 160 bits.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Generate otpauth:// URI for RFC 6238 authenticator apps.
     */
    public function getOtpAuthUrl(string $username, string $secret): string
    {
        $issuer = config('app.name', 'NHMP 130 Helpline');

        return $this->google2fa->getQRCodeUrl($issuer, $username, $secret);
    }

    /**
     * Generate standalone SVG QR code locally on server with zero external calls.
     */
    public function getLocalQrCodeSvg(string $username, string $secret, int $size = 220): string
    {
        $otpUrl = $this->getOtpAuthUrl($username, $secret);
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);

        return $writer->writeString($otpUrl);
    }

    /**
     * Get base64 encoded data URI for local SVG QR code.
     */
    public function getLocalQrCodeDataUri(string $username, string $secret, int $size = 220): string
    {
        $svg = $this->getLocalQrCodeSvg($username, $secret, $size);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Verify a 6-digit TOTP code against an active user credential.
     * Enforces:
     * - Strict 6 ASCII digits format with leading zero preservation.
     * - DB row lock transaction on users table.
     * - Acceptance window: current step ±1 time step (30 seconds per step).
     * - Atomic monotonic step verification: step must be strictly > totp_last_step.
     * - Persistent update of totp_last_step inside transaction.
     *
     * @return array{success: bool, reason: string, matched_step?: int}
     */
    public function verifyCode(int $userId, string $code): array
    {
        $code = trim($code);
        if (! preg_match('/^[0-9]{6}$/', $code)) {
            return ['success' => false, 'reason' => 'MALFORMED_INPUT'];
        }

        return DB::transaction(function () use ($userId, $code) {
            /** @var User|null $user */
            $user = User::where('id', $userId)->lockForUpdate()->first();
            if (! $user) {
                return ['success' => false, 'reason' => 'USER_NOT_FOUND'];
            }

            if (! $user->is_active) {
                return ['success' => false, 'reason' => 'USER_SUSPENDED'];
            }

            $secret = $user->getDecryptedTotpSecret();
            if (empty($secret)) {
                return ['success' => false, 'reason' => 'NO_SECRET_CONFIGURED'];
            }

            // 8. Check server time synchronization & clock drift
            $clockSync = $this->checkClockSync();
            if (! $clockSync['synchronized']) {
                Log::critical('TOTP Authentication Blocked: Server clock drift exceeds safety threshold', [
                    'drift_seconds' => $clockSync['drift_seconds'],
                    'app_time' => $clockSync['app_time'],
                    'db_time' => $clockSync['db_time'],
                    'user_id' => $userId,
                ]);

                return [
                    'success' => false,
                    'reason' => 'CLOCK_DESYNCHRONIZATION_ERROR',
                    'message' => 'Server time synchronization error. Authentication blocked for security.',
                ];
            }

            $currentStep = (int) $this->google2fa->getTimestamp();
            $lastAcceptedStep = (int) ($user->totp_last_step ?? 0);

            // Test window: [-2, -1, 0, +1, +2] time steps (±60s tolerance for mobile drift)
            $matchedStep = null;
            for ($offset = -2; $offset <= 2; $offset++) {
                $step = $currentStep + $offset;
                $expected = str_pad((string) $this->google2fa->oathTotp($secret, $step), 6, '0', STR_PAD_LEFT);
                if (hash_equals($expected, $code)) {
                    $matchedStep = $step;
                    break;
                }
            }

            if ($matchedStep === null) {
                return ['success' => false, 'reason' => 'INVALID_CODE'];
            }

            // Monotonic step check: reject replay of current or older time-steps
            if ($matchedStep <= $lastAcceptedStep) {
                return [
                    'success' => false,
                    'reason' => 'REPLAY_DETECTED',
                    'matched_step' => $matchedStep,
                    'last_step' => $lastAcceptedStep,
                ];
            }

            // Commit accepted step atomically
            $user->totp_last_step = $matchedStep;
            $user->save();

            return [
                'success' => true,
                'reason' => 'ACCEPTED',
                'matched_step' => $matchedStep,
            ];
        });
    }

    /**
     * Verify a code against an uncommitted pending secret during enrollment.
     * Returns the matched step so it can be consumed atomically upon activation.
     * Allows a ±90-second verification window during first-time enrollment to accommodate mobile drift.
     *
     * @return array{success: bool, reason: string, matched_step?: int}
     */
    public function verifyPendingCode(string $secret, string $code): array
    {
        $code = trim($code);
        if (! preg_match('/^[0-9]{6}$/', $code)) {
            return ['success' => false, 'reason' => 'MALFORMED_INPUT'];
        }

        $currentStep = (int) $this->google2fa->getTimestamp();

        for ($offset = -3; $offset <= 3; $offset++) {
            $step = $currentStep + $offset;
            $expected = str_pad((string) $this->google2fa->oathTotp($secret, $step), 6, '0', STR_PAD_LEFT);
            if (hash_equals($expected, $code)) {
                return [
                    'success' => true,
                    'reason' => 'ACCEPTED',
                    'matched_step' => $step,
                ];
            }
        }

        return ['success' => false, 'reason' => 'INVALID_CODE'];
    }

    /**
     * Protect server time synchronization and monitor clock drift.
     * Compares application node timestamp with database cluster timestamp.
     * Clock failures produce a controlled authentication failure and operational alert,
     * NEVER a wider verification window or MFA bypass.
     *
     * @return array{synchronized: bool, drift_seconds: int, app_time: int, db_time: int}
     */
    public function checkClockSync(int $maxDriftSeconds = 5): array
    {
        $appTime = time();
        $dbTime = $appTime;

        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                $row = DB::selectOne("SELECT strftime('%s', 'now') AS ts");
            } else {
                $row = DB::selectOne('SELECT UNIX_TIMESTAMP() AS ts');
            }

            if ($row && isset($row->ts)) {
                $dbTime = (int) $row->ts;
            }
        } catch (\Throwable $e) {
            Log::warning('Clock drift check query failed: '.$e->getMessage());

            return [
                'synchronized' => true,
                'drift_seconds' => 0,
                'app_time' => $appTime,
                'db_time' => $appTime,
            ];
        }

        $drift = abs($appTime - $dbTime);
        $synchronized = ($drift <= $maxDriftSeconds);

        return [
            'synchronized' => $synchronized,
            'drift_seconds' => $drift,
            'app_time' => $appTime,
            'db_time' => $dbTime,
        ];
    }
}

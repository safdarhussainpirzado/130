<?php

namespace App\Services\TwoFactor;

use App\Models\TwoFactorRecoveryCode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RecoveryService
{
    /**
     * Generate 10 independent recovery codes with at least 128 bits of entropy each.
     * Formats codes as XXXX-XXXX-XXXX-XXXX for readability.
     *
     * @return array{plain_codes: array<string>, generation_id: string}
     */
    public function generateCodes(int $userId): array
    {
        $plainCodes = [];
        $records = [];
        $generationId = (string) Str::uuid();
        $now = now();

        for ($i = 0; $i < 10; $i++) {
            // 16 bytes = 128 bits of cryptographic entropy
            $rawBytes = random_bytes(16);
            $hex = bin2hex($rawBytes);
            // 16-character alphanumeric code divided into 4 chunks
            $formatted = strtoupper(
                substr($hex, 0, 4).'-'.
                substr($hex, 4, 4).'-'.
                substr($hex, 8, 4).'-'.
                substr($hex, 12, 4)
            );

            $plainCodes[] = $formatted;

            $normalized = $this->normalizeCode($formatted);
            $records[] = [
                'user_id' => $userId,
                'code_hash' => Hash::make($normalized),
                'generation_id' => $generationId,
                'consumed_at' => null,
                'created_at' => $now,
            ];
        }

        DB::transaction(function () use ($userId, $records) {
            // Invalidate all previous generations for this user
            TwoFactorRecoveryCode::where('user_id', $userId)->delete();

            // Insert new generation
            TwoFactorRecoveryCode::insert($records);
        });

        return [
            'plain_codes' => $plainCodes,
            'generation_id' => $generationId,
        ];
    }

    /**
     * Atomically verify and consume a single-use recovery code.
     *
     * @return array{success: bool, reason: string, remaining_count?: int}
     */
    public function verifyAndConsumeCode(int $userId, string $inputCode): array
    {
        $normalized = $this->normalizeCode($inputCode);

        // Bounded input length check (minimum 10, maximum 32 alphanumeric chars)
        if (strlen($normalized) < 10 || strlen($normalized) > 32) {
            return ['success' => false, 'reason' => 'MALFORMED_RECOVERY_CODE'];
        }

        return DB::transaction(function () use ($userId, $normalized) {
            // Lock unconsumed recovery codes for this user
            $activeCodes = TwoFactorRecoveryCode::where('user_id', $userId)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get();

            if ($activeCodes->isEmpty()) {
                return ['success' => false, 'reason' => 'NO_RECOVERY_CODES_AVAILABLE'];
            }

            $matchedRecord = null;
            foreach ($activeCodes as $codeRecord) {
                if (Hash::check($normalized, $codeRecord->code_hash)) {
                    $matchedRecord = $codeRecord;
                    break;
                }
            }

            if (! $matchedRecord) {
                return ['success' => false, 'reason' => 'INVALID_RECOVERY_CODE'];
            }

            // Consume the matched code atomically
            $matchedRecord->consumed_at = now();
            $matchedRecord->save();

            $remainingCount = TwoFactorRecoveryCode::where('user_id', $userId)
                ->whereNull('consumed_at')
                ->count();

            return [
                'success' => true,
                'reason' => 'CONSUMED',
                'remaining_count' => $remainingCount,
            ];
        });
    }

    /**
     * Get count of unconsumed recovery codes for a user without exposing hashes.
     */
    public function getRemainingCount(int $userId): int
    {
        return TwoFactorRecoveryCode::where('user_id', $userId)
            ->whereNull('consumed_at')
            ->count();
    }

    /**
     * Normalize recovery code input (strip non-alphanumeric, uppercase).
     */
    public function normalizeCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }
}

<?php

namespace Tests\Unit\Security;

use App\Casts\GracefulEncrypted;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\CommuterProfile;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ComprehensiveSecurityAndAuditTest extends TestCase
{

    /**
     * 1. Test Docker Secrets resolution & fallback.
     */
    public function test_docker_secret_resolution(): void
    {
        $this->assertEquals('default_value', docker_secret('non_existent_secret_xyz', 'default_value'));
    }

    /**
     * 2. Test Deterministic Phone Blind Index with Pakistani dialing variations.
     */
    public function test_phone_blind_index_canonicalization(): void
    {
        $variations = [
            '03001234567',
            '0300-1234567',
            '+92 300 1234567',
            '+92-300-1234567',
            '00923001234567',
            '923001234567',
        ];

        $hashes = array_map(fn($phone) => phone_blind_index($phone), $variations);
        $uniqueHashes = array_unique($hashes);

        $this->assertCount(1, $uniqueHashes, 'All Pakistani phone variations must yield the identical blind index hash.');
        $this->assertEquals(64, strlen($hashes[0]), 'Blind index must be a 64-character SHA-256 HMAC.');
    }

    /**
     * 3. Test Argon2id password hashing and parameters.
     */
    public function test_argon2id_password_hashing(): void
    {
        $password = 'CandidateSecurePass2026!';
        $hash = Hash::make($password);

        $this->assertTrue(str_starts_with($hash, '$argon2id$'), 'Password must be hashed with Argon2id algorithm.');
        $this->assertTrue(Hash::check($password, $hash), 'Password verification must succeed with Argon2id.');

        $info = Hash::info($hash);
        $this->assertEquals('argon2id', $info['algoName']);
    }

    /**
     * 4. Test Zero-Downtime auto-rehash detection for legacy Bcrypt hashes.
     */
    public function test_legacy_bcrypt_auto_rehash_detection(): void
    {
        // Legacy Bcrypt hash
        $legacyBcrypt = '$2y$12$e80y6xJkWbC1r1Z9V8c6ye1q0k9p4.W4vLw1q5u7x3s1d5f7g9h1j';
        $this->assertTrue(Hash::needsRehash($legacyBcrypt), 'Legacy Bcrypt hash must require rehash to Argon2id.');
    }

    /**
     * 5. Test AES-256-GCM encryption, decryption, and plaintext fallback.
     */
    public function test_graceful_encrypted_cast(): void
    {
        $cast = new GracefulEncrypted();
        $model = new Call();

        $plainText = '03001234567';
        $cipher = $cast->set($model, 'caller_number', $plainText, []);

        $this->assertNotEquals($plainText, $cipher, 'Raw database value must be encrypted.');
        $this->assertGreaterThan(100, strlen($cipher), 'Ciphertext must contain IV, payload, and MAC.');

        // Verify successful decryption
        $decrypted = $cast->get($model, 'caller_number', $cipher, []);
        $this->assertEquals($plainText, $decrypted, 'Decrypted value must match original plaintext.');

        // Test fallback for legacy unencrypted database rows
        $legacyPlain = '03217654321';
        $fallbackDecrypted = $cast->get($model, 'caller_number', $legacyPlain, []);
        $this->assertEquals($legacyPlain, $fallbackDecrypted, 'Legacy plaintext row must be returned gracefully.');
    }

    /**
     * 6. Test Selective Field-Level Encryption on Call model:
     * Personal identifiers (caller_number, caller_name) are encrypted.
     * Operational details (details, km_marker_text, status, priority) remain PLAINTEXT.
     */
    public function test_selective_field_encryption_on_call_model(): void
    {
        $call = new Call();
        $casts = $call->getCasts();

        $this->assertEquals(GracefulEncrypted::class, $casts['caller_number']);
        $this->assertEquals(GracefulEncrypted::class, $casts['caller_name']);

        // Assert operational fields are NOT encrypted
        $this->assertArrayNotHasKey('details', $casts);
        $this->assertArrayNotHasKey('km_marker_text', $casts);
        $this->assertArrayNotHasKey('status', $casts);
        $this->assertArrayNotHasKey('priority', $casts);
    }

    /**
     * 7. Test User and CommuterProfile selective encryption.
     */
    public function test_user_and_commuter_encryption_casts(): void
    {
        $user = new User();
        $userCasts = $user->getCasts();
        $this->assertEquals(GracefulEncrypted::class, $userCasts['cnic']);
        $this->assertEquals(GracefulEncrypted::class, $userCasts['mobile_no']);

        $commuter = new CommuterProfile();
        $commuterCasts = $commuter->getCasts();
        $this->assertEquals(GracefulEncrypted::class, $commuterCasts['emergency_contacts']);
    }

    /**
     * 8. Test Tamper-Evident Audit Log Hash Chaining.
     */
    public function test_audit_log_hash_chaining(): void
    {
        $salt = config('app.audit_salt') ?: (function_exists('docker_secret') ? docker_secret('audit_salt', 'nhmp_130_audit_salt') : 'nhmp_130_audit_salt');

        $log = new AuditLog([
            'user_id'    => 1,
            'action'     => 'test_action',
            'table_name' => 'calls',
            'record_id'  => 999999,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit/11',
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'dispatched'],
        ]);

        // Trigger booted creating hook
        $log->save();

        $this->assertNotNull($log->previous_hash);
        $this->assertEquals(64, strlen($log->previous_hash));
        $this->assertNotNull($log->record_hash);
        $this->assertEquals(64, strlen($log->record_hash));

        // Verify cryptographic validity
        $payload = implode('|', [
            (string) $log->user_id,
            (string) $log->action,
            (string) $log->table_name,
            (string) $log->record_id,
            (string) $log->ip_address,
            json_encode($log->old_values),
            json_encode($log->new_values),
            $log->previous_hash,
        ]);
        $expectedHash = hash_hmac('sha256', $payload, $salt);
        $this->assertEquals($expectedHash, $log->record_hash, 'Audit log record_hash must match HMAC signature.');
    }

    /**
     * 9. Test Audit Log Tamper Detection and DB Append-Only Trigger.
     */
    public function test_audit_log_tamper_detection(): void
    {
        $salt = config('app.audit_salt') ?: (function_exists('docker_secret') ? docker_secret('audit_salt', 'nhmp_130_audit_salt') : 'nhmp_130_audit_salt');

        // Insert test log record
        $log = AuditLog::create([
            'user_id'    => 1,
            'action'     => 'security_tamper_test',
            'table_name' => 'users',
            'record_id'  => 1,
            'ip_address' => '10.0.0.1',
            'user_agent' => 'TestAgent',
            'old_values' => ['role' => 'operator'],
            'new_values' => ['role' => 'admin'],
        ]);

        // Verify integrity is intact initially
        $result = AuditLog::verifyIntegrity(50);
        $this->assertEquals('intact', $result['status']);

        // Verify database append-only trigger prevents SQL-level tampering
        $tamperBlocked = false;
        try {
            DB::statement("UPDATE audit_logs SET action = 'tampered_action' WHERE id = ?", [$log->id]);
        } catch (\Illuminate\Database\QueryException $e) {
            $tamperBlocked = str_contains($e->getMessage(), 'append-only') || str_contains($e->getMessage(), '45000');
        }
        $this->assertTrue($tamperBlocked, 'Database trigger must strictly forbid UPDATE on audit_logs.');

        // Verify cryptographic detection if record fields are altered
        $tamperedPayload = implode('|', [
            (string) $log->user_id,
            'tampered_action',
            (string) $log->table_name,
            (string) $log->record_id,
            (string) $log->ip_address,
            json_encode($log->old_values),
            json_encode($log->new_values),
            (string) $log->previous_hash,
        ]);
        $tamperedCalculatedHash = hash_hmac('sha256', $tamperedPayload, $salt);
        $this->assertNotEquals($tamperedCalculatedHash, $log->record_hash, 'Tampered action must produce mismatched HMAC signature.');
    }
}

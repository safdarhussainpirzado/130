<?php

namespace Tests\Unit\Security;

use App\Casts\GracefulEncrypted;
use App\Models\Call;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FieldEncryptionAndDockerSecretsTest extends TestCase
{
    /**
     * Test that docker_secret() helper falls back gracefully to env() or default.
     */
    public function test_docker_secret_helper_resolves_correctly(): void
    {
        $resolved = docker_secret('non_existent_secret_key', 'fallback_value');
        $this->assertEquals('fallback_value', $resolved);

        // Test with simulated /tmp secret file
        $tmpSecretPath = '/tmp/test_secret_' . uniqid();
        file_put_contents($tmpSecretPath, "super_secret_value\n");

        // Temporary bind /run/secrets simulation via helper fallback test
        putenv('SIMULATED_SECRET=super_secret_env_value');
        $this->assertEquals('super_secret_env_value', docker_secret('simulated_secret'));

        if (file_exists($tmpSecretPath)) {
            unlink($tmpSecretPath);
        }
    }

    /**
     * Test phone canonicalization and HMAC-SHA256 blind indexing.
     */
    public function test_phone_blind_index_canonicalizes_and_matches_variations(): void
    {
        $standard = phone_blind_index('03001234567');
        $dashed   = phone_blind_index('0300-1234567');
        $intlPlus = phone_blind_index('+92 300 1234567');
        $intlZero = phone_blind_index('00923001234567');

        $this->assertNotNull($standard);
        $this->assertEquals(64, strlen($standard)); // SHA-256 output length
        $this->assertEquals($standard, $dashed, 'Dashed format must generate identical blind index');
        $this->assertEquals($standard, $intlPlus, 'Intl +92 format must generate identical blind index');
        $this->assertEquals($standard, $intlZero, 'Intl 0092 format must generate identical blind index');

        // Different number produces completely distinct hash
        $different = phone_blind_index('03217654321');
        $this->assertNotEquals($standard, $different);
    }

    /**
     * Test Argon2id configuration and password verification.
     */
    public function test_argon2id_password_hashing_and_rehash(): void
    {
        $plainPassword = 'NHMP@SecurePassword2026!';
        $argonHash = Hash::make($plainPassword);

        $this->assertTrue(Hash::check($plainPassword, $argonHash));
        $this->assertFalse(Hash::check('WrongPassword!', $argonHash));

        // When driver is argon2id, an existing Bcrypt hash requires rehash on login
        $legacyBcryptHash = password_hash($plainPassword, PASSWORD_BCRYPT, ['cost' => 4]);
        $this->assertTrue(Hash::check($plainPassword, $legacyBcryptHash));
        
        // Rehash condition: Bcrypt hash needs upgrade under Argon2id configuration
        $this->assertTrue(Hash::needsRehash($legacyBcryptHash));
    }

    /**
     * Test GracefulEncrypted cast handles AES-256-GCM encryption, decryption, and legacy plaintext.
     */
    public function test_graceful_encrypted_cast_encryption_and_legacy_fallback(): void
    {
        $cast = new GracefulEncrypted();
        $call = new Call();

        // 1. Plaintext input gets encrypted to ciphertext
        $plain = '03001234567';
        $cipher = $cast->set($call, 'caller_number', $plain, []);
        $this->assertNotEquals($plain, $cipher);
        $this->assertGreaterThan(100, strlen($cipher)); // AES-256-GCM serialized JSON

        // 2. Ciphertext gets decrypted back to original plaintext
        $decrypted = $cast->get($call, 'caller_number', $cipher, []);
        $this->assertEquals($plain, $decrypted);

        // 3. Historical unencrypted plaintext does not crash; returns as-is
        $legacyPlaintext = '03009999999';
        $handledLegacy = $cast->get($call, 'caller_number', $legacyPlaintext, []);
        $this->assertEquals($legacyPlaintext, $handledLegacy);
    }

    /**
     * Test that operational call details are NOT encrypted.
     */
    public function test_call_operational_details_remain_unencrypted(): void
    {
        $call = new Call();
        $casts = $call->getCasts();

        // Personal Information MUST be encrypted
        $this->assertEquals(GracefulEncrypted::class, $casts['caller_number'] ?? null);
        $this->assertEquals(GracefulEncrypted::class, $casts['caller_name'] ?? null);

        // Operational details MUST NOT be encrypted
        $this->assertArrayNotHasKey('call_number', $casts);
        $this->assertArrayNotHasKey('details', $casts);
        $this->assertArrayNotHasKey('km_marker_text', $casts);
        $this->assertArrayNotHasKey('carriageway_id', $casts);
        $this->assertArrayNotHasKey('office_id', $casts);
        $this->assertArrayNotHasKey('vehicle_no', $casts);
    }

    /**
     * Test that User model has GracefulEncrypted on CNIC and mobile_no.
     */
    public function test_user_model_encrypts_cnic_and_mobile_no(): void
    {
        $user = new User();
        $casts = $user->getCasts();

        $this->assertEquals(GracefulEncrypted::class, $casts['cnic'] ?? null);
        $this->assertEquals(GracefulEncrypted::class, $casts['mobile_no'] ?? null);

        // Username and ID remain unencrypted for authentication lookups
        $this->assertNotEquals(GracefulEncrypted::class, $casts['username'] ?? null);
        $this->assertNotEquals(GracefulEncrypted::class, $casts['id'] ?? null);
    }

    /**
     * Test that emergency_contacts is encrypted in CommuterProfile and WomensJourney.
     */
    public function test_emergency_contacts_are_encrypted(): void
    {
        $commuter = new \App\Models\CommuterProfile();
        $this->assertEquals(GracefulEncrypted::class, $commuter->getCasts()['emergency_contacts'] ?? null);

        $womensJourney = new \App\Models\WomensJourney();
        $this->assertEquals(GracefulEncrypted::class, $womensJourney->getCasts()['emergency_contacts'] ?? null);

        // Test array encryption and decryption roundtrip
        $cast = new GracefulEncrypted();
        $contacts = [
            ['name' => 'Brother', 'phone' => '03001234567', 'relationship' => 'Kin'],
            ['name' => 'Father', 'phone' => '03219876543', 'relationship' => 'Guardian'],
        ];

        $encrypted = $cast->set($commuter, 'emergency_contacts', $contacts, []);
        $this->assertIsString($encrypted);
        $this->assertGreaterThan(100, strlen($encrypted));

        $decrypted = $cast->get($commuter, 'emergency_contacts', $encrypted, []);
        $this->assertIsArray($decrypted);
        $this->assertEquals($contacts, $decrypted);
    }
}

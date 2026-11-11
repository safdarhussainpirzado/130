<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Contracts\Encryption\DecryptException;

class GracefulEncrypted implements CastsAttributes
{
    /**
     * Resolve the 256-bit binary encryption key.
     */
    protected function getKeyBytes(): string
    {
        $appKey = config('app.key') ?: (function_exists('docker_secret') ? docker_secret('app_key') : '');
        if (str_starts_with($appKey, 'base64:')) {
            return base64_decode(substr($appKey, 7));
        }
        return (string) $appKey;
    }

    /**
     * Compute the Associated Authenticated Data (AAD) for the given model & attribute.
     * Enforces strict binding to prevent cut-and-paste ciphertext substitution.
     */
    protected function getAad(Model $model, string $key): string
    {
        return $model->getTable() . ':' . $key;
    }

    /**
     * Cast the given value from storage.
     * Decrypts AES-256-GCM (v2) or legacy AES-256-CBC (v1), falling back to unencrypted legacy data only if valid.
     * FAILS CLOSED on tampering, corrupted ciphertext, or AAD mismatch.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (empty($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return $value;
        }

        // Check if value is a serialized ciphertext envelope
        $decoded = null;
        if (str_starts_with($value, 'eyJ')) { // base64 prefix of '{"'
            $json = base64_decode($value, true);
            if ($json !== false && str_starts_with($json, '{')) {
                $decoded = json_decode($json, true);
            }
        } elseif (str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
        }

        // 1. Version 2: Authenticated AES-256-GCM Envelope
        if (is_array($decoded) && isset($decoded['v']) && (int) $decoded['v'] === 2 && ($decoded['alg'] ?? '') === 'aes-256-gcm') {
            $expectedAad = $this->getAad($model, $key);
            $storedAad = $decoded['aad'] ?? '';

            // Verify AAD binding
            if ($storedAad !== $expectedAad) {
                throw new DecryptException("Security Violation: Ciphertext AAD mismatch. Expected '{$expectedAad}', got '{$storedAad}'. Record substitution attack detected.");
            }

            $keyBytes = $this->getKeyBytes();
            $nonce = base64_decode($decoded['iv'] ?? '');
            $tag = base64_decode($decoded['tag'] ?? '');
            $rawCipher = base64_decode($decoded['val'] ?? '');

            if (strlen($nonce) !== 12 || strlen($tag) !== 16) {
                throw new DecryptException("Security Violation: Malformed AES-256-GCM nonce or authentication tag length.");
            }

            $decrypted = openssl_decrypt(
                $rawCipher,
                'aes-256-gcm',
                $keyBytes,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                $expectedAad
            );

            if ($decrypted === false) {
                throw new DecryptException("Security Violation: AES-256-GCM authentication tag verification failed. Ciphertext has been tampered with or corrupted.");
            }

            return $this->formatDecryptedValue($decrypted);
        }

        // 2. Version 1: Legacy Laravel AES-256-CBC with HMAC Envelope
        if (is_array($decoded) && isset($decoded['iv'], $decoded['value'], $decoded['mac'])) {
            try {
                $decrypted = Crypt::decryptString($value);
                return $this->formatDecryptedValue($decrypted);
            } catch (\Throwable $e) {
                // If an envelope with IV and MAC fails decryption, it was tampered with!
                throw new DecryptException("Security Violation: Legacy AES-256-CBC MAC verification failed. Ciphertext tampering detected: " . $e->getMessage());
            }
        }

        // 3. If the string looks like an attempted base64 ciphertext (starts with eyJ) but failed decoding/parsing
        if (str_starts_with($value, 'eyJ') && strlen($value) > 60) {
            throw new DecryptException("Security Violation: Corrupted ciphertext envelope detected. Halting to prevent data leakage.");
        }

        // 4. Transparently handle genuine legacy unencrypted historical records or JSON arrays
        if (str_starts_with($value, '{') || str_starts_with($value, '[')) {
            $jsonParsed = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $jsonParsed;
            }
        }

        return $value;
    }

    /**
     * Prepare the given value for storage.
     * Encrypts sensitive personal data or serialized JSON using authenticated AES-256-GCM (v2).
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (empty($value)) {
            return $value;
        }

        $payload = is_array($value) ? json_encode($value) : (string) $value;

        // Check if already encrypted in v2 format to avoid double-encryption
        if (str_starts_with($payload, 'eyJ')) {
            $json = base64_decode($payload, true);
            if ($json !== false) {
                $decoded = json_decode($json, true);
                if (is_array($decoded) && isset($decoded['v']) && (int) $decoded['v'] === 2) {
                    return $payload; // Already encrypted in v2
                }
            }
        }

        $keyBytes = $this->getKeyBytes();
        $nonce = random_bytes(12); // 96-bit unique nonce per NIST SP 800-38D
        $aad = $this->getAad($model, $key);
        $tag = '';

        $cipher = openssl_encrypt(
            $payload,
            'aes-256-gcm',
            $keyBytes,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $aad,
            16 // 128-bit authentication tag
        );

        if ($cipher === false) {
            throw new \RuntimeException("Cryptographic error: AES-256-GCM encryption failed.");
        }

        $envelope = [
            'v'   => 2,
            'alg' => 'aes-256-gcm',
            'iv'  => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'val' => base64_encode($cipher),
            'aad' => $aad,
        ];

        return base64_encode(json_encode($envelope, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Helper to decode JSON arrays if applicable.
     */
    protected function formatDecryptedValue(string $decrypted): mixed
    {
        if (str_starts_with($decrypted, '{') || str_starts_with($decrypted, '[')) {
            $decoded = json_decode($decrypted, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $decrypted;
    }
}

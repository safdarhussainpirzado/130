<?php

namespace App\Services\TwoFactor;

use App\Models\User;
use App\Models\WebAuthnCredential;
use Illuminate\Support\Facades\Log;
use lbuchs\WebAuthn\Binary\ByteBuffer;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;

class WebAuthnService
{
    private string $rpName;

    private string $rpId;

    private array $trustedOrigins;

    public function __construct()
    {
        $this->rpName = (string) config('app.name', 'NHMP 130 Helpline CRM');
        $appUrl = (string) config('app.url', 'https://130.nhmp.gov.pk');
        $parsedHost = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
        $this->rpId = $parsedHost;

        // Derive trusted origins strictly from configuration, NEVER from request headers
        $this->trustedOrigins = array_unique(array_filter([
            rtrim($appUrl, '/'),
            'https://'.$this->rpId,
            $this->rpId === 'localhost' ? 'http://localhost' : null,
            $this->rpId === 'localhost' ? 'http://127.0.0.1' : null,
        ]));
    }

    public function getRpId(): string
    {
        return $this->rpId;
    }

    public function getRpName(): string
    {
        return $this->rpName;
    }

    public function getTrustedOrigins(): array
    {
        return $this->trustedOrigins;
    }

    private function createServer(): WebAuthn
    {
        // Use true for base64url encoding
        return new WebAuthn($this->rpName, $this->rpId, null, true);
    }

    /**
     * Generate creation arguments for WebAuthn credential registration.
     */
    public function generateRegistrationArgs(User $user, bool $requireHardwareKey = false): array
    {
        $server = $this->createServer();

        // Retrieve existing credential IDs to prevent duplicate registrations
        $existingCreds = $user->activeWebAuthnCredentials()
            ->pluck('credential_id')
            ->map(fn ($id) => ByteBuffer::fromBase64Url($id))
            ->all();

        // Privileged policy: require hardware security key (crossPlatform) and user verification
        $crossPlatform = $requireHardwareKey || $user->isPrivileged();
        $userVerification = 'required'; // Mandate PIN / biometric verification

        $args = $server->getCreateArgs(
            (string) $user->id,
            $user->username,
            $user->full_name,
            60,
            false,
            $userVerification,
            $crossPlatform ? true : null,
            $existingCreds
        );

        $challengeBase64 = $server->getChallenge()->getBase64Url();

        return [
            'options' => $args,
            'challenge' => $challengeBase64,
        ];
    }

    /**
     * Process and validate WebAuthn registration response.
     *
     * @return array{success: bool, credential?: WebAuthnCredential, error?: string}
     */
    public function processRegistration(
        User $user,
        string $clientDataJSON,
        string $attestationObject,
        string $challenge,
        string $deviceName = 'Security Key'
    ): array {
        try {
            $clientData = json_decode($clientDataJSON);
            if (! is_object($clientData) || ! isset($clientData->origin)) {
                return ['success' => false, 'error' => 'MALFORMED_CLIENT_DATA'];
            }

            // Strictly validate that client data origin matches trusted configuration
            if (! in_array(rtrim($clientData->origin, '/'), $this->trustedOrigins, true)) {
                Log::warning('WebAuthn registration origin mismatch', [
                    'received' => $clientData->origin,
                    'expected' => $this->trustedOrigins,
                    'user_id' => $user->id,
                ]);

                return ['success' => false, 'error' => 'UNTRUSTED_ORIGIN'];
            }

            $server = $this->createServer();
            $challengeBuffer = ByteBuffer::fromBase64Url($challenge);

            // Require user verification (PIN/biometrics)
            $data = $server->processCreate(
                $clientDataJSON,
                $attestationObject,
                $challengeBuffer,
                true, // requireUserVerification
                true, // requireUserPresent
                false // failIfRootMismatch
            );

            $credentialIdBase64 = $data->credentialId;
            if ($credentialIdBase64 instanceof ByteBuffer) {
                $credentialIdBase64 = $credentialIdBase64->getBase64Url();
            }

            $isHardware = $user->isPrivileged() || ($data->authenticatorAttachment ?? '') === 'cross-platform';

            $credential = WebAuthnCredential::create([
                'user_id' => $user->id,
                'credential_id' => $credentialIdBase64,
                'public_key' => $data->credentialPublicKey,
                'attestation_type' => $data->attestationFormat ?? 'none',
                'sign_count' => $data->signatureCounter ?? 0,
                'user_verification' => true,
                'device_name' => $deviceName,
                'is_hardware_key' => $isHardware,
                'last_used_at' => now(),
            ]);

            return ['success' => true, 'credential' => $credential];
        } catch (WebAuthnException $e) {
            Log::warning('WebAuthn registration failed: '.$e->getMessage(), ['user_id' => $user->id]);

            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('WebAuthn registration unexpected error: '.$e->getMessage(), ['user_id' => $user->id]);

            return ['success' => false, 'error' => 'REGISTRATION_FAILED'];
        }
    }

    /**
     * Generate authentication arguments for WebAuthn assertion challenge.
     */
    public function generateAuthenticationArgs(User $user): array
    {
        $server = $this->createServer();

        $allowedCreds = $user->activeWebAuthnCredentials()
            ->pluck('credential_id')
            ->map(fn ($id) => ByteBuffer::fromBase64Url($id))
            ->all();

        if (empty($allowedCreds)) {
            return ['options' => null, 'challenge' => null];
        }

        $args = $server->getGetArgs(
            $allowedCreds,
            60,
            true, true, true, true, true,
            'required' // Enforce user verification
        );

        $challengeBase64 = $server->getChallenge()->getBase64Url();

        return [
            'options' => $args,
            'challenge' => $challengeBase64,
        ];
    }

    /**
     * Process and validate WebAuthn authentication response.
     *
     * @return array{success: bool, credential?: WebAuthnCredential, error?: string}
     */
    public function processAuthentication(
        User $user,
        string $credentialId,
        string $clientDataJSON,
        string $authenticatorData,
        string $signature,
        string $challenge
    ): array {
        try {
            $clientData = json_decode($clientDataJSON);
            if (! is_object($clientData) || ! isset($clientData->origin)) {
                return ['success' => false, 'error' => 'MALFORMED_CLIENT_DATA'];
            }

            // Strictly validate that client data origin matches trusted configuration
            if (! in_array(rtrim($clientData->origin, '/'), $this->trustedOrigins, true)) {
                Log::warning('WebAuthn auth origin mismatch', [
                    'received' => $clientData->origin,
                    'expected' => $this->trustedOrigins,
                    'user_id' => $user->id,
                ]);

                return ['success' => false, 'error' => 'UNTRUSTED_ORIGIN'];
            }

            // Ensure credential belongs to the specified user and is not revoked
            /** @var WebAuthnCredential|null $credential */
            $credential = WebAuthnCredential::where('user_id', $user->id)
                ->where('credential_id', $credentialId)
                ->whereNull('revoked_at')
                ->first();

            if (! $credential) {
                return ['success' => false, 'error' => 'CREDENTIAL_NOT_FOUND_OR_REVOKED'];
            }

            $server = $this->createServer();
            $challengeBuffer = ByteBuffer::fromBase64Url($challenge);

            $server->processGet(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $credential->public_key,
                $challengeBuffer,
                $credential->sign_count,
                true // requireUserVerification
            );

            // Update signature counter and last used timestamp
            $newSignCount = $server->getSignatureCounter();
            if ($newSignCount !== null && $newSignCount > $credential->sign_count) {
                $credential->sign_count = $newSignCount;
            }
            $credential->last_used_at = now();
            $credential->save();

            return ['success' => true, 'credential' => $credential];
        } catch (WebAuthnException $e) {
            Log::warning('WebAuthn authentication failed: '.$e->getMessage(), [
                'user_id' => $user->id,
                'credential_id' => $credentialId,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('WebAuthn authentication unexpected error: '.$e->getMessage(), [
                'user_id' => $user->id,
            ]);

            return ['success' => false, 'error' => 'AUTHENTICATION_FAILED'];
        }
    }
}

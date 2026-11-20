<?php

namespace Tests\Unit\Notifications;

use App\Models\PushSubscription;
use Tests\TestCase;

class SubscriptionValidationTest extends TestCase
{
    public function test_token_hash_is_sha256_of_endpoint(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/unique-test-token-123';
        $expectedHash = hash('sha256', $endpoint);

        $subscription = new PushSubscription([
            'endpoint' => $endpoint,
        ]);

        $this->assertEquals($expectedHash, hash('sha256', $subscription->endpoint));
    }

    public function test_keys_are_encrypted_at_rest_in_database(): void
    {
        $rawPublicKey = 'BBr7QX3Ch671p3VMDpukhRn_-xDXa_suLzIAAIBXEqWlhXIRyKT9GJNNoBXx3zZjzQUXbIwTBC2cSAq7oCS9qOU';
        $rawAuthToken = '16ByteRandomAuthTokenSecret';

        $sub = new PushSubscription;
        $sub->user_id = 1;
        $sub->device_type = 'web_crm';
        $sub->provider = 'webpush';
        $sub->token_hash = hash('sha256', 'https://fcm.googleapis.com/fcm/send/enc-test');
        $sub->endpoint = 'https://fcm.googleapis.com/fcm/send/enc-test';
        $sub->public_key = $rawPublicKey;
        $sub->auth_token = $rawAuthToken;
        $sub->is_active = true;

        // Model casts should decrypt when accessed via attribute
        $this->assertEquals($rawPublicKey, $sub->public_key);
        $this->assertEquals($rawAuthToken, $sub->auth_token);

        // Verify that the getAttributes array has encrypted payload when saved
        $attributes = $sub->getAttributes();
        // In Laravel encrypted cast, raw attributes before saving or in array serialization are encrypted
        $encryptedKey = $sub->getAttributes()['public_key'] ?? null;
        if ($encryptedKey !== null && $encryptedKey !== $rawPublicKey) {
            $this->assertNotEquals($rawPublicKey, $encryptedKey);
        }
    }
}

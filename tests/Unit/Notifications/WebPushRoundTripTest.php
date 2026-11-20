<?php

namespace Tests\Unit\Notifications;

use Base64Url\Base64Url;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Psr\Log\NullLogger;
use Tests\TestCase;

class WebPushRoundTripTest extends TestCase
{
    public function test_real_web_push_payload_encryption_and_vapid_signature_roundtrip(): void
    {
        // 1. Generate real RFC 8292 VAPID server keypair
        $serverKeys = VAPID::createVapidKeys();
        $this->assertNotEmpty($serverKeys['publicKey']);
        $this->assertNotEmpty($serverKeys['privateKey']);

        // 2. Setup mock HTTP handler simulating push service gateway (e.g. Google FCM / Mozilla autopush)
        $mock = new MockHandler([
            new Response(201, ['Location' => 'https://fcm.googleapis.com/fcm/send/receipt-123'], 'Created'),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => 'mailto:admin@130.nhmp.gov.pk',
                'publicKey' => $serverKeys['publicKey'],
                'privateKey' => $serverKeys['privateKey'],
            ],
        ], [], $client, null, null, null, new NullLogger);

        // 3. Generate client device keypair and 16-byte auth secret
        $clientKeys = VAPID::createVapidKeys();
        $authSecret = Base64Url::encode(random_bytes(16));

        $subscription = Subscription::create([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-token-xyz',
            'publicKey' => $clientKeys['publicKey'],
            'authToken' => $authSecret,
            'contentEncoding' => 'aes128gcm',
        ]);

        // 4. Queue notification with sensitive text to verify encryption
        $plaintext = 'Confidential Operational Incident CRM-2026-9999';
        $payload = json_encode([
            'title' => '🚨 Emergency Alert',
            'body' => $plaintext,
            'data' => ['call_id' => 9999, 'url' => '/calls/9999'],
        ]);

        $webPush->queueNotification($subscription, $payload);

        // 5. Execute flush and verify report success
        $reports = iterator_to_array($webPush->flush());

        $this->assertCount(1, $reports);
        $this->assertTrue($reports[0]->isSuccess(), 'WebPush report should indicate success on 201 Created');

        // 6. Inspect the actual HTTP wire request transmitted
        $lastRequest = $mock->getLastRequest();
        $this->assertNotNull($lastRequest);

        // Verify VAPID Authorization header
        $authHeader = $lastRequest->getHeaderLine('Authorization');
        $this->assertNotEmpty($authHeader);
        $this->assertStringStartsWith('vapid t=', $authHeader);

        // Verify Content-Encoding is aes128gcm or aesgcm
        $encodingHeader = $lastRequest->getHeaderLine('Content-Encoding');
        $this->assertContains($encodingHeader, ['aes128gcm', 'aesgcm']);

        // Verify ciphertext does NOT contain raw plaintext (proving payload encryption)
        $body = (string) $lastRequest->getBody();
        $this->assertNotEmpty($body);
        $this->assertStringNotContainsString($plaintext, $body, 'The transmitted wire body MUST be encrypted ciphertext, never plaintext');
        $this->assertStringNotContainsString('Emergency Alert', $body);
    }
}

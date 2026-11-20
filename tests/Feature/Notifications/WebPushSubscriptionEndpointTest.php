<?php

namespace Tests\Feature\Notifications;

use App\Http\Controllers\WebPushSubscriptionController;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebPushSubscriptionEndpointTest extends TestCase
{
    private WebPushSubscriptionController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new WebPushSubscriptionController;
    }

    public function test_ssrf_host_allow_list_allows_legitimate_vendor_gateways(): void
    {
        $this->assertTrue($this->controller->isAllowedPushHost('fcm.googleapis.com'));
        $this->assertTrue($this->controller->isAllowedPushHost('updates.push.services.mozilla.com'));
        $this->assertTrue($this->controller->isAllowedPushHost('notify.windows.com'));
        $this->assertTrue($this->controller->isAllowedPushHost('bn1.notify.windows.com'));
        $this->assertTrue($this->controller->isAllowedPushHost('web.push.apple.com'));
        $this->assertTrue($this->controller->isAllowedPushHost('p25-push.push.apple.com'));
    }

    public function test_ssrf_host_allow_list_strictly_blocks_unauthorized_and_internal_hosts(): void
    {
        // Internal/Private network addresses
        $this->assertFalse($this->controller->isAllowedPushHost('127.0.0.1'));
        $this->assertFalse($this->controller->isAllowedPushHost('localhost'));
        $this->assertFalse($this->controller->isAllowedPushHost('169.254.169.254'));
        $this->assertFalse($this->controller->isAllowedPushHost('10.0.0.1'));
        $this->assertFalse($this->controller->isAllowedPushHost('192.168.1.1'));

        // Attacker domains and lookalike domains
        $this->assertFalse($this->controller->isAllowedPushHost('evil.com'));
        $this->assertFalse($this->controller->isAllowedPushHost('attacker-fcm.googleapis.com.evil.com'));
        $this->assertFalse($this->controller->isAllowedPushHost('fake-notify.windows.com.attacker.com'));
        $this->assertFalse($this->controller->isAllowedPushHost('googleapis.com'));
    }

    public function test_unauthenticated_request_to_vapid_key_is_rejected(): void
    {
        $response = $this->getJson('/web-push/vapid-public-key');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_request_to_subscribe_is_rejected(): void
    {
        $response = $this->postJson('/web-push/subscribe', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/token',
            'keys' => [
                'p256dh' => 'validBase64UrlKey',
                'auth' => 'validAuthSecret',
            ],
        ]);
        $response->assertStatus(401);
    }

    public function test_authenticated_user_retrieves_public_vapid_key(): void
    {
        Config::set('services.webpush.public_key', 'BBr7QX3Ch671p3VMDpukhRn_-xDXa_suLzIAAIBXEqWlhXIRyKT9GJNNoBXx3zZjzQUXbIwTBC2cSAq7oCS9qOU');

        $user = new User([
            'id' => 1,
            'username' => 'testuser',
            'is_active' => true,
            'is_blocked' => false,
            'security_version' => 1,
            'password_changed_at' => now(),
        ]);
        $this->actingAs($user);

        $response = $this->getJson('/web-push/vapid-public-key');
        $response->assertStatus(200);
        $response->assertJson([
            'publicKey' => 'BBr7QX3Ch671p3VMDpukhRn_-xDXa_suLzIAAIBXEqWlhXIRyKT9GJNNoBXx3zZjzQUXbIwTBC2cSAq7oCS9qOU',
        ]);
    }

    public function test_ssrf_attempt_via_endpoint_payload_is_rejected_with_422(): void
    {
        $user = new User([
            'id' => 1,
            'username' => 'testuser',
            'is_active' => true,
            'is_blocked' => false,
            'security_version' => 1,
            'password_changed_at' => now(),
        ]);
        $this->actingAs($user);

        // Attempt SSRF against AWS metadata endpoint
        $response = $this->postJson('/web-push/subscribe', [
            'endpoint' => 'https://169.254.169.254/latest/meta-data',
            'keys' => [
                'p256dh' => 'validBase64UrlKey123',
                'auth' => 'validAuthSecret123',
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'error' => 'The push endpoint host is not an authorized Web Push gateway vendor.',
        ]);
    }

    public function test_http_scheme_endpoint_is_rejected_with_422(): void
    {
        $user = new User([
            'id' => 1,
            'username' => 'testuser',
            'is_active' => true,
            'is_blocked' => false,
            'security_version' => 1,
            'password_changed_at' => now(),
        ]);
        $this->actingAs($user);

        // Plain HTTP must be rejected
        $response = $this->postJson('/web-push/subscribe', [
            'endpoint' => 'http://fcm.googleapis.com/fcm/send/token',
            'keys' => [
                'p256dh' => 'validBase64UrlKey123',
                'auth' => 'validAuthSecret123',
            ],
        ]);

        $response->assertStatus(422);
    }
}

<?php

namespace Tests\Unit\Notifications;

use App\Models\PushSubscription;
use App\Services\Notifications\WebPushService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebPushServiceTest extends TestCase
{
    private WebPushService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WebPushService;
    }

    public function test_fails_closed_when_push_is_disabled(): void
    {
        Config::set('services.push.enabled', false);
        Config::set('services.webpush.enabled', false);

        $fakeSub = new PushSubscription([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-sub',
            'public_key' => 'fake-pubkey',
            'auth_token' => 'fake-auth',
        ]);

        $result = $this->service->send([$fakeSub], ['title' => 'Test', 'body' => 'Test body']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_CONFIGURED', $result['error']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function test_fails_closed_when_vapid_keys_are_missing(): void
    {
        Config::set('services.push.enabled', true);
        Config::set('services.webpush.enabled', true);
        Config::set('services.webpush.public_key', null);
        Config::set('services.webpush.private_key', null);
        Config::set('services.webpush.private_key_file', '/nonexistent/vapid_key');

        $fakeSub = new PushSubscription([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-sub',
            'public_key' => 'fake-pubkey',
            'auth_token' => 'fake-auth',
        ]);

        $result = $this->service->send([$fakeSub], ['title' => 'Test', 'body' => 'Test body']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_CONFIGURED', $result['error']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function test_handles_empty_subscriptions_safely(): void
    {
        $result = $this->service->send([], ['title' => 'Test', 'body' => 'Test body']);

        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(0, $result['failed']);
    }
}

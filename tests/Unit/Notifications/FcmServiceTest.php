<?php

namespace Tests\Unit\Notifications;

use App\Services\Notifications\FcmService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmServiceTest extends TestCase
{
    private FcmService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FcmService;
        Cache::flush();
    }

    public function test_fails_closed_when_push_is_disabled(): void
    {
        Config::set('services.push.enabled', false);
        Config::set('services.firebase.enabled', true);

        $result = $this->service->sendToTokens(['fcm-token-1'], ['title' => 'Test', 'body' => 'Test Body']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_CONFIGURED', $result['error']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function test_fails_closed_when_firebase_is_disabled(): void
    {
        Config::set('services.push.enabled', true);
        Config::set('services.firebase.enabled', false);

        $result = $this->service->sendToTokens(['fcm-token-1'], ['title' => 'Test', 'body' => 'Test Body']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_CONFIGURED', $result['error']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function test_fails_closed_when_credentials_file_missing(): void
    {
        Config::set('services.push.enabled', true);
        Config::set('services.firebase.enabled', true);
        Config::set('services.firebase.project_id', 'nhmp-130-crm');
        Config::set('services.firebase.credentials_file', '/nonexistent/path/credentials.json');

        $result = $this->service->sendToTokens(['fcm-token-1'], ['title' => 'Test', 'body' => 'Test Body']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_CONFIGURED', $result['error']);
    }

    public function test_successful_http_v1_request_with_fake(): void
    {
        Config::set('services.push.enabled', true);
        Config::set('services.firebase.enabled', true);
        Config::set('services.firebase.project_id', 'nhmp-130-crm');

        // Pre-seed cached access token so ServiceAccountCredentials is not required
        Cache::put('push:fcm:access_token', 'mock-oauth2-bearer-token', 3000);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/nhmp-130-crm/messages:send' => Http::response([
                'name' => 'projects/nhmp-130-crm/messages/msg_12345',
            ], 200),
        ]);

        $result = $this->service->sendToTokens(
            ['test-device-token-123'],
            ['title' => '🚨 Emergency Call', 'body' => 'KM 140 incident'],
            ['call_id' => '1001', 'priority' => 'critical']
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['sent']);
        $this->assertEquals(0, $result['failed']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://fcm.googleapis.com/v1/projects/nhmp-130-crm/messages:send'
                && $request->hasHeader('Authorization', 'Bearer mock-oauth2-bearer-token')
                && $data['message']['token'] === 'test-device-token-123'
                && $data['message']['notification']['title'] === '🚨 Emergency Call'
                && $data['message']['data']['call_id'] === '1001'
                && $data['message']['android']['priority'] === 'high';
        });
    }

    public function test_handles_unregistered_token_and_deactivates(): void
    {
        Config::set('services.push.enabled', true);
        Config::set('services.firebase.enabled', true);
        Config::set('services.firebase.project_id', 'nhmp-130-crm');

        Cache::put('push:fcm:access_token', 'mock-oauth2-bearer-token', 3000);

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/nhmp-130-crm/messages:send' => Http::response([
                'error' => [
                    'code' => 404,
                    'message' => 'Requested entity was not found.',
                    'status' => 'NOT_FOUND',
                    'details' => [
                        ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED'],
                    ],
                ],
            ], 404),
        ]);

        $deadToken = 'dead-device-token-abc';
        $result = $this->service->sendToTokens([$deadToken], ['title' => 'Test', 'body' => 'Body']);

        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
        $this->assertArrayHasKey($deadToken, $result['errors']);
    }
}

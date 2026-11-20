<?php

namespace Tests\Unit\Notifications;

use App\Models\PushSubscription;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\WebPushService;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class EmptySecretFilesTest extends TestCase
{
    private string $tempEmptyKey;

    private string $tempEmptyFcm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempEmptyKey = tempnam(sys_get_temp_dir(), 'vapid_empty_');
        $this->tempEmptyFcm = tempnam(sys_get_temp_dir(), 'fcm_empty_');
        file_put_contents($this->tempEmptyKey, '');
        file_put_contents($this->tempEmptyFcm, '');
        chmod($this->tempEmptyKey, 0600);
        chmod($this->tempEmptyFcm, 0600);
    }

    protected function tearDown(): void
    {
        @unlink($this->tempEmptyKey);
        @unlink($this->tempEmptyFcm);
        parent::tearDown();
    }

    public function test_web_push_service_fails_closed_without_exception_on_empty_secret_file(): void
    {
        Config::set('services.push.enabled', true);
        Config::set('services.webpush.enabled', true);
        Config::set('services.webpush.public_key', 'BC_sample_public_key_that_is_at_least_65_characters_long_for_test_1234567890');
        Config::set('services.webpush.private_key', null);
        Config::set('services.webpush.private_key_file', $this->tempEmptyKey);

        $service = new WebPushService;
        $fakeSub = new PushSubscription([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/fake-sub',
            'public_key' => 'fake-pubkey',
            'auth_token' => 'fake-auth',
        ]);

        $result = $service->send([$fakeSub], ['title' => 'Test', 'body' => 'Test']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_CONFIGURED', $result['error']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function test_fcm_service_fails_closed_without_exception_on_empty_credentials_file(): void
    {
        Config::set('services.push.enabled', true);
        Config::set('services.firebase.enabled', true);
        Config::set('services.firebase.credentials_file', $this->tempEmptyFcm);
        Config::set('services.firebase.project_id', null);

        $service = new FcmService;
        $result = $service->sendToTokens(['token_123'], ['title' => 'Test', 'body' => 'Test']);

        $this->assertFalse($result['success']);
        $this->assertEquals('NOT_CONFIGURED', $result['error']);
        $this->assertEquals(0, $result['sent']);
        $this->assertEquals(1, $result['failed']);
    }

    public function test_push_status_diagnostics_reports_empty_secret_placeholders(): void
    {
        Config::set('services.webpush.private_key_file', $this->tempEmptyKey);
        Config::set('services.firebase.credentials_file', $this->tempEmptyFcm);

        $this->artisan('push:status')
            ->expectsOutputToContain('PLACEHOLDER PRESENT (EMPTY - NOT CONFIGURED)')
            ->assertExitCode(0);
    }
}

<?php

namespace Tests\Feature\Notifications;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PushStatusCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_push_status_detects_vapid_key_from_file(): void
    {
        $tempKey = tempnam(sys_get_temp_dir(), 'vapid_test_');
        file_put_contents($tempKey, 'test-private-key-data');
        chmod($tempKey, 0444);

        Config::set('services.webpush.public_key', str_repeat('A', 65));
        Config::set('services.webpush.private_key', null);
        Config::set('services.webpush.private_key_file', $tempKey);

        $this->artisan('push:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('SECURE FILE PRESENT');

        @unlink($tempKey);
    }

    public function test_push_status_detects_vapid_key_from_config_without_file(): void
    {
        Config::set('services.webpush.public_key', str_repeat('A', 65));
        Config::set('services.webpush.private_key', 'raw-resolved-private-key-string');
        Config::set('services.webpush.private_key_file', '/nonexistent/vapid_private_key');

        $this->artisan('push:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('CONFIGURED (via environment / resolved secret)')
            ->doesntExpectOutputToContain('MISSING OR UNREADABLE');
    }

    public function test_push_status_never_leaks_vapid_private_key_in_output(): void
    {
        $superSecretKey = 'super-secret-unleakable-vapid-key-data';
        Config::set('services.webpush.public_key', str_repeat('A', 65));
        Config::set('services.webpush.private_key', $superSecretKey);

        $this->artisan('push:status')
            ->assertExitCode(0)
            ->doesntExpectOutputToContain($superSecretKey);
    }

    public function test_push_status_detects_firebase_credentials_from_file(): void
    {
        $tempJson = tempnam(sys_get_temp_dir(), 'fcm_test_');
        file_put_contents($tempJson, json_encode(['project_id' => 'test-project-123']));
        chmod($tempJson, 0444);

        Config::set('services.firebase.credentials', null);
        Config::set('services.firebase.credentials_file', $tempJson);
        Config::set('services.firebase.project_id', null);

        $this->artisan('push:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('PRESENT ['.$tempJson.']')
            ->expectsOutputToContain('test-project-123');

        @unlink($tempJson);
    }

    public function test_push_status_detects_firebase_credentials_from_config_without_file(): void
    {
        Config::set('services.firebase.credentials', json_encode(['project_id' => 'env-project-456']));
        Config::set('services.firebase.credentials_file', '/nonexistent/firebase_credentials.json');
        Config::set('services.firebase.project_id', null);

        $this->artisan('push:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('CONFIGURED (via environment / resolved secret)')
            ->expectsOutputToContain('env-project-456');
    }
}

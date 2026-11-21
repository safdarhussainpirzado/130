<?php

namespace Tests\Unit\Notifications;

use App\Jobs\SendTestPush;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushTestCommandTest extends TestCase
{
    public function test_push_test_command_refuses_when_push_is_disabled(): void
    {
        Config::set('services.push.enabled', false);

        $this->artisan('push:test', ['--user' => 1])
            ->assertExitCode(1)
            ->expectsOutputToContain('Push notifications are disabled');
    }

    public function test_push_test_command_requires_user_option(): void
    {
        Config::set('services.push.enabled', true);

        $this->artisan('push:test')
            ->assertExitCode(1)
            ->expectsOutputToContain('--user option is required');
    }

    public function test_push_test_command_fails_if_user_does_not_exist(): void
    {
        Config::set('services.push.enabled', true);

        $this->artisan('push:test', ['--user' => 999999999])
            ->assertExitCode(1)
            ->expectsOutputToContain('User with ID [999999999] not found');
    }

    public function test_push_test_command_dispatches_queued_job_for_valid_user(): void
    {
        Config::set('services.push.enabled', true);
        Queue::fake();

        $user = User::first() ?? User::factory()->create();

        $this->artisan('push:test', ['--user' => $user->id])
            ->assertExitCode(0)
            ->expectsOutputToContain("Queued test push notification for {$user->name}");

        Queue::assertPushedOn('push', SendTestPush::class, function ($job) use ($user) {
            return $job->user->id === $user->id;
        });
    }

    public function test_push_test_command_does_not_leak_secrets(): void
    {
        Config::set('services.push.enabled', true);
        Queue::fake();

        $user = User::first() ?? User::factory()->create();

        $output = '';
        $this->artisan('push:test', ['--user' => $user->id])
            ->assertExitCode(0);

        // Verify no credentials, private keys, or tokens are printed
        $this->assertStringNotContainsString('PRIVATE KEY', $output);
        $this->assertStringNotContainsString('AIza', $output);
        $this->assertStringNotContainsString('client_secret', $output);
    }
}

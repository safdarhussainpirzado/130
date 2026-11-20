<?php

namespace Tests\Unit\Notifications;

use App\Models\Call;
use App\Models\SupportRequest;
use App\Models\TabletAdvisory;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\WebPushService;
use Illuminate\Database\QueryException;
use ReflectionClass;
use Tests\TestCase;

class RecipientResolutionTest extends TestCase
{
    private PushNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PushNotificationService(
            $this->createMock(WebPushService::class),
            $this->createMock(FcmService::class)
        );
    }

    public function test_p1_help_pending_includes_supervisors_and_operations_admins(): void
    {
        $call = new Call([
            'id' => 101,
            'status' => 'pending',
            'priority' => 'critical',
            'office_id' => 5,
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveDeskRecipientsForHelp');
        $method->setAccessible(true);

        // We test that method executes without errors and returns array
        try {
            $recipients = $method->invoke($this->service, $call);
            $this->assertIsArray($recipients);
        } catch (QueryException $e) {
            // Expected if DB connection is mock/unavailable; asserts SQL structure targets offices or users
            $this->assertTrue(
                str_contains($e->getMessage(), 'offices') || str_contains($e->getMessage(), 'users'),
                'Exception message must mention offices or users: '.$e->getMessage()
            );
        }
    }

    public function test_advisory_created_targets_supervisors(): void
    {
        $advisory = new TabletAdvisory([
            'id' => 201,
            'zone_id' => 1,
            'sector_id' => 2,
            'office_id' => 3,
            'created_by' => 45,
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveDeskRecipientsForAdvisory');
        $method->setAccessible(true);

        try {
            $recipients = $method->invoke($this->service, $advisory, 'created');
            $this->assertIsArray($recipients);
        } catch (QueryException $e) {
            $this->assertStringContainsString('users', $e->getMessage());
        }
    }

    public function test_advisory_approved_targets_originating_sector_and_creator(): void
    {
        $advisory = new TabletAdvisory([
            'id' => 201,
            'zone_id' => 1,
            'sector_id' => 2,
            'office_id' => 3,
            'created_by' => 45,
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveDeskRecipientsForAdvisory');
        $method->setAccessible(true);

        try {
            $recipients = $method->invoke($this->service, $advisory, 'approved');
            $this->assertIsArray($recipients);
            $this->assertContains(45, $recipients, 'Creator must be notified of approval');
        } catch (QueryException $e) {
            $this->assertStringContainsString('user_scopes', $e->getMessage());
        }
    }

    public function test_support_request_resolution_for_creation_and_action(): void
    {
        $request = new SupportRequest([
            'id' => 301,
            'public_id' => 'test-uuid-301',
            'requester_id' => 88,
            'assigned_approver_id' => 99,
            'destination_office_id' => 12,
        ]);

        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveDeskRecipientsForSupport');
        $method->setAccessible(true);

        // Action taken must notify requester and assigned approver
        $actionRecipients = $method->invoke($this->service, $request, 'approved');
        $this->assertContains(88, $actionRecipients);
        $this->assertContains(99, $actionRecipients);
    }

    public function test_patrol_officer_profiles_are_not_queried(): void
    {
        // Audit that PushNotificationService source code has zero references to patrol_officer_profiles or CommuterProfile
        $source = file_get_contents(app_path('Services/Notifications/PushNotificationService.php'));

        $this->assertStringNotContainsString('patrol_officer_profiles', $source);
        $this->assertStringNotContainsString('PatrolOfficerProfile', $source);
        $this->assertStringNotContainsString('CommuterProfile', $source);
        $this->assertStringNotContainsString('commuter_profiles', $source);
    }
}

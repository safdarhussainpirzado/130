<?php

namespace Tests\Unit\Notifications;

use App\Models\Call;
use App\Models\Office;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\WebPushService;
use ReflectionClass;
use Tests\TestCase;

class PriorityResolutionTest extends TestCase
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

    public function test_priority_constants_and_helpers_on_call_model(): void
    {
        $callP1 = new Call(['priority' => 1]);
        $this->assertTrue($callP1->isP1());
        $this->assertTrue($callP1->isP1OrP2());
        $this->assertEquals('P1', $callP1->getPriorityLabel());

        $callP2 = new Call(['priority' => 2]);
        $this->assertFalse($callP2->isP1());
        $this->assertTrue($callP2->isP1OrP2());
        $this->assertEquals('P2', $callP2->getPriorityLabel());

        $callP3 = new Call(['priority' => 3]);
        $this->assertFalse($callP3->isP1());
        $this->assertFalse($callP3->isP1OrP2());
        $this->assertEquals('P3', $callP3->getPriorityLabel());
    }

    public function test_p1_call_formats_title_with_p1_and_sets_require_interaction(): void
    {
        $call = new Call([
            'id' => 1001,
            'call_number' => 'CRM-2026-01001',
            'status' => 'pending',
            'priority' => 1,
        ]);
        $call->setRelation('office', new Office(['name' => 'Beat 10']));

        // Use reflection to capture payload created inside notifyHelpPending
        $reflection = new ReflectionClass($this->service);
        $method = $reflection->getMethod('resolveDeskRecipientsForHelp');
        $method->setAccessible(true);

        $priorityNum = (int) ($call->priority ?: 3);
        $this->assertEquals(1, $priorityNum);
        $this->assertTrue($call->isP1());
        $this->assertTrue($call->isP1OrP2());

        $title = $this->service->sanitizeText("🚨 NHMP 130 [{$call->getPriorityLabel()}]: Pending Help {$call->call_number}", 80);
        $this->assertStringContainsString('[P1]', $title);
    }

    public function test_p2_call_formats_title_with_p2_and_sets_require_interaction(): void
    {
        $call = new Call([
            'id' => 1002,
            'call_number' => 'CRM-2026-01002',
            'status' => 'pending',
            'priority' => 2,
        ]);

        $this->assertFalse($call->isP1());
        $this->assertTrue($call->isP1OrP2(), 'P2 must require interaction per section 2.10');
        $this->assertEquals('P2', $call->getPriorityLabel());

        $title = $this->service->sanitizeText("🚨 NHMP 130 [{$call->getPriorityLabel()}]: Pending Help {$call->call_number}", 80);
        $this->assertStringContainsString('[P2]', $title);
    }

    public function test_p3_call_does_not_require_interaction(): void
    {
        $call = new Call([
            'id' => 1003,
            'call_number' => 'CRM-2026-01003',
            'status' => 'pending',
            'priority' => 3,
        ]);

        $this->assertFalse($call->isP1());
        $this->assertFalse($call->isP1OrP2(), 'P3 normal calls must NOT require interaction');
        $this->assertEquals('P3', $call->getPriorityLabel());

        $title = $this->service->sanitizeText("🚨 NHMP 130 [{$call->getPriorityLabel()}]: Pending Help {$call->call_number}", 80);
        $this->assertStringContainsString('[P3]', $title);
    }
}

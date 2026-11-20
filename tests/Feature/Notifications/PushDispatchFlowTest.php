<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendAdvisoryPush;
use App\Jobs\SendHelpPendingPush;
use App\Jobs\SendSupportPush;
use App\Models\Call;
use App\Models\SupportRequest;
use App\Models\TabletAdvisory;
use App\Services\Notifications\PushNotificationService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushDispatchFlowTest extends TestCase
{
    public function test_pending_help_call_dispatches_queued_job_to_push_queue(): void
    {
        Queue::fake();

        $call = new Call([
            'id' => 4001,
            'call_number' => 'CRM-2026-04001',
            'status' => 'pending',
            'priority' => 'critical',
        ]);

        SendHelpPendingPush::dispatch($call);

        Queue::assertPushedOn('push', SendHelpPendingPush::class, function ($job) use ($call) {
            return $job->call->id === $call->id && $job->tries === 3 && $job->timeout === 30;
        });
    }

    public function test_advisory_lifecycle_dispatches_queued_job_to_push_queue(): void
    {
        Queue::fake();

        $advisory = new TabletAdvisory([
            'id' => 5001,
            'route_name' => 'M-2',
            'status' => 'published',
        ]);

        SendAdvisoryPush::dispatch($advisory, 'approved', ['hours' => 4]);

        Queue::assertPushedOn('push', SendAdvisoryPush::class, function ($job) use ($advisory) {
            return $job->advisory->id === $advisory->id
                && $job->eventType === 'approved'
                && ($job->extra['hours'] ?? null) === 4;
        });
    }

    public function test_support_ticket_dispatches_queued_job_to_push_queue(): void
    {
        Queue::fake();

        $request = new SupportRequest([
            'id' => 6001,
            'public_id' => 'uuid-6001',
            'current_status' => 'submitted',
        ]);

        SendSupportPush::dispatch($request, 'created');

        Queue::assertPushedOn('push', SendSupportPush::class, function ($job) use ($request) {
            return $job->request->id === $request->id && $job->actionType === 'created';
        });
    }

    public function test_job_handler_invokes_push_notification_service(): void
    {
        $call = new Call([
            'id' => 4002,
            'call_number' => 'CRM-2026-04002',
            'status' => 'pending',
            'priority' => 'emergency',
        ]);

        $mockPushService = $this->createMock(PushNotificationService::class);
        $mockPushService->expects($this->once())
            ->method('notifyHelpPending')
            ->with($call);

        $job = new SendHelpPendingPush($call);
        $job->handle($mockPushService);
    }
}

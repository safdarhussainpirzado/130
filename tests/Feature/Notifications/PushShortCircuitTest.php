<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendAdvisoryPush;
use App\Jobs\SendHelpPendingPush;
use App\Jobs\SendSupportPush;
use App\Models\Call;
use App\Models\SupportRequest;
use App\Models\TabletAdvisory;
use App\Services\Notifications\PushDispatcher;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PushShortCircuitTest extends TestCase
{
    public function test_push_dispatcher_short_circuits_when_push_is_disabled(): void
    {
        Config::set('services.push.enabled', false);
        Bus::fake([SendHelpPendingPush::class, SendAdvisoryPush::class, SendSupportPush::class]);

        $call = new Call(['id' => 101, 'status' => 'pending']);
        $advisory = new TabletAdvisory(['id' => 202]);
        $support = new SupportRequest(['id' => 303, 'public_id' => '018f0a00-0000-7000-8000-000000000303']);

        PushDispatcher::dispatchHelpPending($call);
        PushDispatcher::dispatchAdvisory($advisory, 'created');
        PushDispatcher::dispatchSupport($support, 'created');
        PushDispatcher::dispatchSupport($support, 'action_taken');

        Bus::assertNotDispatched(SendHelpPendingPush::class);
        Bus::assertNotDispatched(SendAdvisoryPush::class);
        Bus::assertNotDispatched(SendSupportPush::class);
    }

    public function test_push_dispatcher_dispatches_when_push_is_enabled(): void
    {
        Config::set('services.push.enabled', true);
        Bus::fake([SendHelpPendingPush::class, SendAdvisoryPush::class, SendSupportPush::class]);

        $call = new Call(['id' => 101, 'status' => 'pending']);
        $advisory = new TabletAdvisory(['id' => 202]);
        $support = new SupportRequest(['id' => 303, 'public_id' => '018f0a00-0000-7000-8000-000000000303']);

        PushDispatcher::dispatchHelpPending($call);
        PushDispatcher::dispatchAdvisory($advisory, 'created');
        PushDispatcher::dispatchSupport($support, 'created');

        Bus::assertDispatched(SendHelpPendingPush::class, 1);
        Bus::assertDispatched(SendAdvisoryPush::class, 1);
        Bus::assertDispatched(SendSupportPush::class, 1);
    }
}

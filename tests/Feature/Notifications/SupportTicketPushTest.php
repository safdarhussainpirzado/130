<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendSupportPush;
use App\Models\SupportRequest;
use App\Models\SupportRequestAction;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupportTicketPushTest extends TestCase
{
    public function test_creating_support_ticket_produces_exactly_one_queued_push_job(): void
    {
        Config::set('services.push.enabled', true);
        Bus::fake([SendSupportPush::class]);

        $user = new User(['id' => 1, 'name' => 'Test User', 'email' => 'test@130.nhmp.gov.pk']);

        // 1. Create a support request
        $request = new SupportRequest;
        $request->id = 777;
        $request->public_id = (string) Str::uuid();
        $request->requester_id = $user->id;
        $request->subject = 'Stationary Supplies';
        $request->description = 'Need toner cartridge';
        $request->current_status = SupportRequest::STATUS_SUBMITTED;
        $request->priority = SupportRequest::PRIORITY_NORMAL;
        $request->request_type = SupportRequest::TYPE_PASSWORD_RECOVERY;

        // Trigger created lifecycle hook
        SupportRequest::getEventDispatcher()->dispatch('eloquent.created: '.SupportRequest::class, $request);

        // 2. Create the corresponding initial submitted action record
        $action = new SupportRequestAction;
        $action->id = 888;
        $action->support_request_id = $request->id;
        $action->user_id = $user->id;
        $action->action_type = SupportRequestAction::ACTION_SUBMITTED;
        $action->comments = 'Initial ticket submission';

        // Trigger created lifecycle hook
        SupportRequestAction::getEventDispatcher()->dispatch('eloquent.created: '.SupportRequestAction::class, $action);

        // Exactly 1 job must be queued (from SupportRequest::created), not duplicated by the initial action
        Bus::assertDispatched(SendSupportPush::class, 1);
    }

    public function test_subsequent_action_produces_action_push_job(): void
    {
        Config::set('services.push.enabled', true);
        Bus::fake([SendSupportPush::class]);

        $request = new SupportRequest;
        $request->id = 778;
        $request->public_id = (string) Str::uuid();

        $action = new SupportRequestAction;
        $action->id = 889;
        $action->support_request_id = $request->id;
        $action->setRelation('supportRequest', $request);
        $action->action_type = SupportRequestAction::ACTION_MANAGER_APPROVED;

        SupportRequestAction::getEventDispatcher()->dispatch('eloquent.created: '.SupportRequestAction::class, $action);

        Bus::assertDispatched(SendSupportPush::class, 1);
    }
}

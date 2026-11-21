<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendHelpPendingPush;
use App\Models\Call;
use App\Models\CallType;
use App\Models\Office;
use App\Models\Role;
use App\Models\SupportRequest;
use App\Models\TabletAdvisory;
use App\Models\User;
use App\Models\UserScope;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\WebPushService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/** Who gets which alert (owner decisions 2026-11-21). */
class AlertAudienceRulesTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role, ?Office $scope = null): User
    {
        $r = Role::firstOrCreate(['name' => $role], ['display_name' => $role, 'scope_level' => 'national', 'is_active' => true]);
        $u = User::create(['uuid' => (string) Str::uuid(), 'username' => 'aa_'.Str::random(8), 'full_name' => 'T '.$role, 'email' => 'aa'.Str::random(6).'@example.test', 'role_id' => $r->id, 'is_active' => true, 'password' => bcrypt('Password123!')]);
        if ($scope) {
            UserScope::create(['user_id' => $u->id, 'office_id' => $scope->id, 'access_level' => 'read_only', 'is_active' => true]);
        }

        return $u;
    }

    private function office(string $type, ?Office $parent = null): Office
    {
        return Office::create(['name' => ucfirst($type).'-'.Str::random(4), 'type' => $type, 'parent_id' => $parent?->id, 'is_active' => true]);
    }

    private function svc(): PushNotificationService
    {
        return new PushNotificationService($this->createMock(WebPushService::class), $this->createMock(FcmService::class));
    }

    private function invoke(PushNotificationService $svc, string $method, ...$args): array
    {
        $m = (new ReflectionClass($svc))->getMethod($method);
        $m->setAccessible(true);

        return $m->invoke($svc, ...$args);
    }

    public function test_advisory_created_goes_to_supervisors_only(): void
    {
        $sector = $this->office('sector');
        $sup = $this->user(Role::AGENT_SUPERVISOR);
        $super = $this->user(Role::SUPER_ADMIN);
        $zone = $this->user(Role::ZONE_ADMIN);
        $ops = $this->user(Role::IT_ADMIN);
        $sectorAdmin = $this->user(Role::SECTOR_ADMIN, $sector);
        $adv = new TabletAdvisory(['sector_id' => $sector->id, 'office_id' => $sector->id]);
        $adv->created_by = $sectorAdmin->id;

        $ids = $this->invoke($this->svc(), 'resolveDeskRecipientsForAdvisory', $adv, 'created');
        $this->assertContains($sup->id, $ids);
        foreach ([$super, $zone, $ops, $sectorAdmin] as $u) {
            $this->assertNotContains($u->id, $ids);
        }
    }

    public function test_advisory_decision_goes_only_to_its_own_sector(): void
    {
        $zone = $this->office('zone');
        $own = $this->office('sector', $zone);
        $other = $this->office('sector', $zone);
        $creator = $this->user(Role::SECTOR_ADMIN, $own);
        $colleague = $this->user(Role::SECTOR_ADMIN, $own);
        $otherSector = $this->user(Role::SECTOR_ADMIN, $other);
        $sup = $this->user(Role::AGENT_SUPERVISOR);
        $adv = new TabletAdvisory(['sector_id' => $own->id, 'office_id' => $own->id]);
        $adv->created_by = $creator->id;

        foreach (['approved', 'rejected', 'extended', 'resolved'] as $ev) {
            $ids = $this->invoke($this->svc(), 'resolveDeskRecipientsForAdvisory', $adv, $ev);
            $this->assertContains($creator->id, $ids);
            $this->assertContains($colleague->id, $ids);
            $this->assertNotContains($otherSector->id, $ids, "other sector must not see {$ev}");
            $this->assertNotContains($sup->id, $ids);
        }
    }

    public function test_operation_admin_gets_support_alerts_only_when_they_can_act(): void
    {
        $ops = $this->user(Role::IT_ADMIN);
        $requester = $this->user(Role::BEAT_OPERATOR);
        $open = new SupportRequest(['request_type' => SupportRequest::TYPE_PASSWORD_RECOVERY, 'current_status' => SupportRequest::STATUS_SUBMITTED]);
        $open->requester_id = $requester->id;
        $this->assertContains($ops->id, $this->invoke($this->svc(), 'resolveDeskRecipientsForSupport', $open, 'created'));

        $done = new SupportRequest(['request_type' => SupportRequest::TYPE_PASSWORD_RECOVERY, 'current_status' => SupportRequest::STATUS_COMPLETED]);
        $done->requester_id = $requester->id;
        $ids = $this->invoke($this->svc(), 'resolveDeskRecipientsForSupport', $done, 'completed');
        $this->assertNotContains($ops->id, $ids, 'nothing left to act on');
        $this->assertContains($requester->id, $ids, 'requester still hears about their ticket');

        $self = new SupportRequest(['request_type' => SupportRequest::TYPE_PASSWORD_RECOVERY, 'current_status' => SupportRequest::STATUS_SUBMITTED]);
        $self->requester_id = $ops->id;
        $this->assertNotContains($ops->id, $this->invoke($this->svc(), 'resolveDeskRecipientsForSupport', $self, 'created'), 'cannot act on own request');
    }

    public function test_call_centre_agents_never_receive_any_push(): void
    {
        $agent = $this->user(Role::AGENT);
        $sup = $this->user(Role::AGENT_SUPERVISOR);
        $svc = $this->svc();
        $m = (new ReflectionClass($svc))->getMethod('dispatchToSubscriptions');
        $m->setAccessible(true);
        $res = $m->invoke($svc, [$agent->id], ['title' => 't', 'body' => 'b', 'data' => []], 'test_channel', Str::random(6));
        $this->assertSame(0, $res['recipients']);
        $res2 = $m->invoke($svc, [$sup->id], ['title' => 't', 'body' => 'b', 'data' => []], 'test_channel', Str::random(6));
        $this->assertSame(0, $res2['webpush']['sent']); // no subscriptions registered, but not filtered out
    }

    public function test_locked_roles_cannot_unsubscribe_and_agents_cannot_subscribe(): void
    {
        foreach ([Role::BEAT_OPERATOR, Role::SECTOR_ADMIN, Role::ZONE_ADMIN] as $role) {
            $u = $this->user($role);
            $this->actingAs($u)->postJson('/web-push/unsubscribe', ['endpoint' => 'https://wns2-pn1p.notify.windows.com/w/?token=x'])->assertStatus(403);
        }
        $sup = $this->user(Role::AGENT_SUPERVISOR);
        $this->actingAs($sup)->postJson('/web-push/unsubscribe', ['endpoint' => 'https://wns2-pn1p.notify.windows.com/w/?token=x'])->assertOk();
        $agent = $this->user(Role::AGENT);
        $this->actingAs($agent)->postJson('/web-push/subscribe', ['endpoint' => 'https://wns2-pn1p.notify.windows.com/w/?token=x', 'keys' => ['p256dh' => 'abc', 'auth' => 'abc']])->assertStatus(403);
    }

    public function test_pending_help_reminders_are_queued_each_minute_and_stop_when_status_changes(): void
    {
        config(['services.push.enabled' => true]);
        Queue::fake();
        $beat = $this->office('beat');
        $agent = $this->user(Role::AGENT);
        $type = CallType::first() ?? CallType::create(['name' => 'General Help', 'is_active' => true]);
        $make = function (string $status, int $ageSec) use ($beat, $agent, $type) {
            $c = new Call;
            $c->forceFill(['agent_id' => $agent->id, 'call_type_id' => $type->id, 'call_number' => 'CRM-R-'.Str::random(6), 'status' => $status, 'office_id' => $beat->id, 'caller_name' => 'X']);
            $c->save();
            \DB::table('calls')->where('id', $c->id)->update(['created_at' => now()->subSeconds($ageSec)]);

            return $c;
        };
        $pending = $make('pending', 185);
        $young = $make('pending', 20);
        $moved = $make('assigned', 400);

        $this->artisan('push:repeat-pending-help')->assertExitCode(0);
        Queue::assertPushed(SendHelpPendingPush::class, fn ($j) => $j->call->id === $pending->id && $j->repeat === 3);
        Queue::assertNotPushed(SendHelpPendingPush::class, fn ($j) => $j->call->id === $young->id);
        Queue::assertNotPushed(SendHelpPendingPush::class, fn ($j) => $j->call->id === $moved->id);

        // A job already in the queue does nothing once the help is no longer pending.
        \DB::table('calls')->where('id', $pending->id)->update(['status' => 'assigned']);
        $svc = $this->createMock(PushNotificationService::class);
        $svc->expects($this->never())->method('notifyHelpPending');
        (new SendHelpPendingPush($pending, 3))->handle($svc);
    }
}

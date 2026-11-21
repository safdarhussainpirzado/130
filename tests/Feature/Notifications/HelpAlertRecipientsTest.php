<?php

namespace Tests\Feature\Notifications;

use App\Models\Call;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use App\Models\UserScope;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\WebPushService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

/** Help alerts go ONLY to the related beat / sector / zone staff (owner decision 2026-11-21). */
class HelpAlertRecipientsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $roleName, ?Office $scope = null): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => $roleName, 'scope_level' => 'national', 'is_active' => true]);
        $u = User::create(['uuid' => (string) Str::uuid(), 'username' => 'ha_'.Str::random(8), 'full_name' => 'T '.$roleName, 'email' => 'ha'.Str::random(6).'@example.test', 'role_id' => $role->id, 'is_active' => true, 'password' => bcrypt('Password123!')]);
        if ($scope) {
            UserScope::create(['user_id' => $u->id, 'office_id' => $scope->id, 'access_level' => 'read_only', 'is_active' => true]);
        }

        return $u;
    }

    private function tree(): array
    {
        $zone = Office::create(['name' => 'Zone-'.Str::random(4), 'type' => 'zone', 'is_active' => true]);
        $sector = Office::create(['name' => 'Sector-'.Str::random(4), 'type' => 'sector', 'parent_id' => $zone->id, 'is_active' => true]);
        $beat = Office::create(['name' => 'Beat-'.Str::random(4), 'type' => 'beat', 'parent_id' => $sector->id, 'is_active' => true]);
        Office::clearHierarchyCache();

        return [$zone, $sector, $beat];
    }

    private function recipients(?int $officeId): array
    {
        $service = new PushNotificationService($this->createMock(WebPushService::class), $this->createMock(FcmService::class));
        $m = (new ReflectionClass($service))->getMethod('resolveDeskRecipientsForHelp');
        $m->setAccessible(true);

        return $m->invoke($service, new Call(['id' => 9001, 'status' => 'pending', 'priority' => 3, 'office_id' => $officeId]));
    }

    public function test_only_related_beat_sector_zone_staff_receive_the_alert(): void
    {
        [$zone, $sector, $beat] = $this->tree();
        [, , $otherBeat] = $this->tree();

        $beatOp = $this->makeUser(Role::BEAT_OPERATOR, $beat);
        $sectorAdmin = $this->makeUser(Role::SECTOR_ADMIN, $sector);
        $zoneAdmin = $this->makeUser(Role::ZONE_ADMIN, $zone);
        $otherBeatOp = $this->makeUser(Role::BEAT_OPERATOR, $otherBeat);
        $super = $this->makeUser(Role::SUPER_ADMIN);
        $ops = $this->makeUser(Role::IT_ADMIN);
        $sup = $this->makeUser(Role::AGENT_SUPERVISOR);
        $unscopedZoneAdmin = $this->makeUser(Role::ZONE_ADMIN);
        $agent = $this->makeUser(Role::AGENT);

        $ids = $this->recipients($beat->id);

        foreach ([$beatOp, $sectorAdmin, $zoneAdmin] as $u) {
            $this->assertContains($u->id, $ids, $u->role->name.' of the related office must be alerted');
        }
        foreach ([$otherBeatOp, $super, $ops, $sup, $unscopedZoneAdmin, $agent] as $u) {
            $this->assertNotContains($u->id, $ids, $u->role->name.' must NOT be alerted');
        }
    }

    public function test_call_without_a_beat_alerts_nobody(): void
    {
        $this->makeUser(Role::SUPER_ADMIN);
        $this->makeUser(Role::AGENT_SUPERVISOR);
        $this->assertSame([], $this->recipients(null));
    }

    public function test_blocked_inactive_or_privileged_scoped_users_are_not_alerted(): void
    {
        [, , $beat] = $this->tree();
        $blocked = $this->makeUser(Role::BEAT_OPERATOR, $beat);
        $blocked->forceFill(['is_blocked' => true])->save();
        $inactive = $this->makeUser(Role::BEAT_OPERATOR, $beat);
        $inactive->forceFill(['is_active' => false])->save();
        $scopedSuper = $this->makeUser(Role::SUPER_ADMIN, $beat);
        $ok = $this->makeUser(Role::BEAT_OPERATOR, $beat);

        $ids = $this->recipients($beat->id);
        $this->assertContains($ok->id, $ids);
        $this->assertNotContains($blocked->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($scopedSuper->id, $ids);
    }
}

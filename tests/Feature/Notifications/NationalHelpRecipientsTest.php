<?php

namespace Tests\Feature\Notifications;

use App\Models\Call;
use App\Models\Role;
use App\Models\User;
use App\Services\Notifications\FcmService;
use App\Services\Notifications\PushNotificationService;
use App\Services\Notifications\WebPushService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class NationalHelpRecipientsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $roleName): User
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['display_name' => $roleName, 'scope_level' => 'national', 'is_active' => true]);

        return User::create([
            'uuid' => (string) Str::uuid(),
            'username' => 'nh_'.Str::random(8),
            'full_name' => 'National '.$roleName,
            'email' => 'nh'.Str::random(6).'@example.test',
            'role_id' => $role->id,
            'is_active' => true,
            'password' => bcrypt('Password123!'),
        ]);
    }

    private function recipientsFor(int $priority): array
    {
        $service = new PushNotificationService($this->createMock(WebPushService::class), $this->createMock(FcmService::class));
        $m = (new ReflectionClass($service))->getMethod('resolveDeskRecipientsForHelp');
        $m->setAccessible(true);

        return $m->invoke($service, new Call(['id' => 9001, 'status' => 'pending', 'priority' => $priority]));
    }

    public function test_super_admins_and_operation_admins_receive_every_pending_help_not_only_p1(): void
    {
        $super = $this->makeUser(Role::SUPER_ADMIN);
        $ops = $this->makeUser(Role::IT_ADMIN);
        $agent = $this->makeUser(Role::AGENT);

        foreach ([1, 2, 3, 4, 5] as $priority) {
            $ids = $this->recipientsFor($priority);
            $this->assertContains($super->id, $ids, "super admin must get P{$priority}");
            $this->assertContains($ops->id, $ids, "operation admin must get P{$priority}");
            $this->assertNotContains($agent->id, $ids, 'call-centre agents are not desk push recipients');
        }
    }

    public function test_blocked_or_inactive_admins_are_not_notified(): void
    {
        $blocked = $this->makeUser(Role::SUPER_ADMIN);
        $blocked->forceFill(['is_blocked' => true])->save();
        $inactive = $this->makeUser(Role::SUPER_ADMIN);
        $inactive->forceFill(['is_active' => false])->save();

        $ids = $this->recipientsFor(3);
        $this->assertNotContains($blocked->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\CallController;
use App\Models\Call;
use App\Models\CallType;
use App\Models\Role;
use App\Models\User;
use App\Services\CallSearchTokens;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

class CallSearchAndExportTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role, string $scope = 'national'): User
    {
        $r = Role::firstOrCreate(['name' => $role], ['display_name' => $role, 'scope_level' => $scope, 'is_active' => true]);
        $r->forceFill(['scope_level' => $scope])->save();

        return User::create(['uuid' => (string) Str::uuid(), 'username' => 'cs_'.Str::random(8), 'full_name' => 'T '.$role, 'email' => 'cs'.Str::random(6).'@example.test', 'role_id' => $r->id, 'is_active' => true, 'password' => bcrypt('Password123!')]);
    }

    private function makeCall(User $agent, string $name, string $phone): Call
    {
        $type = CallType::first() ?? CallType::create(['name' => 'General Help', 'is_active' => true]);
        $c = new Call;
        $c->forceFill(['agent_id' => $agent->id, 'call_type_id' => $type->id, 'call_number' => 'CRM-S-'.Str::random(6), 'status' => 'pending', 'caller_name' => $name, 'caller_number' => $phone]);
        $c->save();

        return $c;
    }

    private function search(string $term): array
    {
        $ctl = app(CallController::class);
        $m = new ReflectionMethod($ctl, 'applyContextFilters');
        $m->setAccessible(true);
        $q = Call::query();
        $m->invoke($ctl, $q, Request::create('/x', 'GET', ['search' => $term]));

        return $q->pluck('id')->all();
    }

    public function test_tokens_are_created_on_save_and_never_contain_plaintext(): void
    {
        $agent = $this->user('agent', 'call_centre');
        $c = $this->makeCall($agent, 'Fahad Kareem', '03006723497');
        $rows = DB::table('call_search_tokens')->where('call_id', $c->id)->pluck('token_bidx')->all();
        $this->assertNotEmpty($rows);
        foreach ($rows as $r) {
            $this->assertSame(32, strlen($r));
            $this->assertStringNotContainsStringIgnoringCase('fahad', $r);
        }
    }

    public function test_search_by_name_words_prefixes_and_partial_numbers(): void
    {
        $agent = $this->user('agent', 'call_centre');
        $target = $this->makeCall($agent, 'Fahad Kareem', '03006723497');
        $other = $this->makeCall($agent, 'Ali Raza', '03211234567');

        foreach (['Fahad', 'fahad kareem', 'KAREEM', 'faha', 'kar', 'fah kar'] as $term) {
            $ids = $this->search($term);
            $this->assertContains($target->id, $ids, "name search '{$term}'");
            $this->assertNotContains($other->id, $ids, "name search '{$term}' must not match another caller");
        }
        foreach (['03006723497', '+923006723497', '0300-6723497', '6723497', '723497', '3497', '030067'] as $term) {
            $ids = $this->search($term);
            $this->assertContains($target->id, $ids, "phone search '{$term}'");
            $this->assertNotContains($other->id, $ids, "phone search '{$term}'");
        }
        $this->assertNotContains($target->id, $this->search('zzzzqq'));
    }

    public function test_operation_admin_can_export_csv_with_bom_and_resolved_names(): void
    {
        $ops = $this->user(Role::IT_ADMIN);
        foreach (['calls.export', 'calls.view'] as $perm) {
            $pid = DB::table('permissions')->where('name', $perm)->value('id') ?? DB::table('permissions')->insertGetId(['name' => $perm, 'display_name' => $perm, 'module' => 'calls', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('role_permissions')->updateOrInsert(['role_id' => $ops->role_id, 'permission_id' => $pid]);
        }
        $agent = $this->user('agent', 'call_centre');
        $this->makeCall($agent, 'Export Person', '03001112223');
        $ops->refresh();

        $res = $this->actingAs($ops)->get('/calls/export?status=all&search=Export', ['Accept' => 'text/csv']);
        $res->assertOk();
        $body = $res->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('Export Person', $body);
        $this->assertStringContainsString('03001112223', $body);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Call;
use App\Models\CallEvidence;
use App\Models\CommuterPresence;
use App\Models\GeospatialMarker;
use App\Models\HelpFeedback;
use App\Models\Office;
use App\Models\OfficerPresence;
use App\Models\Role;
use App\Models\SurveyCategory;
use App\Models\SurveyLocation;
use App\Models\User;
use Database\Seeders\SurveyCategorySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HumsafarTrackBTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): User
    {
        $r = Role::firstOrCreate(['name' => $role], ['display_name' => $role, 'scope_level' => 'national', 'is_active' => true]);

        return User::create(['uuid' => (string) Str::uuid(), 'username' => 'u_'.Str::random(8), 'full_name' => 'T '.$role, 'email' => 'x'.Str::random(6).'@example.test', 'role_id' => $r->id, 'is_active' => true, 'password' => bcrypt('Password123!'), 'mobile_no' => '03001234567']);
    }

    private function grantAll(User $u): void
    {
        $ids = DB::table('permissions')->where('module', 'humsafar')->pluck('id');
        foreach ($ids as $pid) {
            DB::table('role_permissions')->updateOrInsert(['role_id' => $u->role_id, 'permission_id' => $pid]);
        }
        $u->refresh();
        if (method_exists($u, 'clearPermissionCache')) {
            $u->clearPermissionCache();
        }
    }

    private function cat(): SurveyCategory
    {
        (new SurveyCategorySeeder)->run();

        return SurveyCategory::first();
    }

    private function marker(float $lat, float $lng, string $side): GeospatialMarker
    {
        $beat = Office::create(['name' => 'Beat-T'.Str::random(3), 'type' => 'beat', 'is_active' => true]);

        return GeospatialMarker::create(['office_id' => $beat->id, 'km' => 100, 'lat' => $lat, 'lng' => $lng, 'side' => $side]);
    }

    public function test_seeder_creates_eight_categories_idempotently(): void
    {
        (new SurveyCategorySeeder)->run();
        (new SurveyCategorySeeder)->run();
        $this->assertSame(8, SurveyCategory::count());
    }

    public function test_patrol_creates_survey_with_geo_match_and_24h_rules(): void
    {
        $officer = $this->user('patrol_officer');
        $c = $this->cat();
        $m = $this->marker(31.5000, 74.3000, 'N');
        Sanctum::actingAs($officer);

        $ok = $this->postJson('/api/v1/patrol/surveys', ['category_id' => $c->id, 'name' => 'Rest Stop', 'lat' => 31.5001, 'lng' => 74.3001, 'is_24h' => true, 'submit' => true]);
        $ok->assertCreated()->assertJsonPath('data.geo_status', 'matched')->assertJsonPath('data.status', 'submitted');

        $this->postJson('/api/v1/patrol/surveys', ['category_id' => $c->id, 'name' => 'No hours', 'lat' => 31.5, 'lng' => 74.3])->assertStatus(422);
        $overnight = $this->postJson('/api/v1/patrol/surveys', ['category_id' => $c->id, 'name' => 'Night shop', 'lat' => 31.9, 'lng' => 74.9, 'opens_at' => '20:00', 'closes_at' => '04:00']);
        $overnight->assertCreated()->assertJsonPath('data.geo_status', 'unmatched');
        $this->assertTrue(SurveyLocation::where('name', 'Night shop')->first()->isOpenAt(now()->setTime(2, 0)));
        $this->assertFalse(SurveyLocation::where('name', 'Night shop')->first()->isOpenAt(now()->setTime(12, 0)));
    }

    public function test_opposite_carriageways_need_verification_without_direction(): void
    {
        $officer = $this->user('patrol_officer');
        $c = $this->cat();
        $this->marker(31.6000, 74.4000, 'N');
        $this->marker(31.6001, 74.4001, 'S');
        Sanctum::actingAs($officer);
        $this->postJson('/api/v1/patrol/surveys', ['category_id' => $c->id, 'name' => 'Both sides', 'lat' => 31.6000, 'lng' => 74.4000, 'is_24h' => true])
            ->assertCreated()->assertJsonPath('data.geo_status', 'needs_verification');
        $this->postJson('/api/v1/patrol/surveys', ['category_id' => $c->id, 'name' => 'North side', 'lat' => 31.6000, 'lng' => 74.4000, 'is_24h' => true, 'direction' => 'N'])
            ->assertCreated()->assertJsonPath('data.geo_status', 'matched');
    }

    public function test_officer_sees_only_own_surveys(): void
    {
        $a = $this->user('patrol_officer');
        $b = $this->user('patrol_officer');
        $c = $this->cat();
        Sanctum::actingAs($a);
        $uuid = $this->postJson('/api/v1/patrol/surveys', ['category_id' => $c->id, 'name' => 'Mine', 'lat' => 30.1, 'lng' => 70.1, 'is_24h' => true])->json('data.uuid');
        Sanctum::actingAs($b);
        $this->getJson("/api/v1/patrol/surveys/{$uuid}")->assertNotFound();
        $this->assertCount(0, $this->getJson('/api/v1/patrol/surveys')->json('data'));
    }

    public function test_nearby_returns_only_approved_sorted_within_radius(): void
    {
        $commuter = $this->user('commuter');
        $c = $this->cat();
        $mk = fn ($n, $lat, $lng, $st) => SurveyLocation::create(['category_id' => $c->id, 'name' => $n, 'lat' => $lat, 'lng' => $lng, 'is_24h' => true, 'status' => $st, 'is_active' => true]);
        $mk('Near', 31.5010, 74.3010, 'approved');
        $mk('Far', 31.5300, 74.3300, 'approved');
        $mk('Pending', 31.5005, 74.3005, 'submitted');
        $mk('OutOfRange', 33.0, 75.0, 'approved');
        Sanctum::actingAs($commuter);
        $names = collect($this->getJson('/api/v1/commuter/nearby-locations?lat=31.5&lng=74.3&radius_m=10000')->assertOk()->json('data'))->pluck('name')->all();
        $this->assertSame(['Near', 'Far'], $names);
        $this->getJson('/api/v1/commuter/nearby-locations?lat=31.5')->assertStatus(422);
    }

    public function test_heartbeat_marks_online_and_availability_is_audited_and_separate(): void
    {
        $o = $this->user('patrol_officer');
        Sanctum::actingAs($o);
        $this->postJson('/api/v1/patrol/heartbeat', ['lat' => 31.5, 'lng' => 74.3])->assertOk()->assertJsonPath('online', true);
        $this->putJson('/api/v1/patrol/availability', ['status' => 'prayer_break'])->assertOk();
        $p = OfficerPresence::where('user_id', $o->id)->first();
        $this->assertTrue($p->isOnline());
        $this->assertSame('prayer_break', $p->availability);
        $this->postJson('/api/v1/patrol/availability/return-to-duty')->assertOk();
        $this->assertSame(2, DB::table('officer_availability_log')->where('user_id', $o->id)->count());
        $this->assertNotNull(DB::table('officer_availability_log')->where('user_id', $o->id)->where('status', 'prayer_break')->value('ended_at'));
        $this->putJson('/api/v1/patrol/availability', ['status' => 'bogus'])->assertStatus(422);
        $p->update(['last_seen_at' => now()->subMinutes(10)]);
        $this->assertFalse($p->fresh()->isOnline());
    }

    private function completedCall(User $commuter): Call
    {
        $c = new Call;
        $agent = $this->user('agent');
        $type = \App\Models\CallType::first() ?? \App\Models\CallType::create(['name' => 'General Help', 'is_active' => true]);
        $c->forceFill(['agent_id' => $agent->id, 'call_type_id' => $type->id, 'call_number' => 'CRM-T-'.Str::random(6), 'status' => 'completed', 'completed_at' => now(), 'commuter_user_id' => $commuter->id, 'caller_name' => 'X']);
        $c->save();

        return $c;
    }

    public function test_evidence_validates_content_and_is_one_per_call(): void
    {
        Storage::fake('local');
        $o = $this->user('patrol_officer');
        $com = $this->user('commuter');
        try {
            $call = $this->completedCall($com);
        } catch (\Throwable $e) {
            throw $e;
        }
        Sanctum::actingAs($o);
        $png = UploadedFile::fake()->createWithContent('e.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->postJson("/api/v1/cases/{$call->uuid}/evidence", ['image' => $png])->assertCreated();
        $this->postJson("/api/v1/cases/{$call->uuid}/evidence", ['image' => UploadedFile::fake()->createWithContent('f.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='))])->assertStatus(409);
        $fake = UploadedFile::fake()->createWithContent('x.png', 'not an image at all');
        $call2 = $this->completedCall($com);
        $this->postJson("/api/v1/cases/{$call2->uuid}/evidence", ['image' => $fake])->assertStatus(422);
        $this->assertSame(1, CallEvidence::count());
    }

    public function test_feedback_only_owner_once(): void
    {
        $com = $this->user('commuter');
        $other = $this->user('commuter');
        try {
            $call = $this->completedCall($com);
        } catch (\Throwable $e) {
            throw $e;
        }
        $body = ['timeliness' => 5, 'behaviour' => 4, 'helpfulness' => 4];
        Sanctum::actingAs($other);
        $this->postJson("/api/v1/cases/{$call->uuid}/feedback", $body)->assertForbidden();
        Sanctum::actingAs($com);
        $this->postJson("/api/v1/cases/{$call->uuid}/feedback", $body)->assertCreated()->assertJsonPath('data.overall', 4);
        $this->postJson("/api/v1/cases/{$call->uuid}/feedback", $body)->assertStatus(409);
        $this->postJson("/api/v1/cases/{$call->uuid}/feedback", ['timeliness' => 9, 'behaviour' => 1, 'helpfulness' => 1])->assertStatus(422);
        $this->assertSame(1, HelpFeedback::count());
    }

    public function test_commuter_presence_stores_latest_only(): void
    {
        $com = $this->user('commuter');
        Sanctum::actingAs($com);
        $this->postJson('/api/v1/commuter/presence', ['lat' => 31.1, 'lng' => 74.1])->assertOk();
        $this->postJson('/api/v1/commuter/presence', ['lat' => 31.2, 'lng' => 74.2])->assertOk();
        $this->assertSame(1, CommuterPresence::where('user_id', $com->id)->count());
        $this->assertEquals(31.2, CommuterPresence::where('user_id', $com->id)->value('lat'));
    }

    public function test_admin_pages_are_permission_gated_and_review_works(): void
    {
        $plain = $this->user('agent');
        $admin = $this->user('super_admin');
        $this->grantAll($admin);
        $c = $this->cat();
        $loc = SurveyLocation::create(['category_id' => $c->id, 'name' => 'Review me', 'lat' => 31, 'lng' => 74, 'is_24h' => true, 'status' => 'submitted']);

        $this->assertNotSame(200, $this->actingAs($plain)->get('/humsafar/surveys')->getStatusCode());
        $this->actingAs($admin)->get('/humsafar/surveys')->assertOk()->assertSee('Review me');
        $this->actingAs($admin)->get('/humsafar/catalog')->assertOk()->assertSee('Medical');
        $this->actingAs($admin)->get('/humsafar/officers')->assertOk();
        $this->actingAs($admin)->get('/humsafar/commuters')->assertOk();
        $this->actingAs($admin)->get('/humsafar/monitor')->assertOk();
        $this->actingAs($admin)->get('/humsafar/feedback')->assertOk();
        $this->actingAs($admin)->post("/humsafar/surveys/{$loc->id}/review", ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->actingAs($admin)->post("/humsafar/surveys/{$loc->id}/review", ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $loc->fresh()->status);
    }
}

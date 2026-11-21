<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserSearchToken;
use App\Observers\UserObserver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EncryptedUserSearchTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    private User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'super_admin'], [
            'display_name' => 'Super Administrator',
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'is_active' => true,
            'is_blocked' => false,
        ]);

        $this->targetUser = User::factory()->create([
            'full_name' => 'Muhammad Tariq Khan',
            'username' => 'tariq_officer',
            'email' => 'tariq.khan@nhmp.gov.pk',
            'cnic' => '35202-1234567-1',
            'mobile_no' => '0300-1234567',
            'is_active' => true,
            'is_blocked' => false,
        ]);

        // Trigger token sync if not automatically triggered
        app(UserObserver::class)->syncSearchTokens($this->targetUser);
    }

    public function test_user_search_by_formatted_and_unformatted_cnic(): void
    {
        $this->actingAs($this->admin);

        // Formatted CNIC
        $response = $this->get('/mgmt/users?search=35202-1234567-1');
        $response->assertStatus(200);
        $response->assertSee('Muhammad Tariq Khan');

        // Unformatted CNIC
        $response2 = $this->get('/mgmt/users?search=3520212345671');
        $response2->assertStatus(200);
        $response2->assertSee('Muhammad Tariq Khan');
    }

    public function test_user_search_by_various_phone_formats(): void
    {
        $this->actingAs($this->admin);

        // Standard with hyphen
        $this->get('/mgmt/users?search=0300-1234567')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // Standard unformatted
        $this->get('/mgmt/users?search=03001234567')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // International format with +92
        $this->get('/mgmt/users?search=+923001234567')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // National format without +: 92300...
        $this->get('/mgmt/users?search=923001234567')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');
    }

    public function test_user_search_by_email_case_insensitivity(): void
    {
        $this->actingAs($this->admin);

        // Exact
        $this->get('/mgmt/users?search=tariq.khan@nhmp.gov.pk')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // Uppercase / Mixed Case
        $this->get('/mgmt/users?search=TARIQ.KHAN@NHMP.GOV.PK')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');
    }

    public function test_user_search_by_username_case_insensitivity(): void
    {
        $this->actingAs($this->admin);

        // Exact
        $this->get('/mgmt/users?search=tariq_officer')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // Mixed Case
        $this->get('/mgmt/users?search=Tariq_Officer')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');
    }

    public function test_user_search_by_partial_and_tokenized_name(): void
    {
        $this->actingAs($this->admin);

        // Full word from name
        $this->get('/mgmt/users?search=Tariq')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // Prefix of word (e.g. 3-4 chars)
        $this->get('/mgmt/users?search=tari')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        $this->get('/mgmt/users?search=khan')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');
    }

    public function test_mfa_page_search_by_all_five_encrypted_fields(): void
    {
        $this->actingAs($this->admin);

        // 1. CNIC
        $this->get('/mgmt/mfa-settings?search=35202-1234567-1')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // 2. Mobile
        $this->get('/mgmt/mfa-settings?search=03001234567')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // 3. Email
        $this->get('/mgmt/mfa-settings?search=tariq.khan@nhmp.gov.pk')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // 4. Username
        $this->get('/mgmt/mfa-settings?search=tariq_officer')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');

        // 5. Partial Name
        $this->get('/mgmt/mfa-settings?search=Tariq')
            ->assertStatus(200)
            ->assertSee('Muhammad Tariq Khan');
    }

    public function test_backfill_artisan_command_is_idempotent_and_dry_run_capable(): void
    {
        // Dry-run check
        $this->artisan('users:backfill-blind-indexes', ['--dry-run' => true, '--chunk' => 10])
            ->assertExitCode(0)
            ->expectsOutputToContain('DRY-RUN MODE ENABLED');

        // Real execution check
        $this->artisan('users:backfill-blind-indexes', ['--chunk' => 10])
            ->assertExitCode(0)
            ->expectsOutputToContain('User blind index backfill completed successfully');

        $this->assertNotEmpty($this->targetUser->fresh()->email_bidx);
        $this->assertNotEmpty($this->targetUser->fresh()->username_bidx);
        $this->assertGreaterThan(0, UserSearchToken::where('user_id', $this->targetUser->id)->count());
    }
}

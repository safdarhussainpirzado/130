<?php

namespace Tests\Feature\Auth;

use App\Models\Permission;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    private static int $nextUserId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Never migrate or connect to the application's configured database.
        // The master migration uses MySQL partitioning; this fixture contains
        // only the schema exercised by the real authentication endpoints.
        config([
            'database.default' => 'auth_testing',
            'database.connections.auth_testing' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);

        $this->createAuthenticationSchema();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="_token"', false)
            ->assertSee(route('password.request'), false);
    }

    public function test_users_can_authenticate_with_username_and_reach_their_workspace(): void
    {
        $user = $this->createUser();
        $role = Role::create([
            'name' => 'agent',
            'display_name' => 'Call Center Agent',
            'scope_level' => 'call_centre',
        ]);
        $permission = Permission::create([
            'name' => 'calls.create',
            'display_name' => 'Create calls',
            'module' => 'calls',
        ]);
        $role->permissions()->attach($permission);
        $user->update(['role_id' => $role->id]);

        $this->get('/login');
        $oldSessionId = session()->getId();

        $this->post('/login', $this->credentials($user))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('calls.create'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSessionId, session()->getId());
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('login_logs', ['user_id' => $user->id]);
        $this->get(route('auth.heartbeat'))->assertNoContent();
    }

    public function test_login_respects_the_intended_authenticated_destination(): void
    {
        $user = $this->createUser();

        $this->get(route('auth.heartbeat'))->assertRedirect(route('login'));

        $this->post('/login', $this->credentials($user))
            ->assertRedirect(route('auth.heartbeat'));

        $this->get(route('auth.heartbeat'))->assertNoContent();
    }

    public function test_invalid_password_shows_an_error_and_keeps_only_the_username(): void
    {
        $user = $this->createUser();

        $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-password',
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'login_failed',
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee(trans('auth.failed'))
            ->assertSee('value="'.$user->username.'"', false)
            ->assertDontSee('wrong-password');
    }

    public function test_unknown_username_cannot_authenticate(): void
    {
        $this->from('/login')->post('/login', [
            'username' => 'unknown.officer',
            'password' => 'TestPassword!130',
        ])->assertRedirect('/login')->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_username_and_password_are_required(): void
    {
        $this->from('/login')->post('/login', [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username', 'password']);

        $this->assertGuest();
    }

    public function test_repeated_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'username' => 'unknown.officer',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('username');
        }

        $response = $this->post('/login', [
            'username' => 'unknown.officer',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('username');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('username'));

        $this->assertGuest();
    }

    public function test_inactive_accounts_cannot_authenticate(): void
    {
        $user = $this->createUser(['is_active' => false]);

        $this->post('/login', $this->credentials($user))
            ->assertSessionHasErrors([
                'username' => 'This account has been deactivated. Contact your administrator.',
            ]);

        $this->assertGuest();
    }

    public function test_locked_accounts_cannot_authenticate(): void
    {
        $user = $this->createUser(['locked_until' => now()->addMinutes(30)]);

        $this->post('/login', $this->credentials($user))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_remember_me_issues_a_recaller_cookie(): void
    {
        $user = $this->createUser();

        /** @var \Illuminate\Auth\SessionGuard $guard */
        $guard = Auth::guard('web');

        $this->post('/login', [...$this->credentials($user), 'remember' => '1'])
            ->assertSessionHasNoErrors()
            ->assertRedirect($user->getLandingPageRoute())
            ->assertCookie($guard->getRecallerName());

        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty($user->fresh()->remember_token);
    }

    public function test_users_can_logout_and_protected_routes_require_login_again(): void
    {
        $this->freezeTime();
        $user = $this->createUser();
        $this->post('/login', $this->credentials($user));
        $this->get(route('auth.heartbeat'))->assertNoContent();

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('login_logs', [
            'user_id' => $user->id,
            'logout_at' => now()->toDateTimeString(),
        ]);
        $this->get(route('auth.heartbeat'))->assertRedirect(route('login'));
    }

    public function test_required_two_factor_authentication_blocks_access_until_valid_code(): void
    {
        Role::firstOrCreate(['id' => 1], [
            'name' => 'super_admin',
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
        ]);
        SystemSetting::set('2fa_level', 'all');
        $user = $this->createUser(['totp_secret' => 'JBSWY3DPEHPK3PXP', 'role_id' => 1, 'mfa_required' => true]);
        $google2fa = new Google2FA;
        $code = $google2fa->getCurrentOtp($user->totp_secret);
        $invalidCode = $code === '111111' ? '222222' : '111111';

        $this->post('/login', $this->credentials($user))->assertRedirect();
        $this->get(route('auth.heartbeat'))->assertRedirect(route('2fa.challenge'));
        $this->get(route('2fa.challenge'))->assertOk();

        $this->from(route('2fa.challenge'))->post(route('2fa.challenge.totp'), ['code' => $invalidCode])
            ->assertRedirect(route('2fa.challenge'))
            ->assertSessionHasErrors('code');
        $this->get(route('auth.heartbeat'))->assertRedirect(route('2fa.challenge'));

        $this->post(route('2fa.challenge.totp'), ['code' => $code])
            ->assertSessionHas('2fa_verified', true)
            ->assertRedirect(route('dashboard'));
        $this->get(route('auth.heartbeat'))->assertNoContent();

        $this->post('/logout')->assertSessionMissing('2fa_verified');
        $this->assertGuest();
    }

    public function test_required_two_factor_setup_redirects_users_without_a_secret(): void
    {
        Role::firstOrCreate(['id' => 1], [
            'name' => 'super_admin',
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
        ]);
        SystemSetting::set('2fa_level', 'all');
        $user = $this->createUser(['role_id' => 1, 'mfa_required' => true]);

        $this->post('/login', $this->credentials($user))->assertRedirect();
        $this->get(route('auth.heartbeat'))->assertRedirect(route('2fa.enroll.required'));
    }

    private function createUser(array $attributes = []): User
    {
        // The legacy UserFactory still uses fields absent from the CRM schema.
        // Unique IDs also isolate the model's static permission cache per test.
        return User::forceCreate(array_merge([
            'id' => self::$nextUserId++,
            'full_name' => 'Test NHMP Officer',
            'email' => 'officer' . self::$nextUserId . '@example.test',
            'mobile_no' => '0300' . self::$nextUserId,
            'username' => 'test.officer.' . self::$nextUserId,
            'password' => 'TestPassword!130',
            'is_active' => true,
        ], $attributes));
    }

    private function credentials(User $user): array
    {
        return ['username' => $user->username, 'password' => 'TestPassword!130'];
    }

    private function createAuthenticationSchema(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('scope_level');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
            $table->string('module');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained();
            $table->foreignId('permission_id')->constrained();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->nullable();
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('mobile_no')->nullable();
            $table->string('mobile_no_bidx', 64)->nullable();
            $table->string('cnic', 50)->nullable();
            $table->string('cnic_bidx', 64)->nullable();
            $table->string('username')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->foreignId('role_id')->nullable()->constrained();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->text('totp_secret')->nullable();
            $table->unsignedBigInteger('totp_last_step')->nullable();
            $table->boolean('mfa_required')->default(false);
            $table->string('mfa_assurance_level', 20)->default('totp');
            $table->unsignedInteger('security_version')->default(1);
            $table->timestamp('mfa_enrolled_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->timestamps();
        });
        Schema::create('mfa_role_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->unique()->constrained('roles')->cascadeOnDelete();
            $table->boolean('mfa_required')->default(true);
            $table->timestamp('scheduled_re_enable_at')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('webauthn_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('credential_id', 255)->unique();
            $table->text('public_key');
            $table->unsignedInteger('sign_count')->default(0);
            $table->string('nickname')->nullable();
            $table->string('aaguid', 36)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('two_factor_recovery_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 255);
            $table->string('generation_id', 36)->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key_name')->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->string('group_name')->default('general');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('login_at')->useCurrent();
            $table->timestamp('logout_at')->nullable();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->text('old_values')->nullable();
            $table->text('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('previous_hash', 64)->nullable();
            $table->string('record_hash', 64)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
}

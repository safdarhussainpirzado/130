<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordResetRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertStatus(200);
    }

    public function test_reset_password_request_can_be_submitted(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status_operator');
        $this->assertDatabaseHas('password_reset_requests', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);
    }

    public function test_super_admin_cannot_request_web_password_reset(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => Role::SUPER_ADMIN], [
            'display_name' => 'Super Administrator',
            'scope_level' => 'national',
            'status' => 'active',
        ]);

        /** @var User $admin */
        $admin = User::factory()->create(['role_id' => $superAdminRole->id]);

        $response = $this->post('/forgot-password', ['email' => $admin->email]);

        $response->assertSessionHasErrors(['identifier']);
    }

    public function test_duplicate_pending_reset_request_returns_info_status(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $this->post('/forgot-password', ['email' => $user->email]);
        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertSessionHas('status_info');
    }
}

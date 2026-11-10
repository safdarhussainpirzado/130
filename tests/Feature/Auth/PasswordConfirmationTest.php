<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    public function test_confirm_password_screen_can_be_rendered(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/confirm-password');

        $response->assertStatus(200);
    }

    public function test_password_can_be_confirmed(): void
    {
        $plain = 'P@ssw0rd2026!Strong';
        /** @var User $user */
        $user = User::factory()->create([
            'password' => Hash::make($plain),
        ]);

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => $plain,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }

    public function test_password_is_not_confirmed_with_invalid_password(): void
    {
        /** @var User $user */
        $user = User::factory()->create([
            'password' => Hash::make('P@ssw0rd2026!Strong'),
        ]);

        $response = $this->actingAs($user)->post('/confirm-password', [
            'password' => 'WrongPassword123!',
        ]);

        $response->assertSessionHasErrors();
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    public function test_password_can_be_updated(): void
    {
        $currentPlain = 'P@ssw0rd2026!Old';
        $newPlain = 'P@ssw0rd2026!NewPass';

        /** @var User $user */
        $user = User::factory()->create([
            'password' => Hash::make($currentPlain),
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => $currentPlain,
                'password' => $newPlain,
                'password_confirmation' => $newPlain,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check($newPlain, $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $currentPlain = 'P@ssw0rd2026!Old';
        $newPlain = 'P@ssw0rd2026!NewPass';

        /** @var User $user */
        $user = User::factory()->create([
            'password' => Hash::make($currentPlain),
        ]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'WrongPassword123!',
                'password' => $newPlain,
                'password_confirmation' => $newPlain,
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}

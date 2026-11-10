<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class RegistrationTest extends TestCase
{
    /**
     * Security Verification: Public registration must be strictly disabled.
     * User provisioning is restricted exclusively to administrators.
     */
    public function test_public_registration_screen_is_disabled(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(404);
    }

    public function test_public_registration_submission_is_blocked(): void
    {
        $response = $this->post('/register', [
            'name' => 'Adversary User',
            'email' => 'adversary@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(404);
        $this->assertGuest();
    }
}

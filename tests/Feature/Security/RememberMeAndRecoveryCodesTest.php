<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class RememberMeAndRecoveryCodesTest extends TestCase
{
    public function test_login_form_has_no_remember_me(): void
    {
        $html = $this->get('/login')->getContent();
        $this->assertStringNotContainsString('name="remember"', $html);
    }

    public function test_source_never_passes_remember_true(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Auth/AuthenticatedSessionController.php'));
        $this->assertStringNotContainsString("boolean('remember')", $src);
    }

    public function test_enrollment_does_not_generate_recovery_codes(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/Auth/TwoFactorEnrollmentController.php'));
        $this->assertSame(0, substr_count($src, 'generateCodes(') - 1, 'only generateRecoveryCodes() may call generateCodes');
    }

    public function test_recovery_manage_requires_login(): void
    {
        $this->get('/2fa/recovery-codes')->assertRedirect();
    }
}

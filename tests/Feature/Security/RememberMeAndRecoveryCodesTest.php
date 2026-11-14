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

    public function test_no_code_generation_or_display_exists(): void
    {
        $this->assertFalse(class_exists(\App\Services\TwoFactor\RecoveryService::class));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('2fa.recovery.show'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('2fa.recovery.verify'));
        $this->assertFileDoesNotExist(resource_path('views/auth/two-factor-recovery-codes.blade.php'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('2fa.recovery.generate'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('2fa.recovery.manage'));
    }

    public function test_recovery_entry_page_is_gone(): void
    {
        $this->get('/2fa/recovery')->assertStatus(404);
        $this->post('/2fa/recovery', ['recovery_code' => 'AAAA-BBBB-CCCC-DDDD'])->assertStatus(404);
    }

    public function test_recovery_codes_page_is_gone(): void
    {
        $this->get('/2fa/recovery-codes')->assertStatus(404);
    }
}

<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    /**
     * Validate the user's password without establishing an authenticated session.
     * Enforces dummy password hashing on nonexistent accounts to mitigate timing differences.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): \App\Models\User
    {
        $this->ensureIsNotRateLimited();

        $username = (string) $this->string('username')->trim();
        $password = (string) $this->string('password');

        /** @var \App\Models\User|null $user */
        $user = \App\Models\User::where('username', $username)->first();

        // Mitigation for account enumeration timing differences
        if (!$user) {
            \Illuminate\Support\Facades\Hash::check($password, '$2y$10$e8wVf59B.Z5t9i3s2v4u6.GZq7u8w9x0y1z2a3b4c5d6e7f8g9h0i');
            \Illuminate\Support\Facades\RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        // Account hard-locked by admin
        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'username' => 'This account has been deactivated. Contact your administrator.',
            ]);
        }

        // Time-based lockout still active
        if ($user->locked_until && $user->locked_until->isFuture()) {
            $remaining = now()->diffInMinutes($user->locked_until, false);
            throw ValidationException::withMessages([
                'username' => "Account locked. Try again in {$remaining} minute(s).",
            ]);
        }

        if (!\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            \Illuminate\Support\Facades\RateLimiter::hit($this->throttleKey());

            $attempts = ($user->failed_attempts ?? 0) + 1;
            $lockUntil = $attempts >= 5 ? now()->addMinutes(30) : null;
            $user->update([
                'failed_attempts' => $attempts,
                'locked_until'    => $lockUntil,
            ]);

            \App\Models\AuditLog::create([
                'user_id'    => $user->id,
                'action'     => 'login_failed',
                'table_name' => 'users',
                'record_id'  => $user->id,
                'new_values' => ['reason' => 'invalid_password', 'attempts' => $attempts],
                'ip_address' => $this->ip(),
                'user_agent' => $this->userAgent(),
            ]);

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($this->throttleKey());

        if (($user->failed_attempts ?? 0) > 0) {
            $user->update(['failed_attempts' => 0]);
        }

        return $user;
    }

    /**
     * Legacy authenticate method for backward compatibility.
     */
    public function authenticate(): void
    {
        $this->validateCredentials();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('username')).'|'.$this->ip());
    }
}

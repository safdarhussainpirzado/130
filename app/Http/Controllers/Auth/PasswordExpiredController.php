<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordExpiredController extends Controller
{
    /**
     * Display the password expired change view.
     */
    public function show(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isPasswordExpired()) {
            return redirect($user->getLandingPageRoute())
                ->with('status', 'Your password is active and valid. It will expire on ' . ($user->passwordExpiresAt()?->toFormattedDateString() ?? '15 days from change') . '.');
        }

        $expiryDays = (int) config('auth.password_expiry_days', 15);

        return view('auth.password-expired', [
            'user' => $user,
            'expiryDays' => $expiryDays,
            'lastChangedAt' => $user->password_changed_at ?? $user->created_at,
        ]);
    }

    /**
     * Update the expired password with mandatory history validation.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        // ── Password history check (prevent reuse of last 5 passwords) ───
        $recentHashes = PasswordHistory::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->pluck('password');

        foreach ($recentHashes as $oldHash) {
            if (Hash::check($validated['password'], $oldHash)) {
                throw ValidationException::withMessages([
                    'password' => [
                        'Security Policy Violation: You cannot reuse one of your last 5 passwords. Please choose a completely new password.',
                    ],
                ]);
            }
        }

        // ── Also prevent using current password again ────────────────────
        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => [
                    'Your new password must be different from your current expired password.',
                ],
            ]);
        }

        // ── Archive current password before updating ────────────────────
        try {
            PasswordHistory::create([
                'user_id'  => $user->id,
                'password' => $user->password,
            ]);
        } catch (\Throwable $e) {}

        // ── Update password and set password_changed_at to now() ────────
        $user->forceFill([
            'password'            => Hash::make($validated['password']),
            'password_changed_at' => now(),
            'failed_attempts'     => 0,
            'locked_until'        => null,
        ])->save();

        // Regenerate session for security
        $request->session()->regenerate();

        return redirect($user->getLandingPageRoute())->with(
            'status',
            'Password updated successfully. In compliance with the NHMP 15-day cyber security policy, your new password will be valid for 15 days.'
        );
    }
}

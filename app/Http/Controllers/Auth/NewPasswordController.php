<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    /**
     * Display the password reset view.
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    /**
     * Handle an incoming new password request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'cnic'     => ['required', 'string'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $token = trim((string) $request->input('token'));
        $email = trim((string) $request->input('email'));
        $cnic = trim((string) $request->input('cnic'));

        // 1. Check for single-use high-entropy recovery token (64 hex characters)
        if (strlen($token) === 64 && ctype_xdigit($token)) {
            $recoveryService = app(\App\Services\Support\AccountSecuritySupportService::class);
            try {
                $recoveryService->resetPasswordWithToken($token, (string) $request->input('password'), $email, $cnic);

                return redirect()->route('login')
                    ->with('status', 'Your password has been successfully established and your account credentials updated. You may now log in.');
            } catch (ValidationException $e) {
                return back()->withInput($request->only('email', 'cnic'))
                    ->withErrors($e->errors());
            }
        }

        // 2. Standard Laravel password broker fallback
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password'            => Hash::make($request->password),
                    'password_changed_at' => now(),
                    'remember_token'      => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}

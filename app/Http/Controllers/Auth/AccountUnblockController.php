<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountUnblockController extends Controller
{
    /**
     * Show the unblock and identity verification form.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $token = (string) $request->query('token');

        if (empty($token)) {
            return redirect()->route('login')->withErrors(['username' => 'Invalid or missing unblock security token.']);
        }

        $tokenHash = hash('sha256', $token);
        $user = User::where('unblock_token', $tokenHash)
            ->where('unblock_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            return redirect()->route('login')->withErrors(['username' => 'The unblock verification link is invalid or has expired. Please contact Support.']);
        }

        return view('auth.unblock-account', [
            'token' => $token,
        ]);
    }

    /**
     * Verify CNIC & Email, set new password, and unblock account.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email'],
            'cnic'                  => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tokenHash = hash('sha256', $request->input('token'));
        $user = User::where('unblock_token', $tokenHash)
            ->where('unblock_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'token' => 'The unblock token is invalid or has expired.',
            ]);
        }

        if (strtolower(trim($user->email)) !== strtolower(trim($request->input('email')))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'The provided email does not match our records for this account.',
            ]);
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $request->input('cnic'));
        $userDigits = preg_replace('/[^0-9]/', '', (string) $user->cnic);

        if (empty($digits) || $digits !== $userDigits) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cnic' => 'The provided CNIC number does not match the account holder.',
            ]);
        }

        // Successfully verified: Unblock account and reset password
        $user->unblockAccount($request->input('password'));

        return redirect()->route('login')->with('status', 'Your account has been unblocked successfully. Please log in with your new password.');
    }
}

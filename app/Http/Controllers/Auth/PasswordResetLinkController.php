<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordResetRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'identifier' => ['nullable', 'string', 'max:100'],
            'email'      => ['nullable', 'string', 'max:100'],
            'notes'      => ['nullable', 'string', 'max:500'],
        ]);

        $identifier = trim($request->input('identifier') ?? $request->input('email') ?? '');

        if (empty($identifier)) {
            return back()->withInput()->withErrors([
                'identifier' => 'Please provide your registered email address or username.',
            ]);
        }

        // Look up by username or email
        $user = User::where('email', $identifier)
            ->orWhere('username', $identifier)
            ->first();

        if (!$user) {
            return back()->withInput()
                ->withErrors(['identifier' => 'No active user account found matching this username or email. Please verify your credentials or contact the duty Operation Manager.']);
        }

        // 1. Super Admin is strictly prohibited from web-based resets
        if ($user->role?->name === Role::SUPER_ADMIN) {
            return back()->withInput()
                ->withErrors([
                    'identifier' => 'Super Administrator credentials cannot be reset via web requests. Root access recovery is strictly restricted to server console / SSH infrastructure commands.',
                ]);
        }

        // Check for existing recent pending request to avoid duplicates
        $existingPending = PasswordResetRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->first();

        if ($existingPending) {
            $msg = ($user->role?->name === Role::IT_ADMIN)
                ? "An authorization request [REQ-{$existingPending->id}] for this Operation Manager account is already pending with your peer Operation Managers. Please coordinate directly for peer authorization."
                : "Your password reset request [REQ-{$existingPending->id}] is already pending with the Operation Managers. Please contact the Operations Control Room to obtain your credentials.";

            return back()->with('status_info', $msg);
        }

        $isOpManager = ($user->role?->name === Role::IT_ADMIN);

        $resetReq = PasswordResetRequest::create([
            'user_id'            => $user->id,
            'requester_name'     => $user->full_name,
            'requester_email'    => $user->email,
            'requester_username' => $user->username,
            'requester_role'     => $user->role?->name ?? 'operator',
            'target_group'       => 'operation_admin',
            'status'             => 'pending',
            'user_notes'         => $request->input('notes'),
            'ip_address'         => $request->ip(),
        ]);

        if ($isOpManager) {
            return back()->with('status_op_manager', 
                "Operation Manager reset request [REQ-{$resetReq->id}] dispatched to peer Operation Managers. Please coordinate with an on-duty Operation Manager for peer authorization."
            );
        }

        return back()->with('status_operator', 
            "Password reset request [REQ-{$resetReq->id}] forwarded to Operation Managers. Please contact the Operations Control Room / Duty Operation Manager to verify and receive your credentials."
        );
    }
}

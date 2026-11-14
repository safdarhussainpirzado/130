<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing(['role', 'designation', 'activeScopes.office']);
        }

        $recentLogins = collect();
        if ($user && class_exists(\App\Models\LoginLog::class)) {
            try {
                $recentLogins = \App\Models\LoginLog::where('user_id', $user->id)
                    ->orderByDesc('login_at')
                    ->limit(10)
                    ->get();
            } catch (\Throwable $e) {
                // Graceful fallback if table/schema unavailable
            }
        }

        $lastLogin = $recentLogins->first();
        $prevLogin = $recentLogins->skip(1)->first();

        return view('profile.edit', [
            'user' => $user,
            'recentLogins' => $recentLogins,
            'lastLogin' => $lastLogin,
            'prevLogin' => $prevLogin,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email') && \Illuminate\Support\Facades\Schema::hasColumn('users', 'email_verified_at')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}

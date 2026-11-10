<x-guest-layout>
    <div class="login-page {{ $errors->any() ? 'has-login-errors' : '' }}" id="nhmp-login">
        <div class="ambient-orb ambient-orb-blue" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb-mint" aria-hidden="true"></div>
        <a class="skip-link" href="#expired-heading">Skip to password update form</a>

        <header class="login-header">
            <a class="brand" href="{{ route('login') }}" aria-label="NHMP 130 sign in">
                <span class="brand-emblem" aria-hidden="true"><img src="{{ asset('images/login/shield-logo-web.webp') }}" width="68" height="67" alt="" fetchpriority="high" decoding="async"></span>
                <span class="brand-wordmark">NHMP <strong>130</strong><span>National Highways &amp; Motorway Police</span></span>
            </a>
            <div class="service-pill"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2Z"/><path d="m7 9 2 2 4-4"/></svg>Security Policy · Mandatory 15-Day Password Rotation</div>
        </header>

        <main class="login-main auth-grid-left-form">
            <!-- LEFT COLUMN: FORM CARD -->
            <section class="login-card" aria-labelledby="expired-heading">
                <div class="card-accent" aria-hidden="true"></div>
                <div class="card-heading">
                    <div class="access-icon" style="background: rgba(239, 68, 68, 0.1); color: #ef4444;" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <div>
                        <p class="card-eyebrow" style="color: #ef4444; font-weight: 800;">NHMP CYBER SECURITY MANDATE</p>
                        <h2 id="expired-heading" tabindex="-1">Password Expired<span>.</span></h2>
                    </div>
                </div>

                <p class="card-intro">
                    In compliance with the official NHMP 15-day cyber security rotation policy, your password has expired and must be updated before accessing the 130 operations grid.
                </p>

                <!-- User Identity Badge -->
                <div class="user-id-pill">
                    <div class="user-avatar-circle">
                        {{ strtoupper(substr($user->full_name ?? $user->username ?? 'U', 0, 1)) }}
                    </div>
                    <div class="user-meta">
                        <strong>{{ $user->full_name ?? 'NHMP Personnel' }}</strong>
                        <span>{{ $user->username }} ({{ $user->role?->display_name ?? 'Officer' }})</span>
                    </div>
                    <span class="user-security-badge" style="background: rgba(239, 68, 68, 0.12); color: #dc2626; border-color: rgba(239, 68, 68, 0.25);">Rotation Due</span>
                </div>

                @if ($errors->any())
                    <div class="login-alert" id="login-errors" role="alert" tabindex="-1">
                        <strong>Password Update Required</strong>
                        @foreach ($errors->all() as $error)
                            <span>{{ $error }}</span>
                        @endforeach
                    </div>
                @endif

                <!-- Password Expiry Update Form -->
                <form id="loginForm" method="POST" action="{{ route('password.expired.update') }}">
                    @csrf

                    <!-- Current Password -->
                    <div class="form-field">
                        <label for="current_password">Current (Expired) Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input id="current_password" 
                                   name="current_password" 
                                   type="password" 
                                   required 
                                   autofocus 
                                   autocomplete="current-password" 
                                   placeholder="Enter your current password">
                        </div>
                    </div>

                    <!-- New Password -->
                    <div class="form-field">
                        <label for="password">New Secure Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input id="password" 
                                   name="password" 
                                   type="password" 
                                   required 
                                   autocomplete="new-password" 
                                   placeholder="Min 12 chars, upper/lower/number/symbol">
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div class="form-field">
                        <label for="password_confirmation">Confirm New Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input id="password_confirmation" 
                                   name="password_confirmation" 
                                   type="password" 
                                   required 
                                   autocomplete="new-password" 
                                   placeholder="Re-enter your new password">
                        </div>
                    </div>

                    <!-- Policy Checklist Box -->
                    <div style="background: rgba(241, 245, 249, 0.85); border: 1px solid #e2e8f0; border-radius: 14px; padding: 14px 16px; margin: 16px 0; font-size: 12px; color: #475569;">
                        <strong style="color: #0f172a; display: block; margin-bottom: 6px;">Password Requirements &amp; History Policy:</strong>
                        <ul style="margin: 0; padding-left: 18px; line-height: 1.6;">
                            <li>Minimum <strong>12 characters</strong> with uppercase, lowercase, numbers, and symbols.</li>
                            <li>Cannot reuse any of your <strong>last 5 passwords</strong> (NIST SP 800-63B).</li>
                            <li>Your new password will be valid for <strong>15 days</strong> from today.</li>
                        </ul>
                    </div>

                    <!-- 3D SUBMIT BUTTON -->
                    <button type="submit" id="loginButton" class="sign-in-button" style="background: linear-gradient(135deg, #2563eb, #1d4ed8);">
                        <span id="btnText">Set New Password &amp; Continue</span>
                        <svg class="submit-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        <span id="loadingSpinner" class="login-spinner" hidden aria-hidden="true"></span>
                    </button>
                    <p id="loginProgress" class="sr-only" role="status" aria-live="polite"></p>

                    <div class="form-options" style="justify-content: center; margin-top: 14px;">
                        <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" style="color: #64748b; font-size: 12px; text-decoration: none;">
                            Sign out to another account &rarr;
                        </a>
                    </div>
                </form>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
                    @csrf
                </form>

                <div class="card-footer">
                    <span>Identity Security Protocol</span>
                    <span>NHMP 130 CRM</span>
                </div>
                <p class="access-help">Need assistance? Contact your duty Operation Manager or Helpline admin.</p>
            </section>

            <!-- RIGHT COLUMN: SCENIC BRANDING CARD -->
            @include('auth.partials.scenic-branding-card')
        </main>

        <footer class="login-footer">
            <span><span class="footer-long">National Highways &amp; Motorway Police · </span>© {{ date('Y') }} NHMP 130</span>
            <span class="helpline"><span>Roadside assistance</span><a href="tel:130"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 2 3 4-2 2c1 3 3 5 6 6l2-2 4 3-1 3C9 19 1 11 2 3l3-1Z"/></svg>130</a></span>
        </footer>
    </div>
</x-guest-layout>

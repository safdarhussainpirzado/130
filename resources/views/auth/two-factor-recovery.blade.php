<x-guest-layout>
    <div class="login-page {{ $errors->any() ? 'has-login-errors' : '' }}" id="nhmp-login">
        <div class="ambient-orb ambient-orb-blue" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb-mint" aria-hidden="true"></div>
        <a class="skip-link" href="#recovery-heading">Skip to emergency recovery</a>

        <header class="login-header">
            <a class="brand" href="{{ route('login') }}" aria-label="NHMP 130 sign in">
                <span class="brand-emblem" aria-hidden="true"><img src="{{ asset('images/login/shield-logo-web.webp') }}" width="68" height="67" alt="" fetchpriority="high" decoding="async"></span>
                <span class="brand-wordmark">NHMP <strong>130</strong><span>National Highways &amp; Motorway Police</span></span>
            </a>
            <div class="service-pill"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2Z"/><path d="m7 9 2 2 4-4"/></svg>Security Gate. Multi-Factor Auth.</div>
        </header>

        <main class="login-main auth-grid-left-form">
            <!-- LEFT COLUMN: FORM CARD -->
            <section class="login-card" aria-labelledby="recovery-heading">
                <div class="card-accent" aria-hidden="true"></div>
                <div class="card-heading">
                    <div class="access-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 14h-2v-2h2zm0-4h-2V7h2z"/></svg>
                    </div>
                    <div>
                        <p class="card-eyebrow">NHMP 130 RECOVERY GATE</p>
                        <h2 id="recovery-heading" tabindex="-1">Account Recovery<span>.</span></h2>
                    </div>
                </div>

                <p class="card-intro">Authenticate using your password and an unused 16-character emergency recovery key.</p>

                <div class="mfa-info-note" style="border-color: #fde68a; background: #fffbeb; color: #92400e;">
                    <strong>Restricted Recovery Mode:</strong> Using a recovery code grants emergency access to your workspace. You will be prompted to re-enroll your authenticator.
                </div>

                @if ($errors->any())
                    <div class="login-alert" id="login-errors" role="alert" tabindex="-1">
                        <strong>Recovery Failed</strong>
                        @foreach ($errors->all() as $error)
                            <span>{{ $error }}</span>
                        @endforeach
                    </div>
                @endif

                <form id="loginForm" method="POST" action="{{ route('2fa.recovery.verify') }}">
                    @csrf
                    <div class="form-field">
                        <label for="password">Account Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
                            <input id="password" 
                                   name="password" 
                                   type="password" 
                                   required 
                                   autocomplete="current-password" 
                                   placeholder="Enter your current password">
                            <button type="button" id="togglePassword" hidden aria-label="Show password" aria-controls="password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 3 16 18"/></svg></button>
                        </div>
                    </div>

                    <div class="form-field">
                        <label for="recovery_code">16-Character Emergency Recovery Code</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m4.93 4.93 14.14 14.14"/></svg>
                            <input id="recovery_code" 
                                   name="recovery_code" 
                                   type="text" 
                                   required 
                                   autofocus 
                                   autocomplete="off" 
                                   placeholder="xxxx-xxxx-xxxx-xxxx"
                                   style="font-family: monospace; font-size: 13px; letter-spacing: 1.5px; text-transform: uppercase;">
                        </div>
                    </div>

                    <!-- 3D BUTTON (Matching Login Page) -->
                    <button type="submit" id="loginButton" class="sign-in-button">
                        <span id="btnText">Verify Recovery Code &amp; Sign In</span>
                        <svg class="submit-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        <span id="loadingSpinner" class="login-spinner" hidden aria-hidden="true"></span>
                    </button>
                    <p id="loginProgress" class="sr-only" role="status" aria-live="polite"></p>

                    <div class="form-options">
                        <a href="{{ route('2fa.challenge') }}">
                            Found your phone? Return to standard authenticator challenge &rarr;
                        </a>
                    </div>
                </form>

                <div class="card-footer">
                    <a href="{{ route('login') }}" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: inherit;">
                        <svg viewBox="0 0 20 20" fill="none" style="width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.5;"><path d="M15 10H5m4-4l-4 4 4 4"/></svg>
                        Back to Login
                    </a>
                    <span>NHMP 130 CRM</span>
                </div>
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

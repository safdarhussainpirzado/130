<x-guest-layout>
    <div class="login-page {{ $errors->any() ? 'has-login-errors' : '' }}" id="nhmp-login">
        <div class="ambient-orb ambient-orb-blue" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb-mint" aria-hidden="true"></div>
        <a class="skip-link" href="#enroll-heading">Skip to enrollment notice</a>

        <header class="login-header">
            <a class="brand" href="{{ route('login') }}" aria-label="NHMP 130 sign in">
                <span class="brand-emblem" aria-hidden="true"><img src="{{ asset('images/login/shield-logo-web.webp') }}" width="68" height="67" alt="" fetchpriority="high" decoding="async"></span>
                <span class="brand-wordmark">NHMP <strong>130</strong><span>National Highways &amp; Motorway Police</span></span>
            </a>
            <div class="service-pill"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2Z"/><path d="m7 9 2 2 4-4"/></svg>Security Gate. Multi-Factor Auth.</div>
        </header>

        <main class="login-main auth-grid-left-form">
            <!-- LEFT COLUMN: FORM CARD -->
            <section class="login-card" aria-labelledby="enroll-heading">
                <div class="card-accent" aria-hidden="true"></div>
                <div class="card-heading">
                    <div class="access-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </div>
                    <div>
                        <p class="card-eyebrow">NHMP 130 ENROLLMENT REQUIRED</p>
                        <h2 id="enroll-heading" tabindex="-1">Enrollment Required<span>.</span></h2>
                    </div>
                </div>

                <p class="card-intro">Your account requires Multi-Factor Authentication before accessing the CRM.</p>

                <!-- User Identity Badge -->
                <div class="user-id-pill">
                    <div class="user-avatar-circle">
                        {{ strtoupper(substr($user->full_name ?? $user->username ?? 'U', 0, 1)) }}
                    </div>
                    <div class="user-meta">
                        <strong>{{ $user->full_name ?? 'NHMP Personnel' }}</strong>
                        <span>{{ $user->username }}</span>
                    </div>
                    <span class="user-security-badge" style="color: #b45309; background: #fef3c7; border-color: #fde68a;">Gate Active</span>
                </div>

                @if ($errors->any())
                    <div class="login-alert" id="login-errors" role="alert" tabindex="-1">
                        <strong>Enrollment Error</strong>
                        @foreach ($errors->all() as $error)
                            <span>{{ $error }}</span>
                        @endforeach
                    </div>
                @endif

                <div class="mfa-info-note">
                    <strong>Zero-Trust Policy:</strong> Per NHMP Cybersecurity Standard, all operational personnel must configure an authenticator app (TOTP) to secure their session.
                </div>

                <!-- 3D BUTTON Link to Setup -->
                <a href="{{ route('2fa.enroll.show') }}" class="sign-in-button" style="text-decoration: none;">
                    <span>Proceed to Authenticator Setup (QR Code)</span>
                    <svg class="submit-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </a>

                <!-- Optional Token Entry Accordion -->
                <details style="margin-top: 14px; padding-top: 10px; border-top: 1px solid #c9d8e97d;">
                    <summary style="cursor: pointer; font-size: 11px; font-weight: 650; color: #4964af; outline: none;">
                        Have an Administrator Authorization Token? (Optional)
                    </summary>
                    <form method="GET" action="{{ route('2fa.enroll.show') }}" style="margin-top: 10px; display: flex; gap: 8px;">
                        <input type="text"
                               name="token"
                               placeholder="64-character token..."
                               style="flex: 1; height: 38px; border: 1px solid #c8d7eb; border-radius: 9px; padding-inline: 10px; font-family: monospace; font-size: 11px; color: #2f4666; background: #fff;">
                        <button type="submit" 
                                class="btn-action-outline" 
                                style="padding-inline: 14px; height: 38px;">
                            Apply
                        </button>
                    </form>
                </details>

                <div class="card-footer">
                    <a href="{{ route('login') }}" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: inherit;">
                        <svg viewBox="0 0 20 20" fill="none" style="width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.5;"><path d="M15 10H5m4-4l-4 4 4 4"/></svg>
                        Cancel &amp; Return to Sign In
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

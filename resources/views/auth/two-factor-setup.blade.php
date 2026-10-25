<x-guest-layout>
    <div class="login-page {{ $errors->any() ? 'has-login-errors' : '' }}" id="nhmp-login">
        <div class="ambient-orb ambient-orb-blue" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb-mint" aria-hidden="true"></div>
        <a class="skip-link" href="#setup-heading">Skip to enrollment</a>

        <header class="login-header">
            <a class="brand" href="{{ route('login') }}" aria-label="NHMP 130 sign in">
                <span class="brand-emblem" aria-hidden="true"><img src="{{ asset('images/login/shield-logo-web.webp') }}" width="68" height="67" alt="" fetchpriority="high" decoding="async"></span>
                <span class="brand-wordmark">NHMP <strong>130</strong><span>National Highways &amp; Motorway Police</span></span>
            </a>
            <div class="service-pill"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2Z"/><path d="m7 9 2 2 4-4"/></svg>Security Gate. Multi-Factor Auth.</div>
        </header>

        <main class="login-main auth-grid-left-form">
            <!-- LEFT COLUMN: FORM CARD -->
            <section class="login-card" aria-labelledby="setup-heading">
                <div class="card-accent" aria-hidden="true"></div>
                <div class="card-heading">
                    <div class="access-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    </div>
                    <div>
                        <p class="card-eyebrow">NHMP 130 TWO-FACTOR SETUP</p>
                        <h2 id="setup-heading" tabindex="-1">Set Up Authenticator<span>.</span></h2>
                    </div>
                </div>

                <p class="card-intro">Scan the QR code with Google Authenticator or enter the manual key below.</p>

                <!-- User Identity Badge -->
                <div class="user-id-pill">
                    <div class="user-avatar-circle">
                        {{ strtoupper(substr($user->full_name ?? $user->username ?? 'U', 0, 1)) }}
                    </div>
                    <div class="user-meta">
                        <strong>{{ $user->full_name ?? 'NHMP Personnel' }}</strong>
                        <span>{{ $user->username }}</span>
                    </div>
                    <span class="user-security-badge" style="color: #2563eb; background: #eff6ff; border-color: #bfdbfe;">Enrollment Gate</span>
                </div>

                @if ($errors->any())
                    <div class="login-alert" id="login-errors" role="alert" tabindex="-1">
                        <strong>Activation Failed</strong>
                        @foreach ($errors->all() as $error)
                            <span>{{ $error }}</span>
                        @endforeach
                    </div>
                @endif

                <!-- QR Code & Secret Key Box -->
                <div class="qr-setup-box">
                    <div class="qr-img-wrapper">
                        @if (!empty($qrCodeSvg))
                            {!! $qrCodeSvg !!}
                        @elseif (!empty($qrCodeDataUri))
                            <img src="{{ $qrCodeDataUri }}" alt="Scan with Authenticator App" style="width: 180px; height: 180px; display: block; margin: 0 auto;">
                        @endif
                    </div>
                    <div class="secret-key-display">
                        <span style="font-size: 10px; font-weight: 700; color: #5d728d; text-transform: uppercase; letter-spacing: 0.5px;">Manual Setup Key:</span>
                        <code class="secret-key-code" id="manualSecretInput">{{ $secret }}</code>
                        <button type="button" 
                                id="btnCopySecret"
                                onclick="copyManualSecret()"
                                class="btn-action-outline"
                                style="width: 100%; padding-block: 6px;">
                            <svg viewBox="0 0 20 20" fill="none" style="width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.5;"><rect x="6" y="6" width="10" height="10" rx="2"/><path d="M4 14V4a2 2 0 0 1 2-2h10"/></svg>
                            <span id="btnCopyText">Copy Key</span>
                        </button>
                    </div>
                </div>

                <!-- 6-Digit Code Confirmation Form -->
                <form id="loginForm" method="POST" action="{{ route('2fa.enroll.confirm') }}">
                    @csrf
                    <div class="form-field">
                        <label for="code">Enter 6-digit confirmation code</label>
                        <div class="input-wrap pin-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
                            <input id="code" 
                                   name="code" 
                                   type="text" 
                                   maxlength="6" 
                                   pattern="[0-9]{6}" 
                                   inputmode="numeric" 
                                   required 
                                   autofocus 
                                   autocomplete="one-time-code" 
                                   placeholder="000 000" 
                                   class="totp-code-input">
                        </div>
                    </div>

                    <!-- 3D BUTTON (Matching Login Page) -->
                    <button type="submit" id="loginButton" class="sign-in-button">
                        <span id="btnText">Verify code &amp; activate 2FA</span>
                        <svg class="submit-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        <span id="loadingSpinner" class="login-spinner" hidden aria-hidden="true"></span>
                    </button>
                    <p id="loginProgress" class="sr-only" role="status" aria-live="polite"></p>
                </form>

                <div class="mfa-info-note" style="margin-top: 14px;">
                    <strong>Instructions:</strong> Open <strong>Google Authenticator</strong> or <strong>Microsoft Authenticator</strong>, tap <strong>+</strong>, scan the QR code, and enter the generated 6-digit token.
                </div>

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

    <!-- Vanilla Javascript Copy Function -->
    <script>
        function copyManualSecret() {
            var secret = "{{ $secret }}";
            var btnText = document.getElementById("btnCopyText");
            
            function showSuccess() {
                if (btnText) {
                    btnText.innerText = "Copied!";
                    setTimeout(function() { btnText.innerText = "Copy Key"; }, 2500);
                }
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(secret).then(showSuccess, function() {
                    fallbackCopySecret(secret, showSuccess);
                });
            } else {
                fallbackCopySecret(secret, showSuccess);
            }
        }

        function fallbackCopySecret(text, callback) {
            var tempInput = document.createElement("input");
            tempInput.value = text;
            document.body.appendChild(tempInput);
            tempInput.select();
            try {
                document.execCommand("copy");
                callback();
            } catch (e) {
                console.error("Copy failed", e);
            }
            document.body.removeChild(tempInput);
        }
    </script>
</x-guest-layout>

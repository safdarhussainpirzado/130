@php
    /** @var \Illuminate\Support\ViewErrorBag $errors */
@endphp
<x-guest-layout>
    <div class="login-page {{ $errors->any() ? 'has-login-errors' : '' }}" id="nhmp-login">
        <div class="ambient-orb ambient-orb-blue" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb-mint" aria-hidden="true"></div>
        <a class="skip-link" href="#forgot-heading">Skip to reset form</a>

        <header class="login-header">
            <a class="brand" href="{{ route('login') }}" aria-label="NHMP 130 sign in">
                <span class="brand-emblem" aria-hidden="true"><img src="{{ asset('images/login/shield-logo-web.webp') }}" width="68" height="67" alt="" fetchpriority="high" decoding="async"></span>
                <span class="brand-wordmark">NHMP <strong>130</strong><span>National Highways &amp; Motorway Police</span></span>
            </a>
            <div class="service-pill"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2Z"/><path d="m7 9 2 2 4-4"/></svg>Security Gate. Identity Recovery.</div>
        </header>

        <main class="login-main auth-grid-left-form">
            <!-- LEFT COLUMN: FORM CARD -->
            <section class="login-card" aria-labelledby="forgot-heading">
                <div class="card-accent" aria-hidden="true"></div>
                <div class="card-heading">
                    <div class="access-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div>
                        <p class="card-eyebrow">NHMP 130 IDENTITY RECOVERY</p>
                        <h2 id="forgot-heading" tabindex="-1">Forgot Password<span>.</span></h2>
                    </div>
                </div>

                <p class="card-intro">Enter your registered email address and we will dispatch a secure password reset link.</p>

                @if (session('status_operator'))
                    <div class="login-status" role="status" style="background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.3); color: #065f46; padding: 14px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 13px; line-height: 1.5;">
                        <div style="font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; color: #10b981;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Request Dispatched to Operation Managers
                        </div>
                        {{ session('status_operator') }}
                    </div>
                @endif

                @if (session('status_op_manager'))
                    <div class="login-status" role="status" style="background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); color: #1e40af; padding: 14px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 13px; line-height: 1.5;">
                        <div style="font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; color: #3b82f6;"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            Peer Authorization Request Submitted
                        </div>
                        {{ session('status_op_manager') }}
                    </div>
                @endif

                @if (session('status_info'))
                    <div class="login-status" role="status" style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.3); color: #92400e; padding: 14px 18px; border-radius: 14px; margin-bottom: 20px; font-size: 13px; line-height: 1.5;">
                        <div style="font-weight: 700; display: flex; align-items: center; gap: 6px; margin-bottom: 4px;">
                            <svg viewBox="0 0 20 20" fill="currentColor" style="width: 16px; height: 16px; color: #f59e0b;"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
                            Pending Request Notice
                        </div>
                        {{ session('status_info') }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="login-status" role="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="login-alert" id="login-errors" role="alert" tabindex="-1">
                        <strong>Reset Request Failed</strong>
                        @foreach ($errors->all() as $error)
                            <span>{{ $error }}</span>
                        @endforeach
                    </div>
                @endif

                <form id="loginForm" method="POST" action="{{ route('password.email') }}">
                    @csrf
                    <div class="form-field">
                        <label for="identifier">Registered Email Address or Username</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                            <input id="identifier" 
                                   name="identifier" 
                                   type="text" 
                                   value="{{ old('identifier', old('email')) }}" 
                                   required 
                                   autofocus 
                                   autocomplete="username" 
                                   placeholder="officer@nhmp.gov.pk or username">
                        </div>
                    </div>

                    <!-- 3D BUTTON (Matching Login Page) -->
                    <button type="submit" id="loginButton" class="sign-in-button">
                        <span id="btnText">Submit Password Reset Request</span>
                        <svg class="submit-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                        <span id="loadingSpinner" class="login-spinner" hidden aria-hidden="true"></span>
                    </button>
                    <p id="loginProgress" class="sr-only" role="status" aria-live="polite"></p>

                    <div class="form-options">
                        <a href="{{ route('login') }}">Remember your password? Sign in &rarr;</a>
                    </div>
                </form>

                <div class="card-footer">
                    <a href="{{ route('login') }}" style="display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: inherit;">
                        <svg viewBox="0 0 20 20" fill="none" style="width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.5;"><path d="M15 10H5m4-4l-4 4 4 4"/></svg>
                        Back to Login
                    </a>
                    <span>NHMP 130 CRM</span>
                </div>
                <p class="access-help">Need access? Contact your system administrator.</p>
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

@php
    /** @var \Illuminate\Support\ViewErrorBag $errors */
@endphp
<x-guest-layout>
    <div class="login-page {{ $errors->any() ? 'has-login-errors' : '' }}" id="nhmp-login">
        <div class="ambient-orb ambient-orb-blue" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb-mint" aria-hidden="true"></div>
        <a class="skip-link" href="#unblock-heading">Skip to unblock form</a>

        <header class="login-header">
            <a class="brand" href="{{ route('login') }}" aria-label="NHMP 130 sign in">
                <span class="brand-emblem" aria-hidden="true"><img src="{{ asset('images/login/shield-logo-web.webp') }}" width="68" height="67" alt="" fetchpriority="high" decoding="async"></span>
                <span class="brand-wordmark">NHMP <strong>130</strong><span>National Highways &amp; Motorway Police</span></span>
            </a>
            <div class="service-pill"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2Z"/><path d="m7 9 2 2 4-4"/></svg>Security Gate. Account Recovery.</div>
        </header>

        <main class="login-main auth-grid-left-form" x-data="{
            showPassword: true,
            cnic: '{{ old('cnic') }}',
            password: '',
            password_confirmation: '',
            formatCnic(val) {
                let v = val.replace(/\D/g, '').slice(0, 13);
                if (v.length > 12) return v.slice(0,5) + '-' + v.slice(5,12) + '-' + v.slice(12);
                if (v.length > 5) return v.slice(0,5) + '-' + v.slice(5);
                return v;
            },
            generateStrongPassword() {
                const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
                const randVals = crypto.getRandomValues(new Uint32Array(16));
                this.password = this.password_confirmation = Array.from(randVals, v => chars[v % chars.length]).join('');
            }
        }">
            <!-- LEFT COLUMN: FORM CARD -->
            <section class="login-card" aria-labelledby="unblock-heading">
                <div class="card-accent" aria-hidden="true"></div>
                <div class="card-heading">
                    <div class="access-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div>
                        <p class="card-eyebrow">NHMP 130 RECOVERY GATE</p>
                        <h2 id="unblock-heading" tabindex="-1">Unblock Account<span>.</span></h2>
                    </div>
                </div>

                <p class="card-intro">To restore system access, verify your identity with your registered CNIC and Email, then establish a new high-entropy password.</p>

                @if ($errors->any())
                    <div class="login-error-banner" role="alert">
                        <div class="error-banner-icon" aria-hidden="true">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        </div>
                        <div class="error-banner-content">
                            <p class="error-banner-title">Identity Verification Failed</p>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('account.unblock.verify') }}" class="login-form">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    <!-- EMAIL INPUT -->
                    <div class="field-block {{ $errors->has('email') ? 'field-block-error' : '' }}">
                        <label for="unblock-email">Registered Email Address</label>
                        <div class="field-control">
                            <input id="unblock-email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="officer@nhmp.gov.pk">
                        </div>
                    </div>

                    <!-- CNIC INPUT -->
                    <div class="field-block {{ $errors->has('cnic') ? 'field-block-error' : '' }}">
                        <label for="unblock-cnic">Official CNIC Number</label>
                        <div class="field-control">
                            <input id="unblock-cnic" type="text" name="cnic" x-model="cnic" @input="cnic = formatCnic($event.target.value)" required placeholder="00000-0000000-0" maxlength="15">
                        </div>
                    </div>

                    <!-- NEW PASSWORD -->
                    <div class="field-block {{ $errors->has('password') ? 'field-block-error' : '' }}">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                            <label for="unblock-password" style="margin: 0;">New System Password</label>
                            <button type="button" @click="generateStrongPassword()" style="background: rgba(59, 130, 246, 0.1); color: #2563eb; border: 1px solid rgba(59, 130, 246, 0.2); padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; cursor: pointer;">
                                Generate Strong
                            </button>
                        </div>
                        <div class="field-control">
                            <input id="unblock-password" :type="showPassword ? 'text' : 'password'" name="password" x-model="password" required placeholder="Minimum 8 characters">
                        </div>
                    </div>

                    <!-- CONFIRM PASSWORD -->
                    <div class="field-block {{ $errors->has('password_confirmation') ? 'field-block-error' : '' }}">
                        <label for="unblock-password-confirm">Confirm New Password</label>
                        <div class="field-control">
                            <input id="unblock-password-confirm" :type="showPassword ? 'text' : 'password'" name="password_confirmation" x-model="password_confirmation" required placeholder="Re-enter password">
                        </div>
                    </div>

                    <div style="margin-top: 24px;">
                        <button type="submit" class="submit-btn" style="width: 100%;">
                            <span>Verify Identity &amp; Unblock</span>
                        </button>
                    </div>

                    <div style="text-align: center; margin-top: 16px;">
                        <a href="{{ route('login') }}" style="color: #64748b; font-size: 13px; font-weight: 600; text-decoration: none;">&larr; Return to Sign In</a>
                    </div>
                </form>
            </section>
        </main>
    </div>
</x-guest-layout>

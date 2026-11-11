@php
    /** @var \Illuminate\Support\ViewErrorBag $errors */
@endphp
<x-guest-layout>
    <div class="login-page {{ $errors->any() ? 'has-login-errors' : '' }}" id="nhmp-login">
        <div class="ambient-orb ambient-orb-blue" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb-mint" aria-hidden="true"></div>
        <a class="skip-link" href="#login-heading">Skip to sign in</a>
        <header class="login-header">
            <a class="brand" href="{{ route('login') }}" aria-label="NHMP 130 sign in">
                <span class="brand-emblem" aria-hidden="true"><img src="{{ asset('images/login/shield-logo-web.webp') }}" width="68" height="67" alt="" fetchpriority="high" decoding="async"></span>
                <span class="brand-wordmark">NHMP <strong>130</strong><span>National Highways &amp; Motorway Police</span></span>
            </a>
            <div class="service-pill"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2Z"/><path d="m7 9 2 2 4-4"/></svg>Here for you. Every mile.</div>
        </header>
        <main class="login-main">
            <section class="service-story" aria-labelledby="story-heading">
                <div class="story-copy">
                    <div class="story-eyebrow"><span aria-hidden="true"></span>PAKISTAN’S MOTORWAY HELPLINE</div>
                    <h1 id="story-heading">Your call.<br><span>Our commitment.</span></h1>
                    <p class="story-intro">A reassuring voice. A coordinated response.<br>A helping hand, when it matters most.</p>
                </div>
                @include('auth.partials.helpline-flow')
                <div class="motorway-story" id="motorway-story" data-paused="false">
                    @include('auth.partials.response-scene')
                    <span class="scene-caption"><span aria-hidden="true">✦</span> On the road. By your side.</span>
                </div>
            </section>
            <section class="login-card" aria-labelledby="login-heading">
                <div class="card-accent" aria-hidden="true"></div>
                <div class="card-heading">
                    <div class="access-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m9 12 2 2 4-4"/></svg></div>
                    <div>
                        <p class="card-eyebrow">NHMP 130 RESPONSE PLATFORM</p>
                        <h2 id="login-heading" tabindex="-1">Welcome back<span>.</span></h2>
                    </div>
                </div>
                <p class="card-intro">Your workspace for safer journeys.</p>
                @if (session('status'))
                    <div class="login-status" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="login-alert" id="login-errors" role="alert" tabindex="-1">
                        <strong>We couldn’t sign you in.</strong>
                        @foreach (collect($errors->getMessages())->except(['username', 'password']) as $messages)
                            @foreach ((array) $messages as $message)<span>{{ $message }}</span>@endforeach
                        @endforeach
                    </div>
                @endif
                <form id="loginForm" method="POST" action="{{ route('login', [], false) }}">
                    @csrf
                    <div class="form-field">
                        <label for="username">System login ID</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8" r="3.5"/><path d="M5 21v-3a7 7 0 0 1 14 0v3"/></svg>
                            <input id="username" name="username" type="text" value="{{ old('username') }}" required @if (!$errors->any()) autofocus @endif autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Enter your assigned ID" aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}" @if ($errors->has('username')) aria-describedby="username-error" @endif>
                        </div>
                        @if ($errors->has('username'))<div id="username-error" class="field-error">@foreach ($errors->get('username') as $message)<p>{{ $message }}</p>@endforeach</div>@endif
                    </div>
                    <div class="form-field">
                        <label for="password">Password</label>
                        <div class="input-wrap">
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
                            <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @if ($errors->has('password')) aria-describedby="password-error" @endif>
                            <button type="button" id="togglePassword" hidden aria-label="Show password" aria-controls="password" aria-pressed="false"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/><path class="eye-slash" d="m4 3 16 18"/></svg></button>
                        </div>
                        @if ($errors->has('password'))<div id="password-error" class="field-error">@foreach ($errors->get('password') as $message)<p>{{ $message }}</p>@endforeach</div>@endif
                    </div>
                    <div class="form-options">
                        <label for="remember_me" class="remember-label"><input id="remember_me" type="checkbox" name="remember" @checked(old('remember'))><span>Remember me</span></label>
                        @if (Route::has('password.request'))<a href="{{ route('password.request') }}">Forgot password?</a>@endif
                    </div>
                    <button type="submit" id="loginButton" class="sign-in-button"><span id="btnText">Sign in to your workspace</span><svg class="submit-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg><span id="loadingSpinner" class="login-spinner" hidden aria-hidden="true"></span></button>
                    <p id="loginProgress" class="sr-only" role="status" aria-live="polite"></p>
                </form>
                <div class="card-footer"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><rect x="5" y="8" width="10" height="9" rx="2"/><path d="M7 8V6a3 3 0 0 1 6 0v2"/></svg>Secure access for authorized personnel</div>
                <p class="access-help">Need access? Contact your system administrator.</p>
            </section>
            <div class="story-controls"><span>Courtesy. Help. Integrity.</span><button id="motionToggle" hidden type="button" aria-controls="motorway-story" aria-pressed="false"><svg class="pause-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M5 4v8M11 4v8"/></svg><svg class="play-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="m6 3 6 5-6 5Z"/></svg><span>Pause motion</span></button></div>
        </main>
        <footer class="login-footer"><span><span class="footer-long">National Highways &amp; Motorway Police · </span>© {{ date('Y') }} NHMP 130</span><span class="helpline"><span>Roadside assistance</span><a href="tel:130"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m5 2 3 4-2 2c1 3 3 5 6 6l2-2 4 3-1 3C9 19 1 11 2 3l3-1Z"/></svg>130</a></span></footer>
    </div>
</x-guest-layout>

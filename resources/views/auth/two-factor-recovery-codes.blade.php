<x-guest-layout>
    <div class="login-page {{ $errors->any() ? 'has-login-errors' : '' }}" id="nhmp-login">
        <div class="ambient-orb ambient-orb-blue" aria-hidden="true"></div>
        <div class="ambient-orb ambient-orb-mint" aria-hidden="true"></div>
        <a class="skip-link" href="#recovery-codes-heading">Skip to emergency recovery codes</a>

        <header class="login-header">
            <a class="brand" href="{{ route('login') }}" aria-label="NHMP 130 sign in">
                <span class="brand-emblem" aria-hidden="true"><img src="{{ asset('images/login/shield-logo-web.webp') }}" width="68" height="67" alt="" fetchpriority="high" decoding="async"></span>
                <span class="brand-wordmark">NHMP <strong>130</strong><span>National Highways &amp; Motorway Police</span></span>
            </a>
            <div class="service-pill"><svg viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m10 2 6 2.5V10c0 4-6 7-6 7s-6-3-6-7V4.5L10 2Z"/><path d="m7 9 2 2 4-4"/></svg>Security Gate. Multi-Factor Auth.</div>
        </header>

        <main class="login-main auth-grid-left-form">
            <!-- LEFT COLUMN: FORM CARD -->
            <section class="login-card" aria-labelledby="recovery-codes-heading">
                <div class="card-accent" aria-hidden="true"></div>
                <div class="card-heading">
                    <div class="access-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div>
                        <p class="card-eyebrow">NHMP 130 RECOVERY KEYS</p>
                        <h2 id="recovery-codes-heading" tabindex="-1">Backup Codes<span>.</span></h2>
                    </div>
                </div>

                <p class="card-intro">Store these 10 one-time emergency backup keys in a secure offline location.</p>

                <!-- Warning Notice -->
                <div class="mfa-info-note" style="border-color: #fde68a; background: #fffbeb; color: #92400e;">
                    <strong>One-Time Display Warning:</strong> These codes allow emergency access if your authenticator device is lost. Each key can be used once and will never be shown again.
                </div>

                <!-- 10 Recovery Codes Grid -->
                <div class="recovery-grid" id="recoveryCodesContainer">
                    @foreach ($recoveryCodes as $index => $code)
                        <div class="recovery-chip">
                            <span>#{{ sprintf('%02d', $index + 1) }}</span>
                            <code>{{ $code }}</code>
                        </div>
                    @endforeach
                </div>

                <!-- Action Buttons: Copy, Download, Print -->
                <div class="action-buttons-row">
                    <button type="button" onclick="handleCopyAll()" class="btn-action-outline" id="btnCopyAll">
                        <svg viewBox="0 0 20 20" fill="none" style="width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.5;"><rect x="6" y="6" width="10" height="10" rx="2"/><path d="M4 14V4a2 2 0 0 1 2-2h10"/></svg>
                        <span id="btnCopyAllText">Copy All</span>
                    </button>
                    <button type="button" onclick="handleDownloadTxt()" class="btn-action-outline">
                        <svg viewBox="0 0 20 20" fill="none" style="width: 13px; height: 13px; stroke: #059669; stroke-width: 1.5;"><path d="M10 3v10m-4-4l4 4 4-4M4 17h12"/></svg>
                        <span>Download</span>
                    </button>
                    <button type="button" onclick="window.print()" class="btn-action-outline">
                        <svg viewBox="0 0 20 20" fill="none" style="width: 13px; height: 13px; stroke: #7c3aed; stroke-width: 1.5;"><path d="M6 9V3h8v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h8v4H6z"/></svg>
                        <span>Print</span>
                    </button>
                </div>

                <!-- Acknowledgment & Proceed Form -->
                <form id="loginForm" method="POST" action="{{ route('2fa.enroll.acknowledge') }}">
                    @csrf
                    <div style="margin-bottom: 14px;">
                        <label class="remember-label" style="align-items: flex-start; gap: 8px;">
                            <input type="checkbox" id="ackCheckbox" required onchange="toggleSubmitButton(this)" style="margin-top: 2px;">
                            <span style="font-size: 11px; color: #435a78; line-height: 1.4;">
                                I have securely copied or downloaded these recovery codes and stored them offline in a safe location.
                            </span>
                        </label>
                    </div>

                    <!-- 3D BUTTON (Disabled until checked) -->
                    <button type="submit" id="btnContinueDashboard" class="sign-in-button" disabled style="opacity: 0.5; cursor: not-allowed;">
                        <span id="btnText">I have saved my codes &rarr; Continue</span>
                        <svg class="submit-arrow" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                    </button>
                </form>

                <div class="card-footer">
                    <span>Emergency Keys Protocol</span>
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

    <!-- Vanilla Javascript Handlers -->
    <script>
        const rawCodes = @json($recoveryCodes);

        function toggleSubmitButton(cb) {
            const btn = document.getElementById('btnContinueDashboard');
            if (cb.checked) {
                btn.disabled = false;
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
            } else {
                btn.disabled = true;
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
            }
        }

        function handleCopyAll() {
            const text = "NHMP 130 Emergency Recovery Codes\nGenerated: " + new Date().toISOString() + "\n\n" + rawCodes.join("\n");
            const btnText = document.getElementById("btnCopyAllText");

            function showCopied() {
                if (btnText) {
                    btnText.innerText = "Copied!";
                    setTimeout(() => { btnText.innerText = "Copy All"; }, 2500);
                }
            }

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(showCopied, () => fallbackCopy(text, showCopied));
            } else {
                fallbackCopy(text, showCopied);
            }
        }

        function fallbackCopy(text, cb) {
            const temp = document.createElement("textarea");
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            try { document.execCommand("copy"); cb(); } catch(e){}
            document.body.removeChild(temp);
        }

        function handleDownloadTxt() {
            const content = "=====================================================\n"
                + "NHMP 130 HELPLINE CRM - EMERGENCY RECOVERY CODES\n"
                + "Account: {{ auth()->user()->username ?? 'Operator' }}\n"
                + "Generated: " + new Date().toLocaleString() + "\n"
                + "=====================================================\n\n"
                + "Each recovery code can only be used once.\n\n"
                + rawCodes.map((c, i) => `#${(i+1).toString().padStart(2, '0')}: ${c}`).join("\n")
                + "\n\nKeep this document confidential and offline.";

            const blob = new Blob([content], { type: "text/plain;charset=utf-8" });
            const url = URL.createObjectURL(blob);
            const a = document.createElement("a");
            a.href = url;
            a.download = "NHMP-130-Recovery-Codes-" + Date.now() + ".txt";
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</x-guest-layout>

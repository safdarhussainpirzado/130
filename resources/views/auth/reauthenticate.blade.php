<x-guest-layout>
    <div class="min-h-screen bg-[#0B132B] flex items-center justify-center p-6 relative overflow-hidden font-inter text-slate-100">
        <!-- Ambient Background Glows -->
        <div class="absolute -right-24 -top-24 w-96 h-96 bg-purple-600 rounded-full blur-[140px] opacity-20 pointer-events-none"></div>
        <div class="absolute -left-24 -bottom-24 w-96 h-96 bg-blue-900 rounded-full blur-[140px] opacity-25 pointer-events-none"></div>

        <div class="w-full max-w-lg relative z-10">
            <div class="bg-[#1C2541]/90 backdrop-blur-xl border border-slate-700/60 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
                <!-- Header -->
                <div class="flex items-center space-x-4 pb-6 border-b border-slate-700/60">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-purple-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-purple-500/30">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-purple-400">Step-Up Freshness Gate</span>
                        <h1 class="text-xl font-black text-white">Confirm Your Identity</h1>
                    </div>
                </div>

                <div class="mt-6 p-4 rounded-2xl bg-purple-500/10 border border-purple-500/20 text-purple-200 text-xs leading-relaxed">
                    <strong class="text-white block font-bold mb-1">Protected Operation Verification:</strong>
                    You are accessing a security-critical function (<code class="text-cyan-300 font-mono">{{ $purpose }}</code>). Please re-authenticate your current password and fresh MFA code to continue.
                </div>

                @if ($errors->any())
                    <div class="mt-4 p-4 rounded-2xl bg-red-500/15 border border-red-500/30 text-red-200 text-xs">
                        <ul class="list-disc list-inside space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('auth.reauthenticate.verify') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="password" class="text-[11px] font-bold uppercase tracking-wider text-slate-300 block mb-1">
                            Current Password
                        </label>
                        <input type="password"
                               name="password"
                               id="password"
                               required
                               autocomplete="current-password"
                               class="w-full bg-[#0B132B] border border-slate-700 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>

                    @if (!$requiresWebAuthn)
                        <div>
                            <label for="code" class="text-[11px] font-bold uppercase tracking-wider text-slate-300 block mb-1">
                                6-Digit Authenticator Code (TOTP)
                            </label>
                            <input type="text"
                                   name="code"
                                   id="code"
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   inputmode="numeric"
                                   required
                                   autocomplete="one-time-code"
                                   placeholder="123456"
                                   class="w-full bg-[#0B132B] border border-slate-700 rounded-xl px-4 py-3 text-center text-lg font-mono tracking-widest text-cyan-300 focus:outline-none focus:ring-2 focus:ring-purple-500">
                        </div>
                    @endif

                    <button type="submit" class="w-full py-3.5 px-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-lg shadow-purple-600/30 transition-all">
                        Confirm &amp; Proceed &rarr;
                    </button>
                </form>

                <div class="mt-8 pt-6 border-t border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                    <a href="javascript:history.back()" class="hover:text-white transition-colors">
                        &larr; Cancel and return
                    </a>
                    <span class="text-[10px] uppercase font-mono tracking-widest text-slate-500">
                        NHMP 130 Helpline Security
                    </span>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>

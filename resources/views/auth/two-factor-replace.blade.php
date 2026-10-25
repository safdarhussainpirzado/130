@extends('layouts.app')

@section('title', 'Change Authenticator Device - NHMP 130')
@section('page-title', 'Pair New Authenticator Device')

@section('content')
<div class="py-8 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 font-sans">
    @if ($isRecoveryRestricted)
        <div class="mb-6 p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-sm flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-lg flex-shrink-0"></i>
            <div>
                <strong>Recovery Mode Active:</strong> You signed in using an emergency recovery code. To ensure your account remains safe, please pair your new phone below to regain full access.
            </div>
        </div>
    @endif

    <div class="bg-white border border-slate-200 rounded-3xl p-6 sm:p-8 shadow-sm" x-data="{
        copied: false,
        copySecret() {
            navigator.clipboard.writeText('{{ $secret }}');
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        }
    }">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-100 gap-4">
            <div>
                <span class="text-xs font-bold text-blue-700 uppercase tracking-wider">Device Setup</span>
                <h1 class="text-2xl font-bold text-slate-900 mt-1">Connect New Authenticator App</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Scan the QR code below on your new phone using Google Authenticator or Microsoft Authenticator.
                </p>
            </div>
            <span class="px-3 py-1 bg-slate-100 text-slate-700 rounded-full text-xs font-mono font-bold self-start sm:self-auto">
                {{ $user->username }}
            </span>
        </div>

        @if ($errors->any())
            <div class="mt-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                <ul class="list-disc list-inside space-y-1 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
            <!-- QR Code Display -->
            <div class="flex flex-col items-center p-6 bg-slate-50 rounded-2xl border border-slate-200">
                <img src="{{ $qrCodeDataUri }}" alt="Scan with Authenticator App" class="w-48 h-48 select-none bg-white p-2 rounded-xl shadow-sm">
                <span class="text-xs font-semibold text-slate-600 mt-3 flex items-center gap-1.5">
                    <i class="fa-solid fa-qrcode text-blue-700"></i> Scan with your phone
                </span>
            </div>

            <!-- Confirmation Form -->
            <form method="POST" action="{{ route('2fa.replace.confirm') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-600 block mb-1">
                        Can't scan? Use manual setup key:
                    </label>
                    <div class="flex items-center space-x-2">
                        <input type="text" readonly value="{{ $secret }}" class="flex-1 bg-slate-50 border border-slate-200 rounded-xl px-3 py-2 text-xs font-mono text-blue-900 font-bold select-all focus:outline-none">
                        <button type="button" @click="copySecret" class="px-3 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-bold rounded-xl transition-all">
                            <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                        </button>
                    </div>
                </div>

                <div>
                    <label for="password" class="text-xs font-bold uppercase tracking-wider text-slate-700 block mb-1">
                        Current Account Password
                    </label>
                    <input type="password"
                           name="password"
                           id="password"
                           required
                           autocomplete="current-password"
                           placeholder="Enter your current password"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm text-slate-800 focus:outline-none focus:border-blue-600 focus:bg-white font-medium">
                </div>

                <div>
                    <label for="code" class="text-xs font-bold uppercase tracking-wider text-slate-700 block mb-1">
                        6-Digit Code from New Phone
                    </label>
                    <input type="text"
                           name="code"
                           id="code"
                           maxlength="6"
                           pattern="[0-9]{6}"
                           inputmode="numeric"
                           required
                           placeholder="123456"
                           class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-center text-xl font-mono tracking-widest text-blue-900 font-bold focus:outline-none focus:border-blue-600 focus:bg-white">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-3 px-4 bg-blue-900 hover:bg-blue-800 text-white rounded-xl text-xs font-bold uppercase tracking-wider shadow-sm transition-all">
                        Confirm &amp; Connect Device
                    </button>
                </div>
            </form>
        </div>

        <div class="mt-8 pt-6 border-t border-slate-100 text-xs text-slate-500">
            <p>
                <strong>Note:</strong> Connecting a new phone will automatically generate fresh emergency recovery codes and sign out any other devices for your security.
            </p>
        </div>
    </div>
</div>
@endsection

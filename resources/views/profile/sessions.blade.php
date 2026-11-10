@extends('layouts.app')

@section('title', 'Active Sessions - NHMP 130')
@section('page-title', 'Active Sessions & Device Security')

@section('content')
<div class="py-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 font-inter">
    <!-- Status / Alert Banners -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-xs flex items-center justify-between">
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-red-500/15 border border-red-500/30 text-red-200 text-xs">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-8">
        <!-- Security Assurance Overview Card -->
        <div class="bg-[#1C2541] border border-slate-700/60 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-700/60">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-cyan-400">Account Session Security</span>
                    <h2 class="text-xl font-black text-white mt-1">Active Login Sessions</h2>
                    <p class="text-xs text-slate-400 mt-1">Manage active sessions across browsers and workstations.</p>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="px-4 py-2 bg-[#0B132B] rounded-2xl border border-slate-700/60 text-right">
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 block font-bold">Security Version</span>
                        <span class="text-sm font-mono font-black text-cyan-300">v{{ $securityVersion }}</span>
                    </div>
                    <div class="px-4 py-2 bg-[#0B132B] rounded-2xl border border-slate-700/60 text-right">
                        <span class="text-[9px] uppercase tracking-wider text-slate-400 block font-bold">MFA Factor</span>
                        <span class="text-sm font-mono font-black uppercase {{ $authMethod === 'webauthn' ? 'text-emerald-400' : 'text-blue-400' }}">
                            {{ $authMethod }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Sessions Table -->
            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-700/60 text-slate-400 text-[10px] uppercase tracking-wider font-bold">
                            <th class="py-3 px-4">Device / Client</th>
                            <th class="py-3 px-4">IP Address</th>
                            <th class="py-3 px-4">Last Activity</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @forelse ($sessions as $session)
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-4 px-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 rounded-lg bg-slate-800 flex items-center justify-center text-slate-300 flex-shrink-0">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <span class="font-medium text-white block max-w-xs truncate" title="{{ $session['user_agent'] }}">
                                                {{ $session['user_agent'] ?: 'Unknown Browser' }}
                                            </span>
                                            <span class="text-[10px] text-slate-500 font-mono">
                                                HMAC: {{ substr($session['masked_id'], 0, 16) }}...
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4 font-mono text-cyan-300">
                                    {{ $session['ip_address'] }}
                                </td>
                                <td class="py-4 px-4 text-slate-400">
                                    {{ $session['last_activity'] }}
                                </td>
                                <td class="py-4 px-4">
                                    @if ($session['is_current'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            This Device
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-700/60 text-slate-300">
                                            Active
                                        </span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-right">
                                    @if (!$session['is_current'])
                                        <form method="POST" action="{{ route('account.sessions.revoke', $session['masked_id']) }}" onsubmit="return confirm('Revoke this session immediately?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-400 hover:text-red-300 font-bold hover:underline">
                                                Revoke
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-500">Current</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-500">
                                    No external session records found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Terminate All Other Sessions Card -->
        <div class="bg-[#1C2541] border border-red-500/30 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="flex items-center space-x-4 pb-6 border-b border-slate-700/60">
                <div class="w-10 h-10 rounded-xl bg-red-500/20 text-red-400 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Log Out Other Browser Sessions</h3>
                    <p class="text-xs text-slate-400">Terminates all other logged-in sessions by incrementing your account security version.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('account.sessions.logout-other') }}" class="mt-6 flex flex-col sm:flex-row items-center gap-4">
                @csrf
                <div class="w-full sm:w-80">
                    <input type="password"
                           name="password"
                           required
                           placeholder="Enter password to confirm..."
                           class="w-full bg-[#0B132B] border border-slate-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-red-600 hover:bg-red-500 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-lg shadow-red-600/30 transition-all">
                    Terminate All Other Sessions
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

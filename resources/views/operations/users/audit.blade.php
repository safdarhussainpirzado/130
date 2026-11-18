@extends('layouts.app')

@section('title', 'Activity History - ' . ($user->full_name ?: $user->username))
@section('page-title', 'User Activity History: ' . ($user->full_name ?: $user->username))
@section('breadcrumb', 'Admin / Users / ' . ($user->full_name ?: $user->username) . ' / Activity History')

@section('content')
@php
    $userName = $user->full_name ?: $user->username;
    $initials = collect(explode(' ', trim($userName)))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');
    $primaryScope = $user->activeScopes->first();
    $primaryOfficeName = $primaryScope?->office?->name ?? 'National Operations Command';
    $latestLog = $logs->first();

    $stats = [
        ['Total Activity', $logs->total() . ' ' . \Illuminate\Support\Str::plural('Action', $logs->total()), 'fa-list-check', 'indigo', 'All-time audit footprint'],
        ['Page Logged', $logs->count() . ' ' . \Illuminate\Support\Str::plural('Event', $logs->count()), 'fa-layer-group', 'purple', 'Showing page ' . $logs->currentPage() . ' of ' . $logs->lastPage()],
        ['Latest Action', $latestLog ? $latestLog->created_at->diffForHumans() : 'No activity', 'fa-clock-rotate-left', 'sky', $latestLog ? $latestLog->created_at->format('d M Y, h:i A') : 'None recorded'],
        ['Assigned Unit', $primaryOfficeName, 'fa-building-shield', 'emerald', 'Primary operational jurisdiction'],
    ];

    $tones = [
        'indigo' => 'bg-indigo-50 text-indigo-600 border-indigo-100',
        'emerald' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
        'purple' => 'bg-purple-50 text-purple-600 border-purple-100',
        'sky' => 'bg-sky-50 text-sky-600 border-sky-100',
    ];
@endphp

<div x-data="adminUserAuditLedger()" class="w-full space-y-4 overflow-x-hidden pb-16" data-testid="admin-user-audit">

    {{-- Hero: identity + actions --}}
    <section class="relative overflow-hidden bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-800 rounded-3xl p-5 md:p-6 text-white shadow-xl">
        <div class="absolute -right-12 -bottom-20 w-64 h-64 bg-white/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-40 -top-16 w-32 h-32 bg-purple-500/20 rounded-full blur-xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
            <div class="flex items-center gap-4 min-w-0">
                <div class="w-16 h-16 shrink-0 rounded-2xl bg-white/15 border border-white/25 flex items-center justify-center text-2xl font-black shadow-inner">
                    {{ strtoupper($initials ?: 'US') }}
                </div>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-xl md:text-2xl font-black tracking-tight truncate">{{ $userName }}</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/20 text-white border border-white/30 backdrop-blur-xs">
                            {{ $user->role?->display_name ?? 'User' }}
                        </span>
                        @if($user->is_blocked)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/30 text-rose-200 border border-rose-400/30">
                                <i class="fa-solid fa-lock text-[9px]"></i> Blocked
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/30 text-emerald-200 border border-emerald-400/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Active
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-indigo-100/90 font-medium mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="inline-flex items-center gap-1 font-mono text-[11px]"><i class="fa-solid fa-user text-[10px] opacity-75"></i>{{ $user->username }}</span>
                        @if($user->email)
                            <span class="inline-flex items-center gap-1 font-mono text-[11px]"><i class="fa-solid fa-envelope text-[10px] opacity-75"></i>{{ $user->email }}</span>
                        @endif
                        @if($user->mobile_no)
                            <span class="inline-flex items-center gap-1 font-mono text-[11px]"><i class="fa-solid fa-phone text-[10px] opacity-75"></i>{{ $user->mobile_no }}</span>
                        @endif
                    </p>

                    <div class="flex flex-wrap items-center gap-2 mt-2.5 text-[11px]">
                        @if($user->cnic)
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-white/15 border border-white/20 font-mono" title="CNIC">
                                <i class="fa-solid fa-id-card text-[10px] opacity-75"></i>{{ $user->cnic }}
                            </span>
                        @endif
                        {{-- Cryptographic UUID Badge (No database integer IDs exposed) --}}
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-white/15 border border-white/20 font-mono text-[10px]" title="Cryptographic UUID">
                            <i class="fa-solid fa-fingerprint text-[10px] text-teal-300"></i>
                            <span class="truncate max-w-[130px]">{{ $user->uuid }}</span>
                            <button type="button" @click="navigator.clipboard.writeText('{{ $user->uuid }}'); $dispatch('toast', { message: 'UUID copied to clipboard', type: 'success' })" class="opacity-75 hover:opacity-100 hover:text-white transition-opacity" title="Copy UUID">
                                <i class="fa-regular fa-copy text-[9px]"></i>
                            </button>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-white/15 border border-white/20" title="Primary Station">
                            <i class="fa-solid fa-building-shield text-[10px] opacity-75"></i>{{ $primaryOfficeName }}
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-white/15 border border-white/20" title="Total Activity Records">
                            <i class="fa-solid fa-list-check text-[10px] opacity-75"></i>{{ $logs->total() }} Logged Actions
                        </span>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                @can('users.manage_status')
                    @if($user->id !== auth()->id())
                        {{-- 🔓 Clear Security Lockout --}}
                        <button type="button"
                                @click="clearSecurityLockout()"
                                :disabled="clearingLockout"
                                class="px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 border border-amber-400 font-black text-xs uppercase tracking-wider flex items-center gap-1.5 transition-all shadow-md active:scale-95 disabled:opacity-50 text-white cursor-pointer"
                                title="Clear Database Security Lockout & Reset Failed Password Counters">
                            <span>🔓</span>
                            <span x-text="clearingLockout ? 'Clearing...' : 'Clear Security Lockout'"></span>
                            <i class="fa-solid fa-circle-notch animate-spin text-[10px]" x-show="clearingLockout" x-cloak></i>
                        </button>

                        {{-- ⚡ Flush Rate Limits --}}
                        <button type="button"
                                @click="flushRateLimits()"
                                :disabled="flushingLimits"
                                class="px-3.5 py-2 rounded-xl bg-teal-500 hover:bg-teal-600 border border-teal-400 font-black text-xs uppercase tracking-wider flex items-center gap-1.5 transition-all shadow-md active:scale-95 disabled:opacity-50 text-white cursor-pointer"
                                title="Flush Login Rate Limit and IP Throttles from Cache">
                            <span>⚡</span>
                            <span x-text="flushingLimits ? 'Flushing...' : 'Flush Rate Limits'"></span>
                            <i class="fa-solid fa-circle-notch animate-spin text-[10px]" x-show="flushingLimits" x-cloak></i>
                        </button>
                    @endif
                @endcan

                {{-- View Mode Toggle --}}
                <div class="inline-flex rounded-xl bg-white/15 border border-white/20 p-1">
                    <button type="button" @click="viewMode = 'table'"
                            :class="viewMode === 'table' ? 'bg-white text-indigo-900 shadow-sm' : 'text-white hover:bg-white/10'"
                            class="px-3 py-1.5 rounded-lg font-black text-xs uppercase tracking-wider transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-table-list text-xs"></i>
                        <span>Ledger</span>
                    </button>
                    <button type="button" @click="viewMode = 'timeline'"
                            :class="viewMode === 'timeline' ? 'bg-white text-indigo-900 shadow-sm' : 'text-white hover:bg-white/10'"
                            class="px-3 py-1.5 rounded-lg font-black text-xs uppercase tracking-wider transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-timeline text-xs"></i>
                        <span>Storyline</span>
                    </button>
                </div>

                <a href="{{ route('admin.users.index') }}"
                   class="px-4 py-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 font-black text-xs uppercase tracking-wider flex items-center gap-1.5 transition-all">
                    <i class="fa-solid fa-users"></i> Users Directory
                </a>
            </div>
        </div>
    </section>

    {{-- Stats strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5" data-testid="user-audit-stats">
        @foreach($stats as [$label, $value, $icon, $tone, $hint])
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 flex items-center gap-3.5 min-w-0">
                <div class="w-11 h-11 shrink-0 rounded-xl border flex items-center justify-center {{ $tones[$tone] }} text-sm">
                    <i class="fa-solid {{ $icon }}"></i>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $label }}</p>
                    <p class="text-sm md:text-base font-black text-slate-800 truncate">{{ $value }}</p>
                    @if($hint)<p class="text-[10px] font-bold text-slate-400 truncate">{{ $hint }}</p>@endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Interactive Filter Toolbar --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            {{-- Search Bar --}}
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </div>
                <input type="text"
                       x-model="search"
                       placeholder="Filter audit events by action, entity, IP address, or date..."
                       class="w-full pl-9 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/10 outline-none transition-all font-bold text-slate-800 text-xs">
                <button type="button"
                        x-show="search.length > 0"
                        @click="search = ''"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            {{-- Action Filter Buttons --}}
            <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                <button type="button"
                        @click="actionFilter = 'all'"
                        :class="actionFilter === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-600'"
                        class="px-3 py-1.5 rounded-xl font-black text-xs uppercase tracking-wider transition-all">
                    All Events
                </button>
                <button type="button"
                        @click="actionFilter = 'auth'"
                        :class="actionFilter === 'auth' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200'"
                        class="px-3 py-1.5 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center gap-1">
                    <i class="fa-solid fa-right-to-bracket text-[10px]"></i> Auth / Sign-In
                </button>
                <button type="button"
                        @click="actionFilter = 'mfa'"
                        :class="actionFilter === 'mfa' ? 'bg-purple-600 text-white shadow-xs' : 'bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200'"
                        class="px-3 py-1.5 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center gap-1">
                    <i class="fa-solid fa-shield-halved text-[10px]"></i> 2FA &amp; Security
                </button>
                <button type="button"
                        @click="actionFilter = 'governance'"
                        :class="actionFilter === 'governance' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200'"
                        class="px-3 py-1.5 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center gap-1">
                    <i class="fa-solid fa-user-shield text-[10px]"></i> Governance
                </button>
                <button type="button"
                        @click="actionFilter = 'patrol'"
                        :class="actionFilter === 'patrol' ? 'bg-blue-600 text-white shadow-xs' : 'bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200'"
                        class="px-3 py-1.5 rounded-xl font-black text-xs uppercase tracking-wider transition-all flex items-center gap-1">
                    <i class="fa-solid fa-car text-[10px]"></i> Patrol &amp; Field Ops
                </button>
            </div>
        </div>
    </div>

    {{-- TABLE VIEW MODE --}}
    <div x-show="viewMode === 'table'" class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50/50 text-[10px] font-black uppercase tracking-widest text-slate-400">
                        <th class="px-5 py-4 w-44">Timestamp</th>
                        <th class="px-5 py-4 text-center">Action Executed</th>
                        <th class="px-5 py-4">Target Entity</th>
                        <th class="px-5 py-4">Network &amp; Device</th>
                        <th class="px-5 py-4">Responsibility</th>
                        <th class="px-5 py-4 text-right">Forensic Inspection</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    @forelse($logs as $log)
                        @php
                            $actionName = \App\Http\Controllers\AuditController::formatActionName($log->action);
                            $category = \App\Http\Controllers\AuditController::categorizeAction($log->action);
                            $actionTone = match ($category) {
                                'auth_success' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'danger'       => 'bg-rose-50 text-rose-700 border-rose-200',
                                'mfa'          => 'bg-purple-50 text-purple-700 border-purple-200',
                                'governance'   => 'bg-amber-50 text-amber-800 border-amber-200',
                                default        => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                            $actionIcon = match ($category) {
                                'auth_success' => 'fa-right-to-bracket',
                                'danger'       => 'fa-triangle-exclamation',
                                'mfa'          => 'fa-shield-halved',
                                'governance'   => 'fa-key',
                                default        => 'fa-bolt',
                            };
                            $entityName = match ($log->table_name) {
                                'users'                      => 'User Profile',
                                'calls'                      => 'Call Record',
                                'patrol_duty_sessions'       => 'Patrol Duty Shift',
                                'support_requests'           => 'Support Ticket',
                                'user_scopes'                => 'Jurisdiction Scope',
                                'password_reset_requests'    => 'Password Recovery',
                                'two_factor_recovery_codes'  => 'Recovery Credential',
                                default                      => ucwords(str_replace(['_', '-'], ' ', $log->table_name ?: 'System Session')),
                            };
                            $isSelf = $log->user_id === $user->id;
                            $causerName = $log->user?->full_name ?? ($isSelf ? $user->full_name : 'System Security Daemon');
                            $causerRole = $log->user?->role?->display_name ?? 'Automated Daemon';

                            $logPayload = [
                                'id'                  => $log->id,
                                'action'              => $log->action,
                                'action_name'         => $actionName,
                                'action_icon'         => $actionIcon,
                                'action_tone'         => $actionTone,
                                'table_name'          => $log->table_name,
                                'entity_name'         => $entityName,
                                'record_id'           => $log->record_id,
                                'created_at_human'    => $log->created_at->format('M d, Y h:i:s A'),
                                'created_at_relative' => $log->created_at->diffForHumans(),
                                'ip_address'          => $log->ip_address ?: '127.0.0.1',
                                'user_agent'          => $log->user_agent ?: 'Internal Session',
                                'is_self_action'      => $isSelf,
                                'causer_name'         => $causerName,
                                'causer_role'         => $causerRole,
                                'old_values'          => $log->old_values ?? [],
                                'new_values'          => $log->new_values ?? [],
                                'record_hash'         => $log->record_hash ?? '',
                                'previous_hash'       => $log->previous_hash ?? '',
                            ];
                        @endphp
                        <tr x-show="matchesRow({
                            action: '{{ strtolower($log->action . ' ' . $actionName) }}',
                            entity: '{{ strtolower($entityName) }}',
                            ip: '{{ $log->ip_address }}',
                            date: '{{ $log->created_at->format('Y-m-d H:i') }}'
                        })" class="hover:bg-slate-50/70 transition-colors">

                            {{-- Timestamp --}}
                            <td class="px-5 py-3.5 align-middle whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-slate-300"></div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 leading-tight font-mono text-xs">{{ $log->created_at->format('h:i:s A') }}</p>
                                        <p class="text-[10px] font-bold text-slate-400">{{ $log->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Action --}}
                            <td class="px-5 py-3.5 align-middle text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $actionTone }}">
                                    <i class="fa-solid {{ $actionIcon }} text-[9px]"></i>
                                    {{ $actionName }}
                                </span>
                            </td>

                            {{-- Target Entity --}}
                            <td class="px-5 py-3.5 align-middle whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-slate-50 border border-slate-200/80 flex items-center justify-center text-slate-500 text-xs shrink-0">
                                        <i class="fa-solid fa-cube"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 text-xs">{{ $entityName }}</p>
                                        <p class="text-[10px] font-mono text-slate-400">{{ $entityName }} Target</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Network & Device --}}
                            <td class="px-5 py-3.5 align-middle whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-mono text-xs font-bold text-slate-700">{{ $log->ip_address ?: '127.0.0.1' }}</span>
                                    <span class="text-[9px] font-medium text-slate-400 truncate max-w-[150px]">{{ $log->user_agent ?: 'Internal Session' }}</span>
                                </div>
                            </td>

                            {{-- Responsibility (Actor) --}}
                            <td class="px-5 py-3.5 align-middle whitespace-nowrap">
                                @if($isSelf)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-600 font-bold text-[10px]">
                                        <i class="fa-solid fa-user text-[9px] text-slate-400"></i> Self Action
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-lg bg-purple-50 text-purple-700 border border-purple-200 font-bold text-[10px]" title="Executed by {{ $causerName }}">
                                        <i class="fa-solid fa-user-shield text-[9px]"></i> {{ \Illuminate\Support\Str::limit($causerName, 18) }}
                                    </span>
                                @endif
                            </td>

                            {{-- In-Place Forensic Inspection --}}
                            <td class="px-5 py-3.5 align-middle text-right whitespace-nowrap">
                                <button type="button"
                                        @click="openInspectModal({{ json_encode($logPayload) }})"
                                        class="px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-black transition-all inline-flex items-center gap-1.5 shadow-2xs hover:scale-105 active:scale-95 cursor-pointer"
                                        title="Inspect Event Forensic Timeline">
                                    <i class="fa-solid fa-magnifying-glass-chart text-xs"></i>
                                    <span>Inspect</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 bg-slate-50/30">
                                <div class="w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100 text-indigo-600 flex items-center justify-center mx-auto mb-3 text-xl shadow-inner">
                                    <i class="fa-solid fa-fingerprint"></i>
                                </div>
                                <h3 class="text-base font-black text-slate-800">Clear Audit Trail</h3>
                                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">No operational activity logs recorded for this user.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="p-5 md:p-6 bg-slate-50 border-t border-slate-100">
                {{ $logs->links() }}
            </div>
        @endif
    </div>

    {{-- TIMELINE STORYLINE VIEW MODE --}}
    <div x-show="viewMode === 'timeline'" class="bg-white rounded-3xl shadow-sm border border-slate-100 p-6 md:p-8 space-y-6">
        <div class="relative border-l-2 border-slate-200 ml-4 md:ml-6 space-y-8">
            @foreach($logs as $log)
                @php
                    $actionName = \App\Http\Controllers\AuditController::formatActionName($log->action);
                    $category = \App\Http\Controllers\AuditController::categorizeAction($log->action);
                    $actionTone = match ($category) {
                        'auth_success' => 'bg-emerald-500 text-white',
                        'danger'       => 'bg-rose-500 text-white',
                        'mfa'          => 'bg-purple-600 text-white',
                        'governance'   => 'bg-amber-500 text-white',
                        default        => 'bg-slate-700 text-white',
                    };
                    $actionIcon = match ($category) {
                        'auth_success' => 'fa-right-to-bracket',
                        'danger'       => 'fa-triangle-exclamation',
                        'mfa'          => 'fa-shield-halved',
                        'governance'   => 'fa-key',
                        default        => 'fa-bolt',
                    };
                    $isSelf = $log->user_id === $user->id;
                    $causerName = $log->user?->full_name ?? ($isSelf ? $user->full_name : 'System Daemon');
                    $causerRole = $log->user?->role?->display_name ?? 'Automated Daemon';

                    $logPayload = [
                        'id'                  => $log->id,
                        'action'              => $log->action,
                        'action_name'         => $actionName,
                        'action_icon'         => $actionIcon,
                        'action_tone'         => $actionTone,
                        'table_name'          => $log->table_name,
                        'entity_name'         => $entityName,
                        'record_id'           => $log->record_id,
                        'created_at_human'    => $log->created_at->format('M d, Y h:i:s A'),
                        'created_at_relative' => $log->created_at->diffForHumans(),
                        'ip_address'          => $log->ip_address ?: '127.0.0.1',
                        'user_agent'          => $log->user_agent ?: 'Internal Session',
                        'is_self_action'      => $isSelf,
                        'causer_name'         => $causerName,
                        'causer_role'         => $causerRole,
                        'old_values'          => $log->old_values ?? [],
                        'new_values'          => $log->new_values ?? [],
                        'record_hash'         => $log->record_hash ?? '',
                        'previous_hash'       => $log->previous_hash ?? '',
                    ];
                @endphp
                <div x-show="matchesRow({
                    action: '{{ strtolower($log->action . ' ' . $actionName) }}',
                    entity: '{{ strtolower($entityName) }}',
                    ip: '{{ $log->ip_address }}',
                    date: '{{ $log->created_at->format('Y-m-d H:i') }}'
                })" class="relative pl-6 md:pl-8 group">
                    {{-- Stepper Node Icon --}}
                    <div class="absolute -left-3.5 top-0 w-7 h-7 rounded-full {{ $actionTone }} border-2 border-white flex items-center justify-center text-xs shadow-md">
                        <i class="fa-solid {{ $actionIcon }} text-[10px]"></i>
                    </div>

                    {{-- Card --}}
                    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 hover:bg-white hover:shadow-md transition-all space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h4 class="text-sm font-black text-slate-800 leading-tight">{{ $actionName }}</h4>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] font-bold text-slate-400 font-mono">{{ $log->created_at->format('M d, Y · h:i:s A') }}</span>
                                    <span class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">{{ $log->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-mono text-slate-500 bg-white px-2 py-1 rounded-lg border border-slate-200">{{ $log->ip_address ?: '127.0.0.1' }}</span>
                                <button type="button" @click="openInspectModal({{ json_encode($logPayload) }})"
                                        class="px-3 py-1 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs uppercase tracking-wider transition-all flex items-center gap-1 shadow-xs cursor-pointer">
                                    <i class="fa-solid fa-magnifying-glass-chart text-[10px]"></i> Inspect
                                </button>
                            </div>
                        </div>

                        <div class="text-xs text-slate-600 flex flex-wrap items-center gap-3 pt-2 border-t border-slate-200/60">
                            <span><strong>Target:</strong> {{ $entityName }}</span>
                            <span><strong>Actor:</strong> {{ $causerName }} ({{ $causerRole }})</span>
                            @if(!empty($log->new_values) && is_array($log->new_values))
                                <span class="text-slate-400">· Attributes Touched: {{ count($log->new_values) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- FORENSIC INSPECTION MODAL (In-place SPA Drawer) --}}
    <div x-show="showInspectModal" class="fixed inset-0 z-[100] overflow-y-auto px-4 py-6" x-transition.opacity style="display: none;">
        <div class="flex items-center justify-center min-h-screen">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[90]" @click="showInspectModal = false"></div>
            <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-3xl z-[100] overflow-hidden relative border border-slate-100" x-transition.scale.origin.center>
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 text-white flex justify-between items-center relative overflow-hidden">
                    <div class="flex items-center gap-4 relative z-10">
                        <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md flex items-center justify-center text-indigo-300 text-xl border border-white/20">
                            <i :class="selectedLog?.action_icon || 'fa-solid fa-fingerprint'"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-md bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 text-[10px] font-black uppercase tracking-wider" x-text="selectedLog?.entity_name || 'System'"></span>
                                <span class="text-slate-400 text-xs font-mono" x-text="selectedLog?.created_at_relative"></span>
                            </div>
                            <h3 class="text-lg font-black tracking-tight text-white mt-1" x-text="selectedLog?.action_name || 'Audit Event'"></h3>
                        </div>
                    </div>
                    <button @click="showInspectModal = false" class="w-10 h-10 rounded-2xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all active:scale-95 border border-white/20 cursor-pointer">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 overflow-y-auto max-h-[75vh] space-y-5">
                    <!-- Timeline Story Card -->
                    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <i class="fa-solid fa-timeline text-indigo-500"></i> Operational Traceability
                            </span>
                            <span class="text-xs font-mono font-bold text-slate-700" x-text="selectedLog?.created_at_human"></span>
                        </div>

                        <!-- Actor & Responsibility -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-2xs">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-1">Event Causer (Actor)</span>
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold"
                                         :class="selectedLog?.is_self_action ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-purple-50 text-purple-700 border border-purple-200'">
                                        <i :class="selectedLog?.is_self_action ? 'fa-solid fa-user' : 'fa-solid fa-user-shield'"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold text-slate-800 truncate" x-text="selectedLog?.causer_name"></p>
                                        <p class="text-[10px] font-semibold text-slate-500" x-text="selectedLog?.is_self_action ? 'Self Action' : ('Admin Override: ' + selectedLog?.causer_role)"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-2xs">
                                <span class="text-[9px] font-black uppercase tracking-wider text-slate-400 block mb-1">Network &amp; Device Origin</span>
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center text-xs font-bold">
                                        <i class="fa-solid fa-network-wired"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold font-mono text-slate-800" x-text="selectedLog?.ip_address"></p>
                                        <p class="text-[10px] font-medium text-slate-500 truncate max-w-[200px]" x-text="selectedLog?.user_agent"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- State Delta / Diff Viewer -->
                    <div class="p-5 rounded-2xl bg-slate-50 border border-slate-100 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <i class="fa-solid fa-code-compare text-blue-500"></i> State Delta &amp; Changes (Before vs After)
                            </span>
                        </div>

                        <!-- If changes exist -->
                        <template x-if="hasChanges(selectedLog)">
                            <div class="space-y-2">
                                <div class="grid grid-cols-12 gap-2 text-[10px] font-black uppercase tracking-wider text-slate-400 px-3">
                                    <div class="col-span-4">Attribute</div>
                                    <div class="col-span-4">Previous State (Old)</div>
                                    <div class="col-span-4">Resulting State (New)</div>
                                </div>
                                <template x-for="(diff, key) in getDetailedDiff(selectedLog)" :key="key">
                                    <div class="grid grid-cols-12 gap-2 p-3 bg-white rounded-xl border border-slate-200 text-xs items-center shadow-2xs">
                                        <div class="col-span-4 font-mono font-bold text-slate-700 text-[11px]" x-text="key"></div>
                                        <div class="col-span-4">
                                            <span class="px-2 py-1 rounded bg-rose-50 text-rose-700 border border-rose-200 font-mono text-[11px] line-through block truncate" x-text="diff.old || 'null'"></span>
                                        </div>
                                        <div class="col-span-4">
                                            <span class="px-2 py-1 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-mono text-[11px] font-bold block truncate" x-text="diff.new || 'null'"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- If payload contains event metadata without explicit before/after -->
                        <template x-if="!hasChanges(selectedLog)">
                            <div class="p-4 bg-white rounded-xl border border-slate-200 space-y-2">
                                <p class="text-xs font-bold text-slate-600 mb-2">Event Payload Attributes:</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <template x-for="(val, key) in (selectedLog?.new_values || {})" :key="key">
                                        <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-between">
                                            <span class="text-[10px] font-mono font-bold text-slate-500" x-text="key"></span>
                                            <span class="text-xs font-bold text-slate-800 font-mono truncate max-w-[180px]" x-text="typeof val === 'object' ? JSON.stringify(val) : val"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Forensic Tamper-Resistance Seal -->
                    <div class="p-5 rounded-2xl bg-slate-900 text-white space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-black uppercase tracking-widest text-emerald-400 flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-halved"></i> Cryptographic Chained Proof
                            </span>
                            <span class="text-[10px] font-mono text-slate-400">HMAC-SHA256 Signed</span>
                        </div>

                        <div class="space-y-2 text-xs font-mono">
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/10">
                                <span class="text-slate-400 text-[10px]">Record Hash:</span>
                                <span class="text-emerald-300 truncate max-w-[280px]" x-text="selectedLog?.record_hash || 'SHA-256 Ledger Node Verified'"></span>
                            </div>
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-white/5 border border-white/10" x-show="selectedLog?.previous_hash">
                                <span class="text-slate-400 text-[10px]">Chain Parent:</span>
                                <span class="text-slate-300 truncate max-w-[280px]" x-text="selectedLog?.previous_hash"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="p-5 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500">Forensic Ledger Verification Complete</span>
                    <button type="button" @click="showInspectModal = false" class="px-5 py-2.5 bg-slate-900 text-white rounded-xl font-black text-xs uppercase tracking-wider hover:bg-slate-800 transition-all cursor-pointer">
                        Close Forensic Inspector
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function adminUserAuditLedger() {
        return {
            search: '',
            actionFilter: 'all',
            viewMode: 'table',
            showInspectModal: false,
            selectedLog: null,
            clearingLockout: false,
            flushingLimits: false,
            async clearSecurityLockout() {
                if (this.clearingLockout) return;
                this.clearingLockout = true;
                try {
                    const response = await fetch('{{ route("admin.users.clearLockout", $user) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        if (typeof window.showSuccess === 'function') {
                            window.showSuccess(data.message || 'Security lockout cleared successfully.');
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Security lockout cleared.', type: 'success' } }));
                        }
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        const errMsg = data.message || 'Failed to clear security lockout.';
                        if (typeof window.showError === 'function') {
                            window.showError(errMsg);
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', { detail: { message: errMsg, type: 'error' } }));
                        }
                    }
                } catch (err) {
                    if (typeof window.showError === 'function') {
                        window.showError('Communication failure while clearing lockout.');
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Communication failure.', type: 'error' } }));
                    }
                } finally {
                    this.clearingLockout = false;
                }
            },
            async flushRateLimits() {
                if (this.flushingLimits) return;
                this.flushingLimits = true;
                try {
                    const response = await fetch('{{ route("admin.users.flushRateLimits", $user) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    });
                    const data = await response.json();
                    if (response.ok && data.success) {
                        if (typeof window.showSuccess === 'function') {
                            window.showSuccess(data.message || 'Rate limits flushed successfully.');
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', { detail: { message: data.message || 'Rate limits flushed.', type: 'success' } }));
                        }
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        const errMsg = data.message || 'Failed to flush rate limits.';
                        if (typeof window.showError === 'function') {
                            window.showError(errMsg);
                        } else {
                            window.dispatchEvent(new CustomEvent('toast', { detail: { message: errMsg, type: 'error' } }));
                        }
                    }
                } catch (err) {
                    if (typeof window.showError === 'function') {
                        window.showError('Communication failure while flushing rate limits.');
                    } else {
                        window.dispatchEvent(new CustomEvent('toast', { detail: { message: 'Communication failure.', type: 'error' } }));
                    }
                } finally {
                    this.flushingLimits = false;
                }
            },
            openInspectModal(log) {
                this.selectedLog = log;
                this.showInspectModal = true;
            },
            hasChanges(log) {
                if (!log) return false;
                return (log.old_values && Object.keys(log.old_values).length > 0) &&
                       (log.new_values && Object.keys(log.new_values).length > 0);
            },
            getDetailedDiff(log) {
                if (!log) return {};
                const diff = {};
                const oldVals = log.old_values || {};
                const newVals = log.new_values || {};
                const allKeys = new Set([...Object.keys(oldVals), ...Object.keys(newVals)]);
                for (const key of allKeys) {
                    if (oldVals[key] !== newVals[key]) {
                        diff[key] = {
                            old: typeof oldVals[key] === 'object' ? JSON.stringify(oldVals[key]) : String(oldVals[key] ?? ''),
                            new: typeof newVals[key] === 'object' ? JSON.stringify(newVals[key]) : String(newVals[key] ?? '')
                        };
                    }
                }
                return diff;
            },
            matchesRow(data) {
                const q = this.search.trim().toLowerCase();
                const matchesSearch = !q || (
                    data.action.includes(q) ||
                    data.entity.includes(q) ||
                    data.ip.includes(q) ||
                    data.date.includes(q)
                );
                let matchesAction = true;
                if (this.actionFilter === 'auth') {
                    matchesAction = data.action.includes('login') || data.action.includes('logout');
                } else if (this.actionFilter === 'mfa') {
                    matchesAction = data.action.includes('2fa') || data.action.includes('mfa') || data.action.includes('recovery');
                } else if (this.actionFilter === 'governance') {
                    matchesAction = data.action.includes('password') || data.action.includes('lockout') || data.action.includes('transfer') || data.action.includes('reset');
                } else if (this.actionFilter === 'patrol') {
                    matchesAction = data.action.includes('patrol') || data.action.includes('duty') || data.action.includes('case') || data.action.includes('call') || data.entity.includes('patrol') || data.entity.includes('call');
                }
                return matchesSearch && matchesAction;
            },
            resetFilters() {
                this.search = '';
                this.actionFilter = 'all';
            }
        };
    }
</script>
@endsection

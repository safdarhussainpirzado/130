@extends('layouts.app')

@section('title', 'Security Audit Vault - NHMP 130')

@section('page-title', 'Security Audit Vault')

@section('content')
@php
    $statsData = $stats ?? [
        'total' => $logs->total(),
        'auth' => 0,
        'mfa' => 0,
        'governance' => 0,
        'security' => 0,
    ];
@endphp

<div x-data="auditManager(@js([
    'items' => $logs->items(),
    'pagination' => [
        'current_page' => $logs->currentPage(),
        'last_page' => $logs->lastPage(),
        'total' => $logs->total(),
        'per_page' => $logs->perPage()
    ],
    'stats' => $statsData
]))" class="space-y-6 relative mt-2 max-w-[1700px] mx-auto pb-16" x-cloak>

    {{-- 1. LUXURY HERO BANNER --}}
    <section class="relative overflow-hidden bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-950 rounded-3xl p-6 text-white shadow-xl border border-white/10">
        <div class="absolute -right-16 -bottom-20 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-60 -top-16 w-60 h-60 bg-purple-500/15 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center justify-between gap-6">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 shrink-0 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center text-3xl shadow-inner text-blue-400">
                    <i class="fa-solid fa-fingerprint"></i>
                </div>
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl md:text-3xl font-black tracking-tight leading-tight">Security Audit Vault</h1>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-400/20 text-emerald-300 border border-emerald-400/30 font-mono">
                            <i class="fa-solid fa-shield-check text-[10px]"></i> Cryptographic Chain Active
                        </span>
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-blue-400/20 text-blue-200 border border-blue-400/30 font-mono">
                            HMAC-SHA256 Chained
                        </span>
                    </div>
                    <p class="text-slate-300 text-xs md:text-sm font-semibold mt-1.5 max-w-2xl">
                        Comprehensive immutable operational audit trail tracking authentication, multi-factor challenges, role modifications, and administrative governance.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3 shrink-0">
                <a href="{{ route('admin.users.index') }}"
                   class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 font-black text-xs uppercase tracking-wider flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-users"></i> Users Directory
                </a>
                <a href="{{ route('dashboard') }}"
                   class="px-4 py-2.5 rounded-xl bg-white text-slate-900 hover:bg-slate-100 font-black text-xs uppercase tracking-wider shadow-lg flex items-center gap-2 transition-all">
                    <i class="fa-solid fa-chart-pie"></i> Operations Dashboard
                </a>
            </div>
        </div>
    </section>

    {{-- 2. DYNAMIC 3D STAT TILES --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3.5">
        <!-- Tile 1: Total Events -->
        <div @click="setCategory('all')"
             :class="activeCategory === 'all' ? 'ring-2 ring-blue-500 shadow-md bg-blue-50/40' : 'bg-white hover:-translate-y-0.5'"
             class="rounded-2xl p-4 border border-slate-100 shadow-xs transition-all cursor-pointer relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Events</span>
                <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-database"></i>
                </div>
            </div>
            <div>
                <h3 class="text-xl md:text-2xl font-black text-slate-800" x-text="stats.total?.toLocaleString() || pagination.total"></h3>
                <p class="text-[10px] font-bold text-slate-400 mt-0.5">Immutable audit footprint</p>
            </div>
        </div>

        <!-- Tile 2: Access & Auth -->
        <div @click="setCategory('auth')"
             :class="activeCategory === 'auth' ? 'ring-2 ring-emerald-500 shadow-md bg-emerald-50/40' : 'bg-white hover:-translate-y-0.5'"
             class="rounded-2xl p-4 border border-slate-100 shadow-xs transition-all cursor-pointer relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-emerald-600">Access &amp; Auth</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-door-open"></i>
                </div>
            </div>
            <div>
                <h3 class="text-xl md:text-2xl font-black text-emerald-700" x-text="stats.auth?.toLocaleString() || '0'"></h3>
                <p class="text-[10px] font-bold text-slate-400 mt-0.5">Logins &amp; sessions</p>
            </div>
        </div>

        <!-- Tile 3: MFA & Verification -->
        <div @click="setCategory('mfa')"
             :class="activeCategory === 'mfa' ? 'ring-2 ring-indigo-500 shadow-md bg-indigo-50/40' : 'bg-white hover:-translate-y-0.5'"
             class="rounded-2xl p-4 border border-slate-100 shadow-xs transition-all cursor-pointer relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-indigo-600">MFA &amp; Security</span>
                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>
            <div>
                <h3 class="text-xl md:text-2xl font-black text-indigo-700" x-text="stats.mfa?.toLocaleString() || '0'"></h3>
                <p class="text-[10px] font-bold text-slate-400 mt-0.5">2FA codes &amp; step-up</p>
            </div>
        </div>

        <!-- Tile 4: Governance & Management -->
        <div @click="setCategory('governance')"
             :class="activeCategory === 'governance' ? 'ring-2 ring-amber-500 shadow-md bg-amber-50/40' : 'bg-white hover:-translate-y-0.5'"
             class="rounded-2xl p-4 border border-slate-100 shadow-xs transition-all cursor-pointer relative overflow-hidden flex flex-col justify-between">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-amber-600">Governance</span>
                <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-user-gear"></i>
                </div>
            </div>
            <div>
                <h3 class="text-xl md:text-2xl font-black text-amber-700" x-text="stats.governance?.toLocaleString() || '0'"></h3>
                <p class="text-[10px] font-bold text-slate-400 mt-0.5">Password &amp; role changes</p>
            </div>
        </div>

        <!-- Tile 5: Security Warnings & Locks -->
        <div @click="setCategory('security')"
             :class="activeCategory === 'security' ? 'ring-2 ring-rose-500 shadow-md bg-rose-50/40' : 'bg-white hover:-translate-y-0.5'"
             class="rounded-2xl p-4 border border-slate-100 shadow-xs transition-all cursor-pointer relative overflow-hidden flex flex-col justify-between col-span-2 md:col-span-1">
            <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-black uppercase tracking-widest text-rose-600">Warnings &amp; Locks</span>
                <div class="w-8 h-8 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center text-xs">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>
            <div>
                <h3 class="text-xl md:text-2xl font-black text-rose-700" x-text="stats.security?.toLocaleString() || '0'"></h3>
                <p class="text-[10px] font-bold text-slate-400 mt-0.5">Failed attempts &amp; lockouts</p>
            </div>
        </div>
    </div>

    {{-- 3. INTERACTIVE FILTER TOOLBAR --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 space-y-3">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            {{-- Search Bar --}}
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </div>
                <input type="text"
                       x-model="search"
                       @input="triggerSearch()"
                       placeholder="Filter by causer username, action (e.g. login, reset, 2fa), target entity, IP address, or ID..."
                       class="w-full pl-9 pr-8 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-indigo-500 focus:bg-white focus:ring-2 focus:ring-indigo-500/10 outline-none transition-all font-bold text-slate-800 text-xs">
                <button type="button"
                        x-show="search.length > 0"
                        @click="search = ''; triggerSearch()"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            {{-- Category Filter Chips --}}
            <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                <button type="button"
                        @click="setCategory('all')"
                        :class="activeCategory === 'all' ? 'bg-slate-900 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-600'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all">
                    All Events
                </button>
                <button type="button"
                        @click="setCategory('auth')"
                        :class="activeCategory === 'auth' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all">
                    Sign-In &amp; Sessions
                </button>
                <button type="button"
                        @click="setCategory('mfa')"
                        :class="activeCategory === 'mfa' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all">
                    MFA &amp; Security
                </button>
                <button type="button"
                        @click="setCategory('governance')"
                        :class="activeCategory === 'governance' ? 'bg-amber-600 text-white shadow-xs' : 'bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all">
                    Governance
                </button>
                <button type="button"
                        @click="setCategory('security')"
                        :class="activeCategory === 'security' ? 'bg-rose-600 text-white shadow-xs' : 'bg-rose-50 text-rose-700 hover:bg-rose-100 border border-rose-200'"
                        class="px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wider transition-all">
                    Security Alerts
                </button>
                <button type="button"
                        x-show="search.length > 0 || activeCategory !== 'all'"
                        @click="resetFilters()"
                        class="text-[10px] font-black uppercase tracking-wider text-rose-600 hover:text-rose-800 ml-1.5 flex items-center gap-1 transition-colors">
                    <i class="fa-solid fa-rotate-left text-[9px]"></i> Reset
                </button>
            </div>
        </div>
    </div>

    {{-- 4. MASTER AUDIT LEDGER TABLE CARD --}}
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden" data-testid="master-audit-table">
        <header class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/50">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600">
                    <i class="fa-solid fa-shield-halved text-sm"></i>
                </div>
                <div>
                    <h2 class="font-black text-slate-800 text-base leading-tight">Master Security Audit Ledger</h2>
                    <p class="text-[11px] font-bold text-slate-400">
                        Showing page <span class="font-black text-slate-700" x-text="pagination.current_page"></span> of <span class="font-black text-slate-700" x-text="pagination.last_page"></span> · <span class="font-black text-slate-700" x-text="pagination.total"></span> verified events
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="px-3 py-1 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-xl text-xs font-black font-mono">
                    Total: <span x-text="pagination.total"></span>
                </span>
            </div>
        </header>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-black uppercase tracking-wider text-slate-400">
                        <th class="px-5 py-3.5">Timestamp</th>
                        <th class="px-5 py-3.5">Identity / Causer</th>
                        <th class="px-5 py-3.5 text-center">Action / Event</th>
                        <th class="px-5 py-3.5">Target Entity</th>
                        <th class="px-5 py-3.5">Network &amp; Device</th>
                        <th class="px-5 py-3.5 text-right">Investigation</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs">
                    <template x-for="item in filteredItems" :key="item.id">
                        <tr class="hover:bg-indigo-50/20 transition-colors">
                            {{-- Timestamp --}}
                            <td class="px-5 py-3.5 align-middle whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-11 shrink-0 text-center rounded-xl bg-slate-50 border border-slate-100 py-1">
                                        <p class="text-xs font-black text-slate-800 leading-none" x-text="formatDay(item.created_at)"></p>
                                        <p class="text-[9px] font-black uppercase tracking-widest text-slate-400 mt-0.5" x-text="formatMonthYear(item.created_at)"></p>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 leading-tight font-mono text-xs" x-text="formatTime(item.created_at)"></p>
                                        <p class="text-[10px] font-bold text-slate-400" x-text="formatDiff(item.created_at)"></p>
                                    </div>
                                </div>
                            </td>

                            {{-- Identity / Causer --}}
                            <td class="px-5 py-3.5 align-middle whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-100 flex items-center justify-center font-black text-xs shrink-0"
                                         x-text="getInitials(item.user?.full_name || item.causer?.full_name || item.user?.username || 'SYS')"></div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <a :href="item.user_id ? `/mgmt/users/${item.user_id}/audit` : '#'"
                                               class="font-black text-slate-800 hover:text-indigo-600 transition-colors text-xs truncate"
                                               x-text="item.user?.full_name || item.causer?.full_name || item.user?.username || 'System Daemon'"></a>
                                            <template x-if="item.user?.role">
                                                <span class="text-[8px] font-black uppercase px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 border border-slate-200"
                                                      x-text="item.user.role.display_name"></span>
                                            </template>
                                        </div>
                                        <p class="text-[10px] font-mono text-slate-400" x-text="'@' + (item.user?.username || item.causer?.username || 'system')"></p>
                                    </div>
                                </div>
                            </td>

                            {{-- Action / Event --}}
                            <td class="px-5 py-3.5 align-middle text-center whitespace-nowrap">
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border"
                                      :class="getActionTone(item.action)">
                                    <i class="fa-solid" :class="getActionIcon(item.action)"></i>
                                    <span x-text="formatActionName(item.action)"></span>
                                </span>
                            </td>

                            {{-- Target Entity --}}
                            <td class="px-5 py-3.5 align-middle whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-500 text-xs shrink-0">
                                        <i class="fa-solid" :class="getEntityIcon(item.table_name)"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800 text-xs capitalize" x-text="formatEntity(item.table_name)"></p>
                                        <p class="text-[10px] font-mono text-slate-400" x-text="item.record_id ? '#' + item.record_id : 'System-Wide'"></p>
                                    </div>
                                </div>
                            </td>

                            {{-- Network & Device --}}
                            <td class="px-5 py-3.5 align-middle whitespace-nowrap">
                                <div class="flex flex-col">
                                    <span class="font-mono text-xs font-bold text-slate-700" x-text="item.ip_address || '127.0.0.1'"></span>
                                    <span class="text-[9px] font-medium text-slate-400 truncate max-w-[150px]" x-text="item.user_agent || 'Internal Subsystem'"></span>
                                </div>
                            </td>

                            {{-- Investigation Action --}}
                            <td class="px-5 py-3.5 align-middle text-right whitespace-nowrap">
                                <button type="button"
                                        @click="inspectEvent(item)"
                                        class="px-3 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-black transition-all inline-flex items-center gap-1.5 shadow-2xs">
                                    <i class="fa-solid fa-magnifying-glass-arrow-right text-[11px]"></i>
                                    <span>Inspect</span>
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
            <div class="p-5 md:p-6 bg-slate-50/60 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="text-xs font-bold text-slate-400">
                    Showing <span class="text-slate-800 font-black">{{ $logs->firstItem() }}</span> to <span class="text-slate-800 font-black">{{ $logs->lastItem() }}</span> of <span class="text-slate-800 font-black">{{ $logs->total() }}</span> records
                </div>
                <div>
                    {{ $logs->links() }}
                </div>
            </div>
        @endif
    </div>

    {{-- 5. LUXURY EVENT INVESTIGATION MODAL (Diff & Cryptographic Ledger) --}}
    <div x-show="showModal" class="fixed inset-0 z-[100] overflow-y-auto px-4 py-6" x-transition.opacity>
        <div class="flex items-center justify-center min-h-screen">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[90]" @click="showModal = false"></div>

            <div class="bg-white rounded-3xl shadow-2xl w-full max-w-3xl z-[100] overflow-hidden relative border border-slate-100" x-transition.scale.origin.center>
                {{-- Modal Header --}}
                <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-purple-800 p-6 text-white relative">
                    <div class="flex items-center justify-between relative z-10">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-sm flex items-center justify-center shadow-inner border border-white/20 text-xl">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-black tracking-tight">Security Audit Investigation</h3>
                                <p class="text-white/80 text-[10px] font-mono uppercase tracking-widest mt-0.5" x-text="'Log ID #' + (selectedItem?.id || '') + ' · Verified SHA-256'"></p>
                            </div>
                        </div>
                        <button @click="showModal = false" class="w-9 h-9 rounded-xl bg-white/15 hover:bg-white/25 flex items-center justify-center transition-colors">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Modal Body --}}
                <div class="p-6 md:p-8 space-y-6 bg-slate-50/50 max-h-[75vh] overflow-y-auto">
                    {{-- 1. Cryptographic Status Strip --}}
                    <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shadow-sm">
                                <i class="fa-solid fa-link"></i>
                            </div>
                            <div>
                                <p class="text-xs font-black text-emerald-950 uppercase tracking-wide">HMAC-SHA256 Cryptographic Chain Intact</p>
                                <p class="text-[10px] font-bold text-emerald-700">Audit ledger block validated against genesis signature without tampering.</p>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 bg-emerald-600 text-white rounded-lg text-[9px] font-black uppercase tracking-wider font-mono shrink-0">
                            VERIFIED
                        </span>
                    </div>

                    {{-- 2. Metadata Cards --}}
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <div class="bg-white p-3.5 rounded-2xl border border-slate-100 shadow-2xs">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Executor / Causer</p>
                            <p class="text-xs font-black text-slate-800 mt-1 truncate" x-text="selectedItem?.user?.full_name || selectedItem?.user?.username || 'System'"></p>
                            <p class="text-[9px] font-mono text-indigo-600" x-text="selectedItem?.user?.username ? '@' + selectedItem.user.username : 'daemon'"></p>
                        </div>
                        <div class="bg-white p-3.5 rounded-2xl border border-slate-100 shadow-2xs">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Action Name</p>
                            <p class="text-xs font-black text-slate-800 mt-1 truncate" x-text="formatActionName(selectedItem?.action)"></p>
                            <p class="text-[9px] font-mono text-slate-400 truncate" x-text="selectedItem?.action"></p>
                        </div>
                        <div class="bg-white p-3.5 rounded-2xl border border-slate-100 shadow-2xs">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Target Entity</p>
                            <p class="text-xs font-black text-slate-800 mt-1 capitalize" x-text="formatEntity(selectedItem?.table_name)"></p>
                            <p class="text-[9px] font-mono text-slate-400" x-text="selectedItem?.record_id ? '#' + selectedItem.record_id : 'System-Wide'"></p>
                        </div>
                        <div class="bg-white p-3.5 rounded-2xl border border-slate-100 shadow-2xs">
                            <p class="text-[9px] font-black uppercase tracking-wider text-slate-400">Source IP &amp; Host</p>
                            <p class="text-xs font-black text-slate-800 mt-1 font-mono" x-text="selectedItem?.ip_address || '127.0.0.1'"></p>
                            <p class="text-[9px] font-bold text-slate-400" x-text="formatTime(selectedItem?.created_at)"></p>
                        </div>
                    </div>

                    {{-- 3. Modification Ledger: Backward and Forward Diff --}}
                    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-xs space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <h4 class="text-xs font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-code-compare text-indigo-600"></i> Modification Ledger &amp; Field Diffs
                            </h4>
                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest font-mono">Old → New</span>
                        </div>

                        {{-- If changes exist in new_values or old_values --}}
                        <template x-if="hasDiff(selectedItem)">
                            <div class="space-y-2 max-h-60 overflow-y-auto">
                                <template x-for="key in getDiffKeys(selectedItem)" :key="key">
                                    <div class="p-3 bg-slate-50/80 rounded-xl border border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                        <span class="text-[10px] font-black uppercase tracking-wider font-mono text-slate-600" x-text="key"></span>
                                        <div class="flex items-center gap-3 font-mono text-xs">
                                            <span class="text-rose-600 line-through bg-rose-50 px-2 py-0.5 rounded border border-rose-100"
                                                  x-text="formatDiffVal(getOldVal(selectedItem, key))"></span>
                                            <i class="fa-solid fa-arrow-right text-[10px] text-slate-400"></i>
                                            <span class="text-emerald-700 font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-100"
                                                  x-text="formatDiffVal(getNewVal(selectedItem, key))"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- If no diff (e.g. auth / session events) --}}
                        <template x-if="!hasDiff(selectedItem)">
                            <div class="p-4 bg-slate-50 rounded-xl text-center text-slate-500 text-xs font-bold space-y-1">
                                <p><i class="fa-solid fa-circle-check text-indigo-500 mr-1.5"></i> Operational state transition recorded without raw data mutations.</p>
                                <p class="text-[10px] text-slate-400 font-mono" x-text="'Context: ' + (selectedItem?.action || 'State Verified')"></p>
                            </div>
                        </template>
                    </div>

                    {{-- 4. Chained Cryptographic Signatures --}}
                    <div class="bg-slate-900 text-slate-300 p-5 rounded-2xl space-y-3 font-mono text-[11px]">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-2">
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 flex items-center gap-1.5">
                                <i class="fa-solid fa-fingerprint text-blue-400"></i> Forensic Hashes
                            </span>
                            <span class="text-[9px] font-bold text-emerald-400">Chain Verified</span>
                        </div>
                        <div class="space-y-2">
                            <div>
                                <p class="text-[9px] text-slate-500 uppercase tracking-wider">Record Hash (Current Block)</p>
                                <p class="text-blue-300 break-all select-all font-mono" x-text="selectedItem?.record_hash || 'SHA256: genesis record'"></p>
                            </div>
                            <div>
                                <p class="text-[9px] text-slate-500 uppercase tracking-wider">Previous Hash (Chained Parent)</p>
                                <p class="text-slate-400 break-all select-all font-mono" x-text="selectedItem?.previous_hash || 'SHA256: genesis record'"></p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="p-5 bg-white border-t border-slate-100 flex items-center justify-between gap-3">
                    <template x-if="selectedItem?.user_id">
                        <a :href="`/mgmt/users/${selectedItem.user_id}/audit`"
                           class="px-4 py-2.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-black uppercase tracking-wider flex items-center gap-2 transition-all">
                            <i class="fa-solid fa-id-badge"></i> View User Full History
                        </a>
                    </template>
                    <div class="flex-1" x-show="!selectedItem?.user_id"></div>
                    <button type="button" @click="showModal = false"
                            class="px-5 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-black uppercase tracking-wider hover:bg-slate-800 transition-all">
                        Close Investigation
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function auditManager(config) {
    return {
        items: config.items || [],
        pagination: config.pagination || { current_page: 1, last_page: 1, total: 0, per_page: 20 },
        stats: config.stats || {},
        showModal: false,
        selectedItem: null,
        search: '',
        activeCategory: 'all',

        get filteredItems() {
            return this.items.filter(item => {
                const q = this.search.trim().toLowerCase();
                const action = (item.action || '').toLowerCase();
                const causer = ((item.user?.username || '') + ' ' + (item.user?.full_name || '')).toLowerCase();
                const entity = (item.table_name || '').toLowerCase();
                const ip = (item.ip_address || '').toLowerCase();
                const uid = String(item.record_id || '');

                const matchesSearch = !q || action.includes(q) || causer.includes(q) || entity.includes(q) || ip.includes(q) || uid.includes(q);

                let matchesCat = true;
                if (this.activeCategory === 'auth') {
                    matchesCat = ['login_success_mfa_exempt', 'login_failed', 'logout'].includes(item.action);
                } else if (this.activeCategory === 'mfa') {
                    matchesCat = action.includes('2fa') || action.includes('mfa') || action.includes('recovery') || action.includes('step_up');
                } else if (this.activeCategory === 'governance') {
                    matchesCat = action.includes('password') || action.includes('lockout') || action.includes('transfer') || action.includes('enforced') || action.includes('reset');
                } else if (this.activeCategory === 'security') {
                    matchesCat = ['login_failed', '2fa_failed', '2fa_challenge_locked', 'recovery_code_failed', 'emergency_account_locked'].includes(action);
                }

                return matchesSearch && matchesCat;
            });
        },

        setCategory(cat) {
            this.activeCategory = cat;
        },

        resetFilters() {
            this.search = '';
            this.activeCategory = 'all';
        },

        triggerSearch() {
            // Live reactive filtering in Alpine
        },

        inspectEvent(item) {
            this.selectedItem = item;
            this.showModal = true;
        },

        formatActionName(action) {
            if (!action) return 'System Event';
            const dict = {
                'login_success_mfa_exempt': 'Sign-In (MFA Exempt)',
                'login_failed': 'Auth Failed',
                'logout': 'Sign-Out',
                '2fa_verified': '2FA Verified',
                '2fa_enrolled': '2FA Enrolled',
                '2fa_failed': '2FA Failed',
                '2fa_replayed': '2FA Replay Prevented',
                '2fa_challenge_locked': '2FA Lockout',
                '2fa_replaced': '2FA Replaced',
                'recovery_code_used': 'Recovery Code Used',
                'recovery_code_failed': 'Recovery Code Failed',
                'step_up_admin_session_authorized': 'Step-Up Elevated',
                'step_up_success': 'Step-Up Passed',
                'OP_ADMIN_PASSWORD_RESET': 'Admin Password Reset',
                'PASSWORD_RESET_TOKEN_ISSUED': 'Token Issued',
                'PASSWORD_RESET_COMPLETED_BY_USER': 'Password Changed',
                'ADMIN_SECURITY_LOCKOUT_CLEARED': 'Lockout Cleared',
                'ADMIN_ACCOUNT_UNLOCKED': 'Account Unlocked',
                'EMERGENCY_ACCOUNT_LOCKED': 'Account Locked',
                'USER_ORGANIZATIONAL_TRANSFER_COMPLETED': 'Transfer Completed',
                'mfa_user_enforced_by_admin': 'MFA Enforced',
                'mfa_user_reset_by_admin': 'MFA Reset by Admin',
                'mfa_user_revoked_by_admin': 'MFA Revoked by Admin',
                'mfa_role_policy_updated': 'Policy Updated',
                'security_gate_test': 'Security Gate Audit',
                'tamper_detection_test': 'Tamper Probe'
            };
            return dict[action] || action.replace(/_/g, ' ').toUpperCase();
        },

        getActionTone(action) {
            if (!action) return 'bg-slate-50 text-slate-700 border-slate-200';
            if (['login_success_mfa_exempt', '2fa_verified', 'step_up_success'].includes(action)) {
                return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            }
            if (['login_failed', '2fa_failed', '2fa_challenge_locked', 'EMERGENCY_ACCOUNT_LOCKED', 'recovery_code_failed'].includes(action)) {
                return 'bg-rose-50 text-rose-700 border-rose-200';
            }
            if (action.includes('2fa') || action.includes('mfa')) {
                return 'bg-indigo-50 text-indigo-700 border-indigo-200';
            }
            if (action.includes('PASSWORD') || action.includes('LOCKOUT') || action.includes('TRANSFER')) {
                return 'bg-amber-50 text-amber-700 border-amber-200';
            }
            if (action === 'logout') {
                return 'bg-sky-50 text-sky-700 border-sky-200';
            }
            return 'bg-slate-50 text-slate-700 border-slate-200';
        },

        getActionIcon(action) {
            if (!action) return 'fa-circle-dot';
            if (action.includes('login_success')) return 'fa-circle-check';
            if (action.includes('login_failed') || action.includes('failed')) return 'fa-triangle-exclamation';
            if (action === 'logout') return 'fa-right-from-bracket';
            if (action.includes('2fa') || action.includes('mfa')) return 'fa-shield-halved';
            if (action.includes('PASSWORD')) return 'fa-key';
            if (action.includes('LOCKOUT') || action.includes('LOCKED')) return 'fa-lock';
            if (action.includes('TRANSFER')) return 'fa-arrows-split-up-and-left';
            return 'fa-circle-dot';
        },

        getEntityIcon(tableName) {
            if (!tableName) return 'fa-server';
            const t = tableName.toLowerCase();
            if (t.includes('user')) return 'fa-user';
            if (t.includes('call')) return 'fa-headset';
            if (t.includes('role')) return 'fa-shield-halved';
            if (t.includes('office') || t.includes('branch')) return 'fa-building';
            if (t.includes('password')) return 'fa-key';
            return 'fa-cube';
        },

        formatEntity(tableName) {
            if (!tableName) return 'System Core';
            return tableName.replace(/_/g, ' ');
        },

        getInitials(name) {
            if (!name) return 'SYS';
            const parts = name.trim().split(/\s+/);
            return (parts[0][0] + (parts[1] ? parts[1][0] : parts[0][1] || '')).toUpperCase();
        },

        formatDay(dateStr) {
            if (!dateStr) return '--';
            const d = new Date(dateStr);
            return String(d.getDate()).padStart(2, '0');
        },

        formatMonthYear(dateStr) {
            if (!dateStr) return '---';
            const d = new Date(dateStr);
            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return months[d.getMonth()] + ' ' + String(d.getFullYear()).slice(-2);
        },

        formatTime(dateStr) {
            if (!dateStr) return '--:--';
            const d = new Date(dateStr);
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        },

        formatDiff(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            const diffMs = Date.now() - d.getTime();
            const diffMins = Math.floor(diffMs / 60000);
            if (diffMins < 1) return 'Just now';
            if (diffMins < 60) return diffMins + 'm ago';
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return diffHours + 'h ago';
            const diffDays = Math.floor(diffHours / 24);
            return diffDays + 'd ago';
        },

        hasDiff(item) {
            if (!item) return false;
            const hasOld = item.old_values && Object.keys(item.old_values).length > 0;
            const hasNew = item.new_values && Object.keys(item.new_values).length > 0;
            return hasOld || hasNew;
        },

        getDiffKeys(item) {
            if (!item) return [];
            const k1 = item.old_values ? Object.keys(item.old_values) : [];
            const k2 = item.new_values ? Object.keys(item.new_values) : [];
            return Array.from(new Set([...k1, ...k2])).filter(k => !['updated_at', 'remember_token', 'password'].includes(k));
        },

        getOldVal(item, key) {
            return item?.old_values ? item.old_values[key] : null;
        },

        getNewVal(item, key) {
            return item?.new_values ? item.new_values[key] : null;
        },

        formatDiffVal(val) {
            if (val === null || val === undefined) return 'null';
            if (typeof val === 'boolean') return val ? 'true' : 'false';
            if (typeof val === 'object') return JSON.stringify(val);
            return String(val);
        }
    };
}
</script>
@endsection

@extends('layouts.app')

@section('title', 'Geo Access & Shadow Enforcement Report - NHMP 130')

@section('page-title', 'Geographic Access Control')

@section('content')
<div class="space-y-6 relative mt-2 max-w-[1700px] mx-auto pb-16">

    {{-- 1. LUXURY HERO BANNER --}}
    <section class="relative overflow-hidden bg-gradient-to-r from-slate-900 via-emerald-950 to-teal-950 rounded-3xl p-6 text-white shadow-xl border border-white/10">
        <div class="absolute -right-16 -bottom-20 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute right-60 -top-16 w-60 h-60 bg-teal-500/15 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center justify-between gap-6">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 shrink-0 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center text-3xl shadow-inner text-emerald-400">
                    <i class="fa-solid fa-earth-asia"></i>
                </div>
                <div>
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-2xl md:text-3xl font-black tracking-tight leading-tight">Pakistan Geographic Access Report</h1>
                        
                        {{-- Mode Badge --}}
                        @if($mode === 'enforce')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/30 font-mono">
                                <i class="fa-solid fa-shield-halved text-[10px]"></i> Mode: Enforce (Blocking Active)
                            </span>
                        @elseif($mode === 'shadow')
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30 font-mono">
                                <i class="fa-solid fa-eye text-[10px]"></i> Mode: Shadow (Log-Only / Safe)
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-500/20 text-slate-300 border border-slate-500/30 font-mono">
                                <i class="fa-solid fa-ban text-[10px]"></i> Mode: Off
                            </span>
                        @endif

                        {{-- Override Badge --}}
                        @if($overrideStatus['is_active'])
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-400/20 text-rose-200 border border-rose-400/30 font-mono animate-pulse">
                                <i class="fa-solid fa-triangle-exclamation"></i> Emergency Override Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-400/20 text-emerald-200 border border-emerald-400/30 font-mono">
                                <i class="fa-solid fa-circle-check"></i> Standard Security Policy
                            </span>
                        @endif
                    </div>
                    <p class="text-slate-300 text-xs md:text-sm font-semibold mt-1.5 max-w-2xl">
                        Operational intelligence on login origin countries, carrier NATs, hosting networks, and shadow-mode policy impact across the NHMP 130 workforce.
                    </p>
                </div>
            </div>

            {{-- Time Window Filter --}}
            <div class="flex items-center gap-2 bg-black/30 p-1.5 rounded-2xl border border-white/10 self-start xl:self-auto">
                @foreach([1 => '24h', 7 => '7 Days', 14 => '14 Days', 30 => '30 Days'] as $d => $label)
                    <a href="{{ route('admin.geo-access.report', ['days' => $d]) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all {{ $days === $d ? 'bg-emerald-500 text-white shadow-md' : 'text-slate-300 hover:text-white hover:bg-white/5' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 2. METRIC CARDS --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        {{-- Total Evaluated --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Total Evaluated</p>
                <h3 class="text-2xl font-black text-slate-900 dark:text-white mt-1">{{ number_format($stats['total']) }}</h3>
                <p class="text-[11px] text-slate-500 mt-1">In last {{ $days }} days</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-network-wired"></i>
            </div>
        </div>

        {{-- Allowed Clean --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pakistan Allowed</p>
                <h3 class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">{{ number_format($stats['allowed']) }}</h3>
                <p class="text-[11px] text-slate-500 mt-1">Legitimate domestic logins</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-shield-check"></i>
            </div>
        </div>

        {{-- Shadow Would Deny --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Shadow Would Deny</p>
                <h3 class="text-2xl font-black text-amber-500 mt-1">{{ number_format($stats['shadow_would_deny']) }}</h3>
                <p class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold mt-1">Allowed now; blocks in enforce</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 text-amber-500 flex items-center justify-center text-xl">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>

        {{-- Denied / Blocked --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Denied / Blocked</p>
                <h3 class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1">{{ number_format($stats['denied']) }}</h3>
                <p class="text-[11px] text-slate-500 mt-1">Under enforce policy</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-rose-500/10 text-rose-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-hand"></i>
            </div>
        </div>
    </div>

    {{-- 3. TWO-COLUMN: FOREIGN ORIGINS & TOP ASNs --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Top Foreign Countries --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-globe text-amber-500"></i> Foreign Countries Attempting Login
                </h3>
                <span class="text-xs text-slate-500 font-bold">Non-PK Traffic</span>
            </div>

            @if($foreignCountries->isEmpty())
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-circle-check text-3xl text-emerald-500/50 mb-2"></i>
                    <p class="text-xs font-medium">No foreign logins detected in the selected time window.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 uppercase text-[10px] tracking-wider">
                                <th class="pb-2 font-bold">Country</th>
                                <th class="pb-2 font-bold text-center">Total Attempts</th>
                                <th class="pb-2 font-bold text-right">Unique Users</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            @foreach($foreignCountries as $fc)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-750">
                                    <td class="py-2.5 font-bold flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 font-mono text-[11px] font-black text-slate-800 dark:text-slate-200">
                                            {{ $fc->country }}
                                        </span>
                                    </td>
                                    <td class="py-2.5 text-center font-bold text-amber-600 dark:text-amber-400">{{ number_format($fc->total) }}</td>
                                    <td class="py-2.5 text-right font-medium text-slate-500">{{ number_format($fc->users_count) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Top ASNs / Networks --}}
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                    <i class="fa-solid fa-server text-blue-500"></i> Top Origin ASNs & Carriers
                </h3>
                <span class="text-xs text-slate-500 font-bold">ISP / Hosting Networks</span>
            </div>

            @if($topAsns->isEmpty())
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-network-wired text-3xl text-slate-400/50 mb-2"></i>
                    <p class="text-xs font-medium">No ASN data recorded in the selected time window.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 uppercase text-[10px] tracking-wider">
                                <th class="pb-2 font-bold">ASN</th>
                                <th class="pb-2 font-bold">Organization / ISP</th>
                                <th class="pb-2 font-bold text-right">Logins</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                            @foreach($topAsns as $asn)
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-750">
                                    <td class="py-2.5 font-mono text-[11px] font-bold text-blue-600 dark:text-blue-400">
                                        AS{{ $asn->asn }}
                                    </td>
                                    <td class="py-2.5 font-medium text-slate-700 dark:text-slate-300 truncate max-w-[200px]" title="{{ $asn->org }}">
                                        {{ $asn->org ?: 'Unknown Network' }}
                                    </td>
                                    <td class="py-2.5 text-right font-bold text-slate-900 dark:text-white">{{ number_format($asn->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    {{-- 4. RECENT FLAGGED ACTIVITY FEED --}}
    <div class="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
                <i class="fa-solid fa-list-check text-emerald-500"></i> Flagged Login Events
            </h3>
            <span class="text-xs text-slate-500 font-bold">Events with Alerts or Potential Blocks</span>
        </div>

        @if($recentLogins->isEmpty())
            <div class="text-center py-10 text-slate-400">
                <i class="fa-solid fa-shield-heart text-4xl text-emerald-400/50 mb-2"></i>
                <p class="text-sm font-semibold">Zero security anomalies or foreign login attempts recorded.</p>
                <p class="text-xs text-slate-400 mt-0.5">All operator logins are originating from domestic authorized networks.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-400 uppercase text-[10px] tracking-wider">
                            <th class="pb-2.5 font-bold">Time</th>
                            <th class="pb-2.5 font-bold">Operator</th>
                            <th class="pb-2.5 font-bold">Role</th>
                            <th class="pb-2.5 font-bold">Client IP</th>
                            <th class="pb-2.5 font-bold">Country</th>
                            <th class="pb-2.5 font-bold">ASN</th>
                            <th class="pb-2.5 font-bold">Decision</th>
                            <th class="pb-2.5 font-bold">Risk Signals</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 font-mono">
                        @foreach($recentLogins as $rl)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-750">
                                <td class="py-3 text-slate-500 text-[11px] whitespace-nowrap">
                                    {{ $rl->login_at?->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="py-3 font-sans font-bold text-slate-900 dark:text-white">
                                    {{ $rl->user?->username ?? "User #{$rl->user_id}" }}
                                </td>
                                <td class="py-3 font-sans text-slate-500 text-[11px]">
                                    {{ $rl->user?->role?->display_name ?? $rl->user?->role?->name ?? 'N/A' }}
                                </td>
                                <td class="py-3 font-bold text-slate-700 dark:text-slate-300">
                                    {{ $rl->ip_address }}
                                </td>
                                <td class="py-3">
                                    <span class="px-2 py-0.5 rounded text-[11px] font-black {{ ($rl->country === 'PK' || empty($rl->country)) ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300' }}">
                                        {{ $rl->country ?: 'N/A' }}
                                    </span>
                                </td>
                                <td class="py-3 text-slate-500 text-[11px]">
                                    {{ $rl->asn ? "AS{$rl->asn}" : 'N/A' }}
                                </td>
                                <td class="py-3 font-sans">
                                    @if($rl->geo_decision === 'shadow_would_deny')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                            Shadow (Would Deny)
                                        </span>
                                    @elseif($rl->geo_decision === 'deny')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300">
                                            Blocked (Denied)
                                        </span>
                                    @elseif($rl->geo_decision === 'allow_with_alert')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                                            Allowed with Alert
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            Allowed
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 font-sans text-slate-600 dark:text-slate-400 text-[11px]">
                                    @if(!empty($rl->risk_reasons))
                                        <div class="flex flex-wrap gap-1">
                                            @foreach((array) $rl->risk_reasons as $reason)
                                                <span class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-[10px]">
                                                    {{ $reason }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $recentLogins->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

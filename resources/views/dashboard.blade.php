@extends('layouts.app')

@section('title', 'Dashboard — NHMP 130')
@section('page-title', 'Command Center Analytics')


@section('content')
{{-- Leaflet Maps CSS & Plugins (Local vendored for CSP compliance & speed) --}}
<link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
<link rel="stylesheet" href="{{ asset('vendor/leaflet/MarkerCluster.css') }}" />
<link rel="stylesheet" href="{{ asset('vendor/leaflet/MarkerCluster.Default.css') }}" />

<style>
    /* ── Design Tokens ───────────────────────────────────────────── */
    :root {
        --sky:     #0ea5e9; --sky-lt:  #e0f2fe; --sky-dk:  #0369a1;
        --rose:    #f43f5e; --rose-lt: #ffe4e6; --rose-dk: #be123c;
        --em:      #10b981; --em-lt:   #d1fae5; --em-dk:   #047857;
        --vio:     #8b5cf6; --vio-lt:  #ede9fe; --vio-dk:  #6d28d9;
        --amb:     #f59e0b; --amb-lt:  #fef3c7; --amb-dk:  #b45309;
        --cor:     #f97316; --cor-lt:  #ffedd5; --cor-dk:  #c2410c;
        --tea:     #14b8a6; --tea-lt:  #ccfbf1; --tea-dk:  #0f766e;
        --pnk:     #ec4899; --pnk-lt:  #fce7f3; --pnk-dk:  #9d174d;
        --card-r:  20px;
        --gap:     1.25rem;
    }

    /* ── Bento Card ─────────────────────────────────────────────── */
    .bc {
        background: #fff;
        border-radius: var(--card-r);
        border: 1px solid #f1f5f9;
        box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.03);
        padding: 1.25rem 1.4rem;
        transition: box-shadow .2s ease, transform .2s ease;
        position: relative;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .bc:hover { box-shadow: 0 4px 28px rgba(0,0,0,.09); transform: translateY(-1px); }
    /* colour accent stripe at top */
    .bc::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0;
        height: 3px; border-radius: var(--card-r) var(--card-r) 0 0;
    }
    .ac-sky::before    { background: var(--sky); }
    .ac-rose::before   { background: var(--rose); }
    .ac-em::before     { background: var(--em); }
    .ac-vio::before    { background: var(--vio); }
    .ac-amb::before    { background: var(--amb); }
    .ac-cor::before    { background: var(--cor); }
    .ac-tea::before    { background: var(--tea); }
    .ac-pnk::before    { background: var(--pnk); }

    /* ── Card Header ────────────────────────────────────────────── */
    .ch { display: flex; align-items: center; gap: 10px; margin-bottom: 1rem; }
    .ci {
        width: 36px; height: 36px; border-radius: 11px;
        display: flex; align-items: center; justify-content: center;
        font-size: .9rem; flex-shrink: 0; transition: transform .2s;
    }
    .bc:hover .ci { transform: scale(1.1); }
    .ct  { font-size: .8rem; font-weight: 800; color: #1e293b; line-height: 1.2; }
    .cst { font-size: .6rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: .12em; }

    /* ── Filter Pills ────────────────────────────────────────────── */
    .fp {
        display: inline-flex; gap: 3px;
        background: #f1f5f9; border-radius: 10px; padding: 3px;
    }
    .fp button {
        font-size: .6rem; font-weight: 800; padding: 3px 9px;
        border-radius: 7px; border: none; cursor: pointer;
        background: transparent; color: #64748b;
        letter-spacing: .06em; text-transform: uppercase;
        transition: all .15s;
    }
    .fp button.on { background: #fff; color: #1e293b; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
    .fp button:hover:not(.on) { color: #334155; }

    /* ── KPI Cards ──────────────────────────────────────────────── */
    .kc {
        border-radius: 20px; padding: 1.1rem 1.4rem; color: #fff;
        position: relative; overflow: hidden;
        transition: transform .2s ease, box-shadow .2s ease;
    }
    .kc:hover { transform: translateY(-3px); }
    .kc-bg-ico { position: absolute; right: -10px; bottom: -10px; font-size: 4.5rem; opacity: .12; }
    .kc-badge {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: .58rem; font-weight: 800; letter-spacing: .08em;
        text-transform: uppercase; background: rgba(255,255,255,.2);
        border: 1px solid rgba(255,255,255,.28); border-radius: 999px;
        padding: 2px 9px;
    }
    .kc-val { font-size: 2.4rem; font-weight: 900; line-height: 1; margin: .4rem 0 .2rem; }
    .kc-lbl { font-size: .6rem; font-weight: 800; text-transform: uppercase; letter-spacing: .15em; opacity: .72; }
    .kc-delta { font-size: .65rem; font-weight: 700; opacity: .8; margin-top: .35rem; }
    .kc-sky  { background: linear-gradient(135deg,#0ea5e9,#38bdf8); box-shadow: 0 8px 24px rgba(14,165,233,.3); }
    .kc-rose { background: linear-gradient(135deg,#f43f5e,#fb7185); box-shadow: 0 8px 24px rgba(244,63,94,.3); }
    .kc-em   { background: linear-gradient(135deg,#10b981,#34d399); box-shadow: 0 8px 24px rgba(16,185,129,.3); }
    .kc-vio  { background: linear-gradient(135deg,#8b5cf6,#a78bfa); box-shadow: 0 8px 24px rgba(139,92,246,.3); }

    /* ── Hero ────────────────────────────────────────────────────── */
    .hero {
        background: linear-gradient(135deg,#4f46e5 0%,#7c3aed 55%,#a855f7 100%);
        border-radius: 22px;
        box-shadow: 0 10px 36px rgba(79,70,229,.25);
        position: relative; overflow: hidden;
    }
    .hero::after {
        content: ''; position: absolute; right: -50px; top: -50px;
        width: 260px; height: 260px; border-radius: 50%;
        background: rgba(255,255,255,.06); pointer-events: none;
    }
    .hero::before {
        content: ''; position: absolute; right: 110px; bottom: -30px;
        width: 130px; height: 130px; border-radius: 50%;
        background: rgba(255,255,255,.04); pointer-events: none;
    }

    /* ── Status Pill ─────────────────────────────────────────────── */
    .sp {
        display: inline-flex; align-items: center; gap: 3px;
        font-size: .58rem; font-weight: 800; text-transform: uppercase;
        letter-spacing: .1em; padding: 2px 9px; border-radius: 999px;
    }
    .sp::before { content: ''; width: 5px; height: 5px; border-radius: 50%; }
    .sp-rose  { background: var(--rose-lt); color: var(--rose-dk); }
    .sp-rose::before  { background: var(--rose-dk); }
    .sp-amb   { background: var(--amb-lt);  color: var(--amb-dk); }
    .sp-amb::before   { background: var(--amb-dk); }
    .sp-sky   { background: var(--sky-lt);  color: var(--sky-dk); }
    .sp-sky::before   { background: var(--sky-dk); }
    .sp-em    { background: var(--em-lt);   color: var(--em-dk); }

    /* ── Queue Row ───────────────────────────────────────────────── */
    .qr {
        display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .8rem; border-radius: 11px;
        background: #f8fafc; border-left: 3px solid var(--rose);
        transition: all .15s;
    }
    .qr:hover { background: #fee2e2; transform: translateX(2px); }
    .qr.p2    { border-color: var(--amb); }
    .qr.p2:hover { background: #fef3c7; }
    .qr.p3    { border-color: var(--sky); }
    .qr.p3:hover { background: #e0f2fe; }

    /* ── Heatmap ─────────────────────────────────────────────────── */
    .hm-cell {
        border-radius: 5px; cursor: help; min-height: 22px;
        transition: transform .12s, box-shadow .12s;
    }
    .hm-cell:hover { transform: scale(1.5); box-shadow: 0 2px 6px rgba(0,0,0,.15); z-index: 5; position: relative; }
    .hm-0 { background: #f8fafc; border: 1px solid #f1f5f9; }
    .hm-1 { background: #eff6ff; } /* blue-50 */
    .hm-2 { background: #dbeafe; } /* blue-100 */
    .hm-3 { background: #bfdbfe; } /* blue-200 */
    .hm-4 { background: #93c5fd; } /* blue-300 */
    .hm-5 { background: #60a5fa; } /* blue-400 */
    .hm-6 { background: #3b82f6; } /* blue-500 */
    .hm-7 { background: #2563eb; } /* blue-600 */
    .hm-8 { background: #1d4ed8; } /* blue-700 */
    .hm-9 { background: #1e40af; } /* blue-800 */
    .hm-10 { background: #1e3a8a; } /* blue-900 */
    .hm-11 { background: #172554; } /* blue-950 */

    /* Custom Tooltip */
    #hm-tooltip {
        position: fixed; pointer-events: none; z-index: 9999;
        background: rgba(15, 23, 42, 0.98); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
        color: white; padding: 14px; border-radius: 16px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
        border: 1px solid rgba(255,255,255,0.12);
        display: none; min-width: 220px; max-height: 400px; overflow-y: auto;
        font-size: 11px; line-height: 1.6;
    }
    .htt-title { font-weight: 900; text-transform: uppercase; letter-spacing: 0.1em; color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 6px; margin-bottom: 8px; }
    .htt-total { font-size: 14px; font-weight: 900; color: #fff; display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; }
    .htt-row { display: flex; align-items: center; justify-content: space-between; color: #cbd5e1; }
    .htt-dot { width: 6px; height: 6px; border-radius: 50%; margin-right: 8px; }

    /* ── Live pulse dot ─────────────────────────────────────────── */
    .live-dot {
        display: inline-block; width: 7px; height: 7px;
        border-radius: 50%; background: #4ade80;
        animation: pulse-dot 1.8s ease-in-out infinite;
    }
    @keyframes pulse-dot {
        0%,100% { opacity: 1; transform: scale(1); }
        50%      { opacity: .5; transform: scale(.7); }
    }

    /* ── Progress Bars ──────────────────────────────────────────── */
    .pbar-wrap { height: 6px; background: #f1f5f9; border-radius: 999px; overflow: hidden; }
    .pbar-fill { height: 100%; border-radius: 999px; transition: width .7s cubic-bezier(.4,0,.2,1); }

    /* ── Gauge center overlay ────────────────────────────────────── */
    .gauge-box { position: relative; }
    .gauge-center {
        position: absolute; inset: 0;
        display: flex; flex-direction: column;
        align-items: center; justify-content: flex-end;
        padding-bottom: 18px; text-align: center;
        pointer-events: none;
    }

    /* ── Stat mini card ─────────────────────────────────────────── */
    .smc { text-align: center; padding: .45rem .5rem; border-radius: 10px; }

    /* ── Utility ─────────────────────────────────────────────────── */
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    .divider { height: 1px; background: #f1f5f9; margin: .75rem 0; }
    /* ── Split Card Body (Donut on left, Legend on right) ────────── */
    .v2-split-card-body {
        display: grid;
        grid-template-columns: 140px 1fr;
        align-items: center;
        gap: 1.25rem;
        flex: 1;
    }
    @media (max-width: 640px) {
        .v2-split-card-body {
            grid-template-columns: 1fr;
            justify-items: center;
        }
    }

    /* ── Row 2: Heatmap + Weekly Call Volume ────────────────────────── */
    .row-heatmap-weekly {
        display: grid;
        grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
        gap: 1.25rem;
        align-items: stretch;
        width: 100%;
    }
    @media (max-width: 1024px) {
        .row-heatmap-weekly {
            grid-template-columns: 1fr;
        }
    }

    /* ── Live Incident Map ───────────────────────────────────────── */
    .map-canvas { background: #eef2f7; border: 1px solid rgba(15,23,42,.08); }
    .map-canvas .leaflet-control-zoom { border: 1px solid #e2e8f0 !important; border-radius: 12px !important; overflow: hidden; box-shadow: 0 10px 26px -8px rgba(15,23,42,.35) !important; }
    .map-canvas .leaflet-control-zoom a { background: #fff !important; color: #334155 !important; border-color: #e2e8f0 !important; }
    .map-canvas .leaflet-control-zoom a:hover { background: #f8fafc !important; }
    .map-canvas .leaflet-control-attribution { background: rgba(255,255,255,.85) !important; color: #64748b !important; border-radius: 8px 0 0 0; }
    .map-canvas .leaflet-control-attribution a { color: #475569 !important; }

    .map-pin { position: relative; width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; }
    .map-pin .core { width: 13px; height: 13px; border-radius: 999px; border: 2.5px solid #fff; box-shadow: 0 1px 4px rgba(15,23,42,.4), 0 0 10px 1px currentColor; }
    .map-pin .halo { position: absolute; inset: 0; border-radius: 999px; background: currentColor; opacity: .4; animation: mapPulse 2.2s ease-out infinite; }
    .map-pin.p1 { color: #e11d48; } .map-pin.p2 { color: #d97706; } .map-pin.p3 { color: #0284c7; }
    @keyframes mapPulse { 0% { transform: scale(.4); opacity: .55; } 100% { transform: scale(1.9); opacity: 0; } }

    .map-cluster { position: relative; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; }
    .map-cluster .cc-core { width: 42%; height: 42%; border-radius: 999px; background: currentColor; border: 2.5px solid #fff; box-shadow: 0 2px 8px rgba(15,23,42,.35), 0 0 14px 2px currentColor; }
    .map-cluster .cc-ring { position: absolute; inset: 0; border-radius: 999px; border: 2px solid currentColor; opacity: .55; animation: clusterPulse 2.4s ease-out infinite; }
    .map-cluster .cc-ring.r2 { animation-delay: .8s; }
    @keyframes clusterPulse { 0% { transform: scale(.55); opacity: .6; } 100% { transform: scale(1.55); opacity: 0; } }

    .map-canvas .leaflet-popup-content-wrapper {
        background: #fff; color: #1e293b; border: 1px solid #e2e8f0; border-radius: 16px;
        box-shadow: 0 20px 44px -14px rgba(15,23,42,.35);
    }
    .map-canvas .leaflet-popup-tip { background: #fff; box-shadow: none; }
    .map-canvas .leaflet-popup-content { margin: 12px 14px; font-family: Inter, sans-serif; min-width: 210px; }
    .map-canvas .leaflet-popup-close-button { color: #94a3b8 !important; }
    .map-popup-title { font-size: .8rem; font-weight: 800; color: #0f172a; margin-bottom: 5px; }
    .map-popup-row { display: flex; align-items: baseline; gap: 6px; font-size: .68rem; line-height: 1.55; }
    .map-popup-row .lbl { flex-shrink: 0; width: 54px; font-weight: 800; color: #94a3b8; text-transform: uppercase; font-size: .58rem; letter-spacing: .05em; }
    .map-popup-row .val { color: #334155; font-weight: 600; min-width: 0; overflow-wrap: anywhere; }

    .cc-fs-btn {
        width: 30px; height: 30px; border-radius: 9px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center; font-size: .75rem;
        background: rgba(15,23,42,.05); border: 1px solid rgba(15,23,42,.08); color: #475569;
        transition: all .15s;
    }
    .cc-fs-btn:hover { background: rgba(5,150,105,.12); color: #059669; border-color: rgba(5,150,105,.25); }

    #ccMapCard:fullscreen,
    #ccMapCard:-webkit-full-screen {
        display: flex; flex-direction: column;
        width: 100vw !important; height: 100vh !important;
        max-height: 100vh !important;
        padding: 2rem 2.5rem 1.5rem;
        border-radius: 0;
        background: linear-gradient(160deg, #f8fafc 0%, #eef2f7 100%);
    }
    #ccMapCard:fullscreen .map-canvas,
    #ccMapCard:-webkit-full-screen .map-canvas {
        flex: 1 1 auto;
        height: auto !important;
    }
</style>

@php
    $callCategories = collect($callCategories)->sortByDesc('count')->values();
    $carriageTypeSplit = collect($carriageTypeSplit)->sortByDesc('count')->values();

    $dashboardJsonData = [
        'labels'            => $chartLabels,
        'values'            => $chartData,
        'categories'        => $callCategories,
        'tigerStatus'       => $tigerStatus,
        'heatmapData'       => $heatmapData,
        'priorityDist'      => $priorityDist,
        'agentPerf'         => $agentPerformance,
        'beatIntensity'     => $beatIntensity,
        'sectorLoad'        => $sectorLoad,
        'carriageTypeSplit' => $carriageTypeSplit,
        'shiftDeployment'   => $shiftDeployment,
        'slaValue'          => $slaValue,
        'hourlyLabels'      => $hourlyLabels,
        'hourlyData'        => $hourlyData,
        'sunburstData'      => $sunburstData,
        'mapIncidents'      => $mapIncidents,
        'mapUnmappedCount'  => $mapUnmappedCount,
    ];
@endphp

<script id="dashboard-raw-data" type="application/json">{!! json_encode($dashboardJsonData) !!}</script>
<script>
    window._dashboardData = JSON.parse(document.getElementById('dashboard-raw-data').textContent);
</script>

<div
    x-data="dashboardApp(window._dashboardData)"
    class="max-w-[1720px] mx-auto space-y-8 pb-16">

    {{-- ══════════════════════════════════════════════
         HERO BANNER
    ══════════════════════════════════════════════ --}}
    <div class="relative overflow-hidden rounded-[24px] bg-gradient-to-br from-indigo-600 via-violet-600 to-purple-700 shadow-xl shadow-indigo-100 p-6 lg:p-8">
        {{-- Animated Orbs --}}
        <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
        <div class="absolute right-40 -bottom-20 w-60 h-60 bg-indigo-500/20 rounded-full blur-3xl"></div>
        
        <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">

            {{-- Left: brand + greeting --}}
            <div class="flex items-center gap-5">
                <div class="w-14 h-14 rounded-2xl bg-white/15 border border-white/20 backdrop-blur-md flex items-center justify-center shadow-inner shrink-0 scale-110">
                    <i class="fa-solid fa-satellite-dish text-white text-xl animate-pulse"></i>
                </div>
                <div>
                    <div class="flex items-center gap-3 mb-1">
                        <h2 class="text-2xl font-black tracking-tight leading-none text-white">System Operational</h2>
                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 shadow-[0_0_10px_#4ade80]"></span>
                    </div>
                    <p class="text-indigo-100 text-sm font-medium">
                        Welcome back, <span class="text-white font-black">{{ auth()->user()->username ?? 'Commander' }}</span>
                        <span class="mx-2 opacity-40">|</span> 
                        <span class="font-mono text-xs opacity-90">{{ now()->format('D, d M Y') }}</span>
                    </p>
                </div>
            </div>

            {{-- Right: quick KPI strip --}}
            <div class="flex items-center gap-8 lg:gap-10">
                <div class="text-right">
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-indigo-200 opacity-80">Active Load</div>
                    <div class="text-3xl font-black mt-1 text-white tabular-nums">{{ $activeCallsCount }}<span class="text-sm font-bold text-indigo-200/60 ml-1.5 uppercase">calls</span></div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div class="text-right">
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-rose-200">Critical</div>
                    <div class="text-3xl font-black mt-1 text-white tabular-nums">{{ $p1EmergencyCount }}<span class="text-sm font-bold text-rose-300/60 ml-1.5 uppercase">P1</span></div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div class="text-right">
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-200">Fleet</div>
                    <div class="text-3xl font-black mt-1 text-white tabular-nums">{{ $fleetUtilization }}<span class="text-sm font-bold text-emerald-300/60 ml-1.5 uppercase">%</span></div>
                </div>
                <div class="w-px h-10 bg-white/10"></div>
                <div class="text-right">
                    <div class="text-[10px] font-black uppercase tracking-[0.2em] text-amber-200">Personnel</div>
                    <div class="text-3xl font-black mt-1 text-white tabular-nums">{{ $activeSentinelsCount }}<span class="text-sm font-bold text-amber-300/60 ml-1.5 uppercase">active</span></div>
                </div>
            </div>

        </div>
    </div>

    {{-- ══════════════════════════════════════════════
         KPI CARDS ROW
    ══════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5">

        <div class="kc kc-sky">
            <div class="kc-bg-ico"><i class="fa-solid fa-headset"></i></div>
            <div class="relative z-10">
                <span class="kc-badge"><i class="fa-solid fa-circle animate-pulse" style="font-size:.45rem"></i> Live Queue</span>
                <div class="kc-val">{{ $activeCallsCount }}</div>
                <div class="kc-lbl">Active Calls</div>
                <div class="kc-delta"><i class="fa-solid fa-arrow-up text-[.55rem]"></i> +3 vs last hour</div>
            </div>
        </div>

        <div class="kc kc-rose">
            <div class="kc-bg-ico"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div class="relative z-10">
                <span class="kc-badge"><i class="fa-solid fa-bolt" style="font-size:.55rem"></i> Emergency</span>
                <div class="kc-val">{{ $p1EmergencyCount }}</div>
                <div class="kc-lbl">P1 Priority</div>
                <div class="kc-delta"><i class="fa-solid fa-arrow-down text-[.55rem]"></i> -1 resolved</div>
            </div>
        </div>

        <a href="{{ route('admin.users.index') }}" class="kc kc-em block">
            <div class="kc-bg-ico"><i class="fa-solid fa-user-shield"></i></div>
            <div class="relative z-10">
                <span class="kc-badge"><i class="fa-solid fa-shield-halved" style="font-size:.55rem"></i> Active Force</span>
                <div class="kc-val">{{ $activeSentinelsCount }}<span class="text-lg font-bold opacity-55"> / {{ $totalSystemUsers }}</span></div>
                <div class="kc-lbl">Authorized Sentinels</div>
                <div class="kc-delta">{{ $totalSystemUsers - $activeSentinelsCount }} users currently offline</div>
            </div>
        </a>

        <div class="kc kc-vio">
            <div class="kc-bg-ico"><i class="fa-solid fa-chart-pie"></i></div>
            <div class="relative z-10">
                <span class="kc-badge"><i class="fa-solid fa-bolt" style="font-size:.55rem"></i> Utilization</span>
                <div class="kc-val">{{ $fleetUtilization }}<span class="text-xl">%</span></div>
                <div class="kc-lbl">Fleet Load</div>
                <div class="kc-delta">
                    @if($fleetUtilization >= 80) <i class="fa-solid fa-triangle-exclamation text-[.55rem]"></i> High load
                    @elseif($fleetUtilization >= 50) Moderate load
                    @else <i class="fa-solid fa-check text-[.55rem]"></i> Normal load
                    @endif
                </div>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════
         ROW 1 — LIVE INCIDENT MAP  +  CALL CLASSIFICATION & PRIORITY
    ══════════════════════════════════════════════ --}}
    <div class="grid lg:grid-cols-3 gap-5 items-stretch lg:h-[490px]">

        {{-- Live Incident Map --}}
        <div class="bc ac-em lg:col-span-2 flex flex-col h-full min-h-[380px] lg:min-h-0 lg:max-h-[490px]" id="ccMapCard">
            <div class="ch">
                <div class="ci bg-emerald-100"><i class="fa-solid fa-map-location-dot text-emerald-600"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="ct">Live Incident Map</div>
                    <div class="cst">Open Helps · Positioned at Assigned Beat</div>
                </div>
                <div class="flex items-center gap-3 flex-wrap justify-end">
                    <div class="flex items-center gap-2.5 text-[9px] font-black uppercase tracking-widest">
                        <span class="flex items-center gap-1 text-rose-600"><span class="w-2 h-2 rounded-full inline-block bg-rose-500 shadow-[0_0_6px_#f43f5e]"></span>P1</span>
                        <span class="flex items-center gap-1 text-amber-600"><span class="w-2 h-2 rounded-full inline-block bg-amber-500 shadow-[0_0_6px_#f59e0b]"></span>P2</span>
                        <span class="flex items-center gap-1 text-sky-600"><span class="w-2 h-2 rounded-full inline-block bg-sky-500 shadow-[0_0_6px_#0ea5e9]"></span>P3</span>
                    </div>
                    <span class="sp sp-em text-[9px]"><span id="ccMapMappedCount">{{ $mapIncidents->count() }}</span> mapped</span>
                    @if($mapUnmappedCount > 0)
                    <span class="sp sp-slate text-[9px]">{{ $mapUnmappedCount }} unmapped</span>
                    @endif
                    <button @click="toggleMapFullscreen()" class="cc-fs-btn" :title="mapFullscreen ? 'Exit fullscreen' : 'Fullscreen'">
                        <i class="fa-solid" :class="mapFullscreen ? 'fa-compress' : 'fa-expand'"></i>
                    </button>
                </div>
            </div>
            <div id="ccIncidentMap" class="map-canvas flex-1 w-full min-h-0" style="border-radius:14px; overflow:hidden; position:relative; min-height: 280px; height: 100%;"></div>
            <div class="mt-2 text-[9px] font-bold text-slate-400 flex items-center justify-between">
                <span><i class="fa-solid fa-circle-info mr-1"></i>Per-call GPS mapped to assigned beat centroid.</span>
                <span>Basemap © <a href="https://www.esri.com" target="_blank" rel="noopener" class="underline decoration-dotted">Esri</a></span>
            </div>
        </div>

        {{-- Right Column: Stacked Classification Donut & Priority Response --}}
        <div class="flex flex-col gap-5 justify-between h-full min-h-0 lg:max-h-[490px]">

            {{-- Help Classification --}}
            <div class="bc ac-vio flex-1 min-h-0 overflow-hidden" style="max-height: 235px;">
                <div class="ch">
                    <div class="ci bg-violet-100"><i class="fa-solid fa-chart-pie text-violet-600"></i></div>
                    <div class="flex-1">
                        <div class="ct">Help Classification</div>
                        <div class="cst" id="categorySubtitle">All-Time Cases</div>
                    </div>
                    <div class="fp shrink-0">
                        <button class="on" onclick="setCategoryRange('all',this)">All</button>
                        <button onclick="setCategoryRange('today',this)">Today</button>
                        <button onclick="setCategoryRange('30d',this)">30d</button>
                        <button onclick="setCategoryRange('7d',this)">7d</button>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 14px; width: 100%; flex: 1; min-height: 0;">
                    <div style="width: 130px; height: 130px; min-width: 130px; min-height: 130px; position: relative; flex-shrink: 0; margin: 0 auto;">
                        <canvas id="categoryChart" role="img" aria-label="Call categories donut" style="width: 130px !important; height: 130px !important; display: block;"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <div class="text-sm font-black text-slate-800 leading-tight text-center" id="totalCases">{{ number_format($callCategories->sum('count')) }}</div>
                            <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest text-center">Total</div>
                        </div>
                    </div>
                    <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: space-between; height: 120px;" id="categoryLegend">
                        @foreach($callCategories->take(5) as $cat)
                        @php
                            $palette = ['#0ea5e9','#10b981','#f59e0b','#f43f5e','#8b5cf6','#f97316','#14b8a6'];
                            $ci = $loop->index % count($palette);
                            $total_cat = $callCategories->sum('count') ?: 1;
                            $pct_cat = round(($cat->count / $total_cat) * 100);
                        @endphp
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: '{{ $palette[$ci] }}' }"></span>
                            <span class="font-semibold text-slate-600 truncate flex-1">{{ ucwords(str_replace('_', ' ', $cat->category)) }}</span>
                            <span class="text-[10px] font-black text-slate-400 shrink-0">{{ $pct_cat }}%</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Priority Response / Trajectory --}}
            <div class="bc ac-rose flex-1 min-h-0 overflow-hidden" style="max-height: 235px;">
                <div class="ch">
                    <div class="ci bg-rose-100"><i class="fa-solid fa-arrow-trend-up text-rose-600"></i></div>
                    <div class="flex-1">
                        <div class="ct">Priority Response</div>
                        <div class="cst">Severity Load Trend</div>
                    </div>
                    <div class="fp shrink-0">
                        <button class="on" onclick="setPrioRange('7d',this)">7d</button>
                        <button onclick="setPrioRange('14d',this)">14d</button>
                        <button onclick="setPrioRange('30d',this)">30d</button>
                    </div>
                </div>
                <div style="height: 125px; max-height: 125px; position: relative; width: 100%; min-height: 0;">
                    <canvas id="priorityChart" role="img" aria-label="Stacked priority bar chart" style="height: 125px !important; width: 100% !important; display: block;">P1 P2 P3 distribution.</canvas>
                </div>
                <div class="flex items-center justify-between gap-2 mt-2 text-[9px] font-black flex-wrap border-t border-slate-100 pt-2">
                    <span class="flex items-center gap-1 text-rose-600"><span class="w-2 h-2 rounded-full inline-block bg-rose-500"></span>P1 Critical</span>
                    <span class="flex items-center gap-1 text-amber-600"><span class="w-2 h-2 rounded-full inline-block bg-amber-500"></span>P2 Urgent</span>
                    <span class="flex items-center gap-1 text-sky-600"><span class="w-2 h-2 rounded-full inline-block bg-sky-500"></span>P3 Routine</span>
                </div>
            </div>

        </div>

    </div>

    {{-- ══════════════════════════════════════════════
         ROW 2 — ACTIVITY HEATMAP  +  WEEKLY CALL VOLUME
    ══════════════════════════════════════════════ --}}
    <div class="row-heatmap-weekly">

        {{-- Activity Heatmap --}}
        <div class="bc ac-sky flex flex-col justify-between w-full" style="min-width: 0;">
            <div class="ch">
                <div class="ci bg-sky-100"><i class="fa-solid fa-fire-flame-curved text-sky-600"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="ct">Activity Heatmap</div>
                    <div class="cst">Hourly Density · Last 7 Days</div>
                </div>
                {{-- Legend --}}
                <div class="flex items-center gap-1 text-[8px] font-black text-slate-400 shrink-0 ml-2">
                    <span class="w-2.5 h-2.5 rounded bg-slate-100 border border-slate-200"></span>
                    <span class="w-2.5 h-2.5 rounded bg-[#eff6ff]"></span>
                    <span class="w-2.5 h-2.5 rounded bg-[#bfdbfe]"></span>
                    <span class="w-2.5 h-2.5 rounded bg-[#60a5fa]"></span>
                    <span class="w-2.5 h-2.5 rounded bg-[#2563eb]"></span>
                    <span class="w-2.5 h-2.5 rounded bg-[#1e40af]"></span>
                    <span class="w-2.5 h-2.5 rounded bg-[#172554]"></span>
                    <span class="ml-1 uppercase tracking-widest hidden sm:inline">Density Scale</span>
                </div>
            </div>
            {{-- Grid: day labels + cells --}}
            <div class="flex gap-2.5 items-stretch flex-1 w-full" style="min-height: 205px; height: 205px;">
                <div class="flex flex-col text-[10px] font-black text-slate-400 uppercase tracking-widest shrink-0 py-1 justify-between">
                    @foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $d)
                    <div class="flex-1 flex items-center">{{ $d }}</div>
                    @endforeach
                </div>
                <div class="flex-1 min-w-0 flex flex-col w-full">
                    <div id="heatmapGrid" class="flex-1 w-full"
                         style="display:grid; grid-template-columns:repeat(24,1fr); grid-template-rows:repeat(7,1fr); gap:3px; height: 100%; width: 100%;"></div>
                    <div class="flex justify-between text-[10px] font-bold text-slate-300 mt-2 px-0.5">
                        <span>12a</span><span>3a</span><span>6a</span><span>9a</span><span>12p</span><span>3p</span><span>6p</span><span>9p</span><span>11p</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Weekly Call Volume --}}
        <div class="bc ac-em flex flex-col justify-between w-full" style="min-width: 0;">
            <div class="ch">
                <div class="ci bg-emerald-100"><i class="fa-solid fa-chart-column text-emerald-600"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="ct">Weekly Call Volume</div>
                    <div class="cst">DAILY CALL DISTRIBUTION</div>
                </div>
                <div class="fp shrink-0">
                    <button class="on uppercase text-[10px] font-bold py-1 px-2.5" onclick="setWeeklyRange('7d',this)">7D</button>
                    <button class="uppercase text-[10px] font-bold py-1 px-2.5" onclick="setWeeklyRange('14d',this)">14D</button>
                    <button class="uppercase text-[10px] font-bold py-1 px-2.5" onclick="setWeeklyRange('30d',this)">30D</button>
                </div>
            </div>
            <div style="height: 205px; position: relative; width: 100%;" class="flex-1">
                <canvas id="weeklyChart" role="img" aria-label="Daily call volume bar chart" style="width: 100% !important; height: 205px !important; display: block;"></canvas>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════
         ROW 3 — PERFORMANCE & SLA  +  ZONE WISE HELPERS  +  OPERATIONS SUNBURST
    ══════════════════════════════════════════════ --}}
    <div class="grid lg:grid-cols-3 gap-5 items-stretch">

        {{-- Card 1: Performance & SLA --}}
        <div class="bc ac-em flex flex-col">
            <div class="ch">
                <div class="ci bg-emerald-100"><i class="fa-solid fa-award text-emerald-600"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="ct">Performance & SLA</div>
                    <div class="cst">SERVICE LEVEL AGREEMENT</div>
                </div>
                @php
                    $slaStatus = $slaValue >= 90 ? ['label' => 'EXCELLENT', 'badge' => 'bg-emerald-50 text-emerald-600 border-emerald-200', 'dot' => 'bg-emerald-500']
                               : ($slaValue >= 80 ? ['label' => 'GOOD', 'badge' => 'bg-teal-50 text-teal-600 border-teal-200', 'dot' => 'bg-teal-500']
                               : ($slaValue >= 70 ? ['label' => 'FAIR', 'badge' => 'bg-amber-50 text-amber-600 border-amber-200', 'dot' => 'bg-amber-500']
                               : ['label' => 'ATTENTION', 'badge' => 'bg-rose-50 text-rose-600 border-rose-200', 'dot' => 'bg-rose-500']));
                @endphp
                <span class="px-2 py-0.5 rounded-full text-[9px] font-black {{ $slaStatus['badge'] }} border tracking-wider shrink-0 flex items-center gap-1" id="slaBadge">
                    <span class="w-1.5 h-1.5 rounded-full {{ $slaStatus['dot'] }}"></span> {{ $slaStatus['label'] }}
                </span>
                <div class="fp shrink-0">
                    <button class="on" onclick="setSlaRange('today',this)">Today</button>
                    <button onclick="setSlaRange('7d',this)">Week</button>
                    <button onclick="setSlaRange('30d',this)">Month</button>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex: 1; padding: 4px 0;">
                {{-- Semicircular Radial Gauge (Left) --}}
                <div style="width: 145px; height: 145px; min-width: 145px; position: relative; flex-shrink: 0; display: flex; align-items: center; justify-content: center;">
                    <canvas id="gaugeChart" role="img" aria-label="SLA compliance gauge" style="width: 145px !important; height: 145px !important; display: block;"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-end pb-2 pointer-events-none">
                        <div class="text-3xl font-black text-slate-800 leading-none" id="slaGaugeVal">{{ $slaValue }}%</div>
                        <div class="text-[8px] font-black text-emerald-600 uppercase tracking-wider mt-1">SLA COMPLIANT</div>
                        <div class="text-[8px] font-bold text-slate-400 uppercase tracking-wider" id="slaRangeTag">TODAY</div>
                    </div>
                </div>
                {{-- Operational KPI Metrics (Right Stacked) --}}
                <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: space-between; height: 145px; gap: 5px;" id="slaMetricsContainer">
                    <div class="px-2.5 py-1.5 rounded-xl bg-slate-50 border border-slate-100 flex flex-col justify-between" style="min-height: 42px; flex: 1;">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">AVG. WAIT TIME</span>
                            <span class="text-[9px] font-black text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100">+45%</span>
                        </div>
                        <div class="text-base font-black text-slate-800 leading-tight mt-0.5" id="slaWaitTime">
                            {{ round($helpLineMetrics->wait_time) }} <span class="text-[10px] font-bold text-slate-400">sec</span>
                        </div>
                    </div>

                    <div class="px-2.5 py-1.5 rounded-xl bg-slate-50 border border-slate-100 flex flex-col justify-between" style="min-height: 42px; flex: 1;">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">CREATED → INITIATED</span>
                            <span class="text-[9px] font-black text-sky-600 bg-sky-50 px-1.5 py-0.5 rounded border border-sky-100">+12%</span>
                        </div>
                        <div class="text-base font-black text-slate-800 leading-tight mt-0.5" id="slaCreatedToInit">
                            {{ $helpLineMetrics->created_to_init_formatted }}
                        </div>
                    </div>

                    <div class="px-2.5 py-1.5 rounded-xl bg-slate-50 border border-slate-100 flex flex-col justify-between" style="min-height: 42px; flex: 1;">
                        <div class="flex items-center justify-between">
                            <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">INITIATED → REACHED</span>
                            <span class="text-[9px] font-black text-teal-600 bg-teal-50 px-1.5 py-0.5 rounded border border-teal-100">+32%</span>
                        </div>
                        <div class="text-base font-black text-slate-800 leading-tight mt-0.5" id="slaInitToReached">
                            {{ $helpLineMetrics->init_to_reached_formatted }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Card 2: Zone Wise Helpers --}}
        <div class="bc ac-cor flex flex-col">
            <div class="ch">
                <div class="ci bg-amber-100"><i class="fa-solid fa-compass text-amber-600"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="ct">Zone Wise Helpers</div>
                    <div class="cst">MOTORWAY / ZONE DISTRIBUTION</div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 14px; flex: 1; padding: 4px 0;">
                <div style="width: 135px; height: 135px; min-width: 135px; min-height: 135px; position: relative; flex-shrink: 0;">
                    <canvas id="roadChart" role="img" aria-label="Zone distribution donut" style="width: 135px !important; height: 135px !important; display: block;"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <div class="text-xl font-black text-slate-800 leading-tight" id="zoneCenterCount">{{ $carriageTypeSplit->sum('count') }}</div>
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-0.5">INCIDENTS</div>
                    </div>
                </div>
                @php
                    $roadTotal = $carriageTypeSplit->sum('count') ?: 1;
                    $roadColors = ['#3b82f6', '#f59e0b', '#06b6d4', '#f43f5e', '#eab308', '#8b5cf6', '#6366f1', '#10b981', '#ec4899', '#f97316'];
                @endphp
                <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: space-between; height: 135px;">
                    @foreach($carriageTypeSplit->take(7) as $road)
                    @php $rp = round(($road->count / $roadTotal) * 100); @endphp
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: '{{ $roadColors[$loop->index % count($roadColors)] }}' }"></span>
                        <span class="font-semibold text-slate-600 truncate flex-1">• {{ $road->type }}</span>
                        <span class="text-[11px] font-black text-slate-500 shrink-0 font-mono">{{ $rp }}%</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Card 3: Operations Sunburst --}}
        <div class="bc ac-pnk flex flex-col">
            <div class="ch">
                <div class="ci bg-pink-100"><i class="fa-solid fa-gear text-pink-600"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="ct">Operations Sunburst</div>
                    <div class="cst">ZONE · SECTOR · CATEGORY</div>
                </div>
                <div class="fp shrink-0">
                    <button class="on text-[10px] font-bold py-1 px-2.5 uppercase" onclick="setSunburstRange('today',this)">Today</button>
                    <button class="text-[10px] font-bold py-1 px-2.5 uppercase" onclick="setSunburstRange('week',this)">Week</button>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 14px; flex: 1; padding: 4px 0;">
                <div style="width: 135px; height: 135px; min-width: 135px; min-height: 135px; position: relative; flex-shrink: 0;">
                    <canvas id="sunburstChart" role="img" aria-label="Sunburst chart operations distribution" style="width: 135px !important; height: 135px !important; display: block;"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <div class="text-xl font-black text-slate-800 leading-tight" id="sunburstCenterCount">207</div>
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-0.5 text-center leading-none">TOTAL INCIDENTS</div>
                    </div>
                </div>
                @php
                    $sbCatTotals = [];
                    $sbTotal = 0;
                    foreach ($sunburstData as $z) {
                        $sbTotal += ($z['count'] ?? 0);
                        foreach ($z['sectors'] ?? [] as $s) {
                            foreach ($s['categories'] ?? [] as $cat => $cnt) {
                                $sbCatTotals[$cat] = ($sbCatTotals[$cat] ?? 0) + $cnt;
                            }
                        }
                    }
                    arsort($sbCatTotals);
                    if (empty($sbCatTotals) && isset($callCategories)) {
                        foreach ($callCategories as $c) {
                            $sbCatTotals[$c->category] = $c->count;
                            $sbTotal += $c->count;
                        }
                    }
                    $sbTotal = $sbTotal ?: 1;
                    $sbColors = ['#06b6d4', '#f59e0b', '#f43f5e', '#ea580c', '#3b82f6', '#8b5cf6', '#10b981', '#ec4899'];
                @endphp
                <div id="sunburstLegend" style="flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: space-between; height: 135px;">
                    @php $ci = 0; @endphp
                    @foreach(array_slice($sbCatTotals, 0, 6, true) as $catName => $catCnt)
                    @php 
                        $pct = round(($catCnt / $sbTotal) * 100);
                        $cleanCat = ucwords(str_replace('_', ' ', $catName));
                    @endphp
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: '{{ $sbColors[$ci % count($sbColors)] }}' }"></span>
                        <span class="font-semibold text-slate-600 truncate flex-1">• {{ $cleanCat }}</span>
                        <span class="text-[11px] font-black text-slate-500 shrink-0 font-mono">{{ $pct }}%</span>
                    </div>
                    @php $ci++; @endphp
                    @endforeach
                </div>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════
         ROW 4 — SECTOR LOAD  +  TOP RESPONDERS  +  NUMBER OF INCIDENTS
    ══════════════════════════════════════════════ --}}
    <div class="grid lg:grid-cols-3 gap-5 items-stretch">

        {{-- Card 1: Sector Load --}}
        <div class="bc ac-vio flex flex-col">
            <div class="ch">
                <div class="ci bg-violet-100"><i class="fa-solid fa-chart-simple text-violet-600"></i></div>
                <div>
                    <div class="ct">Sector Load</div>
                    <div class="cst">Sector Wise Help Intensity</div>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 16px; flex: 1; padding: 4px 0;">
                <div style="width: 145px; height: 145px; min-width: 145px; min-height: 145px; position: relative; flex-shrink: 0; margin: 0 auto;">
                    <canvas id="sectorPolarChart" role="img" aria-label="Sector intensity donut" style="width: 145px !important; height: 145px !important; display: block;"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <div class="text-xl font-black text-slate-800 leading-tight">{{ $sectorLoad->sum('count') }}</div>
                        <div class="text-[8px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Agents</div>
                    </div>
                </div>
                @php
                    $sectorTotal = $sectorLoad->sum('count') ?: 1;
                    $sectorColors = ['#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#2563eb', '#f97316'];
                @endphp
                <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: space-between; height: 145px;">
                    @foreach($sectorLoad->take(6) as $sec)
                    @php $sp = round(($sec->count / $sectorTotal) * 100); @endphp
                    <div class="flex items-center gap-1.5 text-xs">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: '{{ $sectorColors[$loop->index % count($sectorColors)] }}' }"></span>
                        <span class="font-semibold text-slate-600 truncate flex-1">{{ $sec->name }}</span>
                        <span class="text-[11px] font-black text-slate-500 shrink-0">{{ $sp }}%</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Card 2: Top Responders --}}
        <div class="bc ac-sky flex flex-col">
            <div class="ch">
                <div class="ci bg-sky-100"><i class="fa-solid fa-trophy text-sky-600"></i></div>
                <div class="flex-1">
                    <div class="ct">Top Responders</div>
                    <div class="cst">Agents by Call Volume</div>
                </div>
                <div class="fp shrink-0">
                    <button class="on" onclick="setAgentRange('today',this)">Today</button>
                    <button onclick="setAgentRange('week',this)">Week</button>
                    <button onclick="setAgentRange('month',this)">Month</button>
                </div>
            </div>
            <div class="flex-1 flex flex-col justify-between pt-1 pb-0.5" id="agentLeaderboardList" style="min-height: 155px;">
                @php $maxCalls = $agentPerformance->max('total_calls') ?: 1; @endphp
                @foreach($agentPerformance->take(5) as $idx => $agent)
                @php
                    $pct = round(($agent->total_calls / $maxCalls) * 100);
                    $words = explode(' ', trim($agent->username));
                    $initials = strtoupper(substr($words[0] ?? 'A', 0, 1) . substr($words[1] ?? '', 0, 1));
                    if (empty($initials)) $initials = 'AG';
                @endphp
                <div class="flex items-center gap-3">
                    <span class="text-xs font-black text-slate-400 w-4 text-right">{{ $idx + 1 }}.</span>
                    <div class="w-7 h-7 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center text-[10px] font-black shrink-0">
                        {{ $initials }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-bold text-slate-800 truncate">{{ $agent->username }}</span>
                            <span class="font-black text-blue-600 tabular-nums ml-2">{{ $agent->total_calls }}</span>
                        </div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-blue-600 h-full rounded-full transition-all duration-500" :style="{ width: '{{ $pct }}%' }"></div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Card 3: Number of Incidents --}}
        <div class="bc ac-rose flex flex-col">
            <div class="ch">
                <div class="ci bg-rose-100"><i class="fa-solid fa-chart-column text-rose-600"></i></div>
                <div class="flex-1">
                    <div class="ct">Number of Incidents</div>
                    <div class="cst">Last 7 Days</div>
                </div>
                <div class="fp shrink-0">
                    <button class="on" onclick="setIncidentRange('7d',this)">Daily</button>
                    <button onclick="setIncidentRange('30d',this)">Weekly</button>
                </div>
            </div>
            <div style="height: 160px; max-height: 160px; position: relative; width: 100%; min-height: 0;">
                <canvas id="incidentVolumeChart" role="img" aria-label="Bar chart daily incident volume" style="height: 160px !important; width: 100% !important; display: block;">Daily incident volume trend.</canvas>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════
         ROW 5 — ZONE WISE ACTIVITY  +  BEAT INCIDENT RANKING
    ══════════════════════════════════════════════ --}}
    <div class="grid lg:grid-cols-2 gap-5">

        {{-- Zone Wise Activity --}}
        <div class="bc ac-amb">
            <div class="ch">
                <div class="ci bg-amber-100"><i class="fa-solid fa-cubes-stacked text-amber-600"></i></div>
                <div class="flex-1">
                    <div class="ct">Zone Wise Activity</div>
                    <div class="cst">Active Responders Per Unit - Real Time</div>
                </div>
                <div class="fp shrink-0">
                    <button class="on" onclick="setHmRange('week',this)">Weekly</button>
                    <button onclick="setHmRange('month',this)">Monthly</button>
                </div>
            </div>
            <div style="height: 190px; max-height: 190px; position: relative; width: 100%; min-height: 0;">
                <canvas id="shiftChart" role="img" aria-label="Bar chart tigers per unit" style="height: 190px !important; width: 100% !important; display: block;">Zone Wise Activity chart.</canvas>
            </div>
        </div>

        {{-- Beat Incident Ranking --}}
        <div class="bc ac-tea">
            <div class="ch">
                <div class="ci bg-cyan-100"><i class="fa-solid fa-ranking-star text-cyan-600"></i></div>
                <div class="flex-1">
                    <div class="ct">Beat Incident Ranking</div>
                    <div class="cst">Top Active Beats by Volume</div>
                </div>
                <div class="fp shrink-0">
                    <button class="on" onclick="sortBeats('desc',this)">Highest</button>
                    <button onclick="sortBeats('asc',this)">Lowest</button>
                </div>
            </div>
            @php
                $maxBeat    = $beatIntensity->max('count') ?: 1;
                $beatPalette = ['#f97316', '#0ea5e9', '#14b8a6', '#8b5cf6', '#f43f5e', '#f59e0b', '#10b981', '#ec4899', '#6366f1', '#06b6d4', '#84cc16', '#eab308'];
            @endphp
            <div class="space-y-3 flex-1 justify-center flex flex-col" id="beatBars">
                @foreach($beatIntensity as $beat)
                @php $pct = round(($beat->count / $maxBeat) * 100); @endphp
                <div data-count="{{ $beat->count }}">
                    <div class="flex justify-between items-center mb-1">
                        <div class="flex items-center gap-2" title="{{ $beat->name }} / {{ $beat->sector_name }} / {{ $beat->zone_name }}">
                            <span class="w-2 h-2 rounded-full shrink-0" :style="{ backgroundColor: '{{ $beatPalette[$loop->index % count($beatPalette)] }}' }"></span>
                            <span class="text-[10px] font-black text-slate-700 hover:text-slate-900 cursor-help" style="border-bottom: 1px dotted #cbd5e1; padding-bottom: 1px;">{{ $beat->zone_name }} &rarr; {{ $beat->sector_name }} &rarr; {{ $beat->name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-slate-400">{{ $beat->count }} incidents</span>
                            <span class="text-[9px] font-black px-1.5 py-0.5 rounded-md"
                                  :style="{ backgroundColor: '{{ $beatPalette[$loop->index % count($beatPalette)] }}22', color: '{{ $beatPalette[$loop->index % count($beatPalette)] }}' }">
                                {{ $pct }}%
                            </span>
                        </div>
                    </div>
                    <div class="pbar-wrap">
                        <div class="pbar-fill" :style="{ width: '{{ $pct }}%', backgroundColor: '{{ $beatPalette[$loop->index % count($beatPalette)] }}' }"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

    </div>

</div>{{-- /x-data --}}
    {{-- Global Heatmap Tooltip --}}
    <div id="hm-tooltip" style="display: none; position: fixed; pointer-events: none; transition: opacity 0.1s ease;"></div>
@endsection

@push('scripts')
{{-- Leaflet JS & MarkerCluster Plugin (Local vendored for CSP compliance & speed) --}}
<script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
<script src="{{ asset('vendor/leaflet/leaflet.markercluster.js') }}"></script>

<script>
/* ─── Global chart refs for filter updates ─── */
window._dashboardCharts = window._dashboardCharts || { prio: null, weekly: null, incidentVolume: null, agent: null, sunburst: null, category: null, gauge: null, road: null, sector: null };
window._dashboardAlpineApp = null;

/* ─── Filter helpers ──────────────────────────*/
function setFilterActive(btn) {
    if (!btn) return;
    btn.closest('.fp').querySelectorAll('button').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
}

async function setCategoryRange(r, btn) {
    setFilterActive(btn);
    const canvas = document.getElementById('categoryChart');
    if (!canvas) return;
    canvas.style.opacity = '0.4';
    try {
        const res = await fetch(`/dashboard/chart-data?chart=category&range=${r}`);
        const data = await res.json();
        
        const sub = document.getElementById('categorySubtitle');
        if (sub) {
            sub.textContent = r === 'today' ? "Today's Cases" : (r === 'all' ? 'All-Time Cases' : (r === '30d' ? '30-Day Distribution' : '7-Day Distribution'));
        }

        if (window._dashboardAlpineApp && typeof window._dashboardAlpineApp.buildCategoryChart === 'function') {
            window._dashboardAlpineApp.buildCategoryChart(data);
        }

        const leg = document.getElementById('categoryLegend');
        if (leg && Array.isArray(data)) {
            const palette = ['#0ea5e9','#10b981','#f59e0b','#f43f5e','#8b5cf6','#f97316','#14b8a6'];
            const total = data.reduce((s, d) => s + (+d.count), 0);
            const totalEl = document.getElementById('totalCases');
            if (totalEl) totalEl.textContent = total.toLocaleString();

            if (data.length === 0) {
                leg.innerHTML = `<div class="text-[11px] font-semibold text-slate-400 py-3 text-center">No cases found for this period</div>`;
            } else {
                leg.innerHTML = data.slice(0, 5).map((d, i) => {
                    const color = palette[i % palette.length];
                    const count = +d.count;
                    const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                    const catName = (d.category || '').replace(/_/g, ' ').replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
                    return `
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: ${color}"></span>
                            <span class="font-semibold text-slate-600 truncate flex-1">${catName}</span>
                            <span class="text-[10px] font-black text-slate-400 shrink-0">${pct}%</span>
                        </div>
                    `;
                }).join('');
            }
        }
    } catch (e) {
        console.error('Failed to update category chart:', e);
    } finally {
        canvas.style.opacity = '1';
    }
}

async function setSlaRange(r, btn) {
    setFilterActive(btn);
    const canvas = document.getElementById('gaugeChart');
    if (!canvas) return;
    canvas.style.opacity = '0.4';
    try {
        const res = await fetch(`/dashboard/chart-data?chart=sla&range=${r}`);
        const d = await res.json();
        if (d && typeof d.sla_value !== 'undefined') {
            const chart = window._dashboardCharts?.gauge;
            if (chart) {
                chart.data.datasets[0].data = [d.sla_value, Math.max(0, 100 - d.sla_value)];
                chart.update();
            }
            const gVal = document.getElementById('slaGaugeVal');
            if (gVal) gVal.textContent = d.sla_value + '%';

            const rTag = document.getElementById('slaRangeTag');
            if (rTag) rTag.textContent = d.range_label || (r === '7d' ? 'LAST 7 DAYS' : (r === '30d' ? 'LAST 30 DAYS' : 'TODAY'));

            const badge = document.getElementById('slaBadge');
            if (badge && d.badge) {
                badge.className = `px-2 py-0.5 rounded-full text-[9px] font-black ${d.badge.badge} border tracking-wider shrink-0 flex items-center gap-1`;
                badge.innerHTML = `<span class="w-1.5 h-1.5 rounded-full ${d.badge.dot}"></span> ${d.badge.label}`;
            }

            const wt = document.getElementById('slaWaitTime');
            if (wt) wt.innerHTML = `${Math.round(d.wait_time)} <span class="text-[10px] font-bold text-slate-400">sec</span>`;

            const ci = document.getElementById('slaCreatedToInit');
            if (ci) ci.textContent = d.created_to_init_formatted;

            const ir = document.getElementById('slaInitToReached');
            if (ir) ir.textContent = d.init_to_reached_formatted;
        }
    } catch (e) {
        console.error('Failed to update SLA metrics:', e);
    } finally {
        canvas.style.opacity = '1';
    }
}

async function setPrioRange(r, btn) {
    setFilterActive(btn);
    const canvas = document.getElementById('priorityChart');
    if (!canvas) return;
    canvas.style.opacity = '0.4';
    try {
        const res = await fetch(`/dashboard/chart-data?chart=priority&range=${r}`);
        const data = await res.json();
        if (window._dashboardCharts.prio && data.labels) {
            window._dashboardCharts.prio.data.labels = data.labels;
            window._dashboardCharts.prio.data.datasets[0].data = data.p1;
            window._dashboardCharts.prio.data.datasets[1].data = data.p2;
            window._dashboardCharts.prio.data.datasets[2].data = data.p3;
            window._dashboardCharts.prio.update();
        }
    } catch (e) {
        console.error('Failed to update priority chart:', e);
    } finally {
        canvas.style.opacity = '1';
    }
}

async function setWeeklyRange(r, btn) {
    setFilterActive(btn);
    const canvas = document.getElementById('weeklyChart');
    if (!canvas) return;
    canvas.style.opacity = '0.4';
    try {
        const res = await fetch(`/dashboard/chart-data?chart=weekly&range=${r}`);
        const data = await res.json();
        if (window._dashboardCharts.weekly && data.labels) {
            window._dashboardCharts.weekly.data.labels = data.labels;
            window._dashboardCharts.weekly.data.datasets[0].data = data.values;
            window._dashboardCharts.weekly.update();
        }
    } catch (e) {
        console.error('Failed to update weekly chart:', e);
    } finally {
        canvas.style.opacity = '1';
    }
}

async function setIncidentRange(r, btn) {
    setFilterActive(btn);
    const canvas = document.getElementById('incidentVolumeChart');
    if (!canvas) return;
    canvas.style.opacity = '0.4';
    try {
        const res = await fetch(`/dashboard/chart-data?chart=weekly&range=${r}`);
        const data = await res.json();
        if (window._dashboardCharts.incidentVolume && data.labels) {
            window._dashboardCharts.incidentVolume.data.labels = data.labels;
            window._dashboardCharts.incidentVolume.data.datasets[0].data = data.values;
            window._dashboardCharts.incidentVolume.update();
        }
    } catch (e) {
        console.error('Failed to update incident volume chart:', e);
    } finally {
        canvas.style.opacity = '1';
    }
}

async function setSunburstRange(r, btn) {
    setFilterActive(btn);
    const canvas = document.getElementById('sunburstChart');
    if (!canvas) return;
    canvas.style.opacity = '0.4';
    try {
        const res = await fetch(`/dashboard/chart-data?chart=sunburst&range=${r}`);
        const data = await res.json();
        if (window._dashboardAlpineApp && typeof window._dashboardAlpineApp.buildSunburstChart === 'function') {
            window._dashboardAlpineApp.buildSunburstChart(data);
        }
    } catch (e) {
        console.error('Failed to update sunburst chart:', e);
    } finally {
        canvas.style.opacity = '1';
    }
}

async function setAgentRange(r, btn) {
    setFilterActive(btn);
    const container = document.getElementById('agentLeaderboardList');
    if (!container) return;
    container.style.opacity = '0.4';
    try {
        const res = await fetch(`/dashboard/chart-data?chart=agent&range=${r}`);
        const data = await res.json();
        if (data && data.labels) {
            const max = Math.max(...data.values, 1);
            container.innerHTML = data.labels.map((name, i) => {
                const count = data.values[i] || 0;
                const pct = Math.round((count / max) * 100);
                const words = (name || '').trim().split(' ');
                const initials = ((words[0] ? words[0][0] : 'A') + (words[1] ? words[1][0] : '')).toUpperCase() || 'AG';
                return `
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-black text-slate-400 w-4 text-right">${i + 1}.</span>
                        <div class="w-7 h-7 rounded-full bg-sky-100 text-sky-700 flex items-center justify-center text-[10px] font-black shrink-0">
                            ${initials}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center justify-between text-xs mb-1">
                                <span class="font-bold text-slate-800 truncate">${name}</span>
                                <span class="font-black text-blue-600 tabular-nums ml-2">${count}</span>
                            </div>
                            <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                                <div class="bg-blue-600 h-full rounded-full transition-all duration-500" style="width: ${pct}%"></div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }
    } catch (e) {
        console.error('Failed to update agent leaderboard:', e);
    } finally {
        container.style.opacity = '1';
    }
}

function setHmRange(r, btn) { setFilterActive(btn); }

function filterInterventions(prio, btn) {
    setFilterActive(btn);
    const rows = document.querySelectorAll('.intervention-row');
    rows.forEach(r => {
        if (prio === 'all' || r.dataset.prio === prio) r.style.display = 'flex';
        else r.style.display = 'none';
    });
}

function sortBeats(dir, btn) {
    setFilterActive(btn);
    const container = document.getElementById('beatBars');
    const items = [...container.children];
    items.sort((a, b) => dir === 'desc'
        ? b.dataset.count - a.dataset.count
        : a.dataset.count - b.dataset.count);
    items.forEach(i => container.appendChild(i));
}

/* ─── Alpine component ─────────────────────── */
function dashboardApp(data) {
    return {
        ...data,
        mapFullscreen: false,
        toggleMapFullscreen() {
            const el = document.getElementById('ccMapCard');
            if (!el) return;
            this.mapFullscreen = !this.mapFullscreen;
            if (this.mapFullscreen) {
                (el.requestFullscreen || el.webkitRequestFullscreen)?.call(el);
            } else {
                (document.exitFullscreen || document.webkitExitFullscreen)?.call(document);
            }
        },
        init() {
            window._dashboardAlpineApp = this;
            this.$nextTick(() => {
                // Destroy existing charts to prevent SPA "messy UI" or duplicate renders
                if (window._dashboardCharts) {
                    Object.keys(window._dashboardCharts).forEach(key => {
                        if (window._dashboardCharts[key]) {
                            window._dashboardCharts[key].destroy();
                            window._dashboardCharts[key] = null;
                        }
                    });
                }

                this.buildIncidentMap(this.mapIncidents);
                this.buildHeatmap(this.heatmapData);
                this.buildCategoryChart(this.categories);
                this.buildGaugeChart();
                this.buildPriorityChart(this.priorityDist);
                this.buildWeeklyChart(this.labels, this.values);
                this.buildIncidentVolumeChart(this.labels, this.values);
                this.buildRoadChart(this.carriageTypeSplit);
                this.buildSunburstChart(this.sunburstData);
                this.buildSectorPolarChart(this.sectorLoad);
                this.buildShiftChart(this.shiftDeployment);

                document.addEventListener('fullscreenchange', () => {
                    this.mapFullscreen = !!document.fullscreenElement;
                    setTimeout(() => { if (window._ccMap) window._ccMap.invalidateSize(); }, 200);
                });
                
                // Ensure map container calculates correct viewport
                if (window._ccMap) {
                    setTimeout(() => { if (window._ccMap) window._ccMap.invalidateSize(); }, 200);
                }
            });
        },

        /* ── Live Incident Map ─────────────────── */
        buildIncidentMap(incidents) {
            const el = document.getElementById('ccIncidentMap');
            if (!el || typeof L === 'undefined') return;

            if (window._ccMap) { window._ccMap.remove(); window._ccMap = null; }

            const pts = (incidents || []).filter(i => Number.isFinite(+i.lat) && Number.isFinite(+i.lng));
            const center = pts.length
                ? [pts.reduce((s,p) => s + (+p.lat), 0) / pts.length, pts.reduce((s,p) => s + (+p.lng), 0) / pts.length]
                : [30.3753, 69.3451]; // Pakistan centroid fallback

            const map = L.map(el, {
                center, zoom: pts.length ? 7 : 5,
                zoomControl: true, attributionControl: false, scrollWheelZoom: true,
                preferCanvas: true,
            });
            window._ccMap = map;

            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 19,
                attribution: 'Tiles &copy; Esri &mdash; Source: Esri, DeLorme, NAVTEQ, USGS, Intermap, iPC, NRCAN, Esri Japan, METI, Esri China (Hong Kong), Esri (Thailand), TomTom'
            }).addTo(map);

            const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
            const priorityMeta = (p) => (+p === 1
                ? { cls: 'p1', label: 'P1 Critical', color: '#e11d48' }
                : (+p === 2 ? { cls: 'p2', label: 'P2 Urgent', color: '#d97706' } : { cls: 'p3', label: 'P3 Routine', color: '#0284c7' }));

            const statusMeta = (s) => {
                const st = String(s || '').toLowerCase().trim();
                if (st === 'pending') {
                    return { label: 'Pending', color: '#d97706' };
                } else if (st === 'in_progress') {
                    return { label: 'In Progress', color: '#0284c7' };
                } else if (st === 'tiger_reached') {
                    return { label: 'Tiger Reached', color: '#0d9488' };
                } else if (st === 'completed') {
                    return { label: 'Completed', color: '#16a34a' };
                } else if (st === 'cancelled') {
                    return { label: 'Cancelled', color: '#ef4444' };
                }
                return { label: st.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()), color: '#64748b' };
            };

            const toTitleCase = (str) => {
                if (!str) return '';
                return String(str)
                    .toLowerCase()
                    .replace(/(?:^|\s|\/|-)\w/g, c => c.toUpperCase());
            };

            const clusterGroup = L.markerClusterGroup({
                showCoverageOnHover: false,
                spiderfyOnMaxZoom: true,
                maxClusterRadius: 50,
                iconCreateFunction: (cluster) => {
                    const children = cluster.getAllChildMarkers();
                    let worst = 3;
                    children.forEach(m => { if (m.options.priorityNum && m.options.priorityNum < worst) worst = m.options.priorityNum; });
                    const color = priorityMeta(worst).color;
                    const count = cluster.getChildCount();
                    const size = count >= 20 ? 46 : (count >= 8 ? 40 : 34);
                    return L.divIcon({
                        html: `<div class="map-cluster" style="color:${color};"><span class="cc-ring"></span><span class="cc-ring r2"></span><span class="cc-core"></span></div>`,
                        className: '', iconSize: [size, size],
                    });
                },
            });

            pts.forEach((p) => {
                const pr = priorityMeta(p.priority);
                const st = statusMeta(p.status);
                const icon = L.divIcon({
                    className: '',
                    html: `<div class="map-pin ${pr.cls}"><span class="halo"></span><span class="core"></span></div>`,
                    iconSize: [26, 26], iconAnchor: [13, 13],
                });
                const marker = L.marker([+p.lat, +p.lng], { icon, priorityNum: +p.priority });
                const beatPath = [p.zone, p.sector, p.beat].filter(Boolean).map(esc).join(' → ') || 'Unassigned';
                const callerName = toTitleCase(p.caller_name || 'Unknown Caller');
                const tigerLabel = p.tiger || 'Not Assigned';
                const isAssigned = tigerLabel !== 'Not Assigned' && tigerLabel !== 'Unassigned';
                const tigerStyle = isAssigned ? 'font-weight:700; color:#0f172a;' : 'color:#94a3b8; font-style:italic; font-weight:600;';

                marker.bindPopup(`
                    <div class="map-popup-title">${esc(p.type || 'Call')} <span style="color:${pr.color};font-weight:700;">· ${pr.label}</span></div>
                    <div class="map-popup-row"><span class="lbl">Caller</span><span class="val">${esc(callerName)}</span></div>
                    <div class="map-popup-row"><span class="lbl">Mobile</span><span class="val">${esc(p.caller_number || '—')}</span></div>
                    <div class="map-popup-row"><span class="lbl">Beat</span><span class="val">${beatPath}</span></div>
                    <div class="map-popup-row"><span class="lbl">Agent</span><span class="val" style="font-weight:600;color:#334155;">${esc(p.agent_name || '—')}</span></div>
                    <div class="map-popup-row"><span class="lbl">Tiger</span><span class="val" style="${tigerStyle}">${esc(tigerLabel)}</span></div>
                    <div class="map-popup-row"><span class="lbl">Call #</span><span class="val">${esc(p.call_number || '—')}</span></div>
                    <div class="map-popup-row"><span class="lbl">Status</span><span class="val"><span style="color:${st.color};font-weight:700;">${st.label}</span> · ${esc(p.age || '')}</span></div>
                `);
                clusterGroup.addLayer(marker);
            });
            map.addLayer(clusterGroup);
            window._ccClusterGroup = clusterGroup;

            const fitMap = () => {
                if (!window._ccMap) return;
                window._ccMap.invalidateSize();
                if (pts.length > 1 && window._ccClusterGroup) {
                    window._ccMap.fitBounds(window._ccClusterGroup.getBounds().pad(0.25), { maxZoom: 8 });
                } else {
                    window._ccMap.setView(center, pts.length ? 7 : 5);
                }
            };

            fitMap();
            setTimeout(fitMap, 150);
            setTimeout(fitMap, 400);
        },

        /* ── Heatmap ─────────────────────────── */
        buildHeatmap(data) {
            const grid = document.getElementById('heatmapGrid');
            const tooltip = document.getElementById('hm-tooltip');
            if (!grid || !tooltip) return;

            const days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sa'];
            grid.innerHTML = ''; 

            for (let d = 1; d <= 7; d++) {
                for (let h = 0; h < 24; h++) {
                    const found = data.find(i => +i.day === d && +i.hour === h);
                    const c = found ? +found.count : 0;
                    
                    // Refined Thresholds for Max ~80-100 load
                    let lvl = 0;
                    if (c >= 80) lvl = 11;
                    else if (c >= 70) lvl = 10;
                    else if (c >= 60) lvl = 9;
                    else if (c >= 50) lvl = 8;
                    else if (c >= 40) lvl = 7;
                    else if (c >= 30) lvl = 6;
                    else if (c >= 20) lvl = 5;
                    else if (c >= 12) lvl = 4;
                    else if (c >= 6)  lvl = 3;
                    else if (c >= 3)  lvl = 2;
                    else if (c > 0)   lvl = 1;

                    const el = document.createElement('div');
                    el.className = `hm-cell hm-${lvl}`;
                    
                    el.addEventListener('mouseenter', (e) => {
                        let typesHtml = '';
                        if (found && found.types) {
                            const sortedTypes = Object.entries(found.types).sort((a,b) => b[1] - a[1]);
                            // Only show top 10 to prevent extreme overflow
                            sortedTypes.slice(0, 10).forEach(([type, tCount]) => {
                                typesHtml += `<div class="htt-row"><span>• ${type}</span><span class="font-bold">${tCount}</span></div>`;
                            });
                            if (sortedTypes.length > 10) {
                                typesHtml += `<div class="htt-row text-slate-500 italic mt-1">+ ${sortedTypes.length - 10} more types</div>`;
                            }
                        }

                        tooltip.innerHTML = `
                            <div class="htt-title">${found ? found.date_str : days[d-1]} | ${String(h).padStart(2,'0')}:00</div>
                            <div class="htt-total"><span>Total Helps</span> <span>${c}</span></div>
                            <div class="divider" style="background:rgba(255,255,255,0.1); margin:8px 0"></div>
                            ${typesHtml}
                        `;
                        tooltip.style.display = 'block';
                    });

                    el.addEventListener('mousemove', (e) => {
                        tooltip.style.display = 'block';
                        
                        let x = e.clientX + 12;
                        let y = e.clientY + 12;
                        
                        const tw = tooltip.offsetWidth || 220;
                        const th = tooltip.offsetHeight || 100;

                        // Horizontal Flip
                        if (x + tw > window.innerWidth) {
                            x = e.clientX - tw - 12;
                        }
                        // Vertical Flip
                        if (y + th > window.innerHeight) {
                            y = e.clientY - th - 12;
                        }
                        
                        tooltip.style.left = x + 'px';
                        tooltip.style.top = y + 'px';
                        tooltip.style.opacity = '1';
                    });

                    el.addEventListener('mouseleave', () => {
                        tooltip.style.display = 'none';
                        tooltip.style.opacity = '0';
                    });

                    grid.appendChild(el);
                }
            }
        },

        /* ── Category Donut ──────────────────── */
        buildCategoryChart(data) {
            const ctx = document.getElementById('categoryChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx) || window._dashboardCharts?.category;
            if (existing) existing.destroy();

            const counts = data.map(d => +d.count);
            const total  = counts.reduce((s,v) => s+v, 0);
            const el = document.getElementById('totalCases');
            if (el) el.textContent = total.toLocaleString();

            const palette = ['#0ea5e9','#10b981','#f59e0b','#f43f5e','#8b5cf6','#f97316','#14b8a6'];
            const labels = data.map(d => (d.category || '').replace(/_/g, ' ').replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()));

            window._dashboardCharts.category = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: palette,
                        hoverOffset: 10, borderRadius: 6, borderWidth: 2, borderColor: '#fff'
                    }]
                },
                options: { responsive:true, maintainAspectRatio:false, cutout:'70%', plugins:{ legend:{ display:false } } }
            });
        },

        /* ── SLA Gauge (Semi-Circle Radial) ─── */
        buildGaugeChart() {
            const ctx = document.getElementById('gaugeChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx) || window._dashboardCharts?.gauge;
            if (existing) existing.destroy();

            const slaVal = Number(this.slaValue || 100);
            window._dashboardCharts.gauge = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['SLA Met', 'Gap'],
                    datasets: [{ 
                        data: [slaVal, Math.max(0, 100 - slaVal)], 
                        backgroundColor: ['#10b981', '#f1f5f9'], 
                        circumference: 180, 
                        rotation: -90, 
                        borderRadius: 10, 
                        borderWidth: 0 
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: { legend: { display: false }, tooltip: { enabled: false } }
                }
            });
        },

        /* ── Priority Stacked Bar ────────────── */
        buildPriorityChart(data) {
            const ctx = document.getElementById('priorityChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx) || window._dashboardCharts?.prio;
            if (existing) existing.destroy();

            const dates = [...new Set(data.map(d => d.date))].sort();
            const get = (p) => dates.map(dt => +(data.find(d => d.date === dt && +d.priority === p)?.count || 0));
            window._dashboardCharts.prio = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: dates.map(d => d.slice(5).replace('-', '/')),
                    datasets: [
                        { label: 'P1 Critical', data: get(1), backgroundColor: '#f43f5e', borderRadius: 4, stack: 's', barPercentage: .7 },
                        { label: 'P2 Urgent',   data: get(2), backgroundColor: '#f59e0b', borderRadius: 4, stack: 's', barPercentage: .7 },
                        { label: 'P3 Routine',  data: get(3), backgroundColor: '#0ea5e9', borderRadius: 4, stack: 's', barPercentage: .7 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 9, weight: 'bold' }, maxRotation: 0, autoSkip: false } },
                        y: { stacked: true, grid: { color: '#f8fafc' }, border: { display: false }, ticks: { font: { size: 9 } } }
                    },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 10,
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 10 },
                            cornerRadius: 8,
                            boxPadding: 4
                        }
                    }
                }
            });
        },

        /* ── Weekly Call Volume (Daily Call Distribution - Green) ── */
        buildWeeklyChart(labels, values) {
            const ctx = document.getElementById('weeklyChart');
            if (!ctx) return;
            if (window._dashboardCharts.weekly) {
                window._dashboardCharts.weekly.destroy();
            }
            window._dashboardCharts.weekly = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Calls',
                        data: values,
                        backgroundColor: '#10b981',
                        hoverBackgroundColor: '#059669',
                        borderRadius: 8,
                        borderSkipped: false,
                        barPercentage: 0.6,
                        categoryPercentage: 0.75
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 9, weight: 'bold' }, color: '#64748b', maxRotation: 0 }
                        },
                        y: {
                            grid: { color: '#f1f5f9' },
                            border: { display: false },
                            ticks: {
                                font: { size: 9 },
                                color: '#94a3b8',
                                callback: (v) => v >= 1000 ? (v / 1000).toLocaleString() + ',000' : v.toLocaleString()
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 10,
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 11 },
                            cornerRadius: 8,
                            displayColors: false
                        }
                    }
                }
            });
        },

        /* ── Number of Incidents (7 Days Daily Bar Chart - Blue) ── */
        buildIncidentVolumeChart(labels, values) {
            const ctx = document.getElementById('incidentVolumeChart');
            if (!ctx) return;
            if (window._dashboardCharts.incidentVolume) {
                window._dashboardCharts.incidentVolume.destroy();
            }
            window._dashboardCharts.incidentVolume = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Incidents',
                        data: values,
                        backgroundColor: '#3b82f6',
                        hoverBackgroundColor: '#2563eb',
                        borderRadius: 6,
                        borderSkipped: false,
                        barPercentage: 0.65,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 9, weight: 'bold' }, color: '#64748b', maxRotation: 0 }
                        },
                        y: {
                            grid: { color: '#f8fafc' },
                            border: { display: false },
                            ticks: { font: { size: 9 }, color: '#94a3b8' }
                        }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.95)',
                            padding: 10,
                            titleFont: { size: 11, weight: 'bold' },
                            bodyFont: { size: 11 },
                            cornerRadius: 8,
                            displayColors: false
                        }
                    }
                }
            });
        },

        /* ── Zone Wise Helpers Donut ─────────── */
        /* ── Zone Wise Helpers Donut ─────────── */
        buildRoadChart(data) {
            const ctx = document.getElementById('roadChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx) || window._dashboardCharts?.road;
            if (existing) existing.destroy();

            const roadColors = ['#3b82f6', '#f59e0b', '#06b6d4', '#f43f5e', '#eab308', '#8b5cf6', '#6366f1', '#10b981', '#ec4899', '#f97316'];
            const total = (data || []).reduce((s, d) => s + (+d.count || 0), 0);
            const cntEl = document.getElementById('zoneCenterCount');
            if (cntEl && total > 0) cntEl.textContent = total;

            window._dashboardCharts.road = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: (data || []).map(d => d.type),
                    datasets: [{ 
                        data: (data || []).map(d => +d.count), 
                        backgroundColor: roadColors, 
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6,
                        borderRadius: 3 
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { display: false } }
                }
            });
        },

        /* ── Operations Sunburst Chart ────────── */
        buildSunburstChart(raw) {
            const ctx = document.getElementById('sunburstChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx) || window._dashboardCharts?.sunburst;
            if (existing) existing.destroy();
            
            const zones = Object.keys(raw || {});
            const palette = ['#0ea5e9','#10b981','#8b5cf6','#f59e0b','#f43f5e','#f97316','#ec4899','#6366f1','#14b8a6','#facc15'];
            
            const ds1Labels = []; const ds1Data = []; const ds1Colors = [];
            const ds2Labels = []; const ds2Data = []; const ds2Colors = [];
            const catTotals = {};
            
            zones.forEach((zName, zi) => {
                const z = raw[zName];
                const baseColor = palette[zi % palette.length];
                ds1Labels.push(zName);
                ds1Data.push(z.count || 0);
                ds1Colors.push(baseColor);
                
                Object.keys(z.sectors || {}).forEach((sName, si) => {
                    const s = z.sectors[sName];
                    ds2Labels.push(sName);
                    ds2Data.push(s.count || 0);
                    ds2Colors.push(baseColor + (si % 2 === 0 ? 'DD' : 'BB'));

                    Object.entries(s.categories || {}).forEach(([cat, cnt]) => {
                        catTotals[cat] = (catTotals[cat] || 0) + cnt;
                    });
                });
            });

            const totalCount = ds1Data.reduce((s, v) => s + v, 0);
            const totalEl = document.getElementById('sunburstCenterCount');
            if (totalEl) totalEl.textContent = totalCount || 207;

            // Dynamically update the category breakdown legend list
            const legendEl = document.getElementById('sunburstLegend');
            if (legendEl && Object.keys(catTotals).length > 0) {
                const sortedCats = Object.entries(catTotals).sort((a,b) => b[1] - a[1]).slice(0, 6);
                const sumCats = sortedCats.reduce((s, c) => s + c[1], 0) || 1;
                const catColors = ['#06b6d4', '#f59e0b', '#f43f5e', '#ea580c', '#3b82f6', '#8b5cf6', '#10b981', '#ec4899'];
                legendEl.innerHTML = sortedCats.map(([cat, cnt], idx) => {
                    const pct = Math.round((cnt / sumCats) * 100);
                    const clean = cat.replace(/_/g, ' ').replace(/\w\S*/g, txt => txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase());
                    const col = catColors[idx % catColors.length];
                    return `
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="w-2 h-2 rounded-full shrink-0" style="background-color: ${col};"></span>
                            <span class="font-semibold text-slate-600 truncate flex-1">• ${clean}</span>
                            <span class="text-[11px] font-black text-slate-500 shrink-0 font-mono">${pct}%</span>
                        </div>
                    `;
                }).join('');
            }

            window._dashboardCharts.sunburst = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    datasets: [
                        { // Outer: Sectors / Categories
                            data: ds2Data.length ? ds2Data : [28, 22, 18, 10, 8, 14],
                            backgroundColor: ds2Colors.length ? ds2Colors : ['#06b6d4', '#f59e0b', '#f43f5e', '#ea580c', '#3b82f6', '#8b5cf6'],
                            labels: ds2Labels.length ? ds2Labels : ['Accident', 'Vehicle Fault', 'Medical', 'Fire', 'Security', 'Other'],
                            weight: 1.5,
                            borderWidth: 2,
                            borderColor: '#fff'
                        },
                        { // Inner: Zones
                            data: ds1Data.length ? ds1Data : [100, 80],
                            backgroundColor: ds1Colors.length ? ds1Colors : ['#0ea5e9', '#10b981'],
                            labels: ds1Labels.length ? ds1Labels : ['Zone 1', 'Zone 2'],
                            weight: 1,
                            borderWidth: 2,
                            borderColor: '#fff'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '55%',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: (c) => ` ${c.chart.data.datasets[c.datasetIndex].labels[c.dataIndex] || ''}: ${c.raw}`
                            }
                        }
                    }
                }
            });
        },

        /* ── Sector Load Donut ───────────────── */
        buildSectorPolarChart(data) {
            const ctx = document.getElementById('sectorPolarChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx) || window._dashboardCharts?.sector;
            if (existing) existing.destroy();

            const sectorColors = ['#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#06b6d4', '#2563eb', '#f97316'];
            window._dashboardCharts.sector = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.map(d => d.name),
                    datasets: [{
                        data: data.map(d => +d.count),
                        backgroundColor: sectorColors,
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '70%',
                    plugins: { legend: { display: false } }
                }
            });
        },

        /* ── Zone Wise Activity (Stacked) ───────── */
        buildShiftChart(data) {
            const ctx = document.getElementById('shiftChart');
            if (!ctx) return;
            const existing = Chart.getChart(ctx);
            if (existing) existing.destroy();

            // Sort higher bars first
            data = [...data].map(d => ({ ...d, total: +d.pending + +d.in_progress + +d.completed })).sort((a, b) => b.total - a.total);
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.name ? d.name.replace(' Zone', '') : ''),
                    datasets: [
                        { label: 'Completed',   data: data.map(d => +d.completed),   backgroundColor: '#10b981', borderRadius: 4, stack: 'z' },
                        { label: 'In Progress', data: data.map(d => +d.in_progress), backgroundColor: '#3b82f6', borderRadius: 4, stack: 'z' },
                        { label: 'Pending',     data: data.map(d => +d.pending),     backgroundColor: '#ec4899', borderRadius: 4, stack: 'z' }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { stacked: true, grid: { display: false }, ticks: { font: { size: 9, weight: 'bold' } } },
                        y: { stacked: true, grid: { color: '#f8fafc' }, border: { display: false }, beginAtZero: true, ticks: { font: { size: 9 } } }
                    },
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.raw;
                                }
                            }
                        }
                    }
                }
            });
        }
    };
}
</script>
@endpush
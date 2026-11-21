@extends('layouts.app')

@section('title', 'Travel Advisories - NHMP 130')
@section('page-title', 'Travel Advisories')

@section('content')
<div id="flash-bridge-index" 
     data-success="{{ session('success') ?? '' }}" 
     data-error="{{ session('error') ?? '' }}" 
     class="hidden"></div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('flash-bridge-index');
        if (!el) return;
        if (el.dataset.success && window.showSuccess) {
            window.showSuccess(el.dataset.success, 'Operation Success');
        }
        if (el.dataset.error && window.showError) {
            window.showError(el.dataset.error, 'Operation Notice');
        }
    });
</script>

<div x-data="advisoryIndexManager({
    initialItems: {{ Js::from($initialItems ?? []) }},
    initialMetrics: {{ Js::from($metrics ?? []) }},
    pagination: {
        current_page: {{ $advisories->currentPage() }},
        last_page: {{ $advisories->lastPage() }},
        per_page: {{ $advisories->perPage() }},
        total: {{ $advisories->total() }},
        from: {{ $advisories->firstItem() ?? 0 }},
        to: {{ $advisories->lastItem() ?? 0 }},
    },
    currentRoad: '{{ $currentRoad ?? "all" }}',
    currentStatus: '{{ $currentStatus ?? "active" }}',
    currentType: '{{ $currentType ?? "all" }}',
    hierarchy: {{ Js::from($hierarchy ?? ['zones' => [], 'sectors' => [], 'beats' => []]) }},
    lock: {{ Js::from($officeScope['lock'] ?? ['zone' => null, 'sector' => null, 'beat' => null]) }},
    selZone: {{ (int) ($selZone ?? 0) }},
    selSector: {{ (int) ($selSector ?? 0) }},
    selBeat: {{ (int) ($selBeat ?? 0) }},
    dateFrom: '{{ $dateFrom ?? "" }}',
    dateTo: '{{ $dateTo ?? "" }}',
    search: '{{ $search ?? "" }}',
    canApprove: {{ ($canApprove ?? false) ? 'true' : 'false' }},
})" class="space-y-5 max-w-[1700px] mx-auto pb-12 pt-1" x-cloak>

    <!-- ROW 1: STATUS CARDS (Active, Pending, Rejected, Expired, Resolved) -->
    <div class="gap-3 pt-3 w-full" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
        <div @click="setCardFilter('all')"
            :class="activeCard === 'all' ? 'card-3d-active blue' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-blue-600 shadow-[0_8px_16px_rgba(37,99,235,0.25)] z-10 text-white">
                <i class="fa-solid fa-layer-group text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-blue-600 uppercase truncate">Active Alerts</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-blue-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">LIVE</span>
                    <h4 class="text-xl sm:text-2xl font-black text-blue-950 truncate" x-text="metrics.total_active"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('pending')"
            :class="activeCard === 'pending' ? 'card-3d-active amber ring-2 ring-amber-400' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-amber-500 shadow-[0_8px_16px_rgba(245,158,11,0.25)] z-10 text-white">
                <i class="fa-solid fa-hourglass-half text-xs sm:text-sm" :class="metrics.pending_approval > 0 ? 'animate-spin' : ''" style="animation-duration: 4s;"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-amber-600 uppercase truncate">Pending</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-amber-500 px-1.5 py-0.5 rounded-full shadow-sm shrink-0" :class="metrics.pending_approval > 0 ? 'animate-pulse' : ''">REVIEW</span>
                    <h4 class="text-xl sm:text-2xl font-black text-amber-950 truncate" x-text="metrics.pending_approval || 0"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('rejected')"
            :class="activeCard === 'rejected' ? 'card-3d-active rose ring-2 ring-rose-400' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-rose-600 shadow-[0_8px_16px_rgba(225,29,72,0.25)] z-10 text-white">
                <i class="fa-solid fa-ban text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-rose-600 uppercase truncate">Rejected</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-rose-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">DECLINED</span>
                    <h4 class="text-xl sm:text-2xl font-black text-rose-950 truncate" x-text="metrics.rejected || 0"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('expired')"
            :class="activeCard === 'expired' ? 'card-3d-active amber ring-2 ring-amber-400' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-orange-600 shadow-[0_8px_16px_rgba(234,88,12,0.28)] z-10 text-white">
                <i class="fa-solid fa-hourglass-end text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-orange-600 uppercase truncate">Expired</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-orange-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">LAPSED</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.expired || 0"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('resolved')"
            :class="activeCard === 'resolved' ? 'card-3d-active slate' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-slate-600 shadow-[0_8px_16px_rgba(71,85,105,0.25)] z-10 text-white">
                <i class="fa-solid fa-clock-rotate-left text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase truncate">Resolved</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-slate-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">ARCHIVE</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.expired_resolved || 0"></h4>
                </div>
            </div>
        </div>

    </div>

    <!-- ROW 2: CATEGORY CARDS (Fog & Smoke, Closure, Diversion, Roadworks, Congestion, Critical) -->
    <div class="gap-3 pt-4 w-full" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));">
        <div @click="setCardFilter('fog')"
            :class="activeCard === 'fog' ? 'card-3d-active amber' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-amber-500 shadow-[0_8px_16px_rgba(245,158,11,0.25)] z-10 text-white">
                <i class="fa-solid fa-smog text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-amber-600 uppercase truncate">Fog & Smoke</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-amber-500 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">VIS</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.fog_alerts || 0"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('closure')"
            :class="activeCard === 'closure' ? 'card-3d-active rose' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-rose-600 shadow-[0_8px_16px_rgba(225,29,72,0.25)] z-10 text-white">
                <i class="fa-solid fa-road-barrier text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-rose-600 uppercase truncate">Closure</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-rose-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">BLOCK</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.road_closures || 0"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('diversion')"
            :class="activeCard === 'diversion' ? 'card-3d-active purple' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-purple-600 shadow-[0_8px_16px_rgba(147,51,234,0.25)] z-10 text-white">
                <i class="fa-solid fa-signs-post text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-purple-600 uppercase truncate">Diversion</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-purple-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">ROUTE</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.diversions || 0"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('works')"
            :class="activeCard === 'works' ? 'card-3d-active amber' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-orange-500 shadow-[0_8px_16px_rgba(249,115,22,0.25)] z-10 text-white">
                <i class="fa-solid fa-triangle-exclamation text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-orange-600 uppercase truncate">Roadworks</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-orange-500 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">LANES</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.road_works || 0"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('traffic')"
            :class="activeCard === 'traffic' ? 'card-3d-active red ring-2 ring-red-400' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-red-500 shadow-[0_8px_16px_rgba(239,68,68,0.25)] z-10 text-white">
                <i class="fa-solid fa-car text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-red-600 uppercase truncate">Congestion</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-red-500 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">JAM</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.traffic_alerts || 0"></h4>
                </div>
            </div>
        </div>

        <div @click="setCardFilter('critical')"
            :class="activeCard === 'critical' ? 'card-3d-active rose' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-4 h-8 w-8 sm:h-10 sm:w-10 xl:h-11 xl:w-11 grid place-items-center rounded-xl bg-red-600 shadow-[0_8px_16px_rgba(220,38,38,0.25)] z-10 text-white">
                <i class="fa-solid fa-radiation text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-3.5 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-red-600 uppercase truncate">Critical</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-red-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">HAZARD</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.critical_hazards || 0"></h4>
                </div>
            </div>
        </div>

    </div>

    <!-- PREMIUM FILTER CARD: corridor + jurisdiction (zone > sector > beat) + status/category/date/search -->
    <style>
        .taf-card{background:#fff;border:1px solid #e2e8f0;border-radius:28px;padding:18px 20px;box-shadow:0 18px 50px -24px rgba(15,23,42,.18);display:grid;gap:14px}
        .taf-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
        .taf-title{display:flex;align-items:center;gap:10px;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
        .taf-title i{width:28px;height:28px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#2563eb,#4f46e5);color:#fff;font-size:12px;box-shadow:0 8px 16px -8px rgba(37,99,235,.6)}
        .taf-count{font-size:11px;font-weight:700;color:#475569;background:#f1f5f9;border-radius:999px;padding:4px 10px}
        .taf-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
        .taf-row{display:grid;grid-template-columns:92px minmax(0,1fr);align-items:start;gap:10px;padding-top:12px;border-top:1px dashed #e2e8f0}
        .taf-label{display:flex;align-items:center;gap:6px;font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;padding-top:9px;white-space:nowrap}
        .taf-chips{display:flex;flex-wrap:wrap;gap:6px;min-width:0}
        .taf-chip{appearance:none;border:1px solid transparent;cursor:pointer;display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 13px;border-radius:12px;font-size:12px;font-weight:700;color:#334155;background:#f1f5f9;transition:all .15s ease;white-space:nowrap}
        .taf-chip:hover{background:#e2e8f0;transform:translateY(-1px)}
        .taf-chip.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
        .taf-chip.on{color:#fff;background:linear-gradient(135deg,#2563eb,#4f46e5);box-shadow:0 10px 20px -10px rgba(37,99,235,.7);border-color:rgba(255,255,255,.25)}
        .taf-chip.locked{cursor:not-allowed;color:#92400e;background:#fffbeb;border-color:#fde68a;box-shadow:none;transform:none}
        .taf-chip.locked i{color:#d97706}
        .taf-chip.on.locked{color:#fff;cursor:not-allowed;transform:none;border-color:rgba(255,255,255,.35)}
        .taf-chip.on.locked i{color:rgba(255,255,255,.92)}
        .taf-chip.zone.on{background:linear-gradient(135deg,#0f766e,#14b8a6);box-shadow:0 10px 20px -10px rgba(13,148,136,.7)}
        .taf-chip.sector.on{background:linear-gradient(135deg,#7c3aed,#a855f7);box-shadow:0 10px 20px -10px rgba(124,58,237,.7)}
        .taf-chip.beat.on{background:linear-gradient(135deg,#ea580c,#f59e0b);box-shadow:0 10px 20px -10px rgba(234,88,12,.7)}
        .taf-hint{font-size:11px;color:#94a3b8;font-weight:600;padding-top:9px}
        .taf-split{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(330px,1fr);gap:18px;align-items:start}
        .taf-scope{display:grid;gap:0;min-width:0}
        .taf-scope .taf-row:first-child{border-top:0;padding-top:2px}
        .taf-side{background:linear-gradient(180deg,#f8fafc,#f1f5f9);border:1px solid #e2e8f0;border-radius:20px;padding:14px;min-width:0}
        .taf-tools{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px;align-items:start}
        .taf-tools .taf-field.dates,.taf-tools .taf-field.wide{grid-column:span 2}
        .taf-side .taf-input{background:#fff;height:38px}
        .taf-side .taf-tools{gap:8px}
        .taf-crumb{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:12px;padding:9px 12px;border-radius:14px;background:linear-gradient(135deg,#eff6ff,#eef2ff);border:1px solid #dbeafe;font-size:11.5px;font-weight:600;color:#475569}
        .taf-crumb i{color:#2563eb}
        .taf-crumb strong{color:#1e293b;font-weight:800}
        .taf-dates .taf-input{min-width:0;padding:0 8px}
        @media (max-width:1100px){.taf-split{grid-template-columns:minmax(0,1fr)}}
        @media (max-width:640px){.taf-tools{grid-template-columns:minmax(0,1fr)}.taf-tools .taf-field.dates,.taf-tools .taf-field.wide{grid-column:span 1}}
        .taf-field{display:grid;gap:5px;min-width:0}
        .taf-field>span{font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#94a3b8;display:flex;align-items:center;gap:6px}
        .taf-input{width:100%;height:40px;border-radius:14px;border:1px solid #e2e8f0;background:#f8fafc;padding:0 12px;font-size:12.5px;font-weight:700;color:#1e293b;outline:none;transition:all .15s ease;font-family:inherit}
        .taf-input:focus{background:#fff;border-color:#60a5fa;box-shadow:0 0 0 3px rgba(59,130,246,.15)}
        .taf-search{position:relative}
        .taf-search i{position:absolute;left:12px;top:13px;font-size:12px;color:#94a3b8}
        .taf-search .taf-input{padding-left:32px;font-weight:600}
        .taf-dates{display:grid;grid-template-columns:1fr 1fr;gap:8px}
        .taf-presets{display:flex;gap:6px;flex-wrap:wrap}
        .taf-mini{appearance:none;border:0;cursor:pointer;height:30px;padding:0 11px;border-radius:10px;font-size:11px;font-weight:800;color:#475569;background:#f1f5f9;font-family:inherit}
        .taf-mini:hover{background:#e2e8f0}
        .taf-mini.danger{color:#e11d48;background:#fff1f2}
        .taf-btn{appearance:none;border:0;cursor:pointer;height:40px;padding:0 16px;border-radius:14px;font-size:12px;font-weight:800;display:inline-flex;align-items:center;gap:8px;font-family:inherit;text-decoration:none}
        .taf-btn.primary{color:#fff;background:linear-gradient(135deg,#2563eb,#4f46e5);box-shadow:0 12px 24px -12px rgba(37,99,235,.8)}
        .taf-btn.ghost{color:#334155;background:#f1f5f9}
        .taf-btn.ghost:hover{background:#e2e8f0}
        @media (max-width:760px){.taf-row{grid-template-columns:1fr}.taf-label{padding-top:0}.taf-card{padding:14px;border-radius:22px}}
    </style>
    <div class="taf-card">
        <div class="taf-head">
            <div class="taf-title"><i class="fa-solid fa-sliders"></i> Filters <span class="taf-count" x-text="(pagination.total || 0) + ' advisories'"></span></div>
            <div class="taf-actions">
                <button type="button" class="taf-btn ghost" x-show="isFiltered()" @click="resetFilters()" title="Reset all filters">
                    <i class="fa-solid fa-rotate-left" style="color:#2563eb"></i> Reset
                </button>
                <a href="{{ route('ops.travel-advisory.create') }}" class="taf-btn primary">
                    <i class="fa-solid fa-plus-circle"></i> Log Advisory / Fog
                </a>
            </div>
        </div>

        <!-- Corridor -->
        <div class="taf-row">
            <div class="taf-label"><i class="fa-solid fa-road"></i> Corridor</div>
            <div class="taf-chips">
                <button type="button" class="taf-chip" :class="currentRoad === 'all' ? 'on' : ''" @click="setRoad('all')">All Corridors</button>
                <template x-for="rCode in corridorList" :key="rCode">
                    <button type="button" class="taf-chip mono" :class="currentRoad === rCode ? 'on' : ''" @click="setRoad(rCode)" x-text="rCode"></button>
                </template>
            </div>
        </div>

        <!-- Two-column body: jurisdiction chips (left) | status, category, dates, search (right) -->
        <div class="taf-split">
        <div class="taf-scope">
        <!-- Zone -->
        <div class="taf-row" x-show="hierarchy.zones.length > 0">
            <div class="taf-label"><i class="fa-solid fa-earth-asia"></i> Zone</div>
            <div class="taf-chips">
                <template x-if="!lock.zone">
                    <button type="button" class="taf-chip zone" :class="!zoneId ? 'on' : ''" @click="setZone(0)">All Zones</button>
                </template>
                <template x-for="z in visibleZones()" :key="z.id">
                    <button type="button" class="taf-chip zone" :class="[zoneId === z.id ? 'on' : '', lock.zone ? 'locked' : '']" :title="lock.zone ? 'Your assigned zone' : ''" @click="setZone(z.id)">
                        <i x-show="lock.zone" class="fa-solid fa-lock"></i><span x-text="z.name"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Sector (loads after a zone is chosen) -->
        <div class="taf-row" x-show="zoneId || lock.sector" x-transition.opacity.duration.150ms>
            <div class="taf-label"><i class="fa-solid fa-diagram-project"></i> Sector</div>
            <div class="taf-chips">
                <template x-if="!lock.sector">
                    <button type="button" class="taf-chip sector" :class="!sectorId ? 'on' : ''" @click="setSector(0)">All Sectors</button>
                </template>
                <template x-for="sx in visibleSectors()" :key="sx.id">
                    <button type="button" class="taf-chip sector" :class="[sectorId === sx.id ? 'on' : '', lock.sector ? 'locked' : '']" :title="lock.sector ? 'Your assigned sector' : ''" @click="setSector(sx.id)">
                        <i x-show="lock.sector" class="fa-solid fa-lock"></i><span x-text="sx.name"></span>
                    </button>
                </template>
                <span class="taf-hint" x-show="visibleSectors().length === 0">No sectors in this zone.</span>
            </div>
        </div>

        <!-- Beat (loads after a sector is chosen) -->
        <div class="taf-row" x-show="sectorId || lock.beat" x-transition.opacity.duration.150ms>
            <div class="taf-label"><i class="fa-solid fa-location-dot"></i> Beat</div>
            <div class="taf-chips">
                <template x-if="!lock.beat">
                    <button type="button" class="taf-chip beat" :class="!beatId ? 'on' : ''" @click="setBeat(0)">All Beats</button>
                </template>
                <template x-for="b in visibleBeats()" :key="b.id">
                    <button type="button" class="taf-chip beat" :class="[beatId === b.id ? 'on' : '', lock.beat ? 'locked' : '']" :title="lock.beat ? 'Your assigned beat' : ''" @click="setBeat(b.id)">
                        <i x-show="lock.beat" class="fa-solid fa-lock"></i><span x-text="b.name"></span>
                    </button>
                </template>
                <span class="taf-hint" x-show="visibleBeats().length === 0">No beats in this sector.</span>
            </div>
        </div>

            <div class="taf-crumb" x-show="zoneId || sectorId || beatId" x-transition.opacity.duration.150ms>
                <i class="fa-solid fa-location-crosshairs"></i>
                <span>Showing advisories for</span>
                <strong x-text="scopeLabel()"></strong>
            </div>
        </div>
        <div class="taf-side">
        <div class="taf-tools">
            <label class="taf-field">
                <span><i class="fa-solid fa-clock-rotate-left"></i> Status</span>
                <select x-model="currentStatus" @change="fetchData(1)" class="taf-input">
                    <option value="active">Active / Live Alerts</option>
                    <option value="pending">Pending Review (Supervisors)</option>
                    <option value="rejected">Rejected Advisories</option>
                    <option value="all">All Records</option>
                    <option value="resolved">Resolved Only</option>
                    <option value="expired">Expired Only</option>
                </select>
            </label>
            <label class="taf-field">
                <span><i class="fa-solid fa-tags"></i> Category</span>
                <select x-model="currentType" @change="fetchData(1)" class="taf-input">
                    <option value="all">All Categories</option>
                    <option value="fog">Fog &amp; Smog</option>
                    <option value="road_closure">Road Closures</option>
                    <option value="road_works">Road Works</option>
                    <option value="traffic">Traffic Congestion</option>
                    <option value="weather">Weather &amp; Rain</option>
                    <option value="diversion">Diversions</option>
                </select>
            </label>
            <div class="taf-field dates">
                <span><i class="fa-solid fa-calendar-days"></i> Inserted between</span>
                <div class="taf-dates">
                    <input type="date" x-model="dateFrom" :max="dateTo || null" @change="fetchData(1)" class="taf-input" aria-label="From date">
                    <input type="date" x-model="dateTo" :min="dateFrom || null" @change="fetchData(1)" class="taf-input" aria-label="To date">
                </div>
                <div class="taf-presets">
                    <button type="button" class="taf-mini" @click="setDatePreset(1)">Today</button>
                    <button type="button" class="taf-mini" @click="setDatePreset(7)">7 Days</button>
                    <button type="button" class="taf-mini" @click="setDatePreset(30)">30 Days</button>
                    <button type="button" class="taf-mini danger" x-show="dateFrom || dateTo" @click="clearDates()">Clear</button>
                </div>
            </div>
            <label class="taf-field wide">
                <span><i class="fa-solid fa-keyboard"></i> Search</span>
                <div class="taf-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" x-model="search" @input.debounce.300ms="fetchData(1)" placeholder="Landmarks, KM, title..." class="taf-input">
                </div>
            </label>
        </div>
        </div>
        </div>
    </div>


    <!-- 3D TACTILE TABLE OF ADVISORIES (Pure Alpine AJAX Driven) -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-sm overflow-hidden relative">
        <!-- Loading Overlay Bar -->
        <div x-show="isLoading" class="absolute inset-0 bg-white/70 backdrop-blur-[1px] z-20 flex items-center justify-center transition-opacity">
            <div class="flex items-center gap-2.5 px-4 py-2 bg-slate-900 text-white rounded-2xl shadow-xl text-xs font-bold">
                <i class="fa-solid fa-spinner fa-spin text-blue-400 text-sm"></i>
                <span>Synchronizing Corridor Feeds...</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-gradient-to-r from-slate-50 to-slate-100/60 border-b border-slate-200 text-slate-500 uppercase tracking-wider font-extrabold text-[11px]">
                        <th class="py-3.5 px-3">Corridor & Span</th>
                        <th class="py-3.5 px-3">Incident & Summary</th>
                        <th class="py-3.5 px-3">Live Conditions</th>
                        <th class="py-3.5 px-3">Jurisdiction</th>
                        <th class="py-3.5 px-3">Inserted &amp; Expires</th>
                        <th class="py-3.5 px-3">Direction & Validity</th>
                        <th class="py-3.5 px-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <template x-for="item in items" :key="item.id">
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                            <!-- Route Column -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-gradient-to-r from-blue-600 to-indigo-700 text-white font-mono font-black text-xs shadow-sm">
                                        <i class="fa-solid fa-road text-[10px]"></i>
                                        <span x-text="item.route_name || 'CORRIDOR'"></span>
                                    </span>
                                    <template x-if="item.is_emergency">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-red-100 text-red-700 animate-pulse border border-red-200">
                                            <i class="fa-solid fa-circle-exclamation text-[8px]"></i> Emergency
                                        </span>
                                    </template>
                                </div>
                                <div class="text-[11px] font-mono text-slate-500 mt-1 font-bold" x-text="'KM ' + Number(item.start_km).toFixed(1) + ' - ' + Number(item.end_km).toFixed(1)"></div>
                            </td>

                            <!-- Advisory Title & Summary -->
                            <td class="py-3 px-3 max-w-xs">
                                <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                    <template x-if="item.advisory_type === 'fog'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-amber-100 text-amber-800 border border-amber-200">
                                            <i class="fa-solid fa-smog mr-1"></i> Fog
                                        </span>
                                    </template>
                                    <template x-if="item.advisory_type === 'road_closure'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-rose-100 text-rose-800 border border-rose-200">
                                            <i class="fa-solid fa-road-barrier mr-1"></i> Closure
                                        </span>
                                    </template>
                                    <template x-if="item.advisory_type === 'road_works' || item.advisory_type === 'maintenance'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-orange-100 text-orange-800 border border-orange-200">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Works
                                        </span>
                                    </template>
                                    <template x-if="item.advisory_type === 'traffic' || item.advisory_type === 'congestion'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-red-100 text-red-800 border border-red-200">
                                            <i class="fa-solid fa-car mr-1"></i> Congestion
                                        </span>
                                    </template>
                                    <template x-if="item.advisory_type === 'weather'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-sky-100 text-sky-800 border border-sky-200">
                                            <i class="fa-solid fa-cloud-showers-heavy mr-1"></i> Weather
                                        </span>
                                    </template>
                                    <template x-if="item.advisory_type === 'diversion'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-purple-100 text-purple-800 border border-purple-200">
                                            <i class="fa-solid fa-arrows-split-up-and-left mr-1"></i> Diversion
                                        </span>
                                    </template>
                                    <template x-if="!['fog','road_closure','road_works','maintenance','traffic','congestion','weather','diversion'].includes(item.advisory_type)">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-slate-100 text-slate-800 border border-slate-200">
                                            <i class="fa-solid fa-bell mr-1"></i> <span x-text="(item.advisory_type || 'Alert').toUpperCase()"></span>
                                        </span>
                                    </template>

                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase"
                                        :class="item.severity === 'critical' ? 'bg-red-600 text-white' : (item.severity === 'warning' ? 'bg-amber-500 text-white' : 'bg-blue-600 text-white')"
                                        x-text="item.severity.toUpperCase()"></span>
                                </div>
                                <div class="font-bold text-slate-900 text-sm leading-snug" x-text="item.title"></div>
                                <div class="text-[11px] text-slate-500 truncate mt-0.5" :title="item.message" x-text="item.message"></div>
                                <div class="text-[11px] text-slate-400 mt-1 flex items-center gap-1 font-semibold">
                                    <i class="fa-solid fa-location-dot text-amber-500"></i>
                                    <span x-text="(item.start_landmark || ('KM ' + item.start_km)) + ' ⇄ ' + (item.end_landmark || ('KM ' + item.end_km))"></span>
                                </div>
                            </td>

                            <!-- Conditions (Visibility, Speed limit, Lane closure) -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="space-y-1">
                                    <template x-if="item.visibility_meters">
                                        <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-xs font-black"
                                            :class="item.visibility_meters < 50 ? 'bg-rose-100 text-rose-800 border border-rose-200' : (item.visibility_meters < 100 ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700')">
                                            <i class="fa-solid fa-eye-slash text-[10px]"></i>
                                            <span x-text="'Vis: ' + item.visibility_meters + 'm'"></span>
                                        </div>
                                    </template>

                                    <template x-if="item.speed_limit_kmh">
                                        <div class="text-[11px] font-bold text-slate-600 flex items-center gap-1">
                                            <i class="fa-solid fa-gauge-high text-slate-400"></i>
                                            <span x-text="'Speed: ' + item.speed_limit_kmh + ' km/h'"></span>
                                        </div>
                                    </template>

                                    <template x-if="item.closure_type">
                                        <div class="text-[10px] font-black text-rose-600 uppercase">
                                            <i class="fa-solid fa-road-barrier text-rose-500 mr-1"></i>
                                            <span x-text="item.closure_type.replace('_', ' ')"></span>
                                        </div>
                                    </template>

                                    <template x-if="!item.visibility_meters && !item.speed_limit_kmh && !item.closure_type">
                                        <span class="text-slate-400 text-xs">Standard</span>
                                    </template>
                                </div>
                            </td>

                            <!-- Reported By / Jurisdiction -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="text-slate-900 font-bold flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-blue-600 text-xs"></i>
                                    <span x-text="item.office_name || 'Wireless Station'"></span>
                                </div>
                                <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mt-0.5" x-text="(item.sector_name || 'Sector') + ' • ' + (item.reported_by_tier || 'Operator') + ' Tier'"></div>
                            </td>

                            <!-- Inserted / Expires (explicit timestamps, Asia/Karachi) -->
                            <td class="py-3 px-3 whitespace-nowrap align-top">
                                <div class="space-y-1.5">
                                    <div>
                                        <div class="flex items-center gap-1 text-[9px] font-black uppercase tracking-wider text-slate-400"><i class="fa-solid fa-circle-plus text-emerald-500"></i> Inserted</div>
                                        <div class="text-[11px] font-bold text-slate-700" x-text="item.created_at_display || '—'"></div>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-1 text-[9px] font-black uppercase tracking-wider text-slate-400"><i class="fa-solid fa-hourglass-end text-rose-500"></i> Expires</div>
                                        <div class="text-[11px] font-bold" :class="item.is_expired ? 'text-rose-600' : 'text-slate-700'" x-text="item.expires_at_display || 'Not configured'"></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Direction + Validity / Expiry Timer (merged column) -->
                            <td class="py-3 px-3 whitespace-nowrap">
                                <div class="mb-1.5">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-slate-100 border border-slate-200 text-[10px] font-black uppercase text-slate-700">
                                        <template x-if="item.carriageway === 'both'"><span class="flex items-center gap-1"><i class="fa-solid fa-arrows-left-right text-blue-500"></i> Both</span></template>
                                        <template x-if="item.carriageway === 'northbound'"><span class="flex items-center gap-1"><i class="fa-solid fa-arrow-up text-emerald-600"></i> NB</span></template>
                                        <template x-if="item.carriageway === 'southbound'"><span class="flex items-center gap-1"><i class="fa-solid fa-arrow-down text-indigo-600"></i> SB</span></template>
                                        <template x-if="item.carriageway !== 'both' && item.carriageway !== 'northbound' && item.carriageway !== 'southbound'"><span x-text="item.carriageway"></span></template>
                                    </span>
                                </div>
                                <template x-if="item.is_pending">
                                    <div>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-black bg-amber-100 text-amber-800 border border-amber-300 shadow-sm animate-pulse">
                                            <i class="fa-solid fa-hourglass-half text-amber-600"></i> Awaiting Approval
                                        </span>
                                        <div class="text-[10px] text-slate-500 font-semibold mt-1 flex items-center gap-1">
                                            <i class="fa-solid fa-user-clock text-slate-400"></i>
                                            <span x-text="'By ' + item.creator_name"></span>
                                        </div>
                                    </div>
                                </template>
                                <template x-if="item.status === 'rejected'">
                                    <div>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-black bg-red-100 text-red-800 border border-red-300">
                                            <i class="fa-solid fa-ban text-red-600"></i> Rejected
                                        </span>
                                        <div class="text-[10px] text-red-500 font-semibold mt-1 truncate max-w-[140px]" :title="item.rejection_reason" x-text="item.rejection_reason || 'Declined'"></div>
                                    </div>
                                </template>
                                <template x-if="item.is_resolved">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200">
                                        <i class="fa-solid fa-check-double text-slate-500"></i> Resolved
                                    </span>
                                </template>
                                <template x-if="item.is_expired">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-rose-100 text-rose-700 border border-rose-200">
                                        <i class="fa-solid fa-clock text-rose-500"></i> Expired
                                    </span>
                                </template>
                                <template x-if="item.is_live">
                                    <div>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span> Live Broadcast
                                        </span>
                                        <div class="text-[10px] text-slate-500 font-semibold mt-1 flex items-center gap-1">
                                            <i class="fa-solid fa-stopwatch text-slate-400"></i>
                                            <span x-text="'Expires ' + item.expires_at_human"></span>
                                        </div>
                                    </div>
                                </template>
                            </td>

                            <!-- Action Buttons (icon buttons, same style as Roles page) -->
                            <td class="py-3 px-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Pending approval -->
                                    <template x-if="item.is_pending">
                                        <div class="flex items-center gap-1.5">
                                            <template x-if="canApprove"><div class="flex items-center gap-1.5">
                                                <button type="button" @click="openApproveModal(item)" title="Approve & Broadcast to Public" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-emerald-600 border-emerald-700 hover:bg-emerald-700"><i class="fa-solid fa-check-to-slot text-xs"></i></button>
                                                <button type="button" @click="openEditModal(item)" title="Edit and Refine Advisory" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-sky-500 border-sky-600 hover:bg-sky-600"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
                                                <button type="button" @click="openRejectModal(item)" title="Reject Advisory" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-rose-500 border-rose-600 hover:bg-rose-600"><i class="fa-solid fa-ban text-xs"></i></button>
                                            </div></template>
                                            <template x-if="!canApprove"><div class="flex items-center gap-1.5">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-amber-100 border border-amber-300 text-amber-600" title="Pending supervisor review"><i class="fa-solid fa-hourglass-half animate-pulse text-xs"></i></span>
                                                <button type="button" @click="openEditModal(item)" title="Edit Advisory" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-sky-500 border-sky-600 hover:bg-sky-600"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
                                            </div></template>
                                        </div>
                                    </template>

                                    <!-- Live / published -->
                                    <template x-if="!item.is_pending && !item.is_resolved && !item.is_rejected">
                                        <div class="flex items-center gap-1.5">
                                            <button type="button" @click="openEditModal(item)" title="Edit Parameters" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-sky-500 border-sky-600 hover:bg-sky-600"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
                                            <button type="button" @click="openExtendModal(item)" title="Extend validity & timer" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-indigo-600 border-indigo-700 hover:bg-indigo-700"><i class="fa-solid fa-clock-rotate-left text-xs"></i></button>
                                            <button type="button" @click="openResolveModal(item)" title="Clear / Mark as Resolved" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-emerald-600 border-emerald-700 hover:bg-emerald-700"><i class="fa-solid fa-check text-xs"></i></button>
                                            <template x-if="canApprove">
                                                <button type="button" @click="openRejectModal(item)" title="Reject Advisory" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-rose-500 border-rose-600 hover:bg-rose-600"><i class="fa-solid fa-ban text-xs"></i></button>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Rejected -->
                                    <template x-if="item.is_rejected">
                                        <div class="flex items-center gap-1.5">
                                            <template x-if="canApprove"><div class="flex items-center gap-1.5">
                                                <button type="button" @click="openApproveModal(item)" title="Re-approve & Publish Live" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-emerald-600 border-emerald-700 hover:bg-emerald-700"><i class="fa-solid fa-rotate-left text-xs"></i></button>
                                                <button type="button" @click="openEditModal(item)" title="Edit and Refine Advisory" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-sky-500 border-sky-600 hover:bg-sky-600"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
                                            </div></template>
                                            <template x-if="!canApprove">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-rose-50 border border-rose-200 text-rose-500" title="Rejected by Supervisor"><i class="fa-solid fa-ban text-xs"></i></span>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Resolved -->
                                    <template x-if="item.is_resolved">
                                        <div class="flex items-center gap-1.5">
                                            <template x-if="canApprove"><div class="flex items-center gap-1.5">
                                                <button type="button" @click="openExtendModal(item)" title="Re-open & Extend Validity" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-indigo-600 border-indigo-700 hover:bg-indigo-700"><i class="fa-solid fa-clock-rotate-left text-xs"></i></button>
                                                <button type="button" @click="openEditModal(item)" title="Edit" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0 cursor-pointer bg-sky-500 border-sky-600 hover:bg-sky-600"><i class="fa-solid fa-pen-to-square text-xs"></i></button>
                                            </div></template>
                                            <template x-if="!canApprove">
                                                <span class="inline-flex items-center justify-center w-8 h-8 rounded-xl bg-slate-100 border border-slate-200 text-slate-400" title="Resolved"><i class="fa-solid fa-check-double text-xs"></i></span>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>

                    <!-- Empty State -->
                    <tr x-show="items.length === 0">
                        <td colspan="7" class="py-14 text-center text-slate-400">
                            <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3 text-2xl">
                                <i class="fa-solid fa-shield-cat"></i>
                            </div>
                            <div class="font-extrabold text-slate-700 text-sm">No advisories found matching selected filters</div>
                            <p class="text-xs text-slate-400 mt-1">Try resetting your filters or log a new incident broadcast.</p>
                            <div class="mt-4">
                                <a href="{{ route('ops.travel-advisory.create') }}"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 transition">
                                    <i class="fa-solid fa-plus-circle"></i> Log Advisory / Fog
                                </a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- AJAX Pagination (Zero Page Reload) -->
        <div x-show="pagination.last_page > 1" class="p-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-600 font-semibold">
            <div>
                Showing <span class="font-bold text-slate-900" x-text="pagination.from"></span> to <span class="font-bold text-slate-900" x-text="pagination.to"></span> of <span class="font-bold text-slate-900" x-text="pagination.total"></span> records
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" @click="goToPage(pagination.current_page - 1)" :disabled="pagination.current_page <= 1"
                    class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                    <i class="fa-solid fa-chevron-left mr-1 text-[10px]"></i> Previous
                </button>

                <div class="px-3 py-1.5 rounded-xl bg-blue-600 text-white font-bold" x-text="pagination.current_page + ' / ' + pagination.last_page"></div>

                <button type="button" @click="goToPage(pagination.current_page + 1)" :disabled="pagination.current_page >= pagination.last_page"
                    class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer">
                    Next <i class="fa-solid fa-chevron-right ml-1 text-[10px]"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         SUPERVISOR MODAL 1: EDIT & REFINE ADVISORY PARAMETERS
         ========================================================================= -->
    <div x-show="isEditModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeEditModal()"
         class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeEditModal()"></div>
        
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="isEditModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @click.stop
                 class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-slate-200">
                
                <!-- Modal Header -->
                <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-sky-600 px-6 py-4 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-inner">
                            <i class="fa-solid fa-pen-to-square text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black tracking-tight">Review & Edit Advisory</h3>
                            <p class="text-xs text-blue-100 font-medium">Call Center Agent Supervisor Corridor Control</p>
                        </div>
                    </div>
                    <button type="button" @click="closeEditModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Modal Body Form -->
                <form @submit.prevent="submitEditModal()" class="p-6 space-y-4">
                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100">
                        <div class="grid gap-2 pt-2" style="grid-template-columns:repeat(3,minmax(0,1fr));">
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Inserted</div><div class="text-[11px] font-bold text-slate-700" x-text="editForm.times?.created_at_display || '—'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Published</div><div class="text-[11px] font-bold text-slate-700" x-text="editForm.times?.published_at_display || 'Not published'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Expires</div><div class="text-[11px] font-bold" :class="editForm.times?.is_expired ? 'text-rose-600' : 'text-slate-700'" x-text="editForm.times?.expires_at_display || 'Not configured'"></div></div>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 pt-1" x-text="'Status: ' + (editForm.times?.validity_label || '—')"></div>
                    </div>
                    <!-- Title -->
                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-1">
                            Advisory Headline / Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" x-model="editForm.title" required maxlength="150"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="e.g. M-2 Dense Fog Advisory - Kot Abdul Malik to Sheikhupura">
                    </div>

                    <!-- Message / Citizen Broadcast Notice -->
                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-1">
                            Commuter Message (Public Portal & Humsafar App) <span class="text-rose-500">*</span>
                        </label>
                        <textarea x-model="editForm.message" required rows="3" maxlength="1000"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Provide clear, concise guidance for the traveling public..."></textarea>
                    </div>

                    <!-- Grid: Type, Severity, Carriageway -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1">Type</label>
                            <select x-model="editForm.advisory_type" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-blue-500">
                                <option value="fog">Fog & Smog</option>
                                <option value="road_closure">Road Closure</option>
                                <option value="road_works">Road Works</option>
                                <option value="traffic">Traffic Congestion</option>
                                <option value="weather">Extreme Weather</option>
                                <option value="diversion">Route Diversion</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1">Severity</label>
                            <select x-model="editForm.severity" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-blue-500">
                                <option value="info">Info / Normal</option>
                                <option value="advisory">Advisory / Caution</option>
                                <option value="warning">Warning / Severe</option>
                                <option value="critical">Critical / Emergency</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1">Carriageway</label>
                            <select x-model="editForm.carriageway" class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700 focus:ring-2 focus:ring-blue-500">
                                <option value="both">Both Bounds</option>
                                <option value="northbound">Northbound</option>
                                <option value="southbound">Southbound</option>
                            </select>
                        </div>
                    </div>

                    <!-- Grid: Visibility, Speed Limit, Extended Duration -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1">Visibility (Meters)</label>
                            <input type="number" x-model="editForm.visibility_meters" min="0" max="5000"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700"
                                placeholder="e.g. 30">
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1">Speed Limit (km/h)</label>
                            <input type="number" x-model="editForm.speed_limit_kmh" min="0" max="150"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700"
                                placeholder="e.g. 50">
                        </div>

                        <div>
                            <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1">Valid For (Hours from now)</label>
                            <input type="number" x-model="editForm.duration_hours" step="0.5" min="0.5" max="72"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700"
                                placeholder="blank = keep current expiry">
                            <p class="text-[10px] text-slate-400 font-semibold mt-1" x-text="editForm.expires_hint || 'Leave blank to keep the current expiry time.'"></p>
                        </div>
                    </div>

                    <!-- Landmarks / Closure details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1">Start Landmark / Interchange</label>
                            <input type="text" x-model="editForm.start_landmark" maxlength="100"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700"
                                placeholder="e.g. Kot Abdul Malik">
                        </div>
                        <div>
                            <label class="block text-[11px] font-black text-slate-600 uppercase tracking-wider mb-1">End Landmark / Interchange</label>
                            <input type="text" x-model="editForm.end_landmark" maxlength="100"
                                class="w-full px-3 py-2 rounded-xl border border-slate-200 text-xs font-bold text-slate-700"
                                placeholder="e.g. Sheikhupura Interchange">
                        </div>
                    </div>

                    <!-- Approve Now Checkbox (if pending and user is supervisor) -->
                    <div x-show="editForm.is_pending && canApprove" class="p-3.5 bg-emerald-50 rounded-2xl border border-emerald-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <i class="fa-solid fa-tower-broadcast text-emerald-600 text-base"></i>
                            <div>
                                <span class="text-xs font-black text-emerald-900 block">Approve & Broadcast Immediately</span>
                                <span class="text-[11px] text-emerald-700">Advisory will transition directly from Pending to Live Broadcast.</span>
                            </div>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" x-model="editForm.approve_now" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                        </label>
                    </div>

                    <!-- Modal Actions -->
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button type="button" @click="closeEditModal()"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" :disabled="modalLoading"
                            class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-blue-600 hover:bg-blue-700 shadow-md shadow-blue-500/20 transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <i class="fa-solid fa-floppy-disk" x-show="!modalLoading"></i>
                            <i class="fa-solid fa-circle-notch animate-spin" x-show="modalLoading"></i>
                            <span x-text="editForm.approve_now ? 'Save & Broadcast Live' : 'Save Changes'"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- =========================================================================
         SUPERVISOR MODAL 2: DIRECT APPROVE & PUBLISH LIVE
         ========================================================================= -->
    <div x-show="isApproveModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeApproveModal()"
         class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeApproveModal()"></div>
        
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="isApproveModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @click.stop
                 class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
                
                <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-inner">
                            <i class="fa-solid fa-check-to-slot text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black tracking-tight">Approve Travel Advisory</h3>
                            <p class="text-xs text-emerald-100 font-medium">Publish live to Commuters & Humsafar</p>
                        </div>
                    </div>
                    <button type="button" @click="closeApproveModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase text-white bg-blue-600" x-text="approveTarget?.route_name || 'Corridor'"></span>
                            <span class="text-xs font-black text-slate-800" x-text="approveTarget?.title"></span>
                        </div>
                        <p class="text-xs text-slate-600 leading-relaxed" x-text="approveTarget?.message"></p>
                        <div class="grid gap-2 pt-2" style="grid-template-columns:repeat(3,minmax(0,1fr));">
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Inserted</div><div class="text-[11px] font-bold text-slate-700" x-text="approveTarget?.created_at_display || '—'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Published</div><div class="text-[11px] font-bold text-slate-700" x-text="approveTarget?.published_at_display || 'Not published'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Expires</div><div class="text-[11px] font-bold" :class="approveTarget?.is_expired ? 'text-rose-600' : 'text-slate-700'" x-text="approveTarget?.expires_at_display || 'Not configured'"></div></div>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 pt-1" x-text="'Status: ' + (approveTarget?.validity_label || '—')"></div>
                        <div class="text-[10px] font-bold text-slate-400 flex items-center gap-1.5 pt-1">
                            <i class="fa-solid fa-user-clock text-slate-400"></i>
                            <span>Logged by: </span>
                            <span class="text-slate-700" x-text="approveTarget?.creator_name || 'Field Wireless Operator'"></span>
                        </div>
                    </div>

                    <p class="text-xs text-slate-600">
                        Approving this advisory will instantly broadcast it across the <strong>Public Travel Advisory Portal</strong>, the <strong>Humsafar Mobile API</strong>, and real-time public ticker channels.
                    </p>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button type="button" @click="closeApproveModal()"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="button" @click="submitApproveModal()" :disabled="modalLoading"
                            class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 shadow-md shadow-emerald-600/20 transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <i class="fa-solid fa-check-double" x-show="!modalLoading"></i>
                            <i class="fa-solid fa-circle-notch animate-spin" x-show="modalLoading"></i>
                            <span>Confirm & Publish Live</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- =========================================================================
         SUPERVISOR MODAL 3: REJECT / DECLINE ADVISORY
         ========================================================================= -->
    <div x-show="isRejectModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeRejectModal()"
         class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeRejectModal()"></div>
        
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="isRejectModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @click.stop
                 class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
                
                <div class="bg-gradient-to-r from-rose-600 to-red-600 px-6 py-4 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-inner">
                            <i class="fa-solid fa-ban text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black tracking-tight">Reject Travel Advisory</h3>
                            <p class="text-xs text-rose-100 font-medium">Decline field wireless operator log</p>
                        </div>
                    </div>
                    <button type="button" @click="closeRejectModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="p-3.5 bg-rose-50/50 rounded-2xl border border-rose-100">
                        <div class="text-xs font-black text-rose-900" x-text="rejectTarget?.title"></div>
                        <div class="text-[11px] text-slate-500 mt-1 line-clamp-2" x-text="rejectTarget?.message"></div>
                        <div class="grid gap-2 pt-2" style="grid-template-columns:repeat(3,minmax(0,1fr));">
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Inserted</div><div class="text-[11px] font-bold text-slate-700" x-text="rejectTarget?.created_at_display || '—'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Published</div><div class="text-[11px] font-bold text-slate-700" x-text="rejectTarget?.published_at_display || 'Not published'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Expires</div><div class="text-[11px] font-bold" :class="rejectTarget?.is_expired ? 'text-rose-600' : 'text-slate-700'" x-text="rejectTarget?.expires_at_display || 'Not configured'"></div></div>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 pt-1" x-text="'Status: ' + (rejectTarget?.validity_label || '—')"></div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-1">
                            Reason for Rejection <span class="text-rose-500">*</span>
                        </label>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <button type="button" @click="rejectionReason = 'Incident Cleared / Normal Traffic Flow Restored'"
                                class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-600 border border-slate-200 transition">
                                + Traffic Restored
                            </button>
                            <button type="button" @click="rejectionReason = 'Duplicate advisory report already published on this corridor'"
                                class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-600 border border-slate-200 transition">
                                + Duplicate Entry
                            </button>
                            <button type="button" @click="rejectionReason = 'Inaccurate location / KM markers verified by field patrol'"
                                class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-600 border border-slate-200 transition">
                                + Inaccurate Marker
                            </button>
                            <button type="button" @click="rejectionReason = 'Weather / fog conditions normalized, visibility > 200m'"
                                class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-rose-50 hover:text-rose-700 text-slate-600 border border-slate-200 transition">
                                + Visibility Normal
                            </button>
                        </div>
                        <textarea x-model="rejectionReason" required rows="3" maxlength="500"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-sm font-medium text-slate-800 focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500"
                            placeholder="State reason (e.g., Inaccurate KM marker, fog has already lifted, duplicate entry)..."></textarea>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button type="button" @click="closeRejectModal()"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="button" @click="submitRejectModal()" :disabled="modalLoading"
                            class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-rose-600 hover:bg-rose-700 shadow-md shadow-rose-600/20 transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <i class="fa-solid fa-ban" x-show="!modalLoading"></i>
                            <i class="fa-solid fa-circle-notch animate-spin" x-show="modalLoading"></i>
                            <span>Confirm Rejection</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- =========================================================================
         SUPERVISOR MODAL 4: CLEAR & RESOLVE ADVISORY
         ========================================================================= -->
    <div x-show="isResolveModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeResolveModal()"
         class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeResolveModal()"></div>
        
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="isResolveModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @click.stop
                 class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
                
                <div class="bg-gradient-to-r from-emerald-600 via-teal-600 to-emerald-700 px-6 py-4 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-inner">
                            <i class="fa-solid fa-check text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black tracking-tight">Clear & Resolve Advisory</h3>
                            <p class="text-xs text-emerald-100 font-medium">Remove incident from live corridor broadcasts</p>
                        </div>
                    </div>
                    <button type="button" @click="closeResolveModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="p-3.5 bg-emerald-50/60 rounded-2xl border border-emerald-100 space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase text-white bg-blue-600" x-text="resolveTarget?.route_name || 'Corridor'"></span>
                            <span class="text-xs font-black text-slate-800" x-text="resolveTarget?.title"></span>
                        </div>
                        <div class="text-[11px] text-slate-600" x-text="resolveTarget?.message"></div>
                        <div class="grid gap-2 pt-2" style="grid-template-columns:repeat(3,minmax(0,1fr));">
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Inserted</div><div class="text-[11px] font-bold text-slate-700" x-text="resolveTarget?.created_at_display || '—'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Published</div><div class="text-[11px] font-bold text-slate-700" x-text="resolveTarget?.published_at_display || 'Not published'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Expires</div><div class="text-[11px] font-bold" :class="resolveTarget?.is_expired ? 'text-rose-600' : 'text-slate-700'" x-text="resolveTarget?.expires_at_display || 'Not configured'"></div></div>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 pt-1" x-text="'Status: ' + (resolveTarget?.validity_label || '—')"></div>
                        <div class="text-[10px] font-bold text-slate-400 pt-1" x-text="'Span: KM ' + resolveTarget?.start_km + ' - ' + resolveTarget?.end_km"></div>
                    </div>

                    <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200 text-xs text-amber-900 flex items-start gap-2.5">
                        <i class="fa-solid fa-triangle-exclamation text-amber-600 mt-0.5 shrink-0"></i>
                        <div>
                            <span class="font-bold block">Confirmation Required</span>
                            <span>Clearing this advisory will immediately update all public feeds, mobile apps, and traffic tickers. Confirm that this incident has cleared and road conditions are safe.</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-1">
                            Resolution Remarks / Notes (Optional)
                        </label>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <button type="button" @click="resolveRemarks = 'Incident cleared, normal traffic resumed.'"
                                class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-600 border border-slate-200 transition">
                                + Traffic Resumed
                            </button>
                            <button type="button" @click="resolveRemarks = 'Road works completed, all lanes re-opened.'"
                                class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-600 border border-slate-200 transition">
                                + Lanes Re-opened
                            </button>
                            <button type="button" @click="resolveRemarks = 'Weather clear, visibility normal.'"
                                class="px-2 py-1 rounded-lg text-[10px] font-bold bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 text-slate-600 border border-slate-200 transition">
                                + Weather Clear
                            </button>
                        </div>
                        <input type="text" x-model="resolveRemarks" maxlength="255"
                            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 text-xs font-semibold text-slate-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                            placeholder="e.g. Traffic normalized, obstruction cleared...">
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button type="button" @click="closeResolveModal()"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="button" @click="submitResolveModal()" :disabled="modalLoading"
                            class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 shadow-md shadow-emerald-600/20 transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <i class="fa-solid fa-check" x-show="!modalLoading"></i>
                            <i class="fa-solid fa-circle-notch animate-spin" x-show="modalLoading"></i>
                            <span>Confirm & Clear Advisory</span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- =========================================================================
         SUPERVISOR MODAL 5: EXTEND VALIDITY & TIMING MODAL
         ========================================================================= -->
    <div x-show="isExtendModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="closeExtendModal()"
         class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeExtendModal()"></div>
        
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="isExtendModalOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 @click.stop
                 class="relative transform overflow-hidden rounded-3xl bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-slate-200">
                
                <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-blue-700 px-6 py-4 text-white flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-inner">
                            <i class="fa-solid fa-clock-rotate-left text-base"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-black tracking-tight">Extend Advisory Validity</h3>
                            <p class="text-xs text-blue-100 font-medium">Add duration & keep live broadcast active</p>
                        </div>
                    </div>
                    <button type="button" @click="closeExtendModal()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-white transition cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="p-6 space-y-4">
                    <div class="p-3.5 bg-blue-50/60 rounded-2xl border border-blue-100 space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 rounded-lg text-[10px] font-black uppercase text-white bg-blue-600" x-text="extendTarget?.route_name || 'Corridor'"></span>
                            <span class="text-xs font-black text-slate-800" x-text="extendTarget?.title"></span>
                        </div>
                        <div class="text-[11px] text-slate-600" x-text="extendTarget?.message"></div>
                        <div class="grid gap-2 pt-2" style="grid-template-columns:repeat(3,minmax(0,1fr));">
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Inserted</div><div class="text-[11px] font-bold text-slate-700" x-text="extendTarget?.created_at_display || '—'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Published</div><div class="text-[11px] font-bold text-slate-700" x-text="extendTarget?.published_at_display || 'Not published'"></div></div>
                            <div class="rounded-xl bg-white border border-slate-200 px-2.5 py-1.5"><div class="text-[9px] font-black uppercase tracking-wider text-slate-400">Expires</div><div class="text-[11px] font-bold" :class="extendTarget?.is_expired ? 'text-rose-600' : 'text-slate-700'" x-text="extendTarget?.expires_at_display || 'Not configured'"></div></div>
                        </div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 pt-1" x-text="'Status: ' + (extendTarget?.validity_label || '—')"></div>
                        <div class="text-[10px] font-bold text-blue-700 pt-1 flex items-center gap-1.5">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <span x-text="'Current Validity: Expires ' + (extendTarget?.expires_at_human || 'soon')"></span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-black text-slate-700 uppercase tracking-wider mb-2">
                            Extension Duration (Hours) <span class="text-rose-500">*</span>
                        </label>
                        <!-- Quick Hour Pills -->
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2 mb-3">
                            <template x-for="h in [1, 2, 4, 6, 12, 24]" :key="h">
                                <button type="button" @click="extendHours = h"
                                    :class="extendHours === h ? 'bg-blue-600 text-white shadow-md ring-2 ring-blue-300 font-black' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold'"
                                    class="py-2 rounded-xl text-xs transition-all text-center cursor-pointer"
                                    x-text="'+' + h + 'h'">
                                </button>
                            </template>
                        </div>

                        <!-- Custom Hours Input -->
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-slate-500 shrink-0">Custom Duration:</span>
                            <div class="relative flex-1">
                                <input type="number" x-model.number="extendHours" step="0.5" min="0.5" max="72"
                                    class="w-full px-3.5 py-2 rounded-xl border border-slate-200 text-xs font-black text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <span class="absolute right-3 top-2 text-xs font-bold text-slate-400">hours</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between text-xs">
                        <span class="text-slate-500 font-medium">New Validity Extension:</span>
                        <span class="font-black text-blue-600" x-text="'+' + extendHours + ' Hours from now'"></span>
                    </div>

                    <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                        <button type="button" @click="closeExtendModal()"
                            class="px-4 py-2.5 rounded-xl border border-slate-200 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="button" @click="submitExtendModal()" :disabled="modalLoading"
                            class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 shadow-md shadow-blue-600/20 transition-all flex items-center gap-1.5 cursor-pointer disabled:opacity-50">
                            <i class="fa-solid fa-clock-rotate-left" x-show="!modalLoading"></i>
                            <i class="fa-solid fa-circle-notch animate-spin" x-show="modalLoading"></i>
                            <span x-text="'Confirm Extension (+' + extendHours + 'h)'"></span>
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<!-- Alpine Logic Component (Strictly Zero Page Reloads / AJAX Powered) -->
<script>
function advisoryIndexManager(config) {
    return {
        items: config.initialItems || [],
        metrics: config.initialMetrics || {},
        pagination: config.pagination || { current_page: 1, last_page: 1, per_page: 15, total: 0, from: 0, to: 0 },

        canApprove: config.canApprove || false,

        init() {
            // Live alerts (supervisor/sector actions) refresh the grid without a manual reload
            window.addEventListener('ta:event', () => this.fetchData());
        },
        isEditModalOpen: false,
        isApproveModalOpen: false,
        isRejectModalOpen: false,
        isResolveModalOpen: false,
        isExtendModalOpen: false,
        modalLoading: false,

        editForm: {
            id: null,
            title: '',
            message: '',
            advisory_type: 'fog',
            severity: 'advisory',
            carriageway: 'both',
            start_km: 0,
            end_km: 0,
            start_landmark: '',
            end_landmark: '',
            visibility_meters: null,
            speed_limit_kmh: null,
            duration_hours: '',
            expires_hint: '',
            is_pending: false,
            approve_now: false,
        },
        approveTarget: null,
        rejectTarget: null,
        rejectionReason: '',
        resolveTarget: null,
        resolveRemarks: '',
        extendTarget: null,
        extendHours: 2,

        currentRoad: config.currentRoad || 'all',
        currentStatus: config.currentStatus || 'active',
        currentType: config.currentType || 'all',
        currentSeverity: 'all',
        hierarchy: config.hierarchy || { zones: [], sectors: [], beats: [] },
        lock: config.lock || { zone: null, sector: null, beat: null },
        zoneId: config.selZone || 0,
        sectorId: config.selSector || 0,
        beatId: config.selBeat || 0,
        dateFrom: config.dateFrom || '',
        dateTo: config.dateTo || '',
        search: config.search || '',

        activeCard: 'all',
        corridorList: ['M-1', 'M-2', 'M-3', 'M-4', 'M-5', 'M-9', 'M-11', 'M-14', 'N-5', 'E-35', 'N-55'],
        isLoading: false,

        scopeLabel() {
            const z = (this.hierarchy.zones || []).find(x => x.id === this.zoneId);
            const sx = (this.hierarchy.sectors || []).find(x => x.id === this.sectorId);
            const b = (this.hierarchy.beats || []).find(x => x.id === this.beatId);
            return [z && z.name, sx && sx.name, b && b.name].filter(Boolean).join('  \u203a  ');
        },
        visibleZones() {
            const z = this.hierarchy.zones || [];
            return this.lock.zone ? z.filter(x => x.id === this.lock.zone) : z;
        },
        visibleSectors() {
            const all = (this.hierarchy.sectors || []).filter(x => x.zone_id === this.zoneId);
            return this.lock.sector ? all.filter(x => x.id === this.lock.sector) : all;
        },
        visibleBeats() {
            const all = (this.hierarchy.beats || []).filter(x => x.sector_id === this.sectorId);
            return this.lock.beat ? all.filter(x => x.id === this.lock.beat) : all;
        },
        setZone(id) {
            if (this.lock.zone) return;
            this.zoneId = id || 0; this.sectorId = 0; this.beatId = 0; this.fetchData(1);
        },
        setSector(id) {
            if (this.lock.sector) return;
            this.sectorId = id || 0; this.beatId = 0; this.fetchData(1);
        },
        setBeat(id) {
            if (this.lock.beat) return;
            this.beatId = id || 0; this.fetchData(1);
        },
        isFiltered() {
            return this.currentRoad !== 'all' || this.currentType !== 'all' || this.currentStatus !== 'active' || !!this.search
                || !!this.dateFrom || !!this.dateTo
                || (!this.lock.zone && !!this.zoneId) || (!this.lock.sector && !!this.sectorId) || (!this.lock.beat && !!this.beatId);
        },
        hasDateRange() { return !!(this.dateFrom || this.dateTo); },
        categoryStatus() { return this.hasDateRange() ? 'all' : 'active'; },
        setDatePreset(days) {
            const fmt = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            const to = new Date(); const from = new Date(); from.setDate(from.getDate() - (days - 1));
            this.dateFrom = fmt(from); this.dateTo = fmt(to);
            this.fetchData(1);
        },
        clearDates() { this.dateFrom = ''; this.dateTo = ''; this.fetchData(1); },

        setCardFilter(type) {
            this.activeCard = type;
            if (type === 'all') {
                this.currentStatus = 'active';
                this.currentType = 'all';
                this.currentSeverity = 'all';
            } else if (type === 'pending') {
                this.currentStatus = 'pending';
                this.currentType = 'all';
                this.currentSeverity = 'all';
            } else if (type === 'rejected') {
                this.currentStatus = 'rejected';
                this.currentType = 'all';
                this.currentSeverity = 'all';
            } else if (type === 'fog') {
                this.currentStatus = this.categoryStatus();
                this.currentType = 'fog';
                this.currentSeverity = 'all';
            } else if (type === 'closure') {
                this.currentStatus = this.categoryStatus();
                this.currentType = 'road_closure';
                this.currentSeverity = 'all';
            } else if (type === 'diversion') {
                this.currentStatus = this.categoryStatus();
                this.currentType = 'diversion';
                this.currentSeverity = 'all';
            } else if (type === 'works') {
                this.currentStatus = this.categoryStatus();
                this.currentType = 'road_works';
                this.currentSeverity = 'all';
            } else if (type === 'traffic') {
                this.currentStatus = this.categoryStatus();
                this.currentType = 'traffic';
                this.currentSeverity = 'all';
            } else if (type === 'critical') {
                this.currentStatus = this.categoryStatus();
                this.currentType = 'all';
                this.currentSeverity = 'critical';
            } else if (type === 'expired') {
                this.currentStatus = 'expired';
                this.currentType = 'all';
                this.currentSeverity = 'all';
            } else if (type === 'resolved') {
                this.currentStatus = 'resolved';
                this.currentType = 'all';
                this.currentSeverity = 'all';
            }
            this.fetchData(1);
        },

        // SUPERVISOR WORKFLOW METHODS
        // ---- Toast helpers: every action reports success or failure through notification.js ----
        notify(kind, message, title) {
            const text = message || (kind === 'success' ? 'Done.' : 'Something went wrong.');
            const fn = kind === 'success' ? window.showSuccess : window.showError;
            if (typeof fn === 'function') {
                fn(text, title);
                return;
            }
            // notification.js not ready yet: retry once shortly, then fall back to a plain alert so nothing is silent
            setTimeout(() => {
                const f2 = kind === 'success' ? window.showSuccess : window.showError;
                if (typeof f2 === 'function') f2(text, title); else window.alert((title ? title + ': ' : '') + text);
            }, 600);
        },
        async readJson(res) {
            try {
                return await res.json();
            } catch (e) {
                return {
                    success: false,
                    message: res.status === 419 ? 'Your session expired. Please refresh the page and try again.'
                        : (res.status === 403 ? 'You do not have permission to do this.'
                        : 'The server returned an unexpected response (HTTP ' + res.status + ').')
                };
            }
        },
        errMsg(data, fallback) {
            if (data && data.errors && typeof data.errors === 'object') {
                const first = Object.values(data.errors).flat()[0];
                if (first) return String(first);
            }
            return (data && data.message) ? data.message : fallback;
        },

        async openEditModal(item) {
            this.modalLoading = true;
            this.editForm = {
                id: item.id,
                title: item.title || '',
                message: item.message || '',
                advisory_type: item.advisory_type || 'fog',
                severity: item.severity || 'advisory',
                carriageway: item.carriageway || 'both',
                start_km: item.start_km || 0,
                end_km: item.end_km || item.start_km || 0,
                start_landmark: item.start_landmark || '',
                end_landmark: item.end_landmark || '',
                visibility_meters: item.visibility_meters !== null && item.visibility_meters !== undefined ? item.visibility_meters : '',
                speed_limit_kmh: item.speed_limit_kmh !== null && item.speed_limit_kmh !== undefined ? item.speed_limit_kmh : '',
                duration_hours: '',
                is_pending: !!item.is_pending,
                approve_now: false,
                times: item,
                expires_hint: item.expires_at_human ? ('Currently expires ' + item.expires_at_human) : '',
            };
            this.isEditModalOpen = true;

            // Optionally fetch freshest details from show endpoint
            try {
                const res = await fetch(`/ops/travel-advisory/${item.id}`, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const d = await this.readJson(res);
                if (d.success && d.advisory) {
                    const adv = d.advisory;
                    this.editForm.title = adv.title;
                    this.editForm.message = adv.message;
                    this.editForm.advisory_type = adv.advisory_type || 'fog';
                    this.editForm.severity = adv.severity || 'advisory';
                    this.editForm.carriageway = adv.carriageway || 'both';
                    this.editForm.start_km = adv.start_km || 0;
                    this.editForm.end_km = adv.end_km || 0;
                    this.editForm.start_landmark = adv.start_landmark || '';
                    this.editForm.end_landmark = adv.end_landmark || '';
                    this.editForm.visibility_meters = adv.visibility_meters;
                    this.editForm.speed_limit_kmh = adv.speed_limit_kmh;
                }
            } catch (e) {
                console.warn('Could not fetch freshest item, using table row data', e);
            } finally {
                this.modalLoading = false;
            }
        },

        closeEditModal() {
            this.isEditModalOpen = false;
        },

        async submitEditModal() {
            if (!this.editForm.title || !this.editForm.message) {
                this.notify('error', 'Title and Message are required.', 'Validation Error');
                return;
            }
            this.editForm.category = this.editForm.advisory_type;
            this.modalLoading = true;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`/ops/travel-advisory/${this.editForm.id}/update`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(this.editForm)
                });

                const data = await this.readJson(res);
                if (data.success) {
                    this.notify('success', data.message, 'Saved Successfully');
                    this.isEditModalOpen = false;
                    this.fetchData();
                } else {
                    this.notify('error', this.errMsg(data, 'Update failed.'), 'Error');
                }
            } catch (err) {
                console.error('Error updating advisory:', err);
                this.notify('error', 'Server error while saving advisory.', 'Server Error');
            } finally {
                this.modalLoading = false;
            }
        },

        openApproveModal(item) {
            this.approveTarget = item;
            this.isApproveModalOpen = true;
        },

        closeApproveModal() {
            this.isApproveModalOpen = false;
            this.approveTarget = null;
        },

        async submitApproveModal() {
            if (!this.approveTarget) return;
            this.modalLoading = true;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`/ops/travel-advisory/${this.approveTarget.id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await this.readJson(res);
                if (data.success) {
                    this.notify('success', data.message, 'Broadcast Approved');
                    this.isApproveModalOpen = false;
                    this.approveTarget = null;
                    this.fetchData();
                } else {
                    this.notify('error', this.errMsg(data, 'Approval failed.'), 'Error');
                }
            } catch (err) {
                console.error('Error approving advisory:', err);
                this.notify('error', 'Server error while approving advisory.', 'Server Error');
            } finally {
                this.modalLoading = false;
            }
        },

        openRejectModal(item) {
            this.rejectTarget = item;
            this.rejectionReason = '';
            this.isRejectModalOpen = true;
        },

        closeRejectModal() {
            this.isRejectModalOpen = false;
            this.rejectTarget = null;
            this.rejectionReason = '';
        },

        async submitRejectModal() {
            if (!this.rejectTarget) return;
            if (!this.rejectionReason.trim()) {
                this.notify('error', 'Please provide a reason for rejecting this advisory.', 'Validation Required');
                return;
            }
            this.modalLoading = true;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`/ops/travel-advisory/${this.rejectTarget.id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ reason: this.rejectionReason.trim() })
                });

                const data = await this.readJson(res);
                if (data.success) {
                    this.notify('success', data.message, 'Advisory Rejected');
                    const rejectedId = this.rejectTarget.id;
                    const wasPending = !!this.rejectTarget.is_pending;
                    const wasLive = !!this.rejectTarget.is_live;

                    this.isRejectModalOpen = false;
                    this.rejectTarget = null;
                    this.rejectionReason = '';

                    // Adjust metrics counters immediately
                    if (wasPending && this.metrics.pending_approval > 0) this.metrics.pending_approval--;
                    if (wasLive && this.metrics.total_active > 0) this.metrics.total_active--;
                    this.metrics.rejected = (this.metrics.rejected || 0) + 1;

                    // Update item or remove from active / pending view
                    if (this.currentStatus === 'active' || this.currentStatus === 'pending') {
                        this.items = this.items.filter(x => x.id !== rejectedId);
                    } else {
                        const item = this.items.find(x => x.id === rejectedId);
                        if (item) {
                            item.status = 'rejected';
                            item.is_pending = false;
                            item.is_live = false;
                            item.is_rejected = true;
                            item.rejection_reason = this.rejectionReason;
                        }
                    }
                    this.fetchData();
                } else {
                    this.notify('error', this.errMsg(data, 'Rejection failed.'), 'Error');
                }
            } catch (err) {
                console.error('Error rejecting advisory:', err);
                this.notify('error', 'Server error while rejecting advisory.', 'Server Error');
            } finally {
                this.modalLoading = false;
            }
        },

        // RESOLVE / CLEAR ADVISORY MODAL WORKFLOW
        openResolveModal(item) {
            this.resolveTarget = item;
            this.resolveRemarks = '';
            this.isResolveModalOpen = true;
        },

        closeResolveModal() {
            this.isResolveModalOpen = false;
            this.resolveTarget = null;
            this.resolveRemarks = '';
        },

        async submitResolveModal() {
            if (!this.resolveTarget) return;
            this.modalLoading = true;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`/ops/travel-advisory/${this.resolveTarget.id}/resolve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ remarks: this.resolveRemarks.trim() })
                });

                const data = await this.readJson(res);
                if (data.success) {
                    this.notify('success', data.message, 'Advisory Cleared');
                    const resolvedId = this.resolveTarget.id;
                    this.isResolveModalOpen = false;
                    this.resolveTarget = null;
                    this.resolveRemarks = '';

                    if (this.currentStatus === 'active') {
                        this.items = this.items.filter(x => x.id !== resolvedId);
                    } else {
                        const item = this.items.find(x => x.id === resolvedId);
                        if (item) {
                            item.is_resolved = true;
                            item.is_live = false;
                            item.is_expired = false;
                            item.status = 'resolved';
                        }
                    }
                    if (this.metrics.total_active > 0) this.metrics.total_active--;
                    this.metrics.expired_resolved = (this.metrics.expired_resolved || 0) + 1;
                    this.fetchData();
                } else {
                    this.notify('error', this.errMsg(data, 'Resolution failed.'), 'Resolution Failed');
                }
            } catch (err) {
                console.error('Error resolving advisory:', err);
                this.notify('error', 'Server error while resolving advisory.', 'Server Error');
            } finally {
                this.modalLoading = false;
            }
        },

        // EXTEND / TIMING WORKFLOW MODAL
        openExtendModal(item) {
            this.extendTarget = item;
            this.extendHours = 2;
            this.isExtendModalOpen = true;
        },

        closeExtendModal() {
            this.isExtendModalOpen = false;
            this.extendTarget = null;
            this.extendHours = 2;
        },

        async submitExtendModal() {
            if (!this.extendTarget) return;
            const hours = parseFloat(this.extendHours);
            if (isNaN(hours) || hours <= 0) {
                this.notify('error', 'Please enter a valid positive duration in hours.', 'Validation Required');
                return;
            }
            this.modalLoading = true;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`/ops/travel-advisory/${this.extendTarget.id}/extend`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ hours: hours })
                });

                const data = await this.readJson(res);
                if (data.success) {
                    this.notify('success', data.message, 'Validity Extended');
                    const targetId = this.extendTarget.id;
                    this.isExtendModalOpen = false;
                    this.extendTarget = null;

                    const item = this.items.find(x => x.id === targetId);
                    if (item) {
                        item.expires_at_human = data.expires_at_human || ('in ' + hours + ' hours');
                        item.is_live = true;
                        item.is_expired = false;
                        item.is_resolved = false;
                        item.status = 'published';
                    }
                    this.fetchData();
                } else {
                    this.notify('error', this.errMsg(data, 'Extension failed.'), 'Extension Failed');
                }
            } catch (err) {
                console.error('Error extending advisory:', err);
                this.notify('error', 'Server error while extending advisory.', 'Server Error');
            } finally {
                this.modalLoading = false;
            }
        },

        setRoad(road) {
            this.currentRoad = road;
            this.fetchData(1);
        },

        resetFilters() {
            this.currentRoad = 'all';
            this.currentStatus = 'active';
            this.currentType = 'all';
            this.currentSeverity = 'all';
            this.search = '';
            this.dateFrom = '';
            this.dateTo = '';
            this.zoneId = this.lock.zone || 0;
            this.sectorId = this.lock.sector || 0;
            this.beatId = this.lock.beat || 0;
            this.activeCard = 'all';
            this.fetchData(1);
        },

        goToPage(page) {
            if (page < 1 || page > this.pagination.last_page) return;
            this.fetchData(page);
        },

        // ASYNC FETCH DATA (ZERO PAGE RELOADS)
        async fetchData(page = null) {
            if (page) this.pagination.current_page = page;
            this.isLoading = true;

            const params = new URLSearchParams({
                ajax: '1',
                page: this.pagination.current_page,
                road: this.currentRoad,
                status: this.currentStatus,
                type: this.currentType,
                severity: this.currentSeverity,
                zone_id: this.zoneId || '',
                sector_id: this.sectorId || '',
                beat_id: this.beatId || '',
                date_from: this.dateFrom || '',
                date_to: this.dateTo || '',
                search: this.search || ''
            });

            try {
                const res = await fetch(`{{ route('ops.travel-advisory.index') }}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await this.readJson(res);
                if (data.success) {
                    this.items = data.items || [];
                    this.pagination = data.pagination || this.pagination;
                    if (data.metrics) this.metrics = data.metrics;
                } else {
                    this.notify('error', this.errMsg(data, 'Could not refresh the advisory list.'), 'Refresh Failed');
                }
            } catch (err) {
                console.error('Error fetching travel advisories:', err);
                this.notify('error', 'Could not refresh the advisory list. Check your connection.', 'Refresh Failed');
            } finally {
                this.isLoading = false;
            }
        }
    };
}
</script>
@endsection

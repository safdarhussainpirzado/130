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
    search: '{{ $search ?? "" }}',
})" class="space-y-5 max-w-[1700px] mx-auto pb-12 pt-1" x-cloak>

    <!-- 3D TACTILE FILTER CARDS (Strict Single Row / 6 Columns, 100% AJAX/Alpine) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 pt-1 w-full" style="grid-template-columns: repeat(6, minmax(0, 1fr));">
        <!-- 1. ALL ACTIVE ALERTS -->
        <div @click="setCardFilter('all')"
            :class="activeCard === 'all' ? 'card-3d-active blue' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-blue-600 shadow-[0_8px_16px_rgba(37,99,235,0.25)] z-10 text-white">
                <i class="fa-solid fa-layer-group text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-4 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-blue-600 uppercase truncate">Active Alerts</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-blue-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">LIVE</span>
                    <h4 class="text-xl sm:text-2xl font-black text-blue-950 truncate" x-text="metrics.total_active"></h4>
                </div>
            </div>
        </div>

        <!-- 2. FOG & SMOG -->
        <div @click="setCardFilter('fog')"
            :class="activeCard === 'fog' ? 'card-3d-active amber' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-amber-500 shadow-[0_8px_16px_rgba(245,158,11,0.25)] z-10 text-white">
                <i class="fa-solid fa-smog text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-4 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-amber-600 uppercase truncate">Fog & Smog</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-amber-500 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">VIS</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.fog_alerts"></h4>
                </div>
            </div>
        </div>

        <!-- 3. CLOSURES & DIVERSIONS -->
        <div @click="setCardFilter('closure')"
            :class="activeCard === 'closure' ? 'card-3d-active rose' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-rose-600 shadow-[0_8px_16px_rgba(225,29,72,0.25)] z-10 text-white">
                <i class="fa-solid fa-ban text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-4 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-rose-600 uppercase truncate">Closures</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-rose-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">BLOCK</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.road_closures"></h4>
                </div>
            </div>
        </div>

        <!-- 4. ROAD WORKS -->
        <div @click="setCardFilter('works')"
            :class="activeCard === 'works' ? 'card-3d-active amber' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-orange-500 shadow-[0_8px_16px_rgba(249,115,22,0.25)] z-10 text-white">
                <i class="fa-solid fa-triangle-exclamation text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-4 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-orange-600 uppercase truncate">Road Works</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-orange-500 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">LANES</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.road_works"></h4>
                </div>
            </div>
        </div>

        <!-- 5. CRITICAL HAZARDS -->
        <div @click="setCardFilter('critical')"
            :class="activeCard === 'critical' ? 'card-3d-active rose' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-red-600 shadow-[0_8px_16px_rgba(220,38,38,0.25)] z-10 text-white">
                <i class="fa-solid fa-radiation text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-4 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-red-600 uppercase truncate">Critical</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-red-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">HAZARD</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.critical_hazards"></h4>
                </div>
            </div>
        </div>

        <!-- 6. EXPIRED / RESOLVED -->
        <div @click="setCardFilter('resolved')"
            :class="activeCard === 'resolved' ? 'card-3d-active slate' : ''"
            class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group select-none">
            <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-slate-600 shadow-[0_8px_16px_rgba(71,85,105,0.25)] z-10 text-white">
                <i class="fa-solid fa-clock-rotate-left text-xs sm:text-sm"></i>
            </div>
            <div class="p-3 sm:p-4 text-right pt-2 relative">
                <p class="text-[9px] font-black tracking-widest text-slate-500 uppercase truncate">Resolved</p>
                <div class="flex items-center justify-end gap-1.5 mt-1">
                    <span class="text-[9px] font-black text-white bg-slate-600 px-1.5 py-0.5 rounded-full shadow-sm shrink-0">ARCHIVE</span>
                    <h4 class="text-xl sm:text-2xl font-black text-slate-900 truncate" x-text="metrics.expired_resolved"></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- HIGHWAY ROUTE PILLS & SEARCH TOOLBAR WITH EMBEDDED ACTION BUTTONS -->
    <div class="bg-white rounded-3xl border border-slate-200/80 p-4 shadow-sm space-y-3.5">
        <!-- Top Toolbar Line: Corridor Pills + Action Buttons -->
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <!-- Route Chips Scroll/Pill Bar -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs scrollbar-none flex-1">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-1 shrink-0 flex items-center gap-1">
                    <i class="fa-solid fa-road text-slate-400"></i> Corridor:
                </span>
                <button type="button" @click="setRoad('all')"
                    class="px-3 py-1.5 rounded-xl font-bold transition shrink-0 cursor-pointer"
                    :class="currentRoad === 'all' ? 'bg-blue-600 text-white shadow-sm ring-2 ring-blue-400' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                    All Corridors
                </button>
                <template x-for="rCode in corridorList" :key="rCode">
                    <button type="button" @click="setRoad(rCode)"
                        class="px-3 py-1.5 rounded-xl font-mono font-bold transition shrink-0 cursor-pointer"
                        :class="currentRoad === rCode ? 'bg-blue-600 text-white shadow-sm ring-2 ring-blue-400' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        x-text="rCode">
                    </button>
                </template>
            </div>

            <!-- Action Buttons: Open Commuter Portal & Log Advisory / Fog -->
            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('public.travel-advisory') }}" target="_blank"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 shadow-sm hover:bg-slate-100 transition-all">
                    <i class="fa-solid fa-arrow-up-right-from-square text-sky-600"></i>
                    <span>Open Commuter Portal</span>
                </a>
                <a href="{{ route('ops.travel-advisory.create') }}"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-500 hover:to-indigo-600 shadow-md shadow-blue-600/25 active:translate-y-0.5 transition-all">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span>Log Advisory / Fog</span>
                </a>
            </div>
        </div>

        <!-- Search & Secondary Filter Form (NO FORM SUBMIT / PURE AJAX) -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-3 border-t border-slate-100">
            <div class="flex flex-wrap items-center gap-2">
                <!-- Status filter dropdown -->
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl text-xs">
                    <i class="fa-solid fa-clock-rotate-left text-slate-400 text-xs"></i>
                    <select x-model="currentStatus" @change="fetchData()" class="bg-transparent font-bold text-slate-700 outline-none cursor-pointer">
                        <option value="active">Active Advisories</option>
                        <option value="all">All Records</option>
                        <option value="resolved">Resolved Only</option>
                        <option value="expired">Expired Only</option>
                    </select>
                </div>

                <!-- Type filter dropdown -->
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl text-xs">
                    <i class="fa-solid fa-tags text-slate-400 text-xs"></i>
                    <select x-model="currentType" @change="fetchData()" class="bg-transparent font-bold text-slate-700 outline-none cursor-pointer">
                        <option value="all">All Categories</option>
                        <option value="fog">Fog & Smog</option>
                        <option value="road_closure">Road Closures</option>
                        <option value="road_works">Road Works</option>
                        <option value="traffic">Traffic Congestion</option>
                        <option value="weather">Weather & Rain</option>
                        <option value="diversion">Diversions</option>
                    </select>
                </div>
            </div>

            <!-- Search input -->
            <div class="flex items-center gap-2">
                <div class="relative w-full sm:w-72">
                    <input type="text" x-model="search" @input.debounce.300ms="fetchData()" placeholder="Search landmarks, KM, title..."
                        class="w-full text-xs font-medium pl-8 pr-3 py-2 rounded-xl bg-slate-50 border border-slate-200 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-100 outline-none transition-all">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                </div>
                <button type="button" @click="resetFilters()"
                    x-show="currentRoad !== 'all' || currentType !== 'all' || currentStatus !== 'active' || search"
                    class="px-3 py-2 bg-slate-100 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-200 transition-colors cursor-pointer" title="Reset Filters">
                    <i class="fa-solid fa-rotate-left"></i>
                </button>
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
                        <th class="py-3.5 px-4">Corridor & Span</th>
                        <th class="py-3.5 px-4">Incident & Summary</th>
                        <th class="py-3.5 px-4">Live Conditions</th>
                        <th class="py-3.5 px-4">Carriageway</th>
                        <th class="py-3.5 px-4">Jurisdiction</th>
                        <th class="py-3.5 px-4">Validity & Timer</th>
                        <th class="py-3.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium">
                    <template x-for="item in items" :key="item.id">
                        <tr class="hover:bg-blue-50/30 transition-colors group">
                            <!-- Route Column -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
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
                            <td class="py-3.5 px-4 max-w-sm">
                                <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                    <template x-if="item.advisory_type === 'fog'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-amber-100 text-amber-800 border border-amber-200">
                                            <i class="fa-solid fa-smog mr-1"></i> Fog
                                        </span>
                                    </template>
                                    <template x-if="item.advisory_type === 'road_closure'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-rose-100 text-rose-800 border border-rose-200">
                                            <i class="fa-solid fa-ban mr-1"></i> Road Closure
                                        </span>
                                    </template>
                                    <template x-if="item.advisory_type === 'road_works'">
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase bg-orange-100 text-orange-800 border border-orange-200">
                                            <i class="fa-solid fa-triangle-exclamation mr-1"></i> Works
                                        </span>
                                    </template>
                                    <template x-if="item.advisory_type === 'traffic'">
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
                            <td class="py-3.5 px-4 whitespace-nowrap">
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

                            <!-- Carriageway Direction -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <span class="capitalize text-slate-700 font-bold inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-100 border border-slate-200">
                                    <template x-if="item.carriageway === 'both'">
                                        <span class="flex items-center gap-1">
                                            <i class="fa-solid fa-arrows-left-right text-blue-500"></i> Both Sides
                                        </span>
                                    </template>
                                    <template x-if="item.carriageway === 'northbound'">
                                        <span class="flex items-center gap-1">
                                            <i class="fa-solid fa-arrow-up text-emerald-600"></i> Northbound (N)
                                        </span>
                                    </template>
                                    <template x-if="item.carriageway === 'southbound'">
                                        <span class="flex items-center gap-1">
                                            <i class="fa-solid fa-arrow-down text-indigo-600"></i> Southbound (S)
                                        </span>
                                    </template>
                                    <template x-if="item.carriageway !== 'both' && item.carriageway !== 'northbound' && item.carriageway !== 'southbound'">
                                        <span x-text="item.carriageway"></span>
                                    </template>
                                </span>
                            </td>

                            <!-- Reported By / Jurisdiction -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
                                <div class="text-slate-900 font-bold flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-blue-600 text-xs"></i>
                                    <span x-text="item.office_name || 'Wireless Station'"></span>
                                </div>
                                <div class="text-[10px] text-slate-500 uppercase tracking-wider font-semibold mt-0.5" x-text="(item.sector_name || 'Sector') + ' • ' + (item.reported_by_tier || 'Operator') + ' Tier'"></div>
                            </td>

                            <!-- Validity / Expiry Timer -->
                            <td class="py-3.5 px-4 whitespace-nowrap">
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

                            <!-- Action Buttons (Zero Page Reload AJAX) -->
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <template x-if="!item.is_resolved">
                                        <div class="flex items-center gap-1.5">
                                            <!-- Extend by +2 Hours -->
                                            <button type="button" @click="extendAdvisory(item.id)" title="Extend validity by +2 hours"
                                                class="px-2.5 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 hover:border-blue-400 shadow-sm transition-all active:scale-95 cursor-pointer flex items-center gap-1">
                                                <i class="fa-solid fa-clock-rotate-left text-blue-600"></i>
                                                <span>+2h</span>
                                            </button>

                                            <!-- Mark Resolved -->
                                            <button type="button" @click="resolveAdvisory(item.id)" title="Clear / Mark as Resolved"
                                                class="px-3 py-1.5 rounded-xl text-xs font-bold text-emerald-700 bg-emerald-50 border border-emerald-200 hover:bg-emerald-100 shadow-sm transition-all active:scale-95 cursor-pointer flex items-center gap-1">
                                                <i class="fa-solid fa-check"></i>
                                                <span>Clear</span>
                                            </button>
                                        </div>
                                    </template>

                                    <!-- View on Public Portal Map -->
                                    <a :href="'{{ route('public.travel-advisory') }}?road=' + (item.route_name || '')" target="_blank" title="View on Live Commuter Map"
                                        class="p-2 rounded-xl text-slate-500 hover:text-blue-600 hover:bg-blue-50 transition-colors">
                                        <i class="fa-solid fa-map-location-dot"></i>
                                    </a>
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
</div>

<!-- Alpine Logic Component (Strictly Zero Page Reloads / AJAX Powered) -->
<script>
function advisoryIndexManager(config) {
    return {
        items: config.initialItems || [],
        metrics: config.initialMetrics || {},
        pagination: config.pagination || { current_page: 1, last_page: 1, per_page: 15, total: 0, from: 0, to: 0 },

        currentRoad: config.currentRoad || 'all',
        currentStatus: config.currentStatus || 'active',
        currentType: config.currentType || 'all',
        currentSeverity: 'all',
        search: config.search || '',

        activeCard: 'all',
        corridorList: ['M-1', 'M-2', 'M-3', 'M-4', 'M-5', 'M-9', 'M-11', 'M-14', 'N-5', 'E-35', 'N-55'],
        isLoading: false,

        setCardFilter(type) {
            this.activeCard = type;
            if (type === 'all') {
                this.currentStatus = 'active';
                this.currentType = 'all';
                this.currentSeverity = 'all';
            } else if (type === 'fog') {
                this.currentStatus = 'active';
                this.currentType = 'fog';
                this.currentSeverity = 'all';
            } else if (type === 'closure') {
                this.currentStatus = 'active';
                this.currentType = 'road_closure';
                this.currentSeverity = 'all';
            } else if (type === 'works') {
                this.currentStatus = 'active';
                this.currentType = 'road_works';
                this.currentSeverity = 'all';
            } else if (type === 'critical') {
                this.currentStatus = 'all';
                this.currentType = 'all';
                this.currentSeverity = 'critical';
            } else if (type === 'resolved') {
                this.currentStatus = 'resolved';
                this.currentType = 'all';
                this.currentSeverity = 'all';
            }
            this.fetchData(1);
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
                search: this.search || ''
            });

            try {
                const res = await fetch(`{{ route('ops.travel-advisory.index') }}?${params.toString()}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (data.success) {
                    this.items = data.items || [];
                    this.pagination = data.pagination || this.pagination;
                    if (data.metrics) this.metrics = data.metrics;
                }
            } catch (err) {
                console.error('Error fetching travel advisories:', err);
            } finally {
                this.isLoading = false;
            }
        },

        // EXTEND ADVISORY BY 2 HOURS VIA AJAX
        async extendAdvisory(id) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`/ops/travel-advisory/${id}/extend`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (data.success) {
                    if (window.showSuccess) {
                        window.showSuccess(data.message, 'Validity Extended');
                    }
                    // Update in-place in items array
                    const item = this.items.find(x => x.id === id);
                    if (item) {
                        item.expires_at_human = data.expires_at_human || 'in 2 hours';
                        item.is_live = true;
                        item.is_expired = false;
                        item.is_resolved = false;
                    }
                } else if (window.showError) {
                    window.showError(data.message || 'Could not extend advisory.', 'Extension Failed');
                }
            } catch (err) {
                console.error('Error extending advisory:', err);
                if (window.showError) window.showError('Network error extending advisory.', 'Server Error');
            }
        },

        // RESOLVE ADVISORY VIA AJAX
        async resolveAdvisory(id) {
            if (!confirm('Clear and resolve this active advisory from commuter feeds?')) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`/ops/travel-advisory/${id}/resolve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await res.json();
                if (data.success) {
                    if (window.showSuccess) {
                        window.showSuccess(data.message, 'Advisory Resolved');
                    }
                    // If viewing active records, remove or update status
                    if (this.currentStatus === 'active') {
                        this.items = this.items.filter(x => x.id !== id);
                        if (this.metrics.total_active > 0) this.metrics.total_active--;
                        this.metrics.expired_resolved++;
                    } else {
                        const item = this.items.find(x => x.id === id);
                        if (item) {
                            item.is_resolved = true;
                            item.is_live = false;
                            item.is_expired = false;
                            item.status = 'resolved';
                        }
                    }
                } else if (window.showError) {
                    window.showError(data.message || 'Could not resolve advisory.', 'Resolution Failed');
                }
            } catch (err) {
                console.error('Error resolving advisory:', err);
                if (window.showError) window.showError('Network error resolving advisory.', 'Server Error');
            }
        }
    };
}
</script>
@endsection

@extends('layouts.app')

@section('title', 'Log Advisory / Fog - NHMP 130')
@section('page-title', 'Log Advisory / Fog')

@section('content')
@php
    $userOfficeId = $operatorOffice?->id ?? null;
    $userSectorId = $operatorOffice?->type === 'beat' ? $operatorOffice->parent_id : ($operatorOffice?->type === 'sector' ? $operatorOffice->id : null);
    $userZoneId = $operatorOffice?->type === 'beat' 
        ? $operatorOffice?->parent?->parent_id 
        : ($operatorOffice?->type === 'sector' ? $operatorOffice?->parent_id : ($operatorOffice?->type === 'zone' ? $operatorOffice->id : null));
@endphp

<div id="flash-bridge-create" 
     data-success="{{ session('success') ?? '' }}" 
     data-error="{{ session('error') ?? '' }}" 
     class="hidden"></div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const el = document.getElementById('flash-bridge-create');
        if (!el) return;
        if (el.dataset.success && window.showSuccess) {
            window.showSuccess(el.dataset.success, 'Broadcast Dispatched');
        }
        if (el.dataset.error && window.showError) {
            window.showError(el.dataset.error, 'Broadcast Error');
        }
    });
</script>

<div x-data="rapidTravelAdvisoryHub({
    tier: '{{ $operatorTier }}',
    initialUserOfficeId: {{ $userOfficeId ? (int)$userOfficeId : 'null' }},
    initialUserSectorId: {{ $userSectorId ? (int)$userSectorId : 'null' }},
    initialUserZoneId: {{ $userZoneId ? (int)$userZoneId : 'null' }},
    zonesData: {{ Js::from($hierarchy['zones'] ?? []) }},
    sectorsData: {{ Js::from($hierarchy['sectors'] ?? []) }},
    beatsData: {{ Js::from($hierarchy['beats'] ?? []) }},
    roadsData: {{ Js::from($hierarchy['roads'] ?? []) }},
    landmarksData: {{ Js::from($landmarks ?? []) }},
})" class="space-y-5 max-w-7xl mx-auto pb-12 pt-1" x-cloak>

    <!-- MAIN FORM: Single Smooth Flow (No Top Bulky Section, No Green Session Box) -->
    <form @submit.prevent="submitForm()" id="rapid-advisory-form" class="space-y-5">
        @csrf

        <!-- SECTION 1: OPERATIONAL JURISDICTION CHIPS (Zone, Sector, Beat) -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-blue-600 text-white font-black text-xs flex items-center justify-center shadow-sm">1</span>
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Operational Jurisdiction & Spatial Anchoring</h2>
                        <p class="text-[11px] text-slate-500">Auto-assigned for Beat operators; hierarchical selection for Sector, Zone, and Central Control</p>
                    </div>
                </div>

                <!-- Navigation Quick Actions -->
                <div class="flex items-center gap-2">
                    <a href="{{ route('public.travel-advisory') }}" target="_blank"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-50 border border-slate-200 hover:bg-slate-100 transition shadow-sm">
                        <i class="fa-solid fa-arrow-up-right-from-square text-sky-600"></i>
                        <span>Commuter Portal</span>
                    </a>
                    <a href="{{ route('ops.travel-advisory.index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-50 border border-slate-200 hover:bg-slate-100 transition shadow-sm">
                        <i class="fa-solid fa-table-list text-blue-600"></i>
                        <span>Active Advisories</span>
                    </a>
                </div>
            </div>

            <!-- CHIPS ROW: Zone, Sector, Beat Auto-Assign Status -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                <!-- ZONE CHIP -->
                <div class="p-3.5 rounded-2xl border transition-all"
                    :class="selectedZoneId ? 'bg-gradient-to-br from-blue-50/60 to-slate-50 border-blue-200 shadow-sm' : 'bg-slate-50 border-slate-200'">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                            <i class="fa-solid fa-map text-blue-600"></i> Zone
                        </span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md"
                            :class="tier === 'beat' || tier === 'sector' || tier === 'zone' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-blue-100 text-blue-800'">
                            <span x-show="tier === 'beat' || tier === 'sector' || tier === 'zone'">Auto-Assign</span>
                            <span x-show="tier === 'national'">Selectable</span>
                        </span>
                    </div>

                    <template x-if="tier === 'beat' || tier === 'sector' || tier === 'zone'">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-slate-900" x-text="selectedZoneName || 'Auto-Assigned Zone'"></span>
                            <i class="fa-solid fa-lock text-slate-400 text-xs" title="Locked by your operational post"></i>
                        </div>
                    </template>

                    <template x-if="tier === 'national'">
                        <div class="flex flex-wrap gap-1.5 mt-1">
                            <template x-for="z in zonesList" :key="z.id">
                                <button type="button" @click="selectZone(z.id)"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer"
                                    :class="selectedZoneId == z.id ? 'bg-blue-600 text-white shadow-sm ring-2 ring-blue-400' : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-400'"
                                    x-text="z.name">
                                </button>
                            </template>
                        </div>
                    </template>
                </div>

                <!-- SECTOR CHIP -->
                <div class="p-3.5 rounded-2xl border transition-all"
                    :class="selectedSectorId ? 'bg-gradient-to-br from-indigo-50/60 to-slate-50 border-indigo-200 shadow-sm' : 'bg-slate-50 border-slate-200'">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                            <i class="fa-solid fa-compass text-indigo-600"></i> Sector
                        </span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md"
                            :class="tier === 'beat' || tier === 'sector' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-indigo-100 text-indigo-800'">
                            <span x-show="tier === 'beat' || tier === 'sector'">Auto-Assign</span>
                            <span x-show="tier === 'zone' || tier === 'national'">Select Sector</span>
                        </span>
                    </div>

                    <template x-if="tier === 'beat' || tier === 'sector'">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-slate-900" x-text="selectedSectorName || 'Auto-Assigned Sector'"></span>
                            <i class="fa-solid fa-lock text-slate-400 text-xs" title="Locked by your operational post"></i>
                        </div>
                    </template>

                    <template x-if="tier === 'zone' || tier === 'national'">
                        <div class="flex flex-wrap gap-1.5 mt-1 max-h-32 overflow-y-auto">
                            <template x-for="s in filteredSectors" :key="s.id">
                                <button type="button" @click="selectSector(s.id)"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer"
                                    :class="selectedSectorId == s.id ? 'bg-indigo-600 text-white shadow-sm ring-2 ring-indigo-400' : 'bg-white text-slate-700 border border-slate-200 hover:border-indigo-400'"
                                    x-text="s.name">
                                </button>
                            </template>
                            <span x-show="filteredSectors.length === 0" class="text-xs text-slate-400 italic">Select a Zone first</span>
                        </div>
                    </template>
                </div>

                <!-- BEAT (OFFICE) CHIP -->
                <div class="p-3.5 rounded-2xl border transition-all"
                    :class="selectedBeatId ? 'bg-gradient-to-br from-amber-50/60 to-slate-50 border-amber-300 shadow-sm' : 'bg-slate-50 border-slate-200'">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                            <i class="fa-solid fa-shield-halved text-amber-600"></i> Beat (Office)
                        </span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md"
                            :class="tier === 'beat' ? 'bg-emerald-100 text-emerald-800 border border-emerald-200' : 'bg-amber-100 text-amber-800'">
                            <span x-show="tier === 'beat'">Current Beat</span>
                            <span x-show="tier !== 'beat'">Select Beat</span>
                        </span>
                    </div>

                    <template x-if="tier === 'beat'">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-slate-900" x-text="selectedBeatName || 'Current Beat'"></span>
                            <span class="text-xs font-mono font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded" x-text="selectedBeatKmRange"></span>
                        </div>
                    </template>

                    <template x-if="tier !== 'beat'">
                        <div class="flex flex-wrap gap-1.5 mt-1 max-h-32 overflow-y-auto">
                            <template x-for="b in filteredBeats" :key="b.id">
                                <button type="button" @click="selectBeat(b.id)"
                                    class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                                    :class="selectedBeatId == b.id ? 'bg-amber-600 text-white shadow-sm ring-2 ring-amber-400' : 'bg-white text-slate-700 border border-slate-200 hover:border-amber-400'">
                                    <span x-text="b.name"></span>
                                    <span class="text-[10px] opacity-80 font-mono" x-show="b.km_min !== null" x-text="'(KM ' + Math.round(b.km_min) + '-' + Math.round(b.km_max) + ')'"></span>
                                </button>
                            </template>
                            <span x-show="filteredBeats.length === 0" class="text-xs text-slate-400 italic">Select a Sector first</span>
                        </div>
                    </template>
                </div>
            </div>

            <!-- DYNAMIC CORRIDOR STRIP & BOUND CHIPS -->
            <div class="mt-4 p-4 rounded-2xl bg-slate-900 text-white flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 shadow-inner">
                <div class="flex items-start sm:items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-blue-500/20 text-blue-400 border border-blue-500/30 flex items-center justify-center text-xl shrink-0">
                        <i class="fa-solid fa-road"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-black text-amber-400 tracking-wide font-mono px-2 py-0.5 rounded bg-amber-400/10 border border-amber-400/30" x-text="resolvedRouteCode"></span>
                            <span class="text-xs text-slate-300 font-semibold" x-text="resolvedRoadTitle"></span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Auto-Mapped from Beat Registry</span>
                        </div>
                        <div class="text-xs text-slate-400 mt-1 flex flex-wrap items-center gap-3">
                            <span class="font-mono text-slate-300 font-bold" x-text="'Beat Span: ' + selectedBeatKmRange"></span>
                            <span class="text-slate-600">•</span>
                            <span class="text-slate-300" x-text="'Sector: ' + selectedSectorName"></span>
                            <span class="text-slate-600">•</span>
                            <span class="text-slate-300" x-text="'Zone: ' + selectedZoneName"></span>
                        </div>
                    </div>
                </div>

                <!-- CARRIAGEWAY BOUND CHIPS -->
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 mr-1">Bound:</span>
                    <button type="button" @click="setCarriageway('both')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        :class="carriagewayBound === 'both' ? 'bg-gradient-to-r from-blue-500 to-sky-600 text-white shadow-md shadow-blue-500/30 ring-2 ring-blue-400' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'">
                        <i class="fa-solid fa-arrows-left-right text-xs"></i>
                        <span>Dual / Both Sides</span>
                    </button>
                    <button type="button" @click="setCarriageway('northbound')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        :class="carriagewayBound === 'northbound' ? 'bg-gradient-to-r from-blue-500 to-sky-600 text-white shadow-md shadow-blue-500/30 ring-2 ring-blue-400' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'">
                        <i class="fa-solid fa-arrow-up text-xs"></i>
                        <span>Northbound (N)</span>
                    </button>
                    <button type="button" @click="setCarriageway('southbound')"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                        :class="carriagewayBound === 'southbound' ? 'bg-gradient-to-r from-blue-500 to-sky-600 text-white shadow-md shadow-blue-500/30 ring-2 ring-blue-400' : 'bg-slate-800 text-slate-300 hover:bg-slate-700'">
                        <i class="fa-solid fa-arrow-down text-xs"></i>
                        <span>Southbound (S)</span>
                    </button>
                </div>
            </div>

            <!-- KM SPAN & LANDMARK SELECTOR (Decimals Allowed via step="any" and min="0") -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                <!-- Start Location & KM -->
                <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-emerald-600"></i> Start Location / Milestone
                        </label>
                        <span class="text-[10px] text-slate-400 font-mono">From Post</span>
                    </div>

                    <!-- Quick Landmark Chips for Current Beat -->
                    <div class="flex flex-wrap gap-1 max-h-24 overflow-y-auto" x-show="beatLandmarks.length > 0">
                        <template x-for="lm in beatLandmarks" :key="'start-' + lm.id">
                            <button type="button" @click="setStartLandmark(lm.location_name, lm.km_numeric)"
                                class="text-[10px] font-bold px-2 py-0.5 rounded bg-white border border-slate-200 hover:border-emerald-500 text-slate-700 hover:text-emerald-700 transition cursor-pointer"
                                x-text="lm.location_name + ' (KM ' + lm.km_numeric + ')'">
                            </button>
                        </template>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <input type="text" x-model="startLandmark" @input="onManualInputUpdate()"
                                placeholder="e.g. Start Landmark"
                                class="w-full text-xs bg-white border border-slate-200 rounded-xl p-2 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <!-- step="any" min="0" allows any non-negative decimal -->
                            <input type="number" step="any" min="0" x-model.number="startKm" @input="onManualKmUpdate('start')" required
                                class="w-full text-xs font-mono font-bold bg-white border border-slate-200 rounded-xl p-2 text-center focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <!-- End Location & KM -->
                <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-rose-600"></i> End Location / Milestone
                        </label>
                        <span class="text-[10px] text-slate-400 font-mono">To Post</span>
                    </div>

                    <!-- Quick Landmark Chips for Current Beat -->
                    <div class="flex flex-wrap gap-1 max-h-24 overflow-y-auto" x-show="beatLandmarks.length > 0">
                        <template x-for="lm in beatLandmarks" :key="'end-' + lm.id">
                            <button type="button" @click="setEndLandmark(lm.location_name, lm.km_numeric)"
                                class="text-[10px] font-bold px-2 py-0.5 rounded bg-white border border-slate-200 hover:border-rose-500 text-slate-700 hover:text-rose-700 transition cursor-pointer"
                                x-text="lm.location_name + ' (KM ' + lm.km_numeric + ')'">
                            </button>
                        </template>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="col-span-2">
                            <input type="text" x-model="endLandmark" @input="onManualInputUpdate()"
                                placeholder="e.g. End Landmark"
                                class="w-full text-xs bg-white border border-slate-200 rounded-xl p-2 font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <!-- step="any" min="0" allows any non-negative decimal -->
                            <input type="number" step="any" min="0" x-model.number="endKm" @input="onManualKmUpdate('end')" required
                                class="w-full text-xs font-mono font-bold bg-white border border-slate-200 rounded-xl p-2 text-center focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: INCIDENT CATEGORY & DYNAMIC CHIPS -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200 shadow-sm space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-slate-900 text-white font-black text-xs flex items-center justify-center shadow-sm">2</span>
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Select Incident Type & Severity</h2>
                        <p class="text-[11px] text-slate-500">Contextual guidance and parameters dynamically adjust based on category</p>
                    </div>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-400">Severity:</span>
                    <button type="button" @click="setSeverity('advisory')"
                        class="px-2.5 py-1 rounded-lg text-xs font-extrabold transition cursor-pointer"
                        :class="severity === 'advisory' ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        Advisory
                    </button>
                    <button type="button" @click="setSeverity('warning')"
                        class="px-2.5 py-1 rounded-lg text-xs font-extrabold transition cursor-pointer"
                        :class="severity === 'warning' ? 'bg-amber-500 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        Warning
                    </button>
                    <button type="button" @click="setSeverity('critical')"
                        class="px-2.5 py-1 rounded-lg text-xs font-extrabold transition cursor-pointer"
                        :class="severity === 'critical' ? 'bg-rose-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        Critical
                    </button>
                </div>
            </div>

            <!-- 6 TACTILE INCIDENT CATEGORY CHIPS -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
                <!-- Fog & Smog -->
                <button type="button" @click="setCategory('fog')"
                    class="p-3 rounded-2xl border-2 transition-all flex flex-col items-center text-center gap-2 group cursor-pointer"
                    :class="advisoryType === 'fog' ? 'border-amber-500 bg-amber-50/70 shadow-md shadow-amber-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'fog' ? 'bg-amber-500 text-white shadow-sm' : 'bg-amber-100 text-amber-600'">
                        <i class="fa-solid fa-smog"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Fog & Smog</div>
                        <div class="text-[10px] text-slate-500">Zero visibility</div>
                    </div>
                </button>

                <!-- Road Closure -->
                <button type="button" @click="setCategory('road_closure')"
                    class="p-3 rounded-2xl border-2 transition-all flex flex-col items-center text-center gap-2 group cursor-pointer"
                    :class="advisoryType === 'road_closure' ? 'border-rose-600 bg-rose-50/70 shadow-md shadow-rose-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'road_closure' ? 'bg-rose-600 text-white shadow-sm' : 'bg-rose-100 text-rose-600'">
                        <i class="fa-solid fa-ban"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Road Closure</div>
                        <div class="text-[10px] text-slate-500">Emergency block</div>
                    </div>
                </button>

                <!-- Diversion -->
                <button type="button" @click="setCategory('diversion')"
                    class="p-3 rounded-2xl border-2 transition-all flex flex-col items-center text-center gap-2 group cursor-pointer"
                    :class="advisoryType === 'diversion' ? 'border-purple-600 bg-purple-50/70 shadow-md shadow-purple-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'diversion' ? 'bg-purple-600 text-white shadow-sm' : 'bg-purple-100 text-purple-600'">
                        <i class="fa-solid fa-arrows-split-up-and-left"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Diversion</div>
                        <div class="text-[10px] text-slate-500">Reroute traffic</div>
                    </div>
                </button>

                <!-- Road Works -->
                <button type="button" @click="setCategory('road_works')"
                    class="p-3 rounded-2xl border-2 transition-all flex flex-col items-center text-center gap-2 group cursor-pointer"
                    :class="advisoryType === 'road_works' ? 'border-orange-500 bg-orange-50/70 shadow-md shadow-orange-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'road_works' ? 'bg-orange-500 text-white shadow-sm' : 'bg-orange-100 text-orange-600'">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Road Works</div>
                        <div class="text-[10px] text-slate-500">Repair zone</div>
                    </div>
                </button>

                <!-- Traffic Congestion -->
                <button type="button" @click="setCategory('traffic')"
                    class="p-3 rounded-2xl border-2 transition-all flex flex-col items-center text-center gap-2 group cursor-pointer"
                    :class="advisoryType === 'traffic' ? 'border-red-500 bg-red-50/70 shadow-md shadow-red-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'traffic' ? 'bg-red-500 text-white shadow-sm' : 'bg-red-100 text-red-600'">
                        <i class="fa-solid fa-car"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Congestion</div>
                        <div class="text-[10px] text-slate-500">Queue & delay</div>
                    </div>
                </button>

                <!-- Weather & Rain -->
                <button type="button" @click="setCategory('weather')"
                    class="p-3 rounded-2xl border-2 transition-all flex flex-col items-center text-center gap-2 group cursor-pointer"
                    :class="advisoryType === 'weather' ? 'border-sky-500 bg-sky-50/70 shadow-md shadow-sky-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'weather' ? 'bg-sky-500 text-white shadow-sm' : 'bg-sky-100 text-sky-600'">
                        <i class="fa-solid fa-cloud-showers-heavy"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Rain & Storm</div>
                        <div class="text-[10px] text-slate-500">Wet tarmac</div>
                    </div>
                </button>
            </div>

            <!-- FOG OPTIONS -->
            <div x-show="advisoryType === 'fog'" x-transition class="p-4 rounded-2xl bg-amber-50/80 border border-amber-200/80 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-amber-900 flex items-center gap-2">
                        <i class="fa-solid fa-eye-slash text-amber-600"></i> Fog Visibility Range (Select Preset Chip or Fine-Tune Slider)
                    </span>
                    <span class="text-base font-black font-mono text-amber-900 px-3 py-1 rounded-xl bg-white border border-amber-300 shadow-sm" x-text="visibilityMeters + ' Meters'"></span>
                </div>

                <!-- Visibility Preset Chips -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <button type="button" @click="setVisibility(25)"
                        class="p-2.5 rounded-xl border text-center transition-all cursor-pointer"
                        :class="visibilityMeters <= 35 ? 'bg-rose-600 text-white border-rose-600 shadow-md' : 'bg-white text-slate-700 border-slate-200 hover:border-rose-400'">
                        <div class="text-xs font-black">&lt; 35m (Zero Visibility)</div>
                        <div class="text-[10px] opacity-80">Dense Fog Convoy</div>
                    </button>
                    <button type="button" @click="setVisibility(75)"
                        class="p-2.5 rounded-xl border text-center transition-all cursor-pointer"
                        :class="visibilityMeters > 35 && visibilityMeters <= 100 ? 'bg-amber-500 text-white border-amber-500 shadow-md' : 'bg-white text-slate-700 border-slate-200 hover:border-amber-400'">
                        <div class="text-xs font-black">50 - 100m (Dense Fog)</div>
                        <div class="text-[10px] opacity-80">Severe Caution</div>
                    </button>
                    <button type="button" @click="setVisibility(150)"
                        class="p-2.5 rounded-xl border text-center transition-all cursor-pointer"
                        :class="visibilityMeters > 100 && visibilityMeters <= 250 ? 'bg-sky-600 text-white border-sky-600 shadow-md' : 'bg-white text-slate-700 border-slate-200 hover:border-sky-400'">
                        <div class="text-xs font-black">100 - 250m (Moderate)</div>
                        <div class="text-[10px] opacity-80">Fog Patches</div>
                    </button>
                    <button type="button" @click="setVisibility(500)"
                        class="p-2.5 rounded-xl border text-center transition-all cursor-pointer"
                        :class="visibilityMeters > 250 ? 'bg-emerald-600 text-white border-emerald-600 shadow-md' : 'bg-white text-slate-700 border-slate-200 hover:border-emerald-400'">
                        <div class="text-xs font-black">500m+ (Patchy Haze)</div>
                        <div class="text-[10px] opacity-80">Light Smog</div>
                    </button>
                </div>

                <!-- Range Slider -->
                <input type="range" min="5" max="1000" step="5" x-model.number="visibilityMeters" @input="onManualInputUpdate()" class="w-full accent-amber-600 cursor-pointer">

                <!-- Speed Guidance Chips for Fog -->
                <div>
                    <label class="text-[11px] font-bold uppercase tracking-wider text-amber-900 block mb-1.5">Fog Advisory Speed Limit:</label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" @click="setSpeedLimit(30)"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                            :class="speedLimit === 30 ? 'bg-amber-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:border-amber-400'">
                            30 km/h (Dense Convoy)
                        </button>
                        <button type="button" @click="setSpeedLimit(50)"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                            :class="speedLimit === 50 ? 'bg-amber-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:border-amber-400'">
                            50 km/h (Recommended)
                        </button>
                        <button type="button" @click="setSpeedLimit(60)"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                            :class="speedLimit === 60 ? 'bg-amber-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:border-amber-400'">
                            60 km/h (Moderate)
                        </button>
                        <button type="button" @click="setSpeedLimit(0)"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                            :class="speedLimit === 0 ? 'bg-rose-700 text-white' : 'bg-white text-slate-700 border border-slate-200 hover:border-rose-400'">
                            0 km/h (Complete Closure)
                        </button>
                    </div>
                </div>
            </div>

            <!-- CLOSURE OPTIONS -->
            <div x-show="advisoryType !== 'fog'" x-transition class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-road-barrier text-rose-600"></i> Lane Status & Closure Restrictions
                </label>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                    <button type="button" @click="setClosureType('')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === '' ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <div class="text-xs font-bold">All Lanes Open</div>
                        <div class="text-[10px] opacity-75">Caution only</div>
                    </button>
                    <button type="button" @click="setClosureType('lane_1')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'lane_1' ? 'bg-orange-600 text-white border-orange-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <div class="text-xs font-bold">Lane 1 Closed</div>
                        <div class="text-[10px] opacity-75">Slow lane</div>
                    </button>
                    <button type="button" @click="setClosureType('lane_2')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'lane_2' ? 'bg-orange-600 text-white border-orange-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <div class="text-xs font-bold">Lane 2 Closed</div>
                        <div class="text-[10px] opacity-75">Center lane</div>
                    </button>
                    <button type="button" @click="setClosureType('shoulder')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'shoulder' ? 'bg-amber-600 text-white border-amber-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <div class="text-xs font-bold">Shoulder Closed</div>
                        <div class="text-[10px] opacity-75">Emergency lane</div>
                    </button>
                    <button type="button" @click="setClosureType('diversion')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'diversion' ? 'bg-purple-600 text-white border-purple-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <div class="text-xs font-bold">Diversion Active</div>
                        <div class="text-[10px] opacity-75">Alternate route</div>
                    </button>
                    <button type="button" @click="setClosureType('full')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'full' ? 'bg-rose-600 text-white border-rose-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <div class="text-xs font-bold">Full Closure</div>
                        <div class="text-[10px] opacity-75">Carriageway blocked</div>
                    </button>
                </div>
            </div>

            <!-- EXPIRY PRESETS -->
            <div class="p-4 rounded-2xl bg-blue-50/60 border border-blue-200/80 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1">
                    <label class="text-xs font-bold text-blue-900 flex items-center gap-1.5">
                        <i class="fa-solid fa-clock-rotate-left text-blue-600"></i> Advisory Validity & Expiry Timeline
                    </label>
                    <div class="text-xs font-bold text-blue-800">
                        <span>Calculated Expiry: </span>
                        <span class="font-mono bg-white px-2 py-0.5 rounded border border-blue-200" x-text="computedExpiryText"></span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="setDuration(1)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        :class="durationHours === 1 ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-400'">
                        1 Hour
                    </button>
                    <button type="button" @click="setDuration(2)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        :class="durationHours === 2 ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-400'">
                        2 Hours
                    </button>
                    <button type="button" @click="setDuration(4)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        :class="durationHours === 4 ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-400'">
                        4 Hours (Standard)
                    </button>
                    <button type="button" @click="setDuration(6)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        :class="durationHours === 6 ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-400'">
                        6 Hours (Fog Shift)
                    </button>
                    <button type="button" @click="setDuration(12)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        :class="durationHours === 12 ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-400'">
                        12 Hours
                    </button>
                    <button type="button" @click="setDuration(24)"
                        class="px-3 py-1.5 rounded-xl text-xs font-bold transition cursor-pointer"
                        :class="durationHours === 24 ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700 border border-slate-200 hover:border-blue-400'">
                        24 Hours
                    </button>
                </div>
            </div>
        </div>

        <!-- SECTION 3: DYNAMIC COMMUTER ADVISORY MESSAGE -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white font-black text-xs flex items-center justify-center shadow-sm">3</span>
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Dynamic Commuter Advisory Synthesis</h2>
                        <p class="text-[11px] text-slate-500">Auto-generated in real-time from your selected chips; click to append or customize anytime</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="resetToAutoGenerated()"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition cursor-pointer">
                        <i class="fa-solid fa-rotate-left text-xs"></i>
                        <span>Re-generate Text</span>
                    </button>
                </div>
            </div>

            <!-- Title -->
            <div>
                <label class="text-xs font-bold text-slate-700 block mb-1">Broadcast Title <span class="text-rose-500">*</span></label>
                <input type="text" x-model="title" required
                    class="w-full text-sm font-bold bg-slate-50 border border-slate-200 rounded-xl p-3 focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Message Textarea -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-bold text-slate-700">Public Commuter Advisory Details <span class="text-rose-500">*</span></label>
                    <span class="text-[10px] text-emerald-600 font-bold flex items-center gap-1">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-synchronized with chips
                    </span>
                </div>
                <textarea x-model="message" rows="3" required
                    class="w-full text-xs font-medium text-slate-800 bg-slate-50 border border-slate-200 rounded-xl p-3 leading-relaxed focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
        </div>

        <!-- SECTION 4: LUXURY COMMUTER PREVIEW & ONE-CLICK BROADCAST BUTTON -->
        <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-3xl p-6 text-white border border-slate-800 shadow-xl space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-500 text-slate-900 flex items-center justify-center font-black text-sm">
                        <i class="fa-solid fa-eye"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-wider">Live Commuter Portal Preview</h3>
                        <p class="text-[11px] text-slate-400">Exact live rendering as seen on nhmp.gov.pk & 130 Humsafar Mobile App</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-[11px] font-mono border border-emerald-500/30 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Broadcast Ready
                </span>
            </div>

            <!-- Live Commuter Card Mockup -->
            <div class="p-5 rounded-2xl bg-white/10 backdrop-blur-md border border-white/15 space-y-3.5">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center gap-2.5">
                        <span class="px-2.5 py-1 rounded-lg bg-amber-400 text-slate-900 font-black text-xs font-mono" x-text="resolvedRouteCode"></span>
                        <span class="text-sm font-bold text-white" x-text="title"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                            :class="severity === 'critical' ? 'bg-rose-500 text-white' : (severity === 'warning' ? 'bg-amber-500 text-slate-900' : 'bg-blue-500 text-white')"
                            x-text="severity.toUpperCase()"></span>
                        <span class="text-[11px] font-mono text-slate-300" x-text="'Valid until ' + computedExpiryText"></span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-300">
                    <span class="font-semibold text-white flex items-center gap-1">
                        <i class="fa-solid fa-location-dot text-amber-400"></i>
                        <span x-text="(startLandmark || ('KM ' + startKm)) + ' ⇄ ' + (endLandmark || ('KM ' + endKm))"></span>
                    </span>
                    <span class="text-slate-500">•</span>
                    <span class="font-mono text-amber-300" x-text="'(KM ' + startKm + ' - ' + endKm + ')'"></span>
                    <span class="text-slate-500">•</span>
                    <span class="capitalize" x-text="carriagewayBound === 'both' ? 'Both Directions' : carriagewayBound"></span>
                    <template x-if="advisoryType === 'fog'">
                        <span class="px-2 py-0.5 rounded bg-amber-500/30 text-amber-200 text-[10px] font-bold" x-text="'Visibility: ' + visibilityMeters + 'm'"></span>
                    </template>
                    <template x-if="closureType">
                        <span class="px-2 py-0.5 rounded bg-rose-500/30 text-rose-200 text-[10px] font-bold" x-text="closureType.replace('_', ' ').toUpperCase()"></span>
                    </template>
                </div>

                <p class="text-xs text-slate-200 leading-relaxed font-normal bg-black/20 p-3 rounded-xl border border-white/5" x-text="message"></p>
            </div>

            <!-- 1-Click Broadcast Button (AJAX Powered, zero full-page reload) -->
            <div>
                <button type="submit" :disabled="isSubmitting"
                    class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-blue-600 via-sky-600 to-blue-700 hover:from-blue-500 hover:to-sky-500 text-white font-black text-sm tracking-wide shadow-xl shadow-blue-500/30 transition-all duration-150 transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2.5 cursor-pointer disabled:opacity-60">
                    <i class="fa-solid" :class="isSubmitting ? 'fa-spinner fa-spin' : 'fa-tower-broadcast animate-pulse'"></i>
                    <span x-text="isSubmitting ? 'Broadcasting Advisory Across Network...' : 'Broadcast Travel Advisory (Instant National Cache Invalidation)'"></span>
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Alpine Logic Component -->
<script>
function rapidTravelAdvisoryHub(config) {
    return {
        tier: config.tier || 'beat',
        zonesList: config.zonesData || [],
        sectorsList: config.sectorsData || [],
        beatsList: config.beatsData || [],
        roadsList: config.roadsData || [],
        landmarksMap: config.landmarksData || {},

        selectedZoneId: config.initialUserZoneId || '',
        selectedSectorId: config.initialUserSectorId || '',
        selectedBeatId: config.initialUserOfficeId || '',

        advisoryType: 'fog',
        severity: 'warning',
        carriagewayBound: 'both',
        visibilityMeters: 35,
        speedLimit: 50,
        closureType: '',
        durationHours: 4,

        startLandmark: '',
        startKm: 210.0,
        endLandmark: '',
        endKm: 245.0,

        title: '',
        message: '',
        isCustomMessageEdited: false,
        isSubmitting: false,

        init() {
            // Priority: if user has assigned beat, load it
            if (this.selectedBeatId) {
                const b = this.beatsList.find(x => x.id == this.selectedBeatId);
                if (b) {
                    if (b.sector_id || b.parent_id) this.selectedSectorId = b.sector_id || b.parent_id;
                    if (b.zone_id) this.selectedZoneId = b.zone_id;
                }
                this.selectBeat(this.selectedBeatId, false);
            } else if (this.zonesList.length > 0) {
                // National admin: start with first zone
                this.selectZone(this.zonesList[0].id);
            }
            this.generateAdvisoryMessage();
        },

        get selectedZoneName() {
            const z = this.zonesList.find(x => x.id == this.selectedZoneId);
            return z ? z.name : 'Central Operations Zone';
        },

        get selectedSectorName() {
            const s = this.sectorsList.find(x => x.id == this.selectedSectorId);
            return s ? s.name : 'Sector Office';
        },

        get selectedBeatName() {
            const b = this.beatsList.find(x => x.id == this.selectedBeatId);
            return b ? b.name : 'Current Beat';
        },

        get selectedBeatKmRange() {
            const b = this.beatsList.find(x => x.id == this.selectedBeatId);
            if (b && b.km_range) return b.km_range;
            if (b && b.km_min !== null && b.km_min !== undefined) return `KM ${parseFloat(b.km_min).toFixed(2)} - ${parseFloat(b.km_max).toFixed(2)}`;
            return 'KM 0 - 50';
        },

        get filteredSectors() {
            if (!this.selectedZoneId) return this.sectorsList;
            return this.sectorsList.filter(s => (s.zone_id == this.selectedZoneId || s.parent_id == this.selectedZoneId));
        },

        get filteredBeats() {
            if (!this.selectedSectorId) {
                if (this.selectedZoneId) {
                    return this.beatsList.filter(b => (b.zone_id == this.selectedZoneId));
                }
                return this.beatsList;
            }
            return this.beatsList.filter(b => (b.sector_id == this.selectedSectorId || b.parent_id == this.selectedSectorId));
        },

        get beatLandmarks() {
            if (!this.selectedBeatId || !this.landmarksMap[this.selectedBeatId]) return [];
            return this.landmarksMap[this.selectedBeatId];
        },

        get resolvedRouteCode() {
            const b = this.beatsList.find(x => x.id == this.selectedBeatId);
            if (b) {
                const s = this.sectorsList.find(x => x.id == (b.sector_id || b.parent_id));
                if (s && s.name) {
                    const match = s.name.match(/(M-\d+|M\d+|N-\d+|N\d+|E-\d+|E\d+|LEW|LEBP|KKH|S-1)/i);
                    if (match) {
                        let c = match[0].toUpperCase();
                        if (c.indexOf('-') === -1 && (c.startsWith('M') || c.startsWith('N') || c.startsWith('E'))) {
                            c = c[0] + '-' + c.slice(1);
                        }
                        return c;
                    }
                }
            }
            return 'M-2';
        },

        get resolvedRoadTitle() {
            const code = this.resolvedRouteCode;
            const clean = code.replace('-', '');
            const r = this.roadsList.find(x => (x.road_short === code || x.road === clean || x.road === code));
            if (r) return `${r.road_name || r.road} (${r.road_from} ⇄ ${r.road_to})`;
            return 'National Motorway Network';
        },

        get computedExpiryText() {
            const d = new Date();
            d.setMinutes(d.getMinutes() + (this.durationHours * 60));
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' PKT';
        },

        selectZone(id) {
            this.selectedZoneId = id;
            const availableSectors = this.sectorsList.filter(s => (s.zone_id == id || s.parent_id == id));
            if (availableSectors.length > 0) {
                this.selectSector(availableSectors[0].id);
            } else {
                this.selectedSectorId = '';
                this.selectedBeatId = '';
            }
        },

        selectSector(id) {
            this.selectedSectorId = id;
            const sec = this.sectorsList.find(x => x.id == id);
            if (sec && (sec.zone_id || sec.parent_id)) {
                this.selectedZoneId = sec.zone_id || sec.parent_id;
            }
            const availableBeats = this.beatsList.filter(b => (b.sector_id == id || b.parent_id == id));
            if (availableBeats.length > 0) {
                this.selectBeat(availableBeats[0].id);
            } else {
                this.selectedBeatId = '';
            }
        },

        selectBeat(id, triggerRegen = true) {
            this.selectedBeatId = id;
            const b = this.beatsList.find(x => x.id == id);
            if (b) {
                if (b.sector_id || b.parent_id) this.selectedSectorId = b.sector_id || b.parent_id;
                if (b.zone_id) this.selectedZoneId = b.zone_id;
                if (b.km_min !== null && b.km_min !== undefined) this.startKm = parseFloat(b.km_min);
                if (b.km_max !== null && b.km_max !== undefined) this.endKm = parseFloat(b.km_max);

                // Auto-pick landmark names if available
                const lms = this.landmarksMap[id];
                if (lms && lms.length > 0) {
                    this.startLandmark = lms[0].location_name;
                    this.endLandmark = lms[lms.length - 1].location_name;
                } else {
                    this.startLandmark = `${b.name} Start Post`;
                    this.endLandmark = `${b.name} End Post`;
                }
            }
            if (triggerRegen) this.generateAdvisoryMessage();
        },

        setStartLandmark(name, km) {
            this.startLandmark = name;
            if (km !== null && km !== undefined) this.startKm = Math.max(0, parseFloat(km));
            this.generateAdvisoryMessage();
        },

        setEndLandmark(name, km) {
            this.endLandmark = name;
            if (km !== null && km !== undefined) this.endKm = Math.max(0, parseFloat(km));
            this.generateAdvisoryMessage();
        },

        onManualKmUpdate(type) {
            if (type === 'start') {
                if (this.startKm < 0 || isNaN(this.startKm)) this.startKm = 0;
            } else {
                if (this.endKm < 0 || isNaN(this.endKm)) this.endKm = 0;
            }
            this.generateAdvisoryMessage();
        },

        setCategory(type) {
            this.advisoryType = type;
            if (type === 'fog') {
                this.severity = 'warning';
                this.speedLimit = 50;
            } else if (type === 'road_closure') {
                this.severity = 'critical';
                this.closureType = 'full';
                this.speedLimit = 0;
            } else if (type === 'diversion') {
                this.severity = 'warning';
                this.closureType = 'diversion';
            } else if (type === 'road_works') {
                this.severity = 'advisory';
                this.closureType = 'lane_1';
                this.speedLimit = 60;
            }
            this.generateAdvisoryMessage();
        },

        setSeverity(sev) {
            this.severity = sev;
            this.generateAdvisoryMessage();
        },

        setCarriageway(bound) {
            this.carriagewayBound = bound;
            this.generateAdvisoryMessage();
        },

        setVisibility(meters) {
            this.visibilityMeters = meters;
            if (meters <= 35) {
                this.severity = 'critical';
                this.speedLimit = 30;
            } else if (meters <= 100) {
                this.severity = 'warning';
                this.speedLimit = 50;
            }
            this.generateAdvisoryMessage();
        },

        setSpeedLimit(kmh) {
            this.speedLimit = kmh;
            this.generateAdvisoryMessage();
        },

        setClosureType(type) {
            this.closureType = type;
            if (type === 'full') this.severity = 'critical';
            this.generateAdvisoryMessage();
        },

        setDuration(hours) {
            this.durationHours = hours;
            this.generateAdvisoryMessage();
        },

        onManualInputUpdate() {
            this.generateAdvisoryMessage();
        },

        resetToAutoGenerated() {
            this.isCustomMessageEdited = false;
            this.generateAdvisoryMessage(true);
        },

        // DYNAMIC ADVISORY GENERATOR
        generateAdvisoryMessage(force = false) {
            const route = this.resolvedRouteCode;
            const boundText = this.carriagewayBound === 'both' ? 'Both Directions' : (this.carriagewayBound === 'northbound' ? 'Northbound (N)' : 'Southbound (S)');
            const beatName = this.selectedBeatName;
            const startLoc = this.startLandmark || `KM ${this.startKm}`;
            const endLoc = this.endLandmark || `KM ${this.endKm}`;

            let t = '';
            let m = '';

            if (this.advisoryType === 'fog') {
                if (this.visibilityMeters <= 35) {
                    t = `Dense Fog Alert - Visibility Below 35m (${route})`;
                    m = `Dense fog advisory on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) under ${beatName}. Atmospheric visibility has critically dropped to under ${this.visibilityMeters}m. Commuters are advised to slow down to ${this.speedLimit} km/h, use fog lights, and follow NHMP convoy piloting. Expected duration: ${this.durationHours} hours.`;
                } else if (this.visibilityMeters <= 100) {
                    t = `Moderate Fog Advisory - Visibility ${this.visibilityMeters}m (${route})`;
                    m = `Moderate fog patches reported on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} - ${this.endKm}). Visibility is restricted to ${this.visibilityMeters}m. Please maintain a safe headway, keep low beams on, and observe a ${this.speedLimit} km/h advisory speed. Valid for ${this.durationHours} hours.`;
                } else {
                    t = `Patchy Haze & Smog Advisory (${route})`;
                    m = `Light fog and smog haze observed along ${route} (${boundText}) from ${startLoc} to ${endLoc}. Tarmac clear with visibility around ${this.visibilityMeters}m. Normal speeds permissible with caution.`;
                }
            } else if (this.advisoryType === 'road_closure') {
                t = `Emergency Road Closure on ${route}`;
                m = `ATTENTION COMMUTERS: Complete carriageway closure in effect on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) under ${beatName}. Traffic is stopped for traveler safety. Please obey on-site NHMP wireless patrol instructions. Clearance expected in ${this.durationHours} hours.`;
            } else if (this.advisoryType === 'diversion') {
                t = `Traffic Diversion Active on ${route}`;
                m = `Traffic diversion implemented on ${route} (${boundText}) at ${startLoc} (KM ${this.startKm}). Commuters are being rerouted through alternate regional corridors. Follow directional signboards and NHMP officers on site. Duration: ${this.durationHours} hours.`;
            } else if (this.advisoryType === 'road_works') {
                const laneText = this.closureType ? this.closureType.replace('_', ' ') : 'Slow lane';
                t = `Active Road Works & Lane Restriction (${route})`;
                m = `Routine highway rehabilitation work in progress on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}). ${laneText} restricted. Advisory speed limit: ${this.speedLimit} km/h. Please slow down and observe work-zone signage.`;
            } else if (this.advisoryType === 'traffic') {
                t = `Traffic Congestion / Queue Alert (${route})`;
                m = `Slow-moving commuter volume reported on ${route} (${boundText}) approaching ${startLoc} (KM ${this.startKm}). Electronic toll booths active and NHMP ground patrols facilitating flow. Commuters should expect minor delays for the next ${this.durationHours} hours.`;
            } else if (this.advisoryType === 'weather') {
                t = `Inclement Weather & Wet Tarmac Alert (${route})`;
                m = `Heavy rain and slippery tarmac reported on ${route} (${boundText}) between ${startLoc} and ${endLoc}. Commuters are cautioned against sudden braking and advised to keep safe following distance.`;
            }

            this.title = t;
            if (!this.isCustomMessageEdited || force) {
                this.message = m;
            }
        },

        // AJAX SUBMISSION WITH NOTIFICATION.JS (ZERO PAGE RELOAD)
        async submitForm() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;

            const payload = {
                advisory_type: this.advisoryType,
                severity: this.severity,
                zone_id: this.selectedZoneId,
                sector_id: this.selectedSectorId,
                office_id: this.selectedBeatId,
                route_name: this.resolvedRouteCode,
                carriageway: this.carriagewayBound,
                start_km: this.startKm,
                end_km: this.endKm,
                start_landmark: this.startLandmark,
                end_landmark: this.endLandmark,
                visibility_meters: this.advisoryType === 'fog' ? this.visibilityMeters : null,
                speed_limit_kmh: this.speedLimit,
                closure_type: this.closureType,
                duration_hours: this.durationHours,
                title: this.title,
                message: this.message,
            };

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                const res = await fetch('{{ route("ops.travel-advisory.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await res.json();

                if (res.ok && data.success) {
                    if (window.showSuccess) {
                        window.showSuccess(data.message || 'Travel advisory broadcasted successfully across Humsafar Network.', 'Broadcast Active');
                    }
                    this.isCustomMessageEdited = false;
                    this.generateAdvisoryMessage(true);
                } else {
                    const errMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : 'Failed to publish advisory.');
                    if (window.showError) {
                        window.showError(errMsg, 'Submission Error');
                    } else {
                        alert(errMsg);
                    }
                }
            } catch (err) {
                console.error('Advisory submission error:', err);
                if (window.showError) {
                    window.showError('Network error connecting to central dispatch server.', 'Dispatch Failed');
                }
            } finally {
                this.isSubmitting = false;
            }
        }
    };
}
</script>
@endsection

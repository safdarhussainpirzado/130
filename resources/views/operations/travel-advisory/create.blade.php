@extends('layouts.app')

@section('title', 'Log Advisory / Fog - NHMP 130')
@section('page-title', 'Log Advisory / Fog')

@section('content')
@php
    $isBeat = ($operatorOffice?->type === 'beat');
    $isSector = ($operatorOffice?->type === 'sector' || $operatorTier === 'sector');
    $isZone = ($operatorOffice?->type === 'zone');

    $userOfficeId = $isBeat ? $operatorOffice->id : null;
    $userSectorId = $isBeat ? $operatorOffice->parent_id : ($isSector ? $operatorOffice->id : null);
    $userZoneId = $isBeat 
        ? $operatorOffice?->parent?->parent_id 
        : ($isSector ? $operatorOffice?->parent_id : ($isZone ? $operatorOffice->id : null));
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
    isSupervisor: {{ ($isSupervisor ?? false) ? 'true' : 'false' }},
    initialUserOfficeId: {{ $userOfficeId ? (int)$userOfficeId : 'null' }},
    initialUserSectorId: {{ $userSectorId ? (int)$userSectorId : 'null' }},
    initialUserZoneId: {{ $userZoneId ? (int)$userZoneId : 'null' }},
    zonesData: {{ Js::from($hierarchy['zones'] ?? []) }},
    sectorsData: {{ Js::from($hierarchy['sectors'] ?? []) }},
    beatsData: {{ Js::from($hierarchy['beats'] ?? []) }},
    roadsData: {{ Js::from($hierarchy['roads'] ?? []) }},
    carriagewaysData: {{ Js::from($carriageways ?? []) }},
    landmarksData: {{ Js::from($landmarks ?? []) }},
})" class="space-y-5 max-w-7xl mx-auto pb-12 pt-1" x-cloak>

    <!-- MAIN FORM: Clean Flow for Operational Staff -->
    <form @submit.prevent="submitForm()" id="rapid-advisory-form" class="space-y-5">
        @csrf

        <!-- SECTION 1: POLICE JURISDICTION & ROAD SECTION -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-blue-600 text-white font-black text-xs flex items-center justify-center shadow-sm">1</span>
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">1. Police Jurisdiction & Beat Selection</h2>
                        <p class="text-[11px] text-slate-500">Select your Zone, Sector, and the Beat(s) where this advisory applies</p>
                    </div>
                </div>

                <!-- Navigation Quick Actions -->
                <div class="flex items-center gap-2">
                    <a href="{{ route('ops.travel-advisory.index') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 bg-slate-50 border border-slate-200 hover:bg-slate-100 transition shadow-sm">
                        <i class="fa-solid fa-table-list text-blue-600"></i>
                        <span>Active Advisories</span>
                    </a>
                </div>
            </div>

            <!-- CHIPS ROW: Zone, Sector, Beats -->
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
                            <span x-show="tier === 'beat' || tier === 'sector' || tier === 'zone'">Assigned Zone</span>
                            <span x-show="tier === 'national'">Choose Zone</span>
                        </span>
                    </div>

                    <template x-if="tier === 'beat' || tier === 'sector' || tier === 'zone'">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-slate-900" x-text="selectedZoneName || 'Your Zone'"></span>
                            <i class="fa-solid fa-lock text-slate-400 text-xs" title="Locked to your assigned post"></i>
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
                            <span x-show="tier === 'beat' || tier === 'sector'">Assigned Sector</span>
                            <span x-show="tier === 'zone' || tier === 'national'">Choose Sector</span>
                        </span>
                    </div>

                    <template x-if="tier === 'beat' || tier === 'sector'">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-slate-900" x-text="selectedSectorName || 'Your Sector'"></span>
                            <i class="fa-solid fa-lock text-slate-400 text-xs" title="Locked to your assigned post"></i>
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

                <!-- BEAT CHIPS (MULTI-BEAT SELECTION SUPPORTED) -->
                <div class="p-3.5 rounded-2xl border transition-all"
                    :class="selectedBeatIds.length > 0 ? 'bg-gradient-to-br from-amber-50/60 to-slate-50 border-amber-300 shadow-sm' : 'bg-slate-50 border-slate-200'">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                            <i class="fa-solid fa-shield-halved text-amber-600"></i> Beat(s)
                        </span>
                        <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 border border-amber-200"
                            x-text="selectedBeatIds.length + ' Selected (Click to Toggle)'">
                        </span>
                    </div>

                    <!-- List of Sector Beats: Multi-Selection Chips -->
                    <div class="flex flex-wrap gap-1.5 mt-1 max-h-36 overflow-y-auto pt-1">
                        <template x-for="b in filteredBeats" :key="b.id">
                            <button type="button" @click="toggleBeat(b.id)"
                                class="px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                                :class="selectedBeatIds.includes(b.id) ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-white shadow-md shadow-amber-500/20 ring-2 ring-amber-400' : 'bg-white text-slate-700 border border-slate-200 hover:border-amber-400 hover:bg-amber-50/30'">
                                <i class="fa-solid fa-check text-[11px]" x-show="selectedBeatIds.includes(b.id)"></i>
                                <span x-text="b.name"></span>
                                <span class="text-[10px] font-mono opacity-80" x-show="b.km_min !== null" x-text="'(KM ' + Math.round(b.km_min) + '-' + Math.round(b.km_max) + ')'"></span>
                            </button>
                        </template>
                        <span x-show="filteredBeats.length === 0" class="text-xs text-slate-400 italic">Select a Sector to see Beats</span>
                    </div>
                </div>
            </div>

            <!-- TARGET CORRIDOR / ROAD SWITCHER -->
            <div class="mt-2 pt-4 border-t border-slate-100 space-y-2.5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                    <label class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-road text-amber-500"></i>
                        <span>Select Highway / Motorway</span>
                    </label>
                    <span class="text-xs text-slate-500">
                        Selected: <strong class="text-slate-900" x-text="resolvedRoadTitle"></strong>
                    </span>
                </div>

                <!-- QUICK HIGHWAY PILLS -->
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="r in quickCorridors" :key="r">
                        <button type="button" @click="selectRoad(r)"
                            class="px-3 py-1.5 rounded-xl text-xs font-black transition-all cursor-pointer font-mono"
                            :class="selectedRoadCode === r ? 'bg-slate-900 text-white shadow-md ring-2 ring-slate-400' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                            x-text="r">
                        </button>
                    </template>
                </div>
            </div>

            <!-- CARRIAGEWAY DIRECTION -->
            <div class="mt-2 pt-4 border-t border-slate-100 space-y-2.5">
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <label class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-arrows-split-up-and-left text-blue-600"></i>
                        <span>Carriageway Direction</span>
                    </label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button type="button" @click="setCarriageway('both')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                            :class="carriagewayBound === 'both' ? 'bg-blue-600 text-white shadow-md ring-2 ring-blue-400' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                            <i class="fa-solid fa-arrows-left-right text-xs"></i>
                            <span>Both Sides (Dual Carriageway)</span>
                        </button>
                        <button type="button" @click="setCarriageway('northbound')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                            :class="carriagewayBound === 'northbound' ? 'bg-blue-600 text-white shadow-md ring-2 ring-blue-400' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                            <i class="fa-solid fa-arrow-up text-xs"></i>
                            <span>Northbound (N)</span>
                        </button>
                        <button type="button" @click="setCarriageway('southbound')"
                            class="px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer"
                            :class="carriagewayBound === 'southbound' ? 'bg-blue-600 text-white shadow-md ring-2 ring-blue-400' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'">
                            <i class="fa-solid fa-arrow-down text-xs"></i>
                            <span>Southbound (S)</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- PROMINENT STARTING POINT & ENDING POINT OVERVIEW (HIGH VISIBILITY) -->
            <div class="mt-4 pt-4 border-t border-slate-100 space-y-3">
                <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-br from-slate-900 to-blue-950 text-white border border-slate-800 shadow-md space-y-4">
                    <div class="flex items-center justify-between flex-wrap gap-2 pb-2 border-b border-white/10">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-300">Selected Road Section & Coverage</span>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2.5 py-1 rounded-lg bg-amber-400 text-slate-950 font-black text-xs font-mono" x-text="resolvedRouteCode"></span>
                            <span class="text-xs font-semibold text-slate-200" x-text="selectedBeatNames"></span>
                            <span class="px-2 py-0.5 rounded bg-blue-500/30 text-blue-300 text-[11px] font-mono border border-blue-400/30" x-text="totalCoverageKm + ' KM Span'"></span>
                        </div>
                    </div>

                    <!-- HIGH CONTRAST START & END POINTS -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                        <!-- Starting Point Card -->
                        <div class="p-4 rounded-xl bg-white/10 backdrop-blur-md border border-emerald-500/30 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/40 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-play"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-400">Starting Point</div>
                                <div class="text-xl font-black text-white font-mono mt-0.5" x-text="'KM ' + (startKm !== null ? startKm : '0.00')"></div>
                                <div class="text-xs text-slate-200 font-semibold truncate mt-0.5" x-text="startLandmark || 'Starting Milestone'"></div>
                            </div>
                        </div>

                        <!-- Ending Point Card -->
                        <div class="p-4 rounded-xl bg-white/10 backdrop-blur-md border border-rose-500/30 flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-rose-500/20 text-rose-400 border border-rose-500/40 flex items-center justify-center text-lg shrink-0">
                                <i class="fa-solid fa-flag-checkered"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-[10px] font-extrabold uppercase tracking-wider text-rose-400">Ending Point</div>
                                <div class="text-xl font-black text-white font-mono mt-0.5" x-text="'KM ' + (endKm !== null ? endKm : '0.00')"></div>
                                <div class="text-xs text-slate-200 font-semibold truncate mt-0.5" x-text="endLandmark || 'Ending Milestone'"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FINE-TUNE MILESTONE INPUTS & QUICK LANDMARK CHIPS -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <!-- Start Location & KM -->
                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-200 space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="text-xs font-bold text-slate-800 flex items-center gap-1.5">
                                <i class="fa-solid fa-location-dot text-emerald-600"></i> Start Location / Milestone
                            </label>
                            <span class="text-[10px] text-slate-400 font-mono">From Post</span>
                        </div>

                        <!-- Quick Landmark Chips -->
                        <div class="flex flex-wrap gap-1 max-h-24 overflow-y-auto" x-show="combinedLandmarks.length > 0">
                            <template x-for="lm in combinedLandmarks" :key="'start-' + lm.id">
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

                        <!-- Quick Landmark Chips -->
                        <div class="flex flex-wrap gap-1 max-h-24 overflow-y-auto" x-show="combinedLandmarks.length > 0">
                            <template x-for="lm in combinedLandmarks" :key="'end-' + lm.id">
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
                                <input type="number" step="any" min="0" x-model.number="endKm" @input="onManualKmUpdate('end')" required
                                    class="w-full text-xs font-mono font-bold bg-white border border-slate-200 rounded-xl p-2 text-center focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: INCIDENT REASON & SEVERITY LEVEL -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200 shadow-sm space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 flex-wrap gap-2">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-slate-900 text-white font-black text-xs flex items-center justify-center shadow-sm">2</span>
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">2. Incident Reason & Severity Level</h2>
                        <p class="text-[11px] text-slate-500">Select why the road is affected and how urgently commuters need to be notified</p>
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
                        :class="severity === 'warning' ? 'bg-amber-500 text-slate-900 shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        Warning
                    </button>
                    <button type="button" @click="setSeverity('critical')"
                        class="px-2.5 py-1 rounded-lg text-xs font-extrabold transition cursor-pointer"
                        :class="severity === 'critical' ? 'bg-rose-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                        Critical
                    </button>
                </div>
            </div>

            <!-- INCIDENT CATEGORIES (PLAIN ENGLISH) -->
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <!-- Fog & Smog -->
                <button type="button" @click="setCategory('fog')"
                    class="p-3.5 rounded-2xl border text-left flex items-center gap-3 transition-all duration-150 group cursor-pointer"
                    :class="advisoryType === 'fog' ? 'border-amber-500 bg-amber-50/70 shadow-md shadow-amber-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'fog' ? 'bg-amber-500 text-white shadow-sm' : 'bg-amber-100 text-amber-600'">
                        <i class="fa-solid fa-smog"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Fog & Smog</div>
                        <div class="text-[10px] text-slate-500">Low visibility</div>
                    </div>
                </button>

                <!-- Road Closure -->
                <button type="button" @click="setCategory('road_closure')"
                    class="p-3.5 rounded-2xl border text-left flex items-center gap-3 transition-all duration-150 group cursor-pointer"
                    :class="advisoryType === 'road_closure' ? 'border-rose-500 bg-rose-50/70 shadow-md shadow-rose-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'road_closure' ? 'bg-rose-500 text-white shadow-sm' : 'bg-rose-100 text-rose-600'">
                        <i class="fa-solid fa-ban"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Road Closure</div>
                        <div class="text-[10px] text-slate-500">Blockage & detour</div>
                    </div>
                </button>

                <!-- Traffic Congestion -->
                <button type="button" @click="setCategory('traffic')"
                    class="p-3.5 rounded-2xl border text-left flex items-center gap-3 transition-all duration-150 group cursor-pointer"
                    :class="advisoryType === 'traffic' ? 'border-red-500 bg-red-50/70 shadow-md shadow-red-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'traffic' ? 'bg-red-500 text-white shadow-sm' : 'bg-red-100 text-red-600'">
                        <i class="fa-solid fa-traffic-light"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Traffic Jam</div>
                        <div class="text-[10px] text-slate-500">Slow movement</div>
                    </div>
                </button>

                <!-- Road Maintenance -->
                <button type="button" @click="setCategory('road_works')"
                    class="p-3.5 rounded-2xl border text-left flex items-center gap-3 transition-all duration-150 group cursor-pointer"
                    :class="advisoryType === 'road_works' ? 'border-orange-500 bg-orange-50/70 shadow-md shadow-orange-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'road_works' ? 'bg-orange-500 text-white shadow-sm' : 'bg-orange-100 text-orange-600'">
                        <i class="fa-solid fa-person-digging"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Road Works</div>
                        <div class="text-[10px] text-slate-500">Repairs & lane work</div>
                    </div>
                </button>

                <!-- Diversion -->
                <button type="button" @click="setCategory('diversion')"
                    class="p-3.5 rounded-2xl border text-left flex items-center gap-3 transition-all duration-150 group cursor-pointer"
                    :class="advisoryType === 'diversion' ? 'border-purple-500 bg-purple-50/70 shadow-md shadow-purple-500/10' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/70'">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center text-lg transition-transform group-hover:scale-105"
                        :class="advisoryType === 'diversion' ? 'bg-purple-500 text-white shadow-sm' : 'bg-purple-100 text-purple-600'">
                        <i class="fa-solid fa-signs-post"></i>
                    </span>
                    <div>
                        <div class="text-xs font-black text-slate-900">Diversion</div>
                        <div class="text-[10px] text-slate-500">Alternate route</div>
                    </div>
                </button>

                <!-- Rain & Bad Weather -->
                <button type="button" @click="setCategory('weather')"
                    class="p-3.5 rounded-2xl border text-left flex items-center gap-3 transition-all duration-150 group cursor-pointer"
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

            <!-- FOG SETTINGS -->
            <div x-show="advisoryType === 'fog'" x-transition class="p-4 rounded-2xl bg-amber-50/80 border border-amber-200/80 space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-amber-900 flex items-center gap-2">
                        <i class="fa-solid fa-eye-slash text-amber-600"></i> Fog Visibility Range (Select Preset Distance or Adjust Slider)
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
                        <div class="text-[10px] opacity-80">Heavy Caution</div>
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
                        <div class="text-xs font-black">500m+ (Light Haze)</div>
                        <div class="text-[10px] opacity-80">Patchy Smog</div>
                    </button>
                </div>

                <!-- Range Slider -->
                <input type="range" min="5" max="1000" step="5" x-model.number="visibilityMeters" @input="onManualInputUpdate()" class="w-full accent-amber-600 cursor-pointer">

                <!-- Speed Guidance Chips for Fog -->
                <div>
                    <label class="text-[11px] font-bold uppercase tracking-wider text-amber-900 block mb-1.5">Recommended Speed Limit:</label>
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
                            0 km/h (Full Closure)
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
                        <i class="fa-solid fa-road text-sm mb-1"></i>
                        <div class="text-xs font-bold">All Lanes Open</div>
                        <div class="text-[10px] opacity-75">Caution only</div>
                    </button>
                    <button type="button" @click="setClosureType('lane_1')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'lane_1' ? 'bg-orange-600 text-white border-orange-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <i class="fa-solid fa-arrow-left text-sm mb-1"></i>
                        <div class="text-xs font-bold">Lane 1 Closed</div>
                        <div class="text-[10px] opacity-75">Slow lane</div>
                    </button>
                    <button type="button" @click="setClosureType('lane_2')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'lane_2' ? 'bg-orange-600 text-white border-orange-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <i class="fa-solid fa-arrows-left-right text-sm mb-1"></i>
                        <div class="text-xs font-bold">Lane 2 Closed</div>
                        <div class="text-[10px] opacity-75">Center lane</div>
                    </button>
                    <button type="button" @click="setClosureType('shoulder')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'shoulder' ? 'bg-amber-600 text-white border-amber-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <i class="fa-solid fa-triangle-exclamation text-sm mb-1"></i>
                        <div class="text-xs font-bold">Shoulder Closed</div>
                        <div class="text-[10px] opacity-75">Emergency lane</div>
                    </button>
                    <button type="button" @click="setClosureType('diversion')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'diversion' ? 'bg-purple-600 text-white border-purple-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <i class="fa-solid fa-signs-post text-sm mb-1"></i>
                        <div class="text-xs font-bold">Diversion Active</div>
                        <div class="text-[10px] opacity-75">Alternate route</div>
                    </button>
                    <button type="button" @click="setClosureType('full')"
                        class="p-2.5 rounded-xl border text-center transition cursor-pointer"
                        :class="closureType === 'full' ? 'bg-rose-600 text-white border-rose-600 shadow-sm' : 'bg-white text-slate-700 border-slate-200 hover:border-slate-300'">
                        <i class="fa-solid fa-road-barrier text-sm mb-1"></i>
                        <div class="text-xs font-bold">Full Closure</div>
                        <div class="text-[10px] opacity-75">All traffic stopped</div>
                    </button>
                </div>
            </div>
        </div>

        <!-- SECTION 3: PUBLIC ADVISORY MESSAGE & DETAILS -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white font-black text-xs flex items-center justify-center shadow-sm">3</span>
                    <div>
                        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">3. Public Advisory Message & Details</h2>
                        <p class="text-[11px] text-slate-500">Auto-generated message for commuters and public travel portals. You can edit this text anytime.</p>
                    </div>
                </div>
                <button type="button" @click="resetToAutoGenerated()"
                    class="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-700 bg-slate-50 border border-slate-200 hover:bg-slate-100 transition flex items-center gap-1.5 cursor-pointer self-start sm:self-auto">
                    <i class="fa-solid fa-rotate text-blue-600"></i>
                    <span>Reset to Auto-Generated Message</span>
                </button>
            </div>

            <!-- Title -->
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-800 flex items-center justify-between">
                    <span>Advisory Headline / Title <span class="text-rose-500">*</span></span>
                    <span class="text-[10px] text-slate-400 font-mono" x-text="(title || '').length + ' / 150'"></span>
                </label>
                <input type="text" x-model="title" required maxlength="150"
                    placeholder="e.g. Dense Fog Alert - Visibility Below 35m (M-2)"
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 font-bold text-slate-900 focus:bg-white focus:ring-2 focus:ring-blue-500">
            </div>

            <!-- Public Advisory Message Body -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <label class="text-xs font-bold text-slate-800">
                        Advisory Details for Commuters <span class="text-rose-500">*</span>
                    </label>
                    <span class="text-[11px] font-bold text-emerald-600 flex items-center gap-1" x-show="!isCustomMessageEdited">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-synced with selections
                    </span>
                    <span class="text-[11px] font-bold text-amber-600 flex items-center gap-1" x-show="isCustomMessageEdited">
                        <i class="fa-solid fa-pen"></i> Custom edited
                    </span>
                </div>
                <textarea x-model="message" @input="isCustomMessageEdited = true" rows="3" required maxlength="1000"
                    placeholder="Enter full advisory details for public commuters..."
                    class="w-full text-xs bg-slate-50 border border-slate-200 rounded-xl p-3 font-normal text-slate-800 leading-relaxed focus:bg-white focus:ring-2 focus:ring-blue-500"></textarea>
            </div>

            <!-- Advisory Active Duration -->
            <div class="pt-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-slate-700">Expected Duration:</span>
                    <div class="flex items-center gap-1.5">
                        <template x-for="h in [1, 2, 4, 6, 12, 24]" :key="h">
                            <button type="button" @click="setDuration(h)"
                                class="px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer"
                                :class="durationHours === h ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                x-text="h + 'h'">
                            </button>
                        </template>
                    </div>
                </div>
                <span class="text-xs text-slate-500 font-mono" x-text="'Valid until: ' + computedExpiryText"></span>
            </div>
        </div>

        <!-- SECTION 4: PUBLIC NOTICE PREVIEW & SUBMISSION -->
        <div class="rounded-3xl p-5 sm:p-6 bg-gradient-to-br from-slate-900 via-slate-850 to-blue-950 text-white border border-slate-800 shadow-xl space-y-5">
            <div class="flex items-center justify-between pb-3 border-b border-white/10 flex-wrap gap-2">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-blue-500/20 text-blue-400 border border-blue-500/30 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-eye"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-black text-white uppercase tracking-wider">4. Public Notice Preview (How Commuters See This)</h3>
                        <p class="text-[11px] text-slate-400">Live preview as displayed on the 130 Humsafar App and NHMP website</p>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-[11px] font-mono border border-emerald-500/30 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Broadcast Preview
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

            <!-- SUPERVISOR DIRECT PUBLISH TOGGLE (IF USER IS SUPERVISOR) -->
            <template x-if="isSupervisor">
                <div class="p-3.5 bg-blue-900/40 rounded-2xl border border-blue-500/30 flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-tower-broadcast text-blue-400 text-base"></i>
                        <div>
                            <span class="text-xs font-black text-white block">Publish Live Immediately (Skip Approval Queue)</span>
                            <span class="text-[11px] text-blue-200">As a Call Center Supervisor, you can directly broadcast this advisory live across public APIs.</span>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" x-model="autoApprove" class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                    </label>
                </div>
            </template>

            <!-- SUBMIT BUTTON -->
            <div class="space-y-2">
                <button type="submit" :disabled="isSubmitting"
                    class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-blue-600 via-sky-600 to-blue-700 hover:from-blue-500 hover:to-sky-500 text-white font-black text-sm tracking-wide shadow-xl shadow-blue-500/30 transition-all duration-150 transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-2.5 cursor-pointer disabled:opacity-60">
                    <i class="fa-solid" :class="isSubmitting ? 'fa-spinner fa-spin' : (isSupervisor && autoApprove ? 'fa-tower-broadcast animate-pulse' : 'fa-paper-plane')"></i>
                    <span x-text="isSubmitting ? 'Dispatching Travel Advisory...' : (isSupervisor && autoApprove ? 'Approve & Broadcast Advisory Live' : 'Submit Travel Advisory for Supervisor Approval')"></span>
                </button>

                <p class="text-center text-[11px] text-slate-400" x-show="!isSupervisor || !autoApprove">
                    <i class="fa-solid fa-circle-info text-amber-400 mr-1"></i>
                    Advisory will be queued for Call Center Agent Supervisor review and approval before going live on public apps and portals.
                </p>
            </div>
        </div>
    </form>
</div>

<!-- Alpine Logic Component -->
<script>
const SECTOR_TO_ROAD_MAP = {
    13: 'M-1',
    14: 'M-2',
    15: 'E-35',
    16: 'M-16',
    17: 'M-14',
    18: 'M-2',
    19: 'M-3',
    20: 'M-11',
    21: 'M-4',
    22: 'M-4',
    23: 'M-5',
    24: 'M-5',
    25: 'N-5',
    26: 'N-5',
    27: 'N-5',
    28: 'N-75',
    29: 'N-5',
    30: 'N-5',
    31: 'N-5',
    32: 'N-5',
    33: 'N-5',
    34: 'N-5',
    35: 'N-55',
    36: 'N-25',
    37: 'N-25',
    38: 'N-10',
    39: 'N-50',
    40: 'N-55',
    41: 'S-1',
    42: 'M-9',
};

function rapidTravelAdvisoryHub(config) {
    return {
        tier: config.tier || 'beat',
        isSupervisor: config.isSupervisor || false,
        autoApprove: false,
        zonesList: config.zonesData || [],
        sectorsList: config.sectorsData || [],
        beatsList: config.beatsData || [],
        roadsList: config.roadsData || [],
        carriagewaysList: config.carriagewaysData || [],
        landmarksMap: config.landmarksData || {},

        selectedRoadCode: 'M-2',
        quickCorridors: ['M-1', 'M-2', 'M-3', 'M-4', 'M-5', 'M-9', 'M-11', 'M-14', 'M-16', 'N-5', 'E-35', 'N-55', 'N-25', 'LEW'],

        selectedZoneId: config.initialUserZoneId || '',
        selectedSectorId: config.initialUserSectorId || '',
        selectedBeatIds: config.initialUserOfficeId ? [config.initialUserOfficeId] : [],

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
            // Priority 1: If operator has an assigned sector, initialize that road
            if (this.selectedSectorId && SECTOR_TO_ROAD_MAP[this.selectedSectorId]) {
                this.selectedRoadCode = SECTOR_TO_ROAD_MAP[this.selectedSectorId];
            } else if (this.selectedBeatIds.length > 0) {
                const b = this.beatsList.find(x => x.id == this.selectedBeatIds[0]);
                if (b && (b.sector_id || b.parent_id)) {
                    const secId = b.sector_id || b.parent_id;
                    if (SECTOR_TO_ROAD_MAP[secId]) {
                        this.selectedRoadCode = SECTOR_TO_ROAD_MAP[secId];
                    }
                }
            }

            // Priority 2: If user has assigned beat, load it
            if (this.selectedBeatIds.length > 0) {
                const b = this.beatsList.find(x => x.id == this.selectedBeatIds[0]);
                if (b) {
                    if (b.sector_id || b.parent_id) this.selectedSectorId = b.sector_id || b.parent_id;
                    if (b.zone_id) this.selectedZoneId = b.zone_id;
                }
                this.recalculateSpanFromSelectedBeats();
            } else if (this.selectedSectorId) {
                this.selectSector(this.selectedSectorId, false);
            } else if (this.zonesList.length > 0) {
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

        get selectedBeats() {
            return this.beatsList
                .filter(b => this.selectedBeatIds.includes(b.id))
                .sort((a, b) => (parseFloat(a.km_min) || 0) - (parseFloat(b.km_min) || 0));
        },

        get selectedBeatNames() {
            if (this.selectedBeats.length === 0) return 'No Beat Selected';
            if (this.selectedBeats.length === 1) return this.selectedBeats[0].name;
            if (this.selectedBeats.length === 2) return `${this.selectedBeats[0].name} & ${this.selectedBeats[1].name}`;
            return `${this.selectedBeats.map(b => b.name).join(', ')}`;
        },

        get selectedBeatKmSpanText() {
            if (this.selectedBeats.length === 0) return `KM ${this.startKm} - ${this.endKm}`;
            const minKm = Math.min(...this.selectedBeats.map(b => (b.km_min !== null ? parseFloat(b.km_min) : 0)));
            const maxKm = Math.max(...this.selectedBeats.map(b => (b.km_max !== null ? parseFloat(b.km_max) : 0)));
            return `KM ${minKm.toFixed(2)} - ${maxKm.toFixed(2)}`;
        },

        get totalCoverageKm() {
            const diff = Math.abs(parseFloat(this.endKm || 0) - parseFloat(this.startKm || 0));
            return diff.toFixed(1);
        },

        get combinedLandmarks() {
            const list = [];
            const seen = new Set();
            for (const b of this.selectedBeats) {
                const lms = this.landmarksMap[b.id] || [];
                for (const lm of lms) {
                    if (!seen.has(lm.id)) {
                        seen.add(lm.id);
                        list.push(lm);
                    }
                }
            }
            return list.sort((a, b) => (parseFloat(a.km_numeric) || 0) - (parseFloat(b.km_numeric) || 0));
        },

        get resolvedRouteCode() {
            return this.selectedRoadCode || 'M-2';
        },

        get resolvedRoadTitle() {
            const code = this.resolvedRouteCode;
            const clean = code.replace('-', '');
            const cw = this.carriagewaysList.find(x => (x.road_short === code || x.road === clean || x.road === code));
            if (cw) return `${cw.road_name || cw.road} (${cw.road_from || ''} - ${cw.road_to || ''})`;
            return `${code} Corridor`;
        },

        get computedExpiryText() {
            const now = new Date();
            now.setMinutes(now.getMinutes() + Math.round(this.durationHours * 60));
            return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) + ' (' + this.durationHours + 'h duration)';
        },

        selectZone(id) {
            this.selectedZoneId = id;
            const availableSectors = this.sectorsList.filter(s => (s.zone_id == id || s.parent_id == id));
            if (availableSectors.length > 0) {
                this.selectSector(availableSectors[0].id);
            } else {
                this.selectedSectorId = '';
                this.selectedBeatIds = [];
            }
        },

        selectSector(id, updateRoad = true) {
            this.selectedSectorId = id;
            const sec = this.sectorsList.find(x => x.id == id);
            if (sec && (sec.zone_id || sec.parent_id)) {
                this.selectedZoneId = sec.zone_id || sec.parent_id;
            }

            if (updateRoad && SECTOR_TO_ROAD_MAP[id]) {
                this.selectedRoadCode = SECTOR_TO_ROAD_MAP[id];
            }

            const availableBeats = this.beatsList.filter(b => (b.sector_id == id || b.parent_id == id));
            if (availableBeats.length > 0) {
                this.selectedBeatIds = [availableBeats[0].id];
                this.recalculateSpanFromSelectedBeats();
            } else {
                this.selectedBeatIds = [];
                if (sec && sec.km_min !== null && sec.km_min !== undefined) {
                    this.startKm = parseFloat(sec.km_min);
                    this.endKm = parseFloat(sec.km_max || (sec.km_min + 30));
                    this.startLandmark = `${sec.name} Start`;
                    this.endLandmark = `${sec.name} End`;
                }
            }
            this.generateAdvisoryMessage();
        },

        toggleBeat(id) {
            const idx = this.selectedBeatIds.indexOf(id);
            if (idx > -1) {
                if (this.selectedBeatIds.length > 1) {
                    this.selectedBeatIds.splice(idx, 1);
                }
            } else {
                this.selectedBeatIds.push(id);
            }
            this.recalculateSpanFromSelectedBeats();
            this.generateAdvisoryMessage();
        },

        recalculateSpanFromSelectedBeats() {
            if (this.selectedBeats.length === 0) return;

            const minKm = Math.min(...this.selectedBeats.map(b => (b.km_min !== null ? parseFloat(b.km_min) : 0)));
            const maxKm = Math.max(...this.selectedBeats.map(b => (b.km_max !== null ? parseFloat(b.km_max) : 0)));

            this.startKm = minKm;
            this.endKm = maxKm;

            const firstBeat = this.selectedBeats[0];
            const lastBeat = this.selectedBeats[this.selectedBeats.length - 1];

            const firstLms = this.landmarksMap[firstBeat.id] || [];
            const lastLms = this.landmarksMap[lastBeat.id] || [];

            this.startLandmark = firstLms.length > 0 ? firstLms[0].location_name : `${firstBeat.name} Start`;
            this.endLandmark = lastLms.length > 0 ? lastLms[lastLms.length - 1].location_name : `${lastBeat.name} End`;
        },

        selectRoad(code) {
            this.selectedRoadCode = code;
            this.generateAdvisoryMessage();
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
            } else if (type === 'traffic') {
                this.severity = 'warning';
                this.speedLimit = 40;
            } else if (type === 'weather') {
                this.severity = 'warning';
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

        // DYNAMIC ADVISORY SYNTHESIS (PLAIN ENGLISH FOR LAYMEN)
        generateAdvisoryMessage(force = false) {
            const route = this.resolvedRouteCode;
            const boundText = this.carriagewayBound === 'both' ? 'Both Directions' : (this.carriagewayBound === 'northbound' ? 'Northbound (N)' : 'Southbound (S)');

            // Multi-beat representation
            let beatText = '';
            if (this.selectedBeats.length === 1) {
                beatText = `under ${this.selectedBeats[0].name}`;
            } else if (this.selectedBeats.length === 2) {
                beatText = `across ${this.selectedBeats[0].name} and ${this.selectedBeats[1].name}`;
            } else if (this.selectedBeats.length > 2) {
                const names = this.selectedBeats.map(b => b.name);
                const last = names.pop();
                beatText = `across ${names.join(', ')} and ${last}`;
            } else {
                beatText = `in Sector ${this.selectedSectorName}`;
            }

            const startLoc = this.startLandmark || `KM ${this.startKm}`;
            const endLoc = this.endLandmark || `KM ${this.endKm}`;

            let t = '';
            let m = '';

            if (this.advisoryType === 'fog') {
                if (this.visibilityMeters <= 35) {
                    t = `Dense Fog Alert - Visibility Below 35m (${route})`;
                    m = `Dense fog reported on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) ${beatText}. Atmospheric visibility has critically dropped below ${this.visibilityMeters}m. Commuters are advised to slow down to ${this.speedLimit} km/h, use fog lights, and follow NHMP convoy piloting. Expected duration: ${this.durationHours} hours.`;
                } else if (this.visibilityMeters <= 100) {
                    t = `Moderate Fog Advisory - Visibility ${this.visibilityMeters}m (${route})`;
                    m = `Moderate fog patches reported on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) ${beatText}. Visibility is restricted to ${this.visibilityMeters}m. Please maintain a safe distance, keep headlights on low beam, and drive within ${this.speedLimit} km/h. Valid for ${this.durationHours} hours.`;
                } else {
                    t = `Patchy Haze & Smog Advisory (${route})`;
                    m = `Light fog and smog haze observed along ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) ${beatText}. Visibility is around ${this.visibilityMeters}m. Road surface is clear, please drive with caution.`;
                }
            } else if (this.advisoryType === 'road_closure') {
                t = `Emergency Road Closure on ${route}`;
                m = `Road closure in effect on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) ${beatText}. All traffic is temporarily stopped for commuter safety. Please follow NHMP patrol instructions on site. Clearance expected in ${this.durationHours} hours.`;
            } else if (this.advisoryType === 'diversion') {
                t = `Traffic Diversion Active on ${route}`;
                m = `Traffic diversion in effect on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) ${beatText}. Commuters are being safely rerouted through alternate routes. Please follow NHMP patrol signboards and on-site instructions. Expected duration: ${this.durationHours} hours.`;
            } else if (this.advisoryType === 'road_works') {
                const laneText = this.closureType ? this.closureType.replace('_', ' ') : 'Slow lane';
                t = `Road Maintenance & Lane Restriction (${route})`;
                m = `Road maintenance in progress on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) ${beatText}. ${laneText} restricted. Advisory speed limit: ${this.speedLimit} km/h. Please slow down and observe work zone signage.`;
            } else if (this.advisoryType === 'traffic') {
                t = `Traffic Congestion / Queue Alert (${route})`;
                m = `Heavy traffic congestion and slow-moving vehicles reported on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) ${beatText}. NHMP ground patrols are on site managing the flow. Commuters should expect minor delays for the next ${this.durationHours} hours.`;
            } else if (this.advisoryType === 'weather') {
                t = `Rain & Slippery Road Advisory (${route})`;
                m = `Rainfall and slippery tarmac reported on ${route} (${boundText}) between ${startLoc} and ${endLoc} (KM ${this.startKm} to ${this.endKm}) ${beatText}. Commuters are advised to reduce speed, avoid sudden braking, and maintain a safe following distance.`;
            }

            this.title = t;
            if (!this.isCustomMessageEdited || force) {
                this.message = m;
            }
        },

        // AJAX SUBMISSION WITH NOTIFICATION.JS (ZERO FULL-PAGE RELOAD)
        async submitForm() {
            if (this.isSubmitting) return;
            this.isSubmitting = true;

            const primaryBeatId = this.selectedBeatIds.length > 0 ? this.selectedBeatIds[0] : null;

            const payload = {
                category: this.advisoryType,
                advisory_type: this.advisoryType,
                severity: this.severity,
                zone_id: this.selectedZoneId,
                sector_id: this.selectedSectorId,
                office_id: primaryBeatId,
                beat_ids: this.selectedBeatIds,
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
                auto_approve: this.isSupervisor && this.autoApprove,
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
                        window.showSuccess(data.message || 'Travel advisory submitted successfully.', 'Submission Success');
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

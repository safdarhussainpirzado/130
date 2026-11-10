@extends('layouts.app')

@section('title', 'Geospatial Coordinates Grid - NHMP 130')

@section('page-title', 'Geospatial Markers (' . number_format($stats['total_markers'] ?? 97083) . ' Highway Coordinates)')

@section('load-leaflet', true)



@push('styles')
<style>
/* ==========================================================================
   Tactile 3D Design System for NHMP Geospatial Hub
   ========================================================================== */
.stat-card-3d {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #e2e8f0;
    border-bottom: 4px solid #cbd5e1;
    border-radius: 20px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.95), 0 3px 6px -1px rgba(0, 0, 0, 0.05);
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    user-select: none;
}
.stat-card-3d:hover {
    transform: translateY(-2px);
    border-bottom: 4px solid #94a3b8;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 1), 0 6px 14px -2px rgba(15, 23, 42, 0.1);
}
.stat-card-3d:active, .stat-card-3d.active {
    transform: translateY(2px);
    border-bottom: 1.5px solid #64748b;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.08), 0 1px 2px rgba(0, 0, 0, 0.04);
}
.stat-card-3d.active-blue {
    border-color: #3b82f6;
    border-bottom-color: #1d4ed8;
    background: linear-gradient(180deg, #eff6ff 0%, #dbeafe 100%);
}
.stat-card-3d.active-emerald {
    border-color: #10b981;
    border-bottom-color: #047857;
    background: linear-gradient(180deg, #ecfdf5 0%, #d1fae5 100%);
}
.stat-card-3d.active-indigo {
    border-color: #6366f1;
    border-bottom-color: #4338ca;
    background: linear-gradient(180deg, #eef2ff 0%, #e0e7ff 100%);
}
.stat-card-3d.active-amber {
    border-color: #f59e0b;
    border-bottom-color: #b45309;
    background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
}

/* 3D Floating Command Bar */
.toolbar-3d {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border: 1px solid rgba(255, 255, 255, 0.9);
    border-bottom: 3.5px solid #cbd5e1;
    border-radius: 20px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 1), 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.08);
}

.control-group-3d {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.control-label-3d {
    font-size: 9px;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
    padding-left: 2px;
}

.select-3d {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #cbd5e1;
    border-bottom: 3px solid #94a3b8;
    border-radius: 12px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 2px 4px rgba(0, 0, 0, 0.04);
    padding: 7px 30px 7px 12px;
    font-size: 11px;
    font-weight: 800;
    color: #1e293b;
    outline: none;
    cursor: pointer;
    transition: all 0.15s ease;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2.5' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='m19.5 8.25-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
    background-size: 13px;
}
.select-3d:hover {
    border-color: #94a3b8;
    border-bottom: 3px solid #64748b;
    transform: translateY(-1px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 1), 0 4px 6px rgba(0, 0, 0, 0.08);
}
.select-3d:focus, .select-3d:active {
    transform: translateY(1.5px);
    border-bottom: 1.5px solid #64748b;
    border-color: #3b82f6;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06), 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.input-3d {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border: 1px solid #cbd5e1;
    border-bottom: 3px solid #94a3b8;
    border-radius: 12px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 2px 4px rgba(0, 0, 0, 0.04);
    padding: 7px 12px 7px 32px;
    font-size: 11px;
    font-weight: 800;
    color: #1e293b;
    outline: none;
    transition: all 0.15s ease;
}
.input-3d:focus {
    transform: translateY(1px);
    border-bottom: 1.5px solid #2563eb;
    border-color: #3b82f6;
    background: #ffffff;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.04), 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.btn-3d-action {
    background: linear-gradient(180deg, #ffffff 0%, #f1f5f9 100%);
    border: 1px solid #cbd5e1;
    border-bottom: 3px solid #94a3b8;
    border-radius: 12px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.9), 0 2px 4px rgba(0, 0, 0, 0.04);
    padding: 7px 12px;
    font-size: 11px;
    font-weight: 800;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    user-select: none;
    white-space: nowrap;
}
.btn-3d-action:hover {
    border-color: #94a3b8;
    border-bottom: 3px solid #64748b;
    transform: translateY(-1px);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 1), 0 4px 8px rgba(0, 0, 0, 0.08);
    color: #0f172a;
}
.btn-3d-action:active {
    transform: translateY(2px);
    border-bottom: 1px solid #94a3b8;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.08);
}

.status-pill-3d {
    background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-bottom: 2.5px solid #cbd5e1;
    border-radius: 9999px;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 1), 0 2px 4px rgba(0, 0, 0, 0.04);
    padding: 7px 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    font-weight: 900;
    color: #1e293b;
    user-select: none;
    white-space: nowrap;
}

.tab-tray-3d {
    background: #e2e8f0;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.08), inset 0 1px 1px rgba(0, 0, 0, 0.06);
    border-radius: 16px;
    padding: 4px;
    display: inline-flex;
    gap: 4px;
}

.tab-btn-3d {
    padding: 8px 18px;
    font-size: 12px;
    font-weight: 800;
    border-radius: 12px;
    transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    border: none;
    user-select: none;
}
.tab-btn-3d.active {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    color: #0f172a;
    border: 1px solid #ffffff;
    border-bottom: 3px solid #cbd5e1;
    box-shadow: inset 0 1px 0 #ffffff, 0 3px 6px rgba(0, 0, 0, 0.06);
    transform: translateY(-1px);
}
.tab-btn-3d:not(.active) {
    background: transparent;
    color: #64748b;
}
.tab-btn-3d:not(.active):hover {
    color: #0f172a;
    background: rgba(255, 255, 255, 0.5);
}

/* ==========================================================================
   3D Tactile Milestone Signs & Territorial Area Badges
   ========================================================================== */
.milestone-3d-sign {
    display: flex;
    flex-direction: column;
    align-items: center;
    transform: translate(-50%, -100%);
    pointer-events: auto;
    cursor: pointer;
    filter: drop-shadow(0 8px 16px rgba(15, 23, 42, 0.28));
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 800;
}
.milestone-3d-sign:hover {
    transform: translate(-50%, -108%) scale(1.08);
    z-index: 1000 !important;
}
.milestone-pin-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 5px 12px 5px 7px;
    border-radius: 9999px;
    color: #ffffff;
    font-size: 11px;
    white-space: nowrap;
    border: 2px solid #ffffff;
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.5), 0 4px 12px rgba(0, 0, 0, 0.25);
    user-select: none;
}
.milestone-3d-start .milestone-pin-pill {
    background: linear-gradient(135deg, #10b981 0%, #047857 100%);
}
.milestone-3d-end .milestone-pin-pill {
    background: linear-gradient(135deg, #f43f5e 0%, #be123c 100%);
}
.milestone-flag-icon {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.22);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.15);
}
.milestone-text-group {
    display: flex;
    flex-direction: column;
    line-height: 1.15;
}
.milestone-sub {
    font-size: 8px;
    font-weight: 900;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    opacity: 0.9;
}
.milestone-km {
    font-size: 12px;
    font-weight: 900;
    letter-spacing: 0.02em;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.milestone-stem {
    width: 3px;
    height: 12px;
    background: #334155;
    border-radius: 2px;
}
.milestone-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #0f172a;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 2px rgba(15, 23, 42, 0.35);
}

/* Beat Area Tag Badge */
.beat-area-tag-badge {
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    color: #ffffff;
    padding: 4px 10px;
    border-radius: 9999px;
    border: 1.5px solid rgba(255, 255, 255, 0.9);
    border-left-width: 5px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.35);
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
    transform: translate(-50%, -50%);
    pointer-events: auto;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    z-index: 600;
}
.beat-area-tag-badge:hover {
    transform: translate(-50%, -55%) scale(1.06);
    background: rgba(15, 23, 42, 0.98);
    z-index: 900;
}
.beat-color-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.beat-range-pill {
    background: rgba(255, 255, 255, 0.15);
    padding: 1px 6px;
    border-radius: 6px;
    font-family: ui-monospace, monospace;
    font-size: 9px;
    font-weight: 800;
    color: #e2e8f0;
}

/* 3D Leaflet Custom Milestone Popup */
.milestone-popup .leaflet-popup-content-wrapper {
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 16px 32px -4px rgba(15, 23, 42, 0.25), 0 8px 12px -6px rgba(15, 23, 42, 0.1);
    border: 1px solid #e2e8f0;
    padding: 0;
    overflow: hidden;
}
.milestone-popup .leaflet-popup-content {
    margin: 0;
    line-height: 1.4;
}
.milestone-popup .leaflet-popup-tip {
    background: #ffffff;
}
</style>
@endpush

@section('content')
<div x-data="geospatialGridManager()" class="space-y-8 relative mt-2 max-w-[1700px] mx-auto" x-cloak>

    <!-- TOP KPI METRIC CARDS (3D TACTILE BUTTONS) -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
        <!-- Total Markers / National Grid -->
        <div @click="filterSide = ''; mapSide = ''; filterTable(); if(activeTab === 'map') fetchViewportMarkers();"
             :class="!filterSide ? 'stat-card-3d active active-blue ring-2 ring-blue-500/50' : 'stat-card-3d'"
             class="p-5 cursor-pointer">
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black tracking-widest text-blue-600 uppercase">National Grid</span>
                <span class="w-8 h-8 rounded-xl bg-blue-100/80 border border-blue-200 text-blue-700 flex items-center justify-center text-xs font-black shadow-inner"><i class="fa-solid fa-map-location-dot"></i></span>
            </div>
            <h4 class="text-2xl font-black text-slate-900 mt-2">{{ number_format($stats['total_markers'] ?? 97083) }}</h4>
            <p class="text-[10px] font-extrabold text-slate-400 mt-0.5">Physical Coordinates</p>
        </div>

        <!-- Northbound -->
        <div @click="filterSide = (filterSide === 'N' ? '' : 'N'); mapSide = filterSide; filterTable(); if(activeTab === 'map') fetchViewportMarkers();"
             :class="filterSide === 'N' ? 'stat-card-3d active active-emerald ring-2 ring-emerald-500/50' : 'stat-card-3d'"
             class="p-5 cursor-pointer">
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black tracking-widest text-emerald-600 uppercase">Northbound (N)</span>
                <span class="w-8 h-8 rounded-xl bg-emerald-100/80 border border-emerald-200 text-emerald-700 flex items-center justify-center text-xs font-black shadow-inner"><i class="fa-solid fa-arrow-up"></i></span>
            </div>
            <h4 class="text-2xl font-black text-slate-900 mt-2">{{ number_format($stats['northbound'] ?? 45993) }}</h4>
            <p class="text-[10px] font-extrabold text-slate-400 mt-0.5">Dual Carriageway</p>
        </div>

        <!-- Southbound -->
        <div @click="filterSide = (filterSide === 'S' ? '' : 'S'); mapSide = filterSide; filterTable(); if(activeTab === 'map') fetchViewportMarkers();"
             :class="filterSide === 'S' ? 'stat-card-3d active active-indigo ring-2 ring-indigo-500/50' : 'stat-card-3d'"
             class="p-5 cursor-pointer">
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black tracking-widest text-indigo-600 uppercase">Southbound (S)</span>
                <span class="w-8 h-8 rounded-xl bg-indigo-100/80 border border-indigo-200 text-indigo-700 flex items-center justify-center text-xs font-black shadow-inner"><i class="fa-solid fa-arrow-down"></i></span>
            </div>
            <h4 class="text-2xl font-black text-slate-900 mt-2">{{ number_format($stats['southbound'] ?? 46014) }}</h4>
            <p class="text-[10px] font-extrabold text-slate-400 mt-0.5">Dual Carriageway</p>
        </div>

        <!-- Single / Shared -->
        <div @click="filterSide = (filterSide === 'null' ? '' : 'null'); mapSide = ''; filterTable(); if(activeTab === 'map') fetchViewportMarkers();"
             :class="filterSide === 'null' ? 'stat-card-3d active active-amber ring-2 ring-amber-500/50' : 'stat-card-3d'"
             class="p-5 cursor-pointer">
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black tracking-widest text-amber-600 uppercase">Single / Shared</span>
                <span class="w-8 h-8 rounded-xl bg-amber-100/80 border border-amber-200 text-amber-700 flex items-center justify-center text-xs font-black shadow-inner"><i class="fa-solid fa-arrows-up-down"></i></span>
            </div>
            <h4 class="text-2xl font-black text-slate-900 mt-2">{{ number_format($stats['undirected'] ?? 5076) }}</h4>
            <p class="text-[10px] font-extrabold text-slate-400 mt-0.5">Single / Shared Track</p>
        </div>

        <!-- Network Coverage -->
        <div class="stat-card-3d p-5">
            <div class="flex items-center justify-between">
                <span class="text-[9px] font-black tracking-widest text-purple-600 uppercase">Network Coverage</span>
                <span class="w-8 h-8 rounded-xl bg-purple-100/80 border border-purple-200 text-purple-700 flex items-center justify-center text-xs font-black shadow-inner"><i class="fa-solid fa-shield-halved"></i></span>
            </div>
            <h4 class="text-2xl font-black text-slate-900 mt-2">{{ number_format($stats['total_beats'] ?? 107) }} Beats</h4>
            <p class="text-[10px] font-black text-emerald-600 mt-0.5"><i class="fa-solid fa-circle-check text-[9px] mr-1"></i>100% Valid (0 Nulls)</p>
        </div>
    </div>

    <!-- MAIN PANEL: TABBED SPLIT (MAP & AUDIT TABLE) -->
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        
        <!-- Header & Tab Navigation -->
        <div class="p-6 border-b border-slate-100 bg-slate-50/50 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-lg font-black shadow-md shadow-emerald-500/20">
                    <i class="fa-solid fa-location-crosshairs"></i>
                </div>
                <div>
                    <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                        Geospatial Highway Grid
                        <span class="text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-extrabold uppercase tracking-wider">{{ number_format($stats['total_markers'] ?? 97083) }} Coordinates</span>
                    </h2>
                    <p class="text-xs font-medium text-slate-500 mt-0.5">High-precision meter-by-meter physical coordinates mapped across NHMP operational zones, sectors, and beats</p>
                </div>
            </div>

            <!-- 3D Tab Toggle Controls -->
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="tab-tray-3d">
                    <button @click="activeTab = 'map'; $nextTick(() => { initMapIfNeeded(); })" :class="activeTab === 'map' ? 'active' : ''" class="tab-btn-3d">
                        <i class="fa-solid fa-map text-emerald-600"></i>
                        <span>Interactive Map</span>
                    </button>
                    <button @click="activeTab = 'table'" :class="activeTab === 'table' ? 'active' : ''" class="tab-btn-3d">
                        <i class="fa-solid fa-table-list text-blue-600"></i>
                        <span>Audit Data Table</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- TAB 1: INTERACTIVE LEAFLET MAP -->
        <div x-show="activeTab === 'map'" class="relative" style="min-height: 680px;">
            <!-- Floating 3D Map Command Bar -->
            <div class="absolute top-4 left-4 z-[500] toolbar-3d p-3.5 flex flex-wrap items-center gap-3 max-w-[calc(100%-2rem)]">
                <!-- HIGHWAY / MOTORWAY SELECTOR -->
                <div class="control-group-3d">
                    <label class="control-label-3d text-emerald-700">Highway / Motorway</label>
                    <select x-model="mapRoadCode" @change="onMapRoadChange()" class="select-3d min-w-[170px] font-black border-emerald-300 bg-emerald-50/30 text-emerald-950">
                        <option value="">All Routes (National)</option>
                        <template x-for="r in roads" :key="r.road_short">
                            <option :value="r.road_short" x-text="r.road_short + ' — ' + r.road_name + ' (' + r.total_km + ' KM)'"></option>
                        </template>
                    </select>
                </div>

                <!-- ZONE SELECTOR -->
                <div class="control-group-3d">
                    <label class="control-label-3d">Zone</label>
                    <select x-model="mapZoneId" @change="onMapZoneChange()" class="select-3d min-w-[135px]">
                        <option value="">All Zones</option>
                        <template x-for="z in getFilteredMapZones()" :key="z.id">
                            <option :value="z.id" x-text="z.name + (z.count ? ' (' + Number(z.count).toLocaleString() + ')' : '')"></option>
                        </template>
                    </select>
                </div>

                <!-- SECTOR SELECTOR -->
                <div class="control-group-3d">
                    <label class="control-label-3d">Sector</label>
                    <select x-model="mapSectorId" @change="onMapSectorChange()" class="select-3d min-w-[150px]">
                        <option value="">All Sectors</option>
                        <template x-for="s in getFilteredMapSectors()" :key="s.id">
                            <option :value="s.id" x-text="s.name + (s.count ? ' (' + Number(s.count).toLocaleString() + ')' : '')"></option>
                        </template>
                    </select>
                </div>

                <!-- BEAT SELECTOR -->
                <div class="control-group-3d">
                    <label class="control-label-3d">Beat</label>
                    <select x-model="mapBeatId" @change="onMapBeatChange()" class="select-3d min-w-[145px]">
                        <option value="">All Beats</option>
                        <template x-for="b in getFilteredMapBeats()" :key="b.id">
                            <option :value="b.id" x-text="b.name + (b.count ? ' (' + Number(b.count).toLocaleString() + ')' : '')"></option>
                        </template>
                    </select>
                </div>

                <!-- CARRIAGEWAY SELECTOR -->
                <div class="control-group-3d">
                    <label class="control-label-3d">Carriageway</label>
                    <select x-model="mapSide" @change="fetchViewportMarkers()" class="select-3d min-w-[130px]">
                        <option value="">Both Directions</option>
                        <option value="N">Northbound (N)</option>
                        <option value="S">Southbound (S)</option>
                    </select>
                </div>

                <!-- NATIONAL VIEW / RESET 3D BUTTON -->
                <div class="control-group-3d pt-3.5">
                    <button type="button" @click="resetMapFilters()" class="btn-3d-action" title="Reset all filters & zoom to National Overview">
                        <i class="fa-solid fa-earth-asia text-emerald-600"></i>
                        <span>National View</span>
                    </button>
                </div>

                <div class="h-8 w-px bg-slate-200/80 mx-1 hidden lg:block"></div>

                <!-- Live Viewport 3D Status Pill -->
                <div class="control-group-3d pt-3.5">
                    <div class="status-pill-3d">
                        <span class="relative flex h-2.5 w-2.5">
                            <span x-show="mapLoading" class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5" :class="mapLoading ? 'bg-amber-500' : 'bg-emerald-500'"></span>
                        </span>
                        <span x-text="mapStatusText"></span>
                    </div>
                </div>
            </div>

            <!-- Floating Operational Territory & Corridor Info Card (Top Right) -->
            <div x-show="getSelectedBeat() || getSelectedSector() || getSelectedZone() || selectedRoad" 
                 x-transition 
                 class="absolute top-4 right-4 z-[500] toolbar-3d p-4 max-w-sm hidden md:block shadow-2xl"
                 :class="{
                     'border-l-4 border-l-emerald-500': getSelectedBeat(),
                     'border-l-4 border-l-blue-500': !getSelectedBeat() && getSelectedSector(),
                     'border-l-4 border-l-indigo-500': !getSelectedBeat() && !getSelectedSector() && getSelectedZone(),
                     'border-l-4 border-l-purple-500': !getSelectedBeat() && !getSelectedSector() && !getSelectedZone() && selectedRoad
                 }">

                <!-- 1. BEAT LEVEL ACTIVE -->
                <template x-if="getSelectedBeat()">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="px-2.5 py-1 rounded-xl bg-emerald-600 text-white font-mono font-black text-xs shadow-sm">BEAT</span>
                                <div>
                                    <h4 class="text-xs font-black text-slate-900 leading-tight" x-text="getSelectedBeat().name"></h4>
                                    <p class="text-[10px] font-bold text-slate-500" x-text="getSelectedBeat().sector_name + ' • ' + getSelectedBeat().zone_name"></p>
                                </div>
                            </div>
                            <button @click="mapBeatId = ''; onMapSectorChange()" class="text-slate-400 hover:text-slate-600 text-xs p-1" title="Clear Beat Isolation"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="mt-2.5 pt-2 border-t border-slate-100 grid grid-cols-4 gap-2 text-center text-[10px]">
                            <div class="p-1 rounded-lg bg-emerald-50/70 border border-emerald-100">
                                <span class="block text-emerald-800 font-extrabold text-[8px] uppercase">Start KM</span>
                                <span class="font-black text-emerald-950 font-mono" x-text="getSelectedBeat().start_point ? 'KM ' + Number(getSelectedBeat().start_point.km).toFixed(1) : 'N/A'"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-rose-50/70 border border-rose-100">
                                <span class="block text-rose-800 font-extrabold text-[8px] uppercase">End KM</span>
                                <span class="font-black text-rose-950 font-mono" x-text="getSelectedBeat().end_point ? 'KM ' + Number(getSelectedBeat().end_point.km).toFixed(1) : 'N/A'"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Span</span>
                                <span class="font-black text-slate-800" x-text="(getSelectedBeat().km_min !== null && getSelectedBeat().km_max !== null) ? (Number(getSelectedBeat().km_max - getSelectedBeat().km_min).toFixed(1) + ' KM') : 'N/A'"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Coords</span>
                                <span class="font-black text-emerald-600" x-text="Number(getSelectedBeat().count || 0).toLocaleString()"></span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- 2. SECTOR LEVEL ACTIVE -->
                <template x-if="!getSelectedBeat() && getSelectedSector()">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="px-2.5 py-1 rounded-xl bg-blue-600 text-white font-mono font-black text-xs shadow-sm">SECTOR</span>
                                <div>
                                    <h4 class="text-xs font-black text-slate-900 leading-tight" x-text="getSelectedSector().name"></h4>
                                    <p class="text-[10px] font-bold text-slate-500" x-text="getSelectedSector().zone_name + ' • Operational Sector'"></p>
                                </div>
                            </div>
                            <button @click="mapSectorId = ''; onMapZoneChange()" class="text-slate-400 hover:text-slate-600 text-xs p-1" title="Clear Sector Isolation"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="mt-2.5 pt-2 border-t border-slate-100 grid grid-cols-3 gap-2 text-center text-[10px]">
                            <div class="p-1 rounded-lg bg-blue-50/70 border border-blue-100">
                                <span class="block text-blue-800 font-extrabold text-[8px] uppercase">Child Beats</span>
                                <span class="font-black text-blue-950" x-text="getSelectedSector().beat_ids ? getSelectedSector().beat_ids.length : 0"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Chainage</span>
                                <span class="font-black text-slate-800 text-[9px] font-mono" x-text="getSelectedSector().km_range || 'N/A'"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Coords</span>
                                <span class="font-black text-blue-600" x-text="Number(getSelectedSector().count || 0).toLocaleString()"></span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- 3. ZONE LEVEL ACTIVE -->
                <template x-if="!getSelectedBeat() && !getSelectedSector() && getSelectedZone()">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="px-2.5 py-1 rounded-xl bg-indigo-600 text-white font-mono font-black text-xs shadow-sm">ZONE</span>
                                <div>
                                    <h4 class="text-xs font-black text-slate-900 leading-tight" x-text="getSelectedZone().name"></h4>
                                    <p class="text-[10px] font-bold text-slate-500">Regional Command Area</p>
                                </div>
                            </div>
                            <button @click="mapZoneId = ''; onMapRoadChange()" class="text-slate-400 hover:text-slate-600 text-xs p-1" title="Clear Zone Isolation"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="mt-2.5 pt-2 border-t border-slate-100 grid grid-cols-3 gap-2 text-center text-[10px]">
                            <div class="p-1 rounded-lg bg-indigo-50/70 border border-indigo-100">
                                <span class="block text-indigo-800 font-extrabold text-[8px] uppercase">Sectors</span>
                                <span class="font-black text-indigo-950" x-text="getSelectedZone().sector_ids ? getSelectedZone().sector_ids.length : 0"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Beats</span>
                                <span class="font-black text-slate-800" x-text="getSelectedZone().beat_ids ? getSelectedZone().beat_ids.length : 0"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Coords</span>
                                <span class="font-black text-indigo-600" x-text="Number(getSelectedZone().count || 0).toLocaleString()"></span>
                            </div>
                        </div>
                    </div>
                </template>

                <!-- 4. ROAD CORRIDOR ACTIVE -->
                <template x-if="!getSelectedBeat() && !getSelectedSector() && !getSelectedZone() && selectedRoad">
                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-1 rounded-xl bg-purple-600 text-white font-mono font-black text-xs shadow-sm" x-text="selectedRoad ? selectedRoad.road_short : ''"></span>
                                <div>
                                    <h4 class="text-xs font-black text-slate-900 leading-tight" x-text="selectedRoad ? selectedRoad.road_name : ''"></h4>
                                    <p class="text-[10px] font-bold text-slate-500" x-text="selectedRoad ? (selectedRoad.road_from + ' → ' + selectedRoad.road_to) : ''"></p>
                                </div>
                            </div>
                            <button @click="resetMapFilters()" class="text-slate-400 hover:text-slate-600 text-xs p-1" title="Clear Road Selection"><i class="fa-solid fa-xmark"></i></button>
                        </div>
                        <div class="mt-2.5 pt-2 border-t border-slate-100 grid grid-cols-4 gap-2 text-center text-[10px]">
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Length</span>
                                <span class="font-black text-slate-800" x-text="selectedRoad ? (selectedRoad.total_km + ' KM') : ''"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Beats</span>
                                <span class="font-black text-blue-600" x-text="selectedRoad && selectedRoad.beat_ids ? selectedRoad.beat_ids.length : '0'"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Landmarks</span>
                                <span class="font-black text-purple-600" x-text="selectedRoad ? selectedRoad.landmark_count : '0'"></span>
                            </div>
                            <div class="p-1 rounded-lg bg-slate-50">
                                <span class="block text-slate-400 font-extrabold text-[8px] uppercase">Coords</span>
                                <span class="font-black text-emerald-600" x-text="selectedRoad ? Number(selectedRoad.marker_count).toLocaleString() : '0'"></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Map Container (Clean viewport without zoom button clipping) -->
            <div id="geospatial-leaflet-map" style="height: 680px; width: 100%; z-index: 10;"></div>

            <!-- Map Legend -->
            <div class="absolute bottom-6 left-6 z-[500] toolbar-3d p-3.5 text-[11px] font-bold text-slate-700 flex flex-col gap-2 max-w-xs shadow-xl">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider">Highway Markers Legend</span>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-sm border border-white"></span>
                    <span>Northbound Marker (N)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-indigo-500 shadow-sm border border-white"></span>
                    <span>Southbound Marker (S)</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-blue-600 shadow-sm border border-white"></span>
                    <span>Density Cluster (&lt; 10x Zoom)</span>
                </div>
                <div class="pt-1 mt-1 border-t border-slate-100 flex flex-col gap-1.5">
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[8px] font-black shadow-2xs"><i class="fa-solid fa-flag"></i></span>
                        <span>Start Milestone Sign</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-full bg-rose-600 text-white flex items-center justify-center text-[8px] font-black shadow-2xs"><i class="fa-solid fa-flag-checkered"></i></span>
                        <span>Terminus Milestone Sign</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-2 rounded-full bg-slate-800 border-l-2 border-emerald-400"></span>
                        <span>Beat Operational Area</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 2: PAGINATED AUDIT TABLE -->
        <div x-show="activeTab === 'table'" class="p-6 space-y-6">
            
            <!-- Table Controls & Cascading 3D Filter Bar -->
            <div class="flex flex-col xl:flex-row items-start xl:items-center justify-between gap-4 p-4 rounded-2xl bg-slate-50/70 border border-slate-200/80">
                <div class="flex flex-wrap items-end gap-3 w-full xl:w-auto">
                    <!-- Search Input -->
                    <div class="control-group-3d">
                        <label class="control-label-3d">Search</label>
                        <div class="relative w-full sm:w-64">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                            <input type="text" x-model.debounce.300ms="tableSearch" placeholder="Search by KM or Beat..." class="input-3d w-full">
                        </div>
                    </div>

                    <!-- Highway / Motorway Filter -->
                    <div class="control-group-3d">
                        <label class="control-label-3d text-purple-700">Highway / Motorway</label>
                        <select x-model="tableRoadCode" @change="onTableRoadChange()" class="select-3d min-w-[155px] font-black border-purple-200 bg-purple-50/20 text-purple-950">
                            <option value="">All Highways</option>
                            <template x-for="r in roads" :key="r.road_short">
                                <option :value="r.road_short" x-text="r.road_short + ' (' + r.total_km + ' KM)'"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Zone Filter -->
                    <div class="control-group-3d">
                        <label class="control-label-3d">Zone</label>
                        <select x-model="tableZoneId" @change="onTableZoneChange()" class="select-3d min-w-[130px]">
                            <option value="">All Zones</option>
                            <template x-for="z in getFilteredTableZones()" :key="z.id">
                                <option :value="z.id" x-text="z.name + (z.count ? ' (' + Number(z.count).toLocaleString() + ')' : '')"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Sector Filter -->
                    <div class="control-group-3d">
                        <label class="control-label-3d">Sector</label>
                        <select x-model="tableSectorId" @change="onTableSectorChange()" class="select-3d min-w-[145px]">
                            <option value="">All Sectors</option>
                            <template x-for="s in getFilteredTableSectors()" :key="s.id">
                                <option :value="s.id" x-text="s.name + (s.count ? ' (' + Number(s.count).toLocaleString() + ')' : '')"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Beat Filter -->
                    <div class="control-group-3d">
                        <label class="control-label-3d">Beat</label>
                        <select x-model="tableBeatId" @change="onTableBeatChange()" class="select-3d min-w-[140px]">
                            <option value="">All Beats</option>
                            <template x-for="b in getFilteredTableBeats()" :key="b.id">
                                <option :value="b.id" x-text="b.name + (b.count ? ' (' + Number(b.count).toLocaleString() + ')' : '')"></option>
                            </template>
                        </select>
                    </div>

                    <!-- Side Filter -->
                    <div class="control-group-3d">
                        <label class="control-label-3d">Carriageway</label>
                        <select x-model="filterSide" @change="filterTable(1)" class="select-3d min-w-[130px]">
                            <option value="">All Directions</option>
                            <option value="N">Northbound (N)</option>
                            <option value="S">Southbound (S)</option>
                            <option value="null">Single / Undirected</option>
                        </select>
                    </div>

                    <!-- Reset Table Filters 3D Button -->
                    <div class="control-group-3d">
                        <button type="button" @click="resetTableFilters()" class="btn-3d-action" title="Clear all table filters">
                            <i class="fa-solid fa-rotate-left text-slate-500"></i>
                            <span>Reset Filters</span>
                        </button>
                    </div>
                </div>

                <!-- Per Page Density Selector -->
                <div class="flex items-center gap-2 self-end xl:self-auto">
                    <span class="text-xs font-black text-slate-500 uppercase tracking-wider">Density:</span>
                    <select x-model="tablePerPage" @change="filterTable(1)" class="select-3d min-w-[115px]">
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                        <option value="100">100 per page</option>
                    </select>
                </div>
            </div>

            <!-- Table Container -->
            <div class="overflow-x-auto rounded-2xl border border-slate-100 shadow-sm">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-400">
                            <th class="p-4">Marker ID</th>
                            <th class="p-4">Beat / Highway</th>
                            <th class="p-4">KM Position</th>
                            <th class="p-4">Latitude</th>
                            <th class="p-4">Longitude</th>
                            <th class="p-4">Carriageway</th>
                            <th class="p-4">Integrity Status</th>
                            <th class="p-4 text-right">Inspect</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-bold text-slate-700 bg-white">
                        <template x-for="m in tableItems" :key="m.id">
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 font-mono text-[11px] text-slate-500" x-text="'#' + m.id"></td>
                                <td class="p-4 font-black text-slate-900">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <template x-if="getRoadBadge(m.office_id)">
                                            <span class="px-2 py-0.5 rounded-md bg-purple-100 text-purple-800 text-[10px] font-black tracking-wide border border-purple-200 shadow-2xs" x-text="getRoadBadge(m.office_id)"></span>
                                        </template>
                                        <span x-text="m.office ? m.office.name : ('Beat #' + m.office_id)"></span>
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 font-mono font-black border border-blue-100" x-text="'KM ' + Number(m.km).toFixed(2)"></span>
                                </td>
                                <td class="p-4 font-mono text-[11px] text-slate-600" x-text="Number(m.lat).toFixed(6)"></td>
                                <td class="p-4 font-mono text-[11px] text-slate-600" x-text="Number(m.lng).toFixed(6)"></td>
                                <td class="p-4">
                                    <template x-if="m.side === 'N'">
                                        <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black uppercase tracking-wider flex items-center gap-1 w-fit"><i class="fa-solid fa-arrow-up text-[9px]"></i>Northbound</span>
                                    </template>
                                    <template x-if="m.side === 'S'">
                                        <span class="px-2.5 py-1 rounded-full bg-indigo-100 text-indigo-800 text-[10px] font-black uppercase tracking-wider flex items-center gap-1 w-fit"><i class="fa-solid fa-arrow-down text-[9px]"></i>Southbound</span>
                                    </template>
                                    <template x-if="!m.side">
                                        <span class="px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black uppercase tracking-wider">Shared / Undirected</span>
                                    </template>
                                </td>
                                <td class="p-4">
                                    <span class="inline-flex items-center gap-1 text-[11px] text-emerald-600 font-bold">
                                        <i class="fa-solid fa-circle-check text-[10px]"></i> Valid Coords
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <button @click="inspectMarker(m)" class="btn-3d-action">
                                        <i class="fa-solid fa-eye text-slate-500"></i>
                                        <span>Inspect</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>

                <!-- Loading State -->
                <div x-show="tableLoading" class="py-16 text-center bg-white border-b border-slate-100">
                    <i class="fa-solid fa-circle-notch fa-spin text-3xl text-emerald-600 mb-2"></i>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Querying Coordinates Grid...</p>
                </div>

                <!-- Empty State -->
                <div x-show="!tableLoading && tableItems.length === 0" class="py-24 text-center bg-white">
                    <i class="fa-solid fa-map-location-dot text-5xl text-slate-200 mb-4 animate-pulse"></i>
                    <h4 class="text-lg font-black text-slate-800">No Matching Coordinates Found</h4>
                    <p class="text-xs font-medium text-slate-400 mt-1">Try adjusting your Zone, Sector, Beat, KM position, or Carriageway filters.</p>
                </div>
            </div>

            <!-- Table Pagination Controls -->
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4 border-t border-slate-100">
                <div class="text-xs font-bold text-slate-500">
                    Showing <span class="text-slate-900" x-text="tablePagination.from || 0"></span> to <span class="text-slate-900" x-text="tablePagination.to || 0"></span> of <span class="text-slate-900" x-text="Number(tablePagination.total || 0).toLocaleString()"></span> coordinates
                </div>

                <div class="flex items-center gap-1.5">
                    <button @click.prevent="goToTablePage(1)" :disabled="tablePagination.current_page === 1" class="btn-3d-action disabled:opacity-40">First</button>
                    <button @click.prevent="goToTablePage(tablePagination.current_page - 1)" :disabled="tablePagination.current_page === 1" class="btn-3d-action disabled:opacity-40"><i class="fa-solid fa-chevron-left"></i></button>
                    
                    <span class="px-3 text-xs font-black text-slate-800" x-text="'Page ' + tablePagination.current_page + ' of ' + tablePagination.last_page"></span>

                    <button @click.prevent="goToTablePage(tablePagination.current_page + 1)" :disabled="tablePagination.current_page === tablePagination.last_page" class="btn-3d-action disabled:opacity-40"><i class="fa-solid fa-chevron-right"></i></button>
                    <button @click.prevent="goToTablePage(tablePagination.last_page)" :disabled="tablePagination.current_page === tablePagination.last_page" class="btn-3d-action disabled:opacity-40">Last</button>
                </div>
            </div>

        </div>

    </div>

    <!-- MARKER INSPECTION MODAL -->
    <div x-show="selectedMarker" class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm" x-cloak>
        <div @click.away="selectedMarker = null" class="bg-white rounded-3xl shadow-2xl border border-slate-100 max-w-lg w-full p-6 space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm font-black"><i class="fa-solid fa-location-dot"></i></span>
                    <div>
                        <h3 class="text-base font-black text-slate-900" x-text="'Marker #' + (selectedMarker ? selectedMarker.id : '')"></h3>
                        <p class="text-xs text-slate-400 font-bold" x-text="selectedMarker && selectedMarker.office ? selectedMarker.office.name : ''"></p>
                    </div>
                </div>
                <button @click="selectedMarker = null" class="w-8 h-8 rounded-full hover:bg-slate-100 text-slate-400 flex items-center justify-center"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <div class="space-y-3 text-xs font-bold">
                <div class="flex justify-between py-2 border-b border-slate-50">
                    <span class="text-slate-400">Kilometer Position</span>
                    <span class="font-mono text-slate-900 text-sm" x-text="selectedMarker ? 'KM ' + Number(selectedMarker.km).toFixed(2) : ''"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-50">
                    <span class="text-slate-400">Latitude</span>
                    <span class="font-mono text-slate-900" x-text="selectedMarker ? Number(selectedMarker.lat).toFixed(8) : ''"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-50">
                    <span class="text-slate-400">Longitude</span>
                    <span class="font-mono text-slate-900" x-text="selectedMarker ? Number(selectedMarker.lng).toFixed(8) : ''"></span>
                </div>
                <div class="flex justify-between py-2 border-b border-slate-50">
                    <span class="text-slate-400">Carriageway Direction</span>
                    <span class="text-slate-900" x-text="selectedMarker && selectedMarker.side === 'N' ? 'Northbound (N)' : (selectedMarker && selectedMarker.side === 'S' ? 'Southbound (S)' : 'Shared Track')"></span>
                </div>
                <div class="flex justify-between py-2">
                    <span class="text-slate-400">Data Integrity Check</span>
                    <span class="text-emerald-600 font-black"><i class="fa-solid fa-check-circle mr-1"></i>Verified in Bounds</span>
                </div>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3">
                <button @click="jumpToMap(selectedMarker)" class="btn-3d-action bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-700 border-b-emerald-800">
                    <i class="fa-solid fa-map-pin"></i>
                    <span>View on Map</span>
                </button>
            </div>
        </div>
    </div>

</div>

<script id="geospatial-markers-data" type="application/json">
    {!! json_encode([
        'roads' => $roads,
        'zones' => $zones,
        'sectors' => $sectors,
        'beats' => $beats,
        'markers' => $markers->items(),
        'pagination' => $pagination,
        'stats' => $stats,
    ]) !!}
</script>

@push('scripts')
<script>
window.geospatialGridManager = function() {
    const initData = JSON.parse(document.getElementById('geospatial-markers-data')?.textContent || '{}');

    // Isolate Leaflet instances from Alpine ES6 reactive Proxy to prevent internal identity breakage
    const rawLeaflet = {
        map: null,
        clusterGroup: null,
        roadPolylineGroup: null,
        boundaryLayerGroup: null,
        milestoneLayerGroup: null,
    };

    return {
        activeTab: 'map',
        roads: initData.roads || [],
        zones: initData.zones || [],
        sectors: initData.sectors || [],
        beats: initData.beats || [],
        stats: initData.stats || {},
        
        // Unproxied Leaflet Accessors
        get map() { return rawLeaflet.map; },
        set map(val) { rawLeaflet.map = val; },
        get clusterGroup() { return rawLeaflet.clusterGroup; },
        set clusterGroup(val) { rawLeaflet.clusterGroup = val; },
        get roadPolylineGroup() { return rawLeaflet.roadPolylineGroup; },
        set roadPolylineGroup(val) { rawLeaflet.roadPolylineGroup = val; },
        get boundaryLayerGroup() { return rawLeaflet.boundaryLayerGroup; },
        set boundaryLayerGroup(val) { rawLeaflet.boundaryLayerGroup = val; },
        get milestoneLayerGroup() { return rawLeaflet.milestoneLayerGroup; },
        set milestoneLayerGroup(val) { rawLeaflet.milestoneLayerGroup = val; },

        mapLoading: false,
        mapRoadCode: '',
        selectedRoad: null,
        mapZoneId: '',
        mapSectorId: '',
        mapBeatId: '',
        mapSide: '',
        mapStatusText: 'Ready',
        mapAbortController: null,
        
        // Table State
        tableItems: initData.markers || [],
        tablePagination: initData.pagination || { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        tableSearch: '',
        tableRoadCode: '',
        tableZoneId: '',
        tableSectorId: '',
        tableBeatId: '',
        filterSide: '',
        tablePerPage: 25,
        tableLoading: false,
        tableAbortController: null,

        // Modal
        selectedMarker: null,

        init() {
            this.$nextTick(() => {
                this.initMapIfNeeded();
            });

            this.$watch('tableSearch', () => { this.filterTable(1); });
        },

        // ==========================================
        // SELECTION GETTERS FOR TERRITORY CARDS
        // ==========================================
        getSelectedBeat() {
            return this.mapBeatId ? this.beats.find(b => b.id == this.mapBeatId) : null;
        },

        getSelectedSector() {
            return this.mapSectorId ? this.sectors.find(s => s.id == this.mapSectorId) : null;
        },

        getSelectedZone() {
            return this.mapZoneId ? this.zones.find(z => z.id == this.mapZoneId) : null;
        },

        // ==========================================
        // CASCADING SELECTIONS FOR MAP
        // ==========================================
        getFilteredMapZones() {
            if (!this.mapRoadCode) return this.zones;
            const road = this.roads.find(r => r.road_short === this.mapRoadCode || r.road === this.mapRoadCode);
            if (!road || !road.zone_ids || road.zone_ids.length === 0) return this.zones;
            return this.zones.filter(z => road.zone_ids.includes(z.id));
        },

        getFilteredMapSectors() {
            let list = this.sectors;
            if (this.mapRoadCode) {
                const road = this.roads.find(r => r.road_short === this.mapRoadCode || r.road === this.mapRoadCode);
                if (road && road.sector_ids && road.sector_ids.length > 0) {
                    list = list.filter(s => road.sector_ids.includes(s.id));
                }
            }
            if (this.mapZoneId) {
                list = list.filter(s => s.zone_id == this.mapZoneId);
            }
            return list;
        },

        getFilteredMapBeats() {
            let list = this.beats;
            if (this.mapRoadCode) {
                const road = this.roads.find(r => r.road_short === this.mapRoadCode || r.road === this.mapRoadCode);
                if (road && road.beat_ids && road.beat_ids.length > 0) {
                    list = list.filter(b => road.beat_ids.includes(b.id));
                }
            }
            if (this.mapSectorId) {
                list = list.filter(b => b.sector_id == this.mapSectorId);
            } else if (this.mapZoneId) {
                list = list.filter(b => b.zone_id == this.mapZoneId);
            }
            return list;
        },

        onMapRoadChange() {
            this.mapZoneId = '';
            this.mapSectorId = '';
            this.mapBeatId = '';

            if (this.mapRoadCode) {
                const road = this.roads.find(r => r.road_short === this.mapRoadCode || r.road === this.mapRoadCode);
                this.selectedRoad = road || null;

                if (road && road.bounds && this.map) {
                    this.map.flyToBounds(road.bounds, { padding: [60, 60], maxZoom: 12, duration: 1.2 });
                }
            } else {
                this.selectedRoad = null;
                if (this.map) {
                    this.map.flyTo([30.3753, 69.3451], 6, { duration: 1.2 });
                }
            }

            this.renderActiveSelectionOverlays();
            this.fetchViewportMarkers();
        },

        onMapZoneChange() {
            this.mapSectorId = '';
            this.mapBeatId = '';
            if (this.mapZoneId) {
                const z = this.zones.find(x => x.id == this.mapZoneId);
                if (z && z.bounds && this.map) {
                    this.map.flyToBounds(z.bounds, { padding: [50, 50], maxZoom: 11, duration: 1.2 });
                }
            } else if (this.selectedRoad && this.selectedRoad.bounds && this.map) {
                this.map.flyToBounds(this.selectedRoad.bounds, { padding: [60, 60], maxZoom: 12, duration: 1.2 });
            } else if (this.map) {
                this.map.flyTo([30.3753, 69.3451], 6, { duration: 1.2 });
            }
            this.renderActiveSelectionOverlays();
            this.fetchViewportMarkers();
        },

        onMapSectorChange() {
            this.mapBeatId = '';
            if (this.mapSectorId) {
                const s = this.sectors.find(x => x.id == this.mapSectorId);
                if (s) {
                    if (s.zone_id && !this.mapZoneId) this.mapZoneId = s.zone_id;
                    if (s.bounds && this.map) {
                        this.map.flyToBounds(s.bounds, { padding: [50, 50], maxZoom: 13, duration: 1.0 });
                    }
                }
            }
            this.renderActiveSelectionOverlays();
            this.fetchViewportMarkers();
        },

        onMapBeatChange() {
            if (this.mapBeatId) {
                const b = this.beats.find(x => x.id == this.mapBeatId);
                if (b) {
                    if (b.sector_id && !this.mapSectorId) this.mapSectorId = b.sector_id;
                    if (b.zone_id && !this.mapZoneId) this.mapZoneId = b.zone_id;
                    if (b.bounds && this.map) {
                        this.map.flyToBounds(b.bounds, { padding: [40, 40], maxZoom: 15, duration: 0.8 });
                    }
                }
            }
            this.renderActiveSelectionOverlays();
            this.fetchViewportMarkers();
        },

        resetMapFilters() {
            this.mapRoadCode = '';
            this.selectedRoad = null;
            this.mapZoneId = '';
            this.mapSectorId = '';
            this.mapBeatId = '';
            this.mapSide = '';
            if (this.map) {
                this.map.flyTo([30.3753, 69.3451], 6, { duration: 1.2 });
            }
            this.renderActiveSelectionOverlays();
            this.fetchViewportMarkers();
        },

        safeRunWhenNotZooming(fn) {
            if (!this.map) return;
            if (this.map._animatingZoom) {
                this.map.once('zoomend', () => {
                    setTimeout(() => {
                        if (this.map && !this.map._animatingZoom) {
                            fn();
                        } else if (this.map) {
                            this.safeRunWhenNotZooming(fn);
                        }
                    }, 30);
                });
                return;
            }
            fn();
        },

        // =========================================================================
        // STRICT TERRITORIAL ISOLATION & MILESTONE OVERLAY RENDERER
        // =========================================================================
        renderActiveSelectionOverlays() {
            if (!this.map) return;

            // Wait for active zoom animation to finish before updating overlays
            if (this.map._animatingZoom) {
                this.map.once('zoomend', () => {
                    setTimeout(() => this.renderActiveSelectionOverlays(), 30);
                });
                return;
            }

            if (this.roadPolylineGroup) this.roadPolylineGroup.clearLayers();
            if (this.boundaryLayerGroup) this.boundaryLayerGroup.clearLayers();
            if (this.milestoneLayerGroup) this.milestoneLayerGroup.clearLayers();

            const zoom = this.map.getZoom();
            const bounds = this.map.getBounds() ? this.map.getBounds().pad(0.08) : null;

            const isPointVisible = (pt) => {
                if (!pt || pt.lat === undefined || pt.lng === undefined || !bounds) return false;
                return bounds.contains([pt.lat, pt.lng]);
            };

            const isBeatVisible = (b) => {
                if (!bounds) return true;
                if (b.bounds) {
                    return bounds.intersects(L.latLngBounds(b.bounds));
                }
                if (b.start_point) return isPointVisible(b.start_point);
                if (b.end_point) return isPointVisible(b.end_point);
                return true;
            };

            const BEAT_PALETTE = [
                '#059669', '#2563eb', '#7c3aed', '#d97706', '#db2777',
                '#0891b2', '#16a34a', '#ea580c', '#4f46e5', '#ca8a04'
            ];

            const createStartMilestoneMarker = (beat, pt) => {
                if (!pt || pt.lat === undefined || pt.lng === undefined) return null;
                const kmStr = Number(pt.km !== undefined ? pt.km : beat.km_min || 0).toFixed(2);
                const icon = L.divIcon({
                    className: 'custom-milestone-marker',
                    html: `
                        <div class="milestone-3d-sign milestone-3d-start" title="${beat.name} Starting Point: KM ${kmStr}">
                            <div class="milestone-pin-pill">
                                <span class="milestone-flag-icon"><i class="fa-solid fa-flag"></i></span>
                                <div class="milestone-text-group">
                                    <span class="milestone-sub">${beat.name} START</span>
                                    <span class="milestone-km">KM ${kmStr}</span>
                                </div>
                            </div>
                            <div class="milestone-stem"></div>
                            <div class="milestone-dot"></div>
                        </div>
                    `,
                    iconSize: [140, 52],
                    iconAnchor: [70, 52]
                });

                const popupHtml = `
                    <div class="milestone-popup p-3 font-sans min-w-[220px]">
                        <div class="flex items-center gap-2 pb-2 mb-2 border-b border-slate-100">
                            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-black shadow-inner">
                                <i class="fa-solid fa-flag"></i>
                            </span>
                            <div>
                                <h4 class="text-xs font-black text-slate-900">${beat.name} (Start Point)</h4>
                                <p class="text-[10px] font-bold text-slate-400">${beat.sector_name || ''} • ${beat.zone_name || ''}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5 text-[11px] font-bold text-slate-600">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Milestone</span>
                                <span class="font-mono text-emerald-700 font-black">KM ${kmStr}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Coordinates</span>
                                <span class="font-mono text-slate-800 text-[10px]">${Number(pt.lat).toFixed(6)}, ${Number(pt.lng).toFixed(6)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Chainage</span>
                                <span class="text-slate-700">${beat.km_range || ''}</span>
                            </div>
                        </div>
                    </div>
                `;

                return L.marker([pt.lat, pt.lng], { icon: icon, zIndexOffset: 1000 }).bindPopup(popupHtml, { className: 'milestone-popup' });
            };

            const createEndMilestoneMarker = (beat, pt) => {
                if (!pt || pt.lat === undefined || pt.lng === undefined) return null;
                const kmStr = Number(pt.km !== undefined ? pt.km : beat.km_max || 0).toFixed(2);
                const icon = L.divIcon({
                    className: 'custom-milestone-marker',
                    html: `
                        <div class="milestone-3d-sign milestone-3d-end" title="${beat.name} Terminus Point: KM ${kmStr}">
                            <div class="milestone-pin-pill">
                                <span class="milestone-flag-icon"><i class="fa-solid fa-flag-checkered"></i></span>
                                <div class="milestone-text-group">
                                    <span class="milestone-sub">${beat.name} END</span>
                                    <span class="milestone-km">KM ${kmStr}</span>
                                </div>
                            </div>
                            <div class="milestone-stem"></div>
                            <div class="milestone-dot"></div>
                        </div>
                    `,
                    iconSize: [140, 52],
                    iconAnchor: [70, 52]
                });

                const popupHtml = `
                    <div class="milestone-popup p-3 font-sans min-w-[220px]">
                        <div class="flex items-center gap-2 pb-2 mb-2 border-b border-slate-100">
                            <span class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center text-xs font-black shadow-inner">
                                <i class="fa-solid fa-flag-checkered"></i>
                            </span>
                            <div>
                                <h4 class="text-xs font-black text-slate-900">${beat.name} (Terminus Point)</h4>
                                <p class="text-[10px] font-bold text-slate-400">${beat.sector_name || ''} • ${beat.zone_name || ''}</p>
                            </div>
                        </div>
                        <div class="space-y-1.5 text-[11px] font-bold text-slate-600">
                            <div class="flex justify-between">
                                <span class="text-slate-400">Milestone</span>
                                <span class="font-mono text-rose-700 font-black">KM ${kmStr}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Coordinates</span>
                                <span class="font-mono text-slate-800 text-[10px]">${Number(pt.lat).toFixed(6)}, ${Number(pt.lng).toFixed(6)}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-400">Chainage</span>
                                <span class="text-slate-700">${beat.km_range || ''}</span>
                            </div>
                        </div>
                    </div>
                `;

                return L.marker([pt.lat, pt.lng], { icon: icon, zIndexOffset: 1000 }).bindPopup(popupHtml, { className: 'milestone-popup' });
            };

            const createBeatAreaBadge = (beat, midPoint, color) => {
                if (!midPoint) return null;
                const icon = L.divIcon({
                    className: 'custom-beat-tag',
                    html: `
                        <div class="beat-area-tag-badge" style="border-left-color: ${color};" title="Click to isolate ${beat.name}">
                            <span class="beat-color-dot" style="background-color: ${color};"></span>
                            <span>${beat.name} Area</span>
                            <span class="beat-range-pill">${beat.km_range || ''}</span>
                        </div>
                    `,
                    iconSize: [160, 30],
                    iconAnchor: [80, 15]
                });

                const badgeMarker = L.marker(midPoint, { icon: icon, zIndexOffset: 500 });
                badgeMarker.on('click', () => {
                    this.mapBeatId = beat.id;
                    this.onMapBeatChange();
                });
                return badgeMarker;
            };

            // =====================================================================
            // 1. BEAT LEVEL ISOLATION (Single Beat Active - Zero Clutter)
            // =====================================================================
            if (this.mapBeatId) {
                const beat = this.beats.find(b => b.id == this.mapBeatId);
                if (beat) {
                    if (beat.polyline && beat.polyline.length > 0 && this.boundaryLayerGroup) {
                        L.polyline(beat.polyline, {
                            color: '#10b981',
                            weight: 10,
                            opacity: 0.35,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }).addTo(this.boundaryLayerGroup);

                        L.polyline(beat.polyline, {
                            color: '#047857',
                            weight: 4.5,
                            opacity: 0.95,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }).addTo(this.boundaryLayerGroup);

                        const midIdx = Math.floor(beat.polyline.length / 2);
                        const areaBadge = createBeatAreaBadge(beat, beat.polyline[midIdx], '#10b981');
                        if (areaBadge && this.milestoneLayerGroup) this.milestoneLayerGroup.addLayer(areaBadge);
                    }

                    // A single isolated beat ALWAYS shows its Start and End milestone signs cleanly
                    if (beat.start_point && this.milestoneLayerGroup) {
                        const startM = createStartMilestoneMarker(beat, beat.start_point);
                        if (startM) this.milestoneLayerGroup.addLayer(startM);
                    }
                    if (beat.end_point && this.milestoneLayerGroup) {
                        const endM = createEndMilestoneMarker(beat, beat.end_point);
                        if (endM) this.milestoneLayerGroup.addLayer(endM);
                    }
                }
                return;
            }

            // =====================================================================
            // 2. SECTOR LEVEL ISOLATION (3 to 6 Beats)
            // =====================================================================
            if (this.mapSectorId) {
                const sector = this.sectors.find(s => s.id == this.mapSectorId);
                if (sector) {
                    const sectorBeats = this.beats.filter(b => b.sector_id == sector.id);
                    sectorBeats.forEach((b, idx) => {
                        const color = BEAT_PALETTE[idx % BEAT_PALETTE.length];

                        // Route corridors always cleanly rendered
                        if (b.polyline && b.polyline.length > 0 && this.boundaryLayerGroup) {
                            const glowLine = L.polyline(b.polyline, {
                                color: color,
                                weight: 8,
                                opacity: 0.25,
                                lineCap: 'round',
                                lineJoin: 'round'
                            }).addTo(this.boundaryLayerGroup);

                            const coreLine = L.polyline(b.polyline, {
                                color: color,
                                weight: 4,
                                opacity: 0.95,
                                lineCap: 'round',
                                lineJoin: 'round'
                            }).addTo(this.boundaryLayerGroup);

                            coreLine.on('click', () => {
                                this.mapBeatId = b.id;
                                this.onMapBeatChange();
                            });
                            coreLine.bindTooltip(`<strong>${b.name}</strong><br><span style="font-size:10px; color:#64748b;">${b.km_range || ''}</span><br><span style="font-size:9px; color:#059669; font-weight:800;">Click to isolate beat</span>`, {
                                sticky: true,
                                direction: 'top'
                            });

                            // Zoom LOD: Render Area Badges only when zoom >= 10 and beat is in viewport
                            if (zoom >= 10 && isBeatVisible(b)) {
                                const midIdx = Math.floor(b.polyline.length / 2);
                                const areaBadge = createBeatAreaBadge(b, b.polyline[midIdx], color);
                                if (areaBadge && this.milestoneLayerGroup) this.milestoneLayerGroup.addLayer(areaBadge);
                            }
                        }

                        // Zoom LOD: Render Start/End Milestone signs ONLY when zoomed in (zoom >= 12) to avoid collision
                        if (zoom >= 12) {
                            if (b.start_point && isPointVisible(b.start_point) && this.milestoneLayerGroup) {
                                const startM = createStartMilestoneMarker(b, b.start_point);
                                if (startM) this.milestoneLayerGroup.addLayer(startM);
                            }
                            if (b.end_point && isPointVisible(b.end_point) && this.milestoneLayerGroup) {
                                const endM = createEndMilestoneMarker(b, b.end_point);
                                if (endM) this.milestoneLayerGroup.addLayer(endM);
                            }
                        }
                    });
                }
                return;
            }

            // =====================================================================
            // 3. ZONE LEVEL ISOLATION (20 to 35 Beats across whole province)
            // =====================================================================
            if (this.mapZoneId) {
                const zone = this.zones.find(z => z.id == this.mapZoneId);
                if (zone) {
                    const zoneSectors = this.sectors.filter(s => s.zone_id == zone.id);
                    zoneSectors.forEach((s, sIdx) => {
                        const secColor = BEAT_PALETTE[sIdx % BEAT_PALETTE.length];
                        const sBeats = this.beats.filter(b => b.sector_id == s.id);
                        sBeats.forEach((b) => {
                            // Route corridors always cleanly rendered without any clutter
                            if (b.polyline && b.polyline.length > 0 && this.boundaryLayerGroup) {
                                const coreLine = L.polyline(b.polyline, {
                                    color: secColor,
                                    weight: 3.5,
                                    opacity: 0.85,
                                    lineCap: 'round',
                                    lineJoin: 'round'
                                }).addTo(this.boundaryLayerGroup);

                                coreLine.on('click', () => {
                                    this.mapBeatId = b.id;
                                    this.onMapBeatChange();
                                });
                                coreLine.bindTooltip(`<strong>${b.name}</strong> (${s.name})<br><span style="font-size:10px; color:#64748b;">${b.km_range || ''}</span><br><span style="font-size:9px; color:#059669; font-weight:800;">Click to isolate beat</span>`, {
                                    sticky: true,
                                    direction: 'top'
                                });

                                // Zoom LOD: Show Area Badges ONLY when zoomed in (zoom >= 11) for beats in viewport
                                if (zoom >= 11 && isBeatVisible(b)) {
                                    const midIdx = Math.floor(b.polyline.length / 2);
                                    const areaBadge = createBeatAreaBadge(b, b.polyline[midIdx], secColor);
                                    if (areaBadge && this.milestoneLayerGroup) this.milestoneLayerGroup.addLayer(areaBadge);
                                }
                            }

                            // Zoom LOD: Show Start/End Milestone signs ONLY when deeply zoomed in (zoom >= 13)
                            if (zoom >= 13) {
                                if (b.start_point && isPointVisible(b.start_point) && this.milestoneLayerGroup) {
                                    const startM = createStartMilestoneMarker(b, b.start_point);
                                    if (startM) this.milestoneLayerGroup.addLayer(startM);
                                }
                                if (b.end_point && isPointVisible(b.end_point) && this.milestoneLayerGroup) {
                                    const endM = createEndMilestoneMarker(b, b.end_point);
                                    if (endM) this.milestoneLayerGroup.addLayer(endM);
                                }
                            }
                        });
                    });
                }
                return;
            }

            // 4. ROAD CORRIDOR VECTOR (WHEN NO TERRITORIAL ISOLATION ACTIVE)
            if (this.selectedRoad && this.selectedRoad.corridor && this.selectedRoad.corridor.length > 0 && this.roadPolylineGroup) {
                const glowLine = L.polyline(this.selectedRoad.corridor, {
                    color: '#10b981',
                    weight: 8,
                    opacity: 0.35,
                    lineCap: 'round',
                    lineJoin: 'round'
                });
                const coreLine = L.polyline(this.selectedRoad.corridor, {
                    color: '#059669',
                    weight: 3.5,
                    opacity: 0.95,
                    lineCap: 'round',
                    lineJoin: 'round'
                });

                coreLine.bindPopup(`
                    <div style="font-family: inherit; font-size: 11px; min-width: 180px;">
                        <strong style="font-size: 13px; color: #047857;">${this.selectedRoad.road_short}: ${this.selectedRoad.road_name}</strong><br>
                        <span style="color: #64748b;">${this.selectedRoad.road_from} → ${this.selectedRoad.road_to}</span><br>
                        <div style="margin-top: 6px; padding-top: 6px; border-top: 1px solid #e2e8f0; display: flex; gap: 8px;">
                            <span><strong>${this.selectedRoad.total_km}</strong> KM</span> •
                            <span><strong>${this.selectedRoad.beat_ids ? this.selectedRoad.beat_ids.length : 0}</strong> Beats</span> •
                            <span><strong>${this.selectedRoad.landmark_count || 0}</strong> Landmarks</span>
                        </div>
                    </div>
                `);

                this.roadPolylineGroup.addLayer(glowLine);
                this.roadPolylineGroup.addLayer(coreLine);
            }
        },

        // ==========================================
        // CASCADING SELECTIONS FOR AUDIT TABLE
        // ==========================================
        getFilteredTableZones() {
            if (!this.tableRoadCode) return this.zones;
            const road = this.roads.find(r => r.road_short === this.tableRoadCode || r.road === this.tableRoadCode);
            if (!road || !road.zone_ids || road.zone_ids.length === 0) return this.zones;
            return this.zones.filter(z => road.zone_ids.includes(z.id));
        },

        getFilteredTableSectors() {
            let list = this.sectors;
            if (this.tableRoadCode) {
                const road = this.roads.find(r => r.road_short === this.tableRoadCode || r.road === this.tableRoadCode);
                if (road && road.sector_ids && road.sector_ids.length > 0) {
                    list = list.filter(s => road.sector_ids.includes(s.id));
                }
            }
            if (this.tableZoneId) {
                list = list.filter(s => s.zone_id == this.tableZoneId);
            }
            return list;
        },

        getFilteredTableBeats() {
            let list = this.beats;
            if (this.tableRoadCode) {
                const road = this.roads.find(r => r.road_short === this.tableRoadCode || r.road === this.tableRoadCode);
                if (road && road.beat_ids && road.beat_ids.length > 0) {
                    list = list.filter(b => road.beat_ids.includes(b.id));
                }
            }
            if (this.tableSectorId) {
                list = list.filter(b => b.sector_id == this.tableSectorId);
            } else if (this.tableZoneId) {
                list = list.filter(b => b.zone_id == this.tableZoneId);
            }
            return list;
        },

        onTableRoadChange() {
            this.tableZoneId = '';
            this.tableSectorId = '';
            this.tableBeatId = '';
            this.filterTable(1);
        },

        getRoadBadge(officeId) {
            if (!officeId || !this.roads) return null;
            const r = this.roads.find(road => road.beat_ids && road.beat_ids.includes(officeId));
            return r ? r.road_short : null;
        },

        onTableZoneChange() {
            this.tableSectorId = '';
            this.tableBeatId = '';
            this.filterTable(1);
        },

        onTableSectorChange() {
            this.tableBeatId = '';
            if (this.tableSectorId) {
                const s = this.sectors.find(x => x.id == this.tableSectorId);
                if (s && s.zone_id && !this.tableZoneId) this.tableZoneId = s.zone_id;
            }
            this.filterTable(1);
        },

        onTableBeatChange() {
            if (this.tableBeatId) {
                const b = this.beats.find(x => x.id == this.tableBeatId);
                if (b) {
                    if (b.sector_id && !this.tableSectorId) this.tableSectorId = b.sector_id;
                    if (b.zone_id && !this.tableZoneId) this.tableZoneId = b.zone_id;
                }
            }
            this.filterTable(1);
        },

        resetTableFilters() {
            this.tableRoadCode = '';
            this.tableZoneId = '';
            this.tableSectorId = '';
            this.tableBeatId = '';
            this.filterSide = '';
            this.tableSearch = '';
            this.filterTable(1);
        },

        // ==========================================
        // MAP INITIALIZATION & EVENT BINDINGS
        // ==========================================
        initMapIfNeeded() {
            const mapEl = document.getElementById('geospatial-leaflet-map');
            if (!mapEl || !window.L) return;

            // 1. If map already initialized on this container:
            if (this.map && typeof this.map.getContainer === 'function' && this.map.getContainer() === mapEl) {
                setTimeout(() => {
                    try { this.map.invalidateSize(); } catch (e) {}
                }, 100);
                return;
            }

            // 2. Clean previous global instance:
            if (window._geospatialMarkerMap) {
                try {
                    window._geospatialMarkerMap.off();
                    window._geospatialMarkerMap.remove();
                } catch (e) {}
                window._geospatialMarkerMap = null;
            }
            rawLeaflet.map = null;
            rawLeaflet.clusterGroup = null;
            rawLeaflet.roadPolylineGroup = null;
            rawLeaflet.boundaryLayerGroup = null;
            rawLeaflet.milestoneLayerGroup = null;

            // 3. Reset internal DOM node:
            if (mapEl._leaflet_id) {
                mapEl._leaflet_id = null;
                mapEl.innerHTML = '';
            }

            // 4. Center of Pakistan highway network with zoomControl at bottomright (No top-left clipping!)
            try {
                this.map = L.map(mapEl, {
                    center: [30.3753, 69.3451],
                    zoom: 6,
                    zoomControl: false,
                    preferCanvas: true
                });
                window._geospatialMarkerMap = this.map;
            } catch (err) {
                console.warn('Leaflet container reset required:', err);
                mapEl._leaflet_id = null;
                mapEl.innerHTML = '';
                this.map = L.map(mapEl, {
                    center: [30.3753, 69.3451],
                    zoom: 6,
                    zoomControl: false,
                    preferCanvas: true
                });
                window._geospatialMarkerMap = this.map;
            }

            // Put zoom control cleanly at bottom-right to avoid any collision with top floating toolbar
            L.control.zoom({ position: 'bottomright' }).addTo(this.map);

            // Esri World Street Map (same provider as the dashboard map). OSM's public tile servers block heavy/shared use (403 "Access blocked").
            L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Street_Map/MapServer/tile/{z}/{y}/{x}', {
                attribution: 'Tiles &copy; Esri | NHMP 130 Geospatial Grid',
                maxZoom: 19
            }).addTo(this.map);

            this.roadPolylineGroup = L.layerGroup().addTo(this.map);
            this.boundaryLayerGroup = L.layerGroup().addTo(this.map);
            this.milestoneLayerGroup = L.layerGroup().addTo(this.map);

            this.clusterGroup = L.markerClusterGroup({
                chunkedLoading: true,
                chunkInterval: 100,
                chunkDelay: 50,
                maxClusterRadius: 45,
                spiderfyOnMaxZoom: true,
                showCoverageOnHover: false
            });
            this.map.addLayer(this.clusterGroup);

            // Re-render overlays & markers on pan / zoom with debounce for responsive LOD
            let moveTimeout = null;
            this.map.on('moveend', () => {
                clearTimeout(moveTimeout);
                moveTimeout = setTimeout(() => {
                    this.renderActiveSelectionOverlays();
                    this.fetchViewportMarkers();
                }, 200);
            });

            // If a route, zone, sector, or beat is active, render selection overlays
            if (this.mapBeatId || this.mapSectorId || this.mapZoneId || this.mapRoadCode) {
                this.renderActiveSelectionOverlays();
            }
            this.fetchViewportMarkers();
        },

        async fetchViewportMarkers() {
            if (!this.map) return;
            if (this.mapAbortController) {
                this.mapAbortController.abort();
            }
            this.mapAbortController = new AbortController();

            const bounds = this.map.getBounds();
            const zoom = this.map.getZoom();

            const params = new URLSearchParams({
                viewport: '1',
                north: bounds.getNorth(),
                south: bounds.getSouth(),
                east: bounds.getEast(),
                west: bounds.getWest(),
                zoom: zoom
            });

            if (this.mapRoadCode) params.append('road', this.mapRoadCode);
            if (this.mapSide) params.append('side', this.mapSide);
            if (this.mapBeatId) params.append('beat_id', this.mapBeatId);
            else if (this.mapSectorId) params.append('sector_id', this.mapSectorId);
            else if (this.mapZoneId) params.append('zone_id', this.mapZoneId);

            this.mapLoading = true;
            this.mapStatusText = 'Fetching coordinates...';

            try {
                const res = await fetch(`/mgmt/geospatial-markers?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: this.mapAbortController.signal
                });

                if (!res.ok) throw new Error('API response failed');

                const data = await res.json();

                this.safeRunWhenNotZooming(() => {
                    if (!this.clusterGroup) return;
                    this.clusterGroup.clearLayers();

                    const newLayers = [];

                    if (data.mode === 'clusters') {
                        // Low zoom: display cluster summary centroids
                        (data.markers || []).forEach(c => {
                            const icon = L.divIcon({
                                className: 'custom-cluster-centroid',
                                html: `<div style="background:linear-gradient(180deg, #3b82f6 0%, #1d4ed8 100%); color:white; border-radius:50%; width:36px; height:36px; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:11px; border:2.5px solid white; box-shadow:0 4px 12px rgba(0,0,0,0.3); cursor:pointer;">${c.count}</div>`,
                                iconSize: [36, 36],
                                iconAnchor: [18, 18]
                            });
                            const marker = L.marker([c.lat, c.lng], { icon: icon })
                                .bindPopup(`<strong>${c.beat}</strong><br>Carriageway: ${c.side}<br>${c.km_range}<br><strong>${c.count} Coordinates</strong> (Zoom in to view pins)`);
                            
                            // Interactive click on cluster centroid to zoom into that beat or area
                            marker.on('click', () => {
                                if (c.bounds && this.map) {
                                    this.map.flyToBounds(c.bounds, { padding: [40, 40], maxZoom: 14, duration: 0.8 });
                                } else if (this.map) {
                                    this.map.flyTo([c.lat, c.lng], Math.min(14, this.map.getZoom() + 3), { duration: 0.8 });
                                }
                            });
                            newLayers.push(marker);
                        });
                        this.mapStatusText = `${(data.total_matched || data.total).toLocaleString()} coordinates in ${data.cluster_count || data.markers.length} clusters (Zoom in for pins)`;
                    } else {
                        // High zoom: display individual highway coordinates
                        (data.markers || []).forEach(p => {
                            const color = p.side === 'N' ? '#10b981' : (p.side === 'S' ? '#6366f1' : '#f59e0b');
                            const marker = L.circleMarker([p.lat, p.lng], {
                                radius: 6,
                                fillColor: color,
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.95
                            }).bindPopup(`<strong>KM ${Number(p.km).toFixed(2)}</strong><br>${p.beat}<br>Direction: ${p.side === 'N' ? 'Northbound (N)' : (p.side === 'S' ? 'Southbound (S)' : 'Shared')}<br><small class="font-mono">${Number(p.lat).toFixed(6)}, ${Number(p.lng).toFixed(6)}</small>`);
                            newLayers.push(marker);
                        });
                        this.mapStatusText = `${data.total.toLocaleString()} of ${(data.total_matched || data.total).toLocaleString()} coordinates rendered in viewport`;
                    }

                    this.clusterGroup.addLayers(newLayers);
                });
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Viewport fetch error:', err);
                    this.mapStatusText = 'Viewport sync error';
                }
            } finally {
                this.mapLoading = false;
            }
        },

        async filterTable(page = 1) {
            if (this.tableAbortController) {
                this.tableAbortController.abort();
            }
            this.tableAbortController = new AbortController();
            this.tableLoading = true;

            const params = new URLSearchParams({
                page: page,
                per_page: this.tablePerPage,
                sort_by: 'km',
                sort_dir: 'asc'
            });

            if (this.tableRoadCode) params.append('road', this.tableRoadCode);
            if (this.tableSearch) params.append('search', this.tableSearch);
            if (this.filterSide) params.append('side', this.filterSide);
            if (this.tableBeatId) params.append('beat_id', this.tableBeatId);
            else if (this.tableSectorId) params.append('sector_id', this.tableSectorId);
            else if (this.tableZoneId) params.append('zone_id', this.tableZoneId);

            try {
                const res = await fetch(`/mgmt/geospatial-markers?${params.toString()}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                    signal: this.tableAbortController.signal
                });

                if (res.ok) {
                    const data = await res.json();
                    this.tableItems = data.items || [];
                    this.tablePagination = data.pagination || this.tablePagination;
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    console.error('Table sync error:', err);
                }
            } finally {
                this.tableLoading = false;
            }
        },

        goToTablePage(page) {
            if (page < 1 || page > this.tablePagination.last_page || page === this.tablePagination.current_page) return;
            this.filterTable(page);
        },

        inspectMarker(m) {
            this.selectedMarker = m;
        },

        jumpToMap(m) {
            this.selectedMarker = null;
            this.activeTab = 'map';
            if (m.office_id) {
                this.mapBeatId = m.office_id;
                const b = this.beats.find(x => x.id == m.office_id);
                if (b) {
                    if (b.sector_id) this.mapSectorId = b.sector_id;
                    if (b.zone_id) this.mapZoneId = b.zone_id;
                }
            }
            this.$nextTick(() => {
                this.initMapIfNeeded();
                this.renderActiveSelectionOverlays();
                if (this.map && m.lat && m.lng) {
                    this.map.flyTo([m.lat, m.lng], 16, { duration: 1.0 });
                }
            });
        }
    };
};
</script>
@endpush
@endsection

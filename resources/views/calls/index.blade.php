@extends('layouts.app')

@section('title', 'Help Management - NHMP 130')

@section('page-title', 'Help Management')

@section('content')

<script id="calls-index-data" type="application/json">
    {!! json_encode([
        'items' => \App\Http\Resources\CallListResource::collection($calls->items())->resolve(),
        'per_page' => $calls->perPage(),
        'current_page' => $calls->currentPage(),
        'last_page' => $calls->lastPage(),
        'total' => $calls->total(),
        'stats' => $stats,
        'zones' => $zones,
        'sectors' => $sectors,
        'beats' => $beats,
        'callTypes' => $callTypes,
        'callSubTypes' => $callSubTypes,
        'agents' => $agents,
        'assignment' => request('assignment', in_array(request('status'), ['assigned', 'unassigned']) ? request('status') : ''),
        'status' => in_array(request('status'), ['assigned', 'unassigned']) ? 'all' : request('status', 'all'),
        'gender' => request('gender', ''),
        'sort' => request('sort', 'created_at'),
        'direction' => request('direction', 'desc'),
        'userRole' => auth()->user()->role?->name,
        'userScope' => auth()->user()->activeScopes->map(fn($s) => [
            'type' => $s->office?->type ?? 'national',
            'id' => $s->office_id
        ])->first(),
        'permissions' => [
            'create' => auth()->user()->hasPermission('calls.create'),
            'calls' => [
                'manage_status' => auth()->user()->hasPermission('calls.manage_status'),
                'update' => auth()->user()->hasPermission('calls.update'),
                'delete' => auth()->user()->hasPermission('calls.delete'),
            ],
            'reports' => [
                'view' => auth()->user()->hasPermission('reports.view'),
            ]
        ]
    ]) !!}
</script>
<script>
    window._indexData = JSON.parse(document.getElementById('calls-index-data').textContent);
</script>

<style>
    .condensed-table td, .condensed-table th {
        padding-top: 0.75rem !important;
        padding-bottom: 0.75rem !important;
    }
    .animate-pulse {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: .5; }
    }
    @keyframes pinkEmergencyBlink {
        0%, 100% {
            background-color: rgba(255, 241, 242, 0.95);
            box-shadow: inset 0 0 15px rgba(244, 63, 94, 0.25), 0 0 15px rgba(244, 63, 94, 0.2);
            border-left-color: #f43f5e;
        }
        50% {
            background-color: rgba(254, 205, 211, 0.65);
            box-shadow: inset 0 0 25px rgba(225, 29, 72, 0.5), 0 0 25px rgba(225, 29, 72, 0.4);
            border-left-color: #e11d48;
        }
    }
    .row-women-pending-blink {
        animation: pinkEmergencyBlink 1.4s ease-in-out infinite !important;
        border-left: 5px solid #e11d48 !important;
    }
    .card-women-pending-blink {
        animation: pinkEmergencyBlink 1.4s ease-in-out infinite !important;
        border: 2px solid #f43f5e !important;
    }
    .custom-scrollbar::-webkit-scrollbar {
        height: 8px;
        width: 8px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f8fafc;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
</style>

<div x-data="callManager(window._indexData)" 
     class="space-y-6 relative mt-0 max-w-[1700px] mx-auto" 
     x-cloak>
        <!-- Floating Sidebar Toggle -->
        <button @click="showSidebar = true; loadFilterOptions()" 
            x-show="!showSidebar"
            x-transition:enter="transition ease-out duration-500 delay-100"
            x-transition:enter-start="translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            class="fixed bottom-20 right-0 z-40 bg-gradient-to-b from-blue-600 to-blue-900 text-white p-2.5 py-6 rounded-l-2xl shadow-[0_0_30px_-5px_rgba(30,58,138,0.4)] hover:shadow-[-5px_0_40px_-5px_rgba(30,58,138,0.7)] hover:pr-4 transition-all duration-300 flex flex-col items-center gap-4 cursor-pointer" title="Open Intelligence Filters">
            <i class="fas fa-sliders-h drop-shadow-lg text-sm"></i>
            <span style="writing-mode: vertical-rl;" class="text-[9px] font-black uppercase tracking-[0.3em] rotate-180 drop-shadow-md text-blue-50">Help Filters</span>
        </button>

        <!-- EMERGENCY WOMEN SAFETY ALERT BANNER -->
        <div x-show="hasPendingWomen()"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 -translate-y-4"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-4"
             class="bg-gradient-to-r from-rose-600 via-pink-600 to-rose-700 text-white rounded-2xl xl:rounded-3xl p-4 sm:p-5 shadow-[0_15px_35px_rgba(225,29,72,0.35)] border-2 border-rose-400 flex flex-wrap items-center justify-between gap-4 animate-pulse relative overflow-hidden"
             id="women-emergency-alert-banner">
            <div class="flex items-center gap-3.5 relative z-10">
                <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shrink-0 shadow-lg">
                    <i class="fa-solid fa-person-dress text-2xl text-rose-100 animate-bounce"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full bg-white text-rose-700 text-[10px] font-black tracking-widest uppercase shadow-sm">Critical Attention</span>
                        <h3 class="text-sm sm:text-base font-black tracking-tight text-white uppercase">
                            Pending Women Safety Emergency Awaiting Initialization
                        </h3>
                    </div>
                    <p class="text-xs text-rose-100 font-medium mt-0.5">
                        Continuous pink beacon and voice command alerting beat, sector & zone operators until call is initialized.
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2.5 relative z-10">
                <button @click="toggleAlarmMute()" 
                        type="button"
                        class="px-3.5 py-2 rounded-xl bg-white/15 hover:bg-white/25 active:scale-95 backdrop-blur-md text-white font-extrabold text-xs flex items-center gap-2 border border-white/30 transition-all cursor-pointer shadow-sm">
                    <i class="fa-solid" :class="alarmMuted ? 'fa-volume-xmark text-rose-200' : 'fa-volume-high text-emerald-300 animate-pulse'"></i>
                    <span x-text="alarmMuted ? 'Unmute Audio Alarm' : 'Audio Alarm Active'"></span>
                </button>
                <button @click="filterGender = 'female'; filterStatus = 'pending'; page = 1; triggerFetch()" 
                        type="button"
                        class="px-4 py-2 rounded-xl bg-white text-rose-700 hover:bg-rose-50 active:scale-95 font-black text-xs shadow-lg transition-all cursor-pointer flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-rose-600"></i>
                    <span>Focus Pending Calls</span>
                </button>
            </div>
        </div>

        <!-- STATS CARDS -->
        <!-- STATUS STATS — STRICT SINGLE ROW (6 COLUMNS) -->
        <div class="grid grid-cols-6 gap-2.5 sm:gap-3 xl:gap-4 pt-6 w-full" style="grid-template-columns: repeat(6, minmax(0, 1fr));">
            <!-- 1. ASSIGNED CALLS CARD -->
            <div @click="filterAssignment = (filterAssignment === 'assigned' ? '' : 'assigned'); filterStatus = 'all'; page = 1; triggerFetch()" 
                 :class="filterAssignment === 'assigned' ? 'card-3d-active blue' : ''" 
                 class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group"
                 id="btn-filter-assigned">
                <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-blue-600 shadow-[0_8px_16px_rgba(37,99,235,0.2)] z-10">
                    <i class="fa-solid fa-map-location-dot text-xs sm:text-sm text-white"></i>
                </div>
                <div class="p-2.5 sm:p-3 xl:p-4 text-right pt-2 relative">
                    <p class="text-[8px] sm:text-[9px] font-black tracking-widest text-blue-600 uppercase truncate">Assigned</p>
                    <div class="flex items-center justify-end gap-1.5 sm:gap-2 mt-1">
                        <span class="text-[8px] sm:text-[10px] font-black text-white bg-blue-600 px-1.5 sm:px-2 py-0.5 rounded-full shadow-sm shrink-0" x-text="(stats.assigned_percent || 0) + '%'"></span>
                        <h4 class="text-lg sm:text-2xl xl:text-3xl font-extrabold text-blue-900 truncate" x-text="stats.assigned || 0"></h4>
                    </div>
                </div>
            </div>

            <!-- 2. WOMEN SAFETY CARD -->
            <div @click="filterGender = (filterGender === 'female' ? '' : 'female'); page = 1; triggerFetch()" 
                 :class="filterGender === 'female' ? 'card-3d-active rose' : ''" 
                 class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group"
                 id="btn-filter-women">
                <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-gradient-to-br from-rose-500 to-pink-600 shadow-[0_8px_16px_rgba(244,63,94,0.25)] z-10">
                    <i class="fa-solid fa-person-dress text-xs sm:text-base text-white"></i>
                </div>
                <div class="p-2.5 sm:p-3 xl:p-4 text-right pt-2 relative">
                    <p class="text-[8px] sm:text-[9px] font-black tracking-widest text-rose-600 uppercase truncate">Women Safety</p>
                    <div class="flex items-center justify-end gap-1.5 sm:gap-2 mt-1">
                        <template x-if="stats.pending_women > 0">
                            <span class="text-[8px] sm:text-[9px] font-black text-rose-600 bg-rose-50 border border-rose-200 px-1.5 py-0.5 rounded-full animate-pulse shrink-0" x-text="stats.pending_women + ' PENDING'"></span>
                        </template>
                        <span class="text-[8px] sm:text-[10px] font-black text-white bg-rose-600 px-1.5 sm:px-2 py-0.5 rounded-full shadow-sm shrink-0" x-text="(stats.women_calls_percent || 0) + '%'"></span>
                        <h4 class="text-lg sm:text-2xl xl:text-3xl font-extrabold text-blue-900 truncate" x-text="stats.women_calls || 0"></h4>
                    </div>
                </div>
            </div>

            <!-- 3. PENDING OPS CARD -->
            <div @click="filterStatus = (filterStatus === 'pending' ? 'all' : 'pending'); page = 1; triggerFetch()" 
                 :class="filterStatus === 'pending' ? 'card-3d-active rose' : ''" 
                 class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group"
                 id="btn-filter-pending">
                <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-rose-600 shadow-[0_8px_16px_rgba(225,29,72,0.2)] z-10">
                    <i class="fa-solid fa-hourglass-start text-xs sm:text-sm text-white"></i>
                </div>
                <div class="p-2.5 sm:p-3 xl:p-4 text-right pt-2 relative">
                    <p class="text-[8px] sm:text-[9px] font-black tracking-widest text-rose-500 uppercase truncate">Pending</p>
                    <div class="flex items-center justify-end gap-1.5 sm:gap-2 mt-1">
                        <span class="text-[8px] sm:text-[10px] font-black text-white bg-rose-600 px-1.5 sm:px-2 py-0.5 rounded-full shadow-sm shrink-0" x-text="stats.pending_percent + '%'"></span>
                        <h4 class="text-lg sm:text-2xl xl:text-3xl font-extrabold text-blue-900 truncate" x-text="stats.pending"></h4>
                    </div>
                </div>
            </div>

            <!-- 4. ACTIVE FIELD CARD -->
            <div @click="filterStatus = (filterStatus === 'in_progress' ? 'all' : 'in_progress'); page = 1; triggerFetch()" 
                 :class="filterStatus === 'in_progress' ? 'card-3d-active amber' : ''" 
                 class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group"
                 id="btn-filter-inprogress">
                <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-amber-500 shadow-[0_8px_16px_rgba(245,158,11,0.2)] z-10">
                    <i class="fa-solid fa-person-running text-xs sm:text-sm text-white"></i>
                </div>
                <div class="p-2.5 sm:p-3 xl:p-4 text-right pt-2 relative">
                    <p class="text-[8px] sm:text-[9px] font-black tracking-widest text-amber-500 uppercase truncate">Active Field</p>
                    <div class="flex items-center justify-end gap-1.5 sm:gap-2 mt-1">
                        <span class="text-[8px] sm:text-[10px] font-black text-white bg-amber-500 px-1.5 sm:px-2 py-0.5 rounded-full shadow-sm shrink-0" x-text="stats.in_progress_percent + '%'"></span>
                        <h4 class="text-lg sm:text-2xl xl:text-3xl font-extrabold text-blue-900 truncate" x-text="stats.in_progress"></h4>
                    </div>
                </div>
            </div>

            <!-- 5. TIGER REACHED CARD -->
            <div @click="filterStatus = (filterStatus === 'tiger_reached' ? 'all' : 'tiger_reached'); page = 1; triggerFetch()" 
                 :class="filterStatus === 'tiger_reached' ? 'card-3d-active sky' : ''" 
                 class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group"
                 id="btn-filter-tiger">
                <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-sky-500 shadow-[0_8px_16px_rgba(14,165,233,0.25)] z-10">
                    <i class="fa-solid fa-location-dot text-xs sm:text-sm text-white"></i>
                </div>
                <div class="p-2.5 sm:p-3 xl:p-4 text-right pt-2 relative">
                    <p class="text-[8px] sm:text-[9px] font-black tracking-widest text-sky-500 uppercase truncate">Reached</p>
                    <div class="flex items-center justify-end gap-1.5 sm:gap-2 mt-1">
                        <span class="text-[8px] sm:text-[10px] font-black text-white bg-sky-500 px-1.5 sm:px-2 py-0.5 rounded-full shadow-sm shrink-0" x-text="stats.tiger_reached_percent + '%'"></span>
                        <h4 class="text-lg sm:text-2xl xl:text-3xl font-extrabold text-blue-900 truncate" x-text="stats.tiger_reached"></h4>
                    </div>
                </div>
            </div>

            <!-- 6. RESOLVED UNITS CARD -->
            <div @click="filterStatus = (filterStatus === 'completed' ? 'all' : 'completed'); page = 1; triggerFetch()" 
                 :class="filterStatus === 'completed' ? 'card-3d-active emerald' : ''" 
                 class="relative flex flex-col bg-white rounded-2xl xl:rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer group"
                 id="btn-filter-completed">
                <div class="absolute -top-3 sm:-top-4 left-3 sm:left-6 h-8 w-8 sm:h-10 sm:w-10 xl:h-12 xl:w-12 grid place-items-center rounded-xl bg-emerald-600 shadow-[0_8px_16px_rgba(16,185,129,0.2)] z-10">
                    <i class="fa-solid fa-check-double text-xs sm:text-sm text-white"></i>
                </div>
                <div class="p-2.5 sm:p-3 xl:p-4 text-right pt-2 relative">
                    <p class="text-[8px] sm:text-[9px] font-black tracking-widest text-emerald-500 uppercase truncate">Resolved</p>
                    <div class="flex items-center justify-end gap-1.5 sm:gap-2 mt-1">
                        <span class="text-[8px] sm:text-[10px] font-black text-white bg-emerald-600 px-1.5 sm:px-2 py-0.5 rounded-full shadow-sm shrink-0" x-text="stats.completed_percent + '%'"></span>
                        <h4 class="text-lg sm:text-2xl xl:text-3xl font-extrabold text-blue-900 truncate" x-text="stats.completed"></h4>
                    </div>
                </div>
            </div>
        </div>

        <!-- CATEGORY FILTERS (LOGICAL COLORS) — STRICT SINGLE ROW (6 COLUMNS) -->
        <div class="grid grid-cols-6 gap-2.5 sm:gap-3 xl:gap-4 mt-6 w-full" style="grid-template-columns: repeat(6, minmax(0, 1fr));">
            <template x-for="cat in stats.categories" :key="cat.id">
                <div @click="filterType = (filterType == cat.id ? '' : cat.id); page = 1; triggerFetch()" 
                     :class="filterType == cat.id ? 'card-3d-active ' + (
                        cat.name.toLowerCase().includes('emergency') ? 'rose' : 
                        cat.name.toLowerCase().includes('complaint') ? 'amber' : 
                        cat.name.toLowerCase().includes('information') ? 'sky' : 
                        cat.name.toLowerCase().includes('crime') ? 'indigo' : 
                        cat.name.toLowerCase().includes('junk') ? 'slate' : 'blue'
                     ) : 'hover:border-blue-200'"
                     class="bg-white p-2.5 sm:p-3 xl:p-4 rounded-2xl xl:rounded-[2rem] border border-slate-100 shadow-[0_10px_30px_rgba(0,0,0,0.02)] transition-all duration-300 cursor-pointer relative group overflow-hidden">
                    <div class="flex justify-between items-start mb-2 sm:mb-3 xl:mb-4 relative z-10">
                        <div class="h-7 w-7 sm:h-8 sm:w-8 xl:h-10 xl:w-10 rounded-xl flex items-center justify-center text-white shadow-lg shrink-0"
                             :class="
                                cat.name.toLowerCase().includes('emergency') ? 'bg-rose-600 shadow-rose-200' : 
                                cat.name.toLowerCase().includes('complaint') ? 'bg-amber-500 shadow-amber-200' : 
                                cat.name.toLowerCase().includes('information') ? 'bg-sky-500 shadow-sky-200' : 
                                cat.name.toLowerCase().includes('crime') ? 'bg-indigo-900 shadow-indigo-200' : 
                                cat.name.toLowerCase().includes('junk') ? 'bg-slate-500 shadow-slate-200' : 'bg-blue-600 shadow-blue-200'
                             ">
                            <i :class="'fas ' + cat.icon + ' text-xs'"></i>
                        </div>
                        <span class="text-[8px] sm:text-[9px] xl:text-[10px] font-black px-1.5 sm:px-2 py-0.5 rounded-lg text-white shrink-0" 
                              :class="
                                cat.name.toLowerCase().includes('emergency') ? 'bg-rose-600' : 
                                cat.name.toLowerCase().includes('complaint') ? 'bg-amber-500' : 
                                cat.name.toLowerCase().includes('information') ? 'bg-sky-500' : 
                                cat.name.toLowerCase().includes('crime') ? 'bg-indigo-900' : 
                                cat.name.toLowerCase().includes('junk') ? 'bg-slate-500' : 'bg-blue-600'
                              "
                              x-text="cat.percent + '%'"></span>
                    </div>
                    <p class="text-[8px] sm:text-[9px] xl:text-[10px] font-black text-slate-400 uppercase tracking-widest truncate mb-0.5 sm:mb-1" x-text="cat.name"></p>
                    <h5 class="text-base sm:text-xl xl:text-3xl font-black text-blue-900 truncate" x-text="cat.count"></h5>
                </div>
            </template>
        </div>

        <!-- MAIN CONTROL PANEL -->
        <div class="grid lg:grid-cols-12 gap-8 relative pb-20">
            <div class="transition-all duration-500" :class="showSidebar ? 'lg:col-span-9' : 'lg:col-span-12'">
                <div class="bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-gray-100 overflow-hidden flex flex-col relative z-20 mt-8">


                    <!-- Grid View -->
                    <div x-show="viewMode === 'grid'" x-transition class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                            <template x-for="item in pagedItems" :key="item.id">
                                <div :class="isPendingWomen(item) ? 'card-women-pending-blink' : 'bg-white border-slate-100 hover:-translate-y-2'"
                                     class="rounded-[2.5rem] shadow-xl border p-8 transition-all duration-300 group relative overflow-hidden flex flex-col justify-between">
                                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform duration-500">
                                        <i class="fa-solid fa-headset text-9xl text-blue-900"></i>
                                    </div>
                                    
                                    <div>
                                        <template x-if="isPendingWomen(item)">
                                            <div class="mb-4">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-600 text-white text-[10px] font-black uppercase tracking-wider shadow-md animate-bounce">
                                                    <i class="fa-solid fa-person-dress"></i>
                                                    <span>WOMEN SOS PENDING</span>
                                                </span>
                                            </div>
                                        </template>
                                        <div class="flex items-start justify-between mb-8 relative z-10">
                                            <div class="w-14 h-14 rounded-2xl bg-slate-50 border-2 border-slate-100 flex items-center justify-center text-blue-900 shadow-sm group-hover:bg-blue-600 group-hover:text-white transition-all duration-500">
                                                <i class="fa-solid fa-satellite-dish text-xl"></i>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase mb-1">Status</div>
                                                <div x-html="getStatusBadge(item.status)"></div>
                                            </div>
                                        </div>

                                        <div class="relative z-10">
                                            <h4 class="text-2xl font-extrabold text-blue-900 tracking-tighter leading-none mb-1 group-hover:text-blue-600 transition-colors" x-text="formatTitleCase(item.caller_name)"></h4>
                                            <span class="text-xs font-black text-blue-900 uppercase" x-text="item.caller_number"></span>
                                            <div class="flex items-center gap-2 mb-6">
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest" x-text="'Time: ' + formatTime(item.created_at)"></span>
                                                <span class="w-1 h-1 rounded-full bg-slate-200"></span>
                                                <span class="text-[9px] font-black text-blue-500 uppercase tracking-widest" x-text="'Date: ' + formatDate(item.created_at)"></span>
                                            </div>

                                            <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 mb-8">
                                                <div class="space-y-4">
                                                    <div class="flex flex-col">
                                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Category</span>
                                                        <span class="text-xs font-black text-blue-900 uppercase" x-text="item.call_type?.name || 'UNCATEGORIZED'"></span>
                                                        <span class="text-[10px] font-bold text-slate-400 italic" x-text="item.call_sub_type?.name || 'GENERAL RESOLUTION'"></span>
                                                    </div>
                                                    <div class="flex flex-col pt-4 border-t border-slate-200/50">
                                                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-1">Beat Assigned: </span>
                                                        <div class="flex items-center gap-2 text-[10px] font-bold text-blue-900">
                                                            <i class="fa-solid fa-map-pin text-blue-400"></i>
                                                            <span x-text="getLocationHierarchy(item)"></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pt-5 border-t border-slate-100 space-y-3 relative z-10">
                                        <!-- Asset row -->
                                        <div class="flex items-center justify-between">
                                            <div x-html="getPriorityBadge(item.priority)"></div>
                                        </div>

                                        <!-- Action Grid -->
                                        <div class="grid grid-cols-4 gap-2">
                                            <!-- View Details -->
                                            <button @click="openDetailModal(item)" title="View Intelligence" class="w-9 h-9 bg-blue-500 border border-blue-600 text-white hover:bg-blue-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                <i class="fas fa-eye text-xs"></i>
                                            </button>

                                            <!-- Contextual Action -->
                                            <template x-if="item.status.toLowerCase() === 'pending' && (item.beat_id || item.office_id) && permissions.calls.manage_status">
                                                <button @click="openTransitionModal(item, 'in_progress')" title="Initiate Help / Dispatch Tiger" class="w-9 h-9 rounded-xl bg-amber-500 border border-amber-600 text-white hover:bg-amber-600 active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                    <i class="fa-solid fa-bolt text-xs"></i>
                                                </button>
                                            </template>
                                            <template x-if="item.status.toLowerCase() === 'in_progress' && (item.beat_id || item.office_id) && permissions.calls.manage_status">
                                                <button @click="openTransitionModal(item, 'tiger_reached')" title="Mark Tiger Reached" class="w-9 h-9 rounded-xl bg-sky-500 border border-sky-600 text-white hover:bg-sky-600 active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                    <i class="fa-solid fa-location-dot text-xs"></i>
                                                </button>
                                            </template>
                                            <template x-if="item.status.toLowerCase() === 'tiger_reached' && (item.beat_id || item.office_id) && permissions.calls.manage_status">
                                                <button @click="openTransitionModal(item, 'completed')" title="Assistance Provided / Complete Help" class="w-9 h-9 rounded-xl bg-emerald-600 border border-emerald-700 text-white hover:bg-emerald-600 active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                    <i class="fa-solid fa-check text-xs"></i>
                                                </button>
                                            </template>

                                            <!-- Agent/Supervisor Reminder -->
                                            <template x-if="['agent', 'agent_supervisor', 'operation_admin', 'super_admin'].includes(userRole) && ['pending', 'in_progress', 'tiger_reached'].includes(item.status) && item.office_id">
                                                <button @click="openReminderModal(item)" title="Send Reminder" class="w-9 h-9 bg-emerald-500 border border-emerald-600 text-white hover:bg-emerald-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                    <i class="fas fa-bell text-xs"></i>
                                                </button>
                                            </template>

                                            <!-- Locked State -->
                                            <template x-if="['completed', 'forwarded', 'junk'].includes(item.status)">
                                                <div class="w-9 h-9 bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center rounded-xl opacity-50 cursor-not-allowed mx-auto aspect-square shrink-0">
                                                    <i class="fas fa-lock text-xs"></i>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Table View -->
                    <div x-show="viewMode === 'table'" x-transition class="overflow-x-auto shadow-inner bg-white rounded-3xl border border-slate-100 custom-scrollbar">
                        <table class="w-full text-left min-w-[1400px] border-collapse" :class="density === 'condensed' ? 'condensed-table' : 'spacious-table'">
                            <thead class="bg-blue-50 border-b border-blue-100">
                                <tr>
                                    <th class="px-3 py-3">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fa-solid fa-satellite-dish text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('created_at')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                ID / TIME
                                                <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('created_at')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    @if(!in_array(auth()->user()->role?->name, ['zone_admin', 'sector_admin', 'beat_operator']))
                                    <th class="px-2 py-3">
                                        <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fa-solid fa-user-tie text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('agent_id')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                Agent
                                                <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('agent_id')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    @endif
                                    <th class="px-2 py-3">
                                        <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fa-solid fa-tags text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('call_type_id')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                Category
                                                <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('call_type_id')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-2 py-3">
                                        <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fa-solid fa-road text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('beat_id')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                Zone / Sector / Beat
                                                <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('beat_id')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-2 py-3">
                                        <div class="flex items-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fa-solid fa-user text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('caller_name')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                Caller Info
                                                <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('caller_name')"></i>
                                            </button>
                                        </div>
                                    </th>

                                    <th class="px-3 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] group cursor-pointer" @click="sortByItem('vehicle_no')">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-emerald-100">
                                                <i class="fa-solid fa-car text-[10px]"></i>
                                            </div>
                                            <span>Vehicle Info<i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('vehicle_no')"></i></span>
                                        </div>
                                    </th>
                                    <th class="px-3 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fa-solid fa-flag text-[10px]"></i>
                                            </div>
                                            <span class="cursor-pointer group flex items-center gap-1.5" @click="sortByItem('priority')">Priority <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('priority')"></i></span>
                                        </div>
                                    </th>
                                    <th class="px-3 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fa-solid fa-shield-halved text-[10px]"></i>
                                            </div>
                                            <span class="cursor-pointer group flex items-center gap-1.5" @click="sortByItem('status')">Status <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('status')"></i></span>
                                        </div>
                                    </th>
                                    <th class="px-3 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fa-solid fa-bolt text-[10px]"></i>
                                            </div>
                                            <span>Actions</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <template x-for="item in pagedItems" :key="item.id">
                                    <tr :class="isPendingWomen(item) ? 'row-women-pending-blink' : 'hover:bg-blue-50/40 border-l-4 border-transparent hover:border-blue-600'" 
                                        class="transition-colors group cursor-pointer" 
                                        @click="window.location='/calls/' + (item.uuid || item.id)">
                                        <td class="px-2 py-3">
                                            <div class="flex flex-col gap-1">
                                                <template x-if="isPendingWomen(item)">
                                                    <div class="flex items-center gap-1.5 mb-1">
                                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-rose-600 text-white text-[9px] font-black uppercase tracking-wider shadow-md animate-bounce">
                                                            <i class="fa-solid fa-person-dress"></i>
                                                            <span>WOMEN SOS PENDING</span>
                                                        </span>
                                                    </div>
                                                </template>
                                                <div class="flex items-center gap-1.5">
                                                    <span class="font-black text-blue-900 text-xs tracking-tighter uppercase group-hover:text-blue-600 transition-colors" x-text="item.call_number"></span>
                                                    <template x-if="item.call_reminder_count > 0">
                                                        <span class="px-1.5 py-0.5 rounded-full bg-rose-100 text-rose-600 text-[8px] font-black border border-rose-200" x-text="item.call_reminder_count"></span>
                                                    </template>
                                                </div>
                                                <div class="flex items-center gap-2 opacity-70">
                                                    <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest" x-text="formatTime(item.created_at)"></span>
                                                    <span class="text-[8px] font-bold text-blue-600 uppercase tracking-widest" x-text="formatDate(item.created_at)"></span>
                                                </div>
                                                <template x-if="item.last_reminder_at">
                                                    <div class="flex items-center gap-1">
                                                        <i class="fa-solid fa-bell text-[7px] text-rose-500 animate-pulse"></i>
                                                        <span class="text-[7px] font-black text-rose-600 uppercase tracking-tight" x-text="'Reminded: ' + formatTime(item.last_reminder_at)"></span>
                                                    </div>
                                                </template>
                                            </div>
                                        </td>
                                        @if(!in_array(auth()->user()->role?->name, ['zone_admin', 'sector_admin', 'beat_operator']))
                                        <td class="px-2 py-3">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-black text-blue-900 uppercase" x-text="item.agent?.full_name || 'System'"></span>
                                                <span class="text-[9px] font-bold text-slate-400 uppercase italic">Call center Agent</span>
                                            </div>
                                        </td>
                                        @endif
                                        <td class="px-2 py-3">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-black text-blue-900 uppercase" x-text="item.call_type?.name || 'UNCATEGORIZED'"></span>
                                                <span class="text-[9px] font-bold text-slate-400 uppercase italic" x-text="item.call_sub_type?.name || 'GENERAL'"></span>
                                            </div>
                                        </td>
                                        <td class="px-2 py-3">
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-2 text-[10px] font-bold text-blue-900">
                                                    <i class="fa-solid fa-map-pin text-[8px] text-blue-400"></i>
                                                    <span x-text="getLocationHierarchy(item)"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-3">
                                            <div class="flex flex-col">
                                                <div class="flex items-center gap-1.5">
                                                    <span class="text-xs font-black text-blue-900 uppercase" x-text="formatTitleCase(item.caller_name) || 'Anonymous'"></span>
                                                    <template x-if="item.caller_gender === 'female' || item.caller_gender === 'women' || item.call_type_id === 10">
                                                        <span class="px-1.5 py-0.5 rounded text-[8px] font-black bg-rose-100 text-rose-700 uppercase shrink-0"><i class="fa-solid fa-person-dress mr-0.5"></i> Female</span>
                                                    </template>
                                                </div>
                                                <span class="text-[10px] font-black text-blue-900 uppercase tracking-widest" x-text="item.caller_number"></span>
                                            </div>
                                        </td>

                                        <td class="px-3 py-3 text-center">
                                            <div class="flex flex-col items-center">
                                                <span class="text-xs font-black text-blue-900 uppercase" x-text="item.vehicle_no || '---'"></span>
                                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mt-0.5" x-text="item.vehicle_type?.name || '---'"></span>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <div x-html="getPriorityBadge(item.priority, true)"></div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 text-center">
                                            <div class="flex flex-col items-center justify-center gap-2">
                                                <div x-html="getStatusBadge(item.status)"></div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-3" @click.stop>
                                            <div class="flex items-center justify-center gap-1.5 w-[120px] mx-auto">
                                                <!-- View Details -->
                                                <button @click="openDetailModal(item)" title="View Intelligence" class="w-9 h-9 bg-blue-500 border border-blue-600 text-white hover:bg-blue-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>

                                                 <!-- Initialize Mission (Pending -> In Progress) -->
                                                 <template x-if="item.status.toLowerCase() === 'pending' && (item.beat_id || item.office_id) && permissions.calls.manage_status">
                                                     <button @click="openTransitionModal(item, 'in_progress')" title="Initiate Help / Dispatch Tiger" class="w-9 h-9 bg-amber-500 border border-amber-600 text-white hover:bg-amber-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                         <i class="fas fa-bolt text-xs"></i>
                                                     </button>
                                                 </template>

                                                 <!-- Mark Tiger Reached (In Progress -> Tiger Reached) -->
                                                 <template x-if="item.status.toLowerCase() === 'in_progress' && (item.beat_id || item.office_id) && permissions.calls.manage_status">
                                                     <button @click="openTransitionModal(item, 'tiger_reached')" title="Mark Tiger Reached" class="w-9 h-9 bg-sky-500 border border-sky-600 text-white hover:bg-sky-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                         <i class="fa-solid fa-location-dot text-xs"></i>
                                                     </button>
                                                 </template>

                                                 <!-- Complete Help (Tiger Reached -> Completed) -->
                                                 <template x-if="item.status.toLowerCase() === 'tiger_reached' && (item.beat_id || item.office_id) && permissions.calls.manage_status">
                                                     <button @click="openTransitionModal(item, 'completed')" title="Assistance Provided / Complete Help" class="w-9 h-9 bg-emerald-600 border border-emerald-700 text-white hover:bg-emerald-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                         <i class="fas fa-check text-xs"></i>
                                                     </button>
                                                 </template>

                                                 <!-- Agent/Supervisor Reminder -->
                                                 <template x-if="['agent', 'agent_supervisor', 'operation_admin', 'super_admin'].includes(userRole) && ['pending', 'in_progress', 'tiger_reached'].includes(item.status) && item.office_id">
                                                     <button @click="openReminderModal(item)" title="Send Reminder" class="w-9 h-9 bg-emerald-500 border border-emerald-600 text-white hover:bg-emerald-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                         <i class="fas fa-bell text-xs"></i>
                                                     </button>
                                                 </template>

                                                 <!-- Locked State -->
                                                 <template x-if="['completed', 'forwarded', 'junk'].includes(item.status)">
                                                     <div class="w-9 h-9 bg-slate-100 border border-slate-200 text-slate-400 flex items-center justify-center rounded-xl opacity-50 cursor-not-allowed mx-auto aspect-square shrink-0">
                                                         <i class="fas fa-lock text-xs"></i>
                                                     </div>
                                                 </template>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div x-show="totalPages > 1" class="p-8 border-t border-slate-100 bg-slate-50/30 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs font-bold text-slate-400">
                            Showing <span class="text-blue-900" x-text="((page - 1) * perPage) + 1"></span> to <span class="text-blue-900" x-text="Math.min(page * perPage, totalRecords)"></span> of <span class="text-blue-900" x-text="totalRecords"></span> Trace Entities
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- First/Next -->
                            <button @click="page = 1" :disabled="page === 1" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 disabled:opacity-30 hover:bg-slate-50 transition-all font-black text-[10px] uppercase">First</button>
                            <button @click="page++" :disabled="page === totalPages" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 hover:bg-slate-50 transition-all"><i class="fa-solid fa-chevron-right"></i></button>
                            
                            <!-- Sliding Window -->
                            <template x-for="p in pagesToShow()" :key="p">
                                <div class="flex items-center">
                                    <template x-if="p === '...'">
                                        <span class="px-2 text-slate-300 font-bold">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button @click="page = p" :class="page === p ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-500/30' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" class="w-10 h-10 rounded-xl border font-black text-xs transition-all" x-text="p"></button>
                                    </template>
                                </div>
                            </template>

                            <!-- Prev/Last -->
                            <button @click="page--" :disabled="page === 1" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 hover:bg-slate-50 transition-all"><i class="fa-solid fa-chevron-left"></i></button>
                            <button @click="page = totalPages" :disabled="page === totalPages" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 disabled:opacity-30 hover:bg-slate-50 transition-all font-black text-[10px] uppercase">Last</button>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div x-show="totalRecords === 0" class="py-32 text-center bg-white">
                        <i class="fa-solid fa-satellite-dish text-6xl text-blue-100 mb-6 animate-pulse"></i>
                        <h3 class="text-2xl font-black text-blue-900">Zero Assistance Presence</h3>
                        <p class="text-slate-400 font-bold mt-2">No helps found matching the specified filter parameters.</p>
                        @can('calls.create')
                        <a href="{{ route('calls.create') }}" class="inline-flex items-center gap-2 mt-8 px-8 py-4 bg-blue-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-xl shadow-blue-600/20 hover:-translate-y-1 transition-all">
                            Initiate New Help
                        </a>
                        @endcan
                    </div>
                </div>
            </div>

            <!-- Right Column - Intelligence Filters -->
            <div class="lg:col-span-3 pb-8 transition-all duration-300" x-show="showSidebar" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-4" x-cloak>
                <div class="lg:sticky lg:top-4 lg:max-h-[calc(100vh-100px)] lg:overflow-y-auto bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-blue-600 shadow-sm">
                                <i class="fas fa-filter"></i>
                            </div>
                            <div>
                                <h2 class="font-extrabold text-blue-900 text-lg tracking-tight">Intelligence Filters</h2>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Control Panel</p>
                            </div>
                        </div>
                        <button @click="showSidebar = false" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-blue-900 shadow-sm flex items-center justify-center">
                            <i class="fas fa-angle-right"></i>
                        </button>
                    </div>

                    <div class="p-4 border-b border-slate-100 bg-blue-50/30 flex flex-col gap-4">
                        <div class="flex items-center justify-between">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Interface Mode</label>
                            <div class="flex items-center bg-white p-1 rounded-xl border border-slate-100 shadow-sm">
                                <button @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400'" class="w-8 h-8 flex items-center justify-center rounded-lg transition-all">
                                    <i class="fas fa-list-ul text-xs"></i>
                                </button>
                                <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400'" class="w-8 h-8 flex items-center justify-center rounded-lg transition-all">
                                    <i class="fas fa-th-large text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 space-y-6 w-full flex-1">
                        <!-- Loading Indicator for On-Demand Filter Options -->
                        <div x-show="isLoadingFilters" class="p-3 bg-blue-50 border border-blue-100 rounded-xl flex items-center gap-2.5 text-blue-700 text-xs shadow-sm animate-pulse" x-cloak>
                            <i class="fas fa-circle-notch fa-spin text-blue-600"></i>
                            <span class="font-bold text-[11px]">Syncing intelligence filter options...</span>
                        </div>

                        <!-- Search -->
                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-search text-blue-500"></i> Search
                            </label>
                            <div class="relative">
                                <input type="text" x-model="search" placeholder="Search ID/Caller/Vehicle..." class="w-full pl-11 pr-4 py-3 bg-white border-2 border-slate-100 rounded-xl focus:border-blue-600 outline-none font-bold text-blue-900 text-xs transition-all focus:shadow-lg focus:shadow-blue-500/10">
                                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                            </div>
                        </div>

                        
                        <!-- Agent Filter -->
                        <template x-if="agents.length > 0">
                            <div class="space-y-4 pt-4 border-t border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                    <i class="fa-solid fa-user-tie text-blue-500"></i> Responsible Agent
                                </label>
                                <select x-model="filterAgent" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-blue-500 transition-all">
                                    <option value="">All Agents</option>
                                    <template x-for="a in agents" :key="a.id">
                                        <option :value="a.id" x-text="a.username"></option>
                                    </template>
                                </select>
                            </div>
                        </template>

                        <!-- Date Range Filters -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-regular fa-calendar-days text-indigo-500"></i> Date & Time Range
                            </label>
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-1">Date From</label>
                                        <input type="date" x-model="dateFrom" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-indigo-500 transition-all">
                                    </div>
                                    <div>
                                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-1">Time From</label>
                                        <input type="time" x-model="timeFrom" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-indigo-500 transition-all">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-1">Date To</label>
                                        <input type="date" x-model="dateTo" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-indigo-500 transition-all">
                                    </div>
                                    <div>
                                        <label class="text-[8px] font-black text-slate-400 uppercase tracking-widest block mb-1">Time To</label>
                                        <input type="time" x-model="timeTo" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-indigo-500 transition-all">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-shield-virus text-emerald-500"></i> Status
                            </label>
                            <div class="grid grid-cols-1 gap-2">
                                <button @click="filterStatus = 'all'" :class="filterStatus === 'all' ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Total Helps</span>
                                    <i class="fas fa-globe-americas transition-opacity" :class="filterStatus === 'all' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'pending'" :class="filterStatus === 'pending' ? 'bg-rose-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Pending Only</span>
                                    <i class="fas fa-clock transition-opacity" :class="filterStatus === 'pending' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'in_progress'" :class="filterStatus === 'in_progress' ? 'bg-amber-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>In-Process (Active)</span>
                                    <i class="fas fa-bolt transition-opacity" :class="filterStatus === 'in_progress' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'tiger_reached'" :class="filterStatus === 'tiger_reached' ? 'bg-sky-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Tiger Reached</span>
                                    <i class="fas fa-location-dot transition-opacity" :class="filterStatus === 'tiger_reached' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'completed'" :class="filterStatus === 'completed' ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Completed</span>
                                    <i class="fas fa-check-double transition-opacity" :class="filterStatus === 'completed' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Taxonomy Matrix -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-solid fa-tags text-blue-500"></i> Category
                            </label>
                            <div class="space-y-3">
                                <select x-model="filterType" @change="filterSubType = ''" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-blue-500 transition-all">
                                    <option value="">Primary Category</option>
                                    <template x-for="t in callTypes" :key="t.id">
                                        <option :value="t.id" x-text="t.name"></option>
                                    </template>
                                </select>
                                <select x-model="filterSubType" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-blue-500 transition-all">
                                    <option value="">Secondary Sub-type</option>
                                    <template x-for="st in filteredSubTypesInFilter" :key="st.id">
                                        <option :value="st.id" x-text="st.name"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <!-- Geography Filters -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fa-solid fa-earth-asia text-indigo-500"></i> Zone, Sector, Beat
                            </label>
                            <div class="space-y-3">
                                <!-- Zone Selector -->
                                <div class="space-y-1">
                                    <select x-model="filterZone" @change="filterSector = ''; filterBeat = ''" 
                                        :disabled="userScope && !['national', 'call_centre'].includes(userScope.type) && !['super_admin', 'operation_admin', 'agent_supervisor', 'agent'].includes(userRole)"
                                        class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-indigo-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                        <option value="">Global Zones</option>
                                        <template x-for="z in zones" :key="z.id">
                                            <option :value="z.id" x-text="z.name"></option>
                                        </template>
                                    </select>
                                    <template x-if="userScope && !['national', 'call_centre'].includes(userScope.type) && !['super_admin', 'operation_admin', 'agent_supervisor', 'agent'].includes(userRole)">
                                        <p class="text-[8px] font-bold text-indigo-400 uppercase tracking-widest ml-4 italic">Zone Selected</p>
                                    </template>
                                </div>

                                <!-- Sector Selector -->
                                <div class="space-y-1">
                                    <select x-model="filterSector" @change="filterBeat = ''" 
                                        :disabled="userScope && ['sector', 'beat'].includes(userScope.type) && !['super_admin', 'operation_admin', 'agent_supervisor', 'agent'].includes(userRole)"
                                        class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-indigo-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                        <option value="">All Sectors</option>
                                        <template x-for="s in filteredSectors" :key="s.id">
                                            <option :value="s.id" x-text="s.name"></option>
                                        </template>
                                    </select>
                                    <template x-if="userScope && ['sector', 'beat'].includes(userScope.type) && !['super_admin', 'operation_admin', 'agent_supervisor', 'agent'].includes(userRole)">
                                        <p class="text-[8px] font-bold text-indigo-400 uppercase tracking-widest ml-4 italic">Sector Selected</p>
                                    </template>
                                </div>

                                <!-- Beat Selector -->
                                <div class="space-y-1">
                                    <select x-model="filterBeat" 
                                        :disabled="userScope && userScope.type === 'beat' && !['super_admin', 'operation_admin', 'agent_supervisor', 'agent'].includes(userRole)"
                                        class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner focus:border-indigo-500 transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                        <option value="">Select Beat</option>
                                        <template x-for="b in filteredBeats" :key="b.id">
                                            <option :value="b.id" x-text="b.name"></option>
                                        </template>
                                    </select>
                                    <template x-if="userScope && userScope.type === 'beat' && !['super_admin', 'operation_admin', 'agent_supervisor', 'agent'].includes(userRole)">
                                        <p class="text-[8px] font-bold text-indigo-400 uppercase tracking-widest ml-4 italic">Beat Restricted</p>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Sort & Density -->
                        <div class="grid grid-cols-1 gap-4 pt-4 border-t border-slate-100">
                             <div class="space-y-3">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-compress-alt text-blue-900"></i> Page Density
                                </label>
                                <div class="grid grid-cols-2 gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200/50">
                                    <button @click="density = 'condensed'" :class="density === 'condensed' ? 'bg-white text-blue-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="py-2 text-[9px] font-black uppercase tracking-widest rounded-lg transition-all">Condensed</button>
                                    <button @click="density = 'spacious'" :class="density === 'spacious' ? 'bg-white text-blue-900 shadow-sm font-black' : 'text-slate-500 hover:text-slate-700'" class="py-2 text-[9px] font-black uppercase tracking-widest rounded-lg transition-all">Spacious</button>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                    <i class="fas fa-list-ol text-blue-900"></i> Records Per Page
                                </label>
                                <div class="grid grid-cols-4 gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200/50">
                                    <template x-for="size in [10, 25, 50, 100]" :key="size">
                                        <button @click="perPage = size; page = 1" :class="perPage == size ? 'bg-white text-blue-900 shadow-sm font-black' : 'text-slate-500 hover:text-slate-700'" class="py-2 text-[9px] uppercase tracking-widest rounded-lg transition-all" x-text="size"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Reset Buttons -->
                        <div class="space-y-3 pt-6 border-t border-slate-100">
                            <button @click="clearFilters()" class="rose-reset-btn w-full py-5 rounded-3xl text-[10px] font-black uppercase tracking-[0.3em] transition-all duration-300 flex items-center justify-center gap-3 active:scale-95">
                                <i class="fas fa-broom"></i> Reset Filters
                            </button>
                            <button @click="showSidebar = false" class="w-full py-4 mt-2 bg-blue-600 text-white rounded-3xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-800 transition-all flex items-center justify-between px-6 shadow-md shadow-blue-600/20">
                                <span>Hide Filters</span><i class="fas fa-eye-slash"></i>
                            </button>
                            <button @click="exportData()" type="button" class="w-full py-4 mt-2 bg-emerald-600 text-white rounded-3xl font-black text-[10px] uppercase tracking-widest hover:bg-emerald-700 transition-all flex items-center justify-center gap-3 shadow-md shadow-emerald-600/20 active:scale-95 text-center disabled:opacity-50" :disabled="isLoading">
                                <i class="fas fa-file-excel" :class="{'animate-pulse': isLoading}"></i> <span x-text="isLoading ? 'Exporting...' : 'Export CSV'"></span>
                            </button>

                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('components.confirm-modal')

        {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
             INTELLIGENCE DETAIL MODAL
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
        <div x-show="showDetailModal" 
             class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-8 bg-white/50 backdrop-blur-sm" 
             x-transition x-cloak>
            <div @click.away="showDetailModal = false" 
                 class="bg-white rounded-[2.5rem] shadow-[0_32px_64px_-12px_rgba(0,0,0,0.14)] w-full max-w-4xl overflow-hidden border border-slate-100 flex flex-col max-h-[90vh]">
                
                <div class="shrink-0 p-10 border-b border-white/10 flex items-center justify-between bg-gradient-to-r from-blue-600 via-indigo-600 to-violet-600 relative overflow-hidden">
                    <div class="absolute inset-0 bg-grid-white/[0.1] bg-[size:20px_20px]"></div>
                    <div class="absolute -right-10 -top-10 w-40 h-40 bg-white/10 rounded-full blur-3xl"></div>
                    <div class="relative z-10 flex items-center gap-6">
                        <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-2xl">
                            <i class="fa-solid fa-satellite-dish text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-3xl font-black text-white tracking-tighter uppercase italic scale-y-110" x-text="selectedItem?.call_number"></h3>
                            <p class="text-[10px] font-black text-blue-100 uppercase tracking-[0.4em] flex items-center gap-2">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-300 animate-pulse"></span>
                                Assistance Details
                            </p>
                        </div>
                    </div>
                    <button @click="showDetailModal = false" class="relative z-10 w-11 h-11 rounded-full bg-white/10 border border-white/20 text-white hover:bg-white/20 transition-all flex items-center justify-center backdrop-blur-md">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-8 space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {{-- Taxonomy & Identity --}}
                        <div class="space-y-6">
                            <div class="group">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Commuter Details</label>
                                <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-blue-900">
                                            <i class="fa-solid fa-phone text-xs"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-blue-900 tabular-nums" x-text="selectedItem?.caller_number"></p>
                                            <p class="text-[9px] font-bold text-slate-500 uppercase" x-text="selectedItem?.caller_name || 'Anonymous Entity'"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="group">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Category</label>
                                <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100">
                                    <p class="text-lg font-black text-blue-900 uppercase tracking-tight" x-text="selectedItem?.call_type?.name"></p>
                                    <p class="text-[10px] font-bold text-blue-600 uppercase tracking-widest mt-1" x-text="selectedItem?.call_sub_type?.name || 'General Resolution'"></p>
                                </div>
                            </div>
                        </div>

                        {{-- Spatial Context --}}
                        <div class="space-y-6">
                            <div class="group">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Beat and location Details</label>
                                <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter">Assigned Beat</span>
                                        <span class="text-xs font-black text-blue-900 uppercase" x-text="selectedItem?.location || ((selectedItem?.office?.parent?.parent?.name || '---') + ' / ' + (selectedItem?.office?.parent?.name || '---') + ' / ' + (selectedItem?.office?.name || '---'))"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter">Location Details</span>
                                        <span class="text-xs font-black text-blue-900 uppercase" x-text="selectedItem?.location_details || '---'"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="group">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Tiger & Priority</label>
                                <div class="p-6 bg-slate-50 rounded-3xl border border-slate-100 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter">Tiger Unit</span>
                                        <span class="text-xs font-black text-amber-600 uppercase" x-text="getAllocatedUnit(selectedItem)"></span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-tighter">Priority Level</span>
                                        <div x-html="getPriorityBadge(selectedItem?.priority)"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Intelligence Log --}}
                    <div class="group">
                        <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest mb-2 block">Commuter Details & Notes</label>
                        <div class="p-8 bg-slate-50 rounded-[2rem] text-navy-900 relative overflow-hidden">
                            <i class="fa-solid fa-quote-left absolute left-4 top-4 text-navy-200 text-2xl"></i>
                            <p class="text-sm pt-2 font-medium leading-relaxed italic relative z-10" x-text="selectedItem?.details || 'Commuter notes are not provided...'"></p>
                        </div>
                    </div>
                </div>

                <div class="shrink-0 p-8 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div class="flex items-center gap-6">
                        <div class="flex flex-col">
                            <span class="text-[8px] font-black text-slate-400 uppercase tracking-widest">Date & Time</span>
                            <span class="text-[10px] font-black text-blue-900" x-text="formatDate(selectedItem?.created_at) + ' ' + formatTime(selectedItem?.created_at)"></span>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <a :href="'/calls/' + (selectedItem?.uuid || selectedItem?.id)" class="px-6 py-3 bg-white border border-slate-200 text-blue-900 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-50 transition-all">View Full Details</a>
                        <button @click="showDetailModal = false" class="px-6 py-3 bg-blue-600 text-white rounded-xl font-black text-[10px] uppercase tracking-widest shadow-lg shadow-blue-600/20 active:scale-95 transition-all">Dismiss</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
             OPERATIONAL TRANSITION MODAL
        ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ --}}
        <div x-show="showTransitionModal" 
             class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-white/50 backdrop-blur-sm" 
             x-transition x-cloak>
            <div @click.away="showTransitionModal = false" 
                 class="bg-white rounded-[3rem] shadow-[0_0_100px_rgba(0,0,0,0.4)] w-full max-w-lg overflow-hidden border border-white/20 transform transition-all">
                
                <div class="p-12 relative overflow-hidden" 
                     :class="targetStatus === 'in_progress' ? 'bg-gradient-to-br from-amber-400 via-orange-500 to-rose-500' : (targetStatus === 'tiger_reached' ? 'bg-gradient-to-br from-blue-600 via-indigo-600 to-sky-500' : 'bg-gradient-to-br from-emerald-400 via-teal-500 to-cyan-500')">
                    <div class="absolute inset-0 bg-grid-white/[0.1] bg-[size:16px_16px]"></div>
                    <div class="absolute -right-12 -top-12 w-48 h-48 bg-white/20 rounded-full blur-[40px]"></div>
                    <div class="relative z-10 flex flex-col gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-xl mb-2">
                            <i class="fa-solid" :class="targetStatus === 'in_progress' ? 'fa-bolt-lightning' : (targetStatus === 'tiger_reached' ? 'fa-location-dot' : 'fa-check-double')"></i>
                        </div>
                        <h3 class="text-4xl font-black text-white tracking-widest italic scale-y-110 uppercase" x-text="targetStatus === 'in_progress' ? 'Initiate Help' : (targetStatus === 'tiger_reached' ? 'Tiger Reached' : 'Complete Help')"></h3>
                        <p class="text-white/80 text-[10px] font-black uppercase tracking-[0.4em] flex items-center gap-2">
                            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                            Operational Authorization Protocol
                        </p>
                    </div>
                </div>

                <div class="p-10 space-y-8">
                    {{-- Unit Allocation for In-Progress or Tiger Reached --}}
                    <template x-if="targetStatus === 'in_progress' || (targetStatus === 'tiger_reached' && !selectedTigerId)">
                        <div class="space-y-4">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4">Tiger Allocation</label>
                            <select x-model="selectedTigerId" 
                                class="w-full px-6 py-5 bg-white border-2 border-slate-100 rounded-2xl outline-none font-bold text-blue-900 text-xs focus:border-blue-500 transition-all shadow-xl">
                                <option value="">Select Tiger Unit...</option>
                                <optgroup label="RESERVE TIGERS">
                                    <option value="T1">Tiger-1</option>
                                    <option value="T2">Tiger-2</option>
                                    <option value="T3">Tiger-3</option>
                                    <option value="T4">Tiger-4</option>
                                </optgroup>
                                <optgroup label="SPECIAL TIGERS">
                                    <option value="ST1">Special Tiger-1</option>
                                    <option value="ST2">Special Tiger-2</option>
                                    <option value="ST3">Special Tiger-3</option>
                                </optgroup>
                            </select>
                            <p class="text-[8px] font-bold text-slate-400 uppercase tracking-wider ml-4">Showing assigned beat tigers</p>
                        </div>
                    </template>

                    {{-- Remarks --}}
                    <div class="space-y-4">
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest ml-4">Remarks</label>
                        <textarea x-model="transitionRemarks" rows="4" 
                            class="w-full px-6 py-5 bg-slate-50 border-2 border-slate-100 rounded-2xl outline-none font-bold text-blue-900 text-xs focus:border-blue-500 transition-all shadow-inner placeholder:text-slate-300"
                            :placeholder="targetStatus === 'tiger_reached' ? 'Enter wireless transmission notes, arrival observations...' : 'Enter help updates or officers details...'"></textarea>
                    </div>

                    <div class="flex gap-4 pt-6">
                        <button @click="showTransitionModal = false" class="flex-1 px-8 py-5 bg-slate-50 text-slate-400 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-100 transition-all">Cancel</button>
                        <button @click="submitTransition()" 
                            :disabled="isTransiting || (targetStatus === 'in_progress' && !selectedTigerId)"
                            :class="targetStatus === 'in_progress' ? 'bg-gradient-to-r from-amber-500 to-orange-600 shadow-orange-500/30' : (targetStatus === 'tiger_reached' ? 'bg-gradient-to-r from-blue-600 to-indigo-600 shadow-blue-500/30' : 'bg-gradient-to-r from-emerald-500 to-cyan-600 shadow-emerald-500/30')"
                            class="flex-[2] px-8 py-5 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-2xl hover:-translate-y-1 transition-all disabled:opacity-30 disabled:cursor-not-allowed flex items-center justify-center gap-3">
                            <template x-if="!isTransiting">
                                <span class="flex items-center gap-3">
                                    <i class="fa-solid" :class="targetStatus === 'in_progress' ? 'fa-bolt-lightning' : (targetStatus === 'tiger_reached' ? 'fa-location-dot' : 'fa-check')"></i>
                                    <span x-text="targetStatus === 'in_progress' ? 'Dispatch Tiger' : (targetStatus === 'tiger_reached' ? 'Mark Tiger Reached' : 'Complete Help')"></span>
                                </span>
                            </template>
                            <template x-if="isTransiting">
                                <i class="fa-solid fa-circle-notch fa-spin"></i>
                            </template>
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        function callManager(config) {
            return {
                items: config.items || [],
                stats: config.stats || {},
                callTypes: config.callTypes || [],
                callSubTypes: config.callSubTypes || [],
                agents: config.agents || [],
                status: config.status || 'all',
                permissions: config.permissions || {},
                zones: config.zones || [],
                sectors: config.sectors || [],
                beats: config.beats || [],
                search: '',
                filterZone: '',
                filterSector: '',
                filterBeat: '',
                filterType: '',
                filterSubType: '',
                filterAgent: '',
                userRole: config.userRole || null,
                userScope: config.userScope || null,
                filterStatus: config.status || 'all',
                filterAssignment: config.assignment || '',
                filterGender: config.gender || '',
                dateFrom: '',
                dateTo: '',
                timeFrom: '',
                timeTo: '',
                totalRecords: config.total || 0,
                totalPages: config.last_page || 1,
                page: config.current_page || 1,
                perPage: config.per_page || 10,
                sortBy: config.sort || 'created_at',
                sortDirection: config.direction || 'desc',
                isLoading: false,
                fetchTimeout: null,
                filterOptionsLoaded: false,
                isLoadingFilters: false,
                alarmAudioCtx: null,
                alarmTimer: null,
                alarmSpeechTimer: null,
                alarmMuted: false,
                alarmAudioUnlocked: false,
                lastSpokenTime: 0,
                lastEmergencySeenId: 0,
                lastEmergencyCheckInitialized: false,

                async loadFilterOptions() {
                    if (this.filterOptionsLoaded || this.isLoadingFilters) return;
                    this.isLoadingFilters = true;

                    try {
                        // Cache is per-user: a shared workstation/tab must never reuse another user's zone/sector/beat lists.
                        const FO_KEY = 'nhmp_calls_filter_options_v3_u{{ (int) auth()->id() }}';
                        try { sessionStorage.removeItem('nhmp_calls_filter_options_v2'); } catch (e) {}
                        const cached = sessionStorage.getItem(FO_KEY);
                        if (cached) {
                            const data = JSON.parse(cached);
                            this.applyFilterOptionsData(data);
                            this.filterOptionsLoaded = true;
                            this.isLoadingFilters = false;
                            return;
                        }

                        const res = await fetch('/calls/filter-options', {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        if (res.ok) {
                            const data = await res.json();
                            this.applyFilterOptionsData(data);
                            try {
                                sessionStorage.setItem(FO_KEY, JSON.stringify(data));
                            } catch (e) {}
                            this.filterOptionsLoaded = true;
                        }
                    } catch (e) {
                        console.error('Failed to load filter options:', e);
                    } finally {
                        this.isLoadingFilters = false;
                    }
                },

                applyFilterOptionsData(data) {
                    if (!data) return;
                    this.zones = data.zones || [];
                    this.sectors = data.sectors || [];
                    this.beats = data.beats || [];
                    this.callTypes = data.callTypes || [];
                    this.callSubTypes = data.callSubTypes || [];
                    this.agents = data.agents || [];

                    const isPrivileged = ['super_admin', 'operation_admin', 'agent_supervisor', 'agent'].includes(this.userRole);
                    if (this.userScope && !isPrivileged) {
                        this.resolveUserScopeHierarchy();
                    }
                },

                resolveUserScopeHierarchy() {
                    if (!this.userScope) return;
                    if (this.userScope.type === 'zone') {
                        this.filterZone = this.userScope.id;
                    } else if (this.userScope.type === 'sector') {
                        const sector = this.sectors.find(s => s.id == this.userScope.id);
                        if (sector) {
                            this.filterZone = sector.parent_id;
                            this.filterSector = sector.id;
                        }
                    } else if (this.userScope.type === 'beat') {
                        const beat = this.beats.find(b => b.id == this.userScope.id);
                        if (beat) {
                            const sector = this.sectors.find(s => s.id == beat.parent_id);
                            if (sector) {
                                this.filterZone = sector.parent_id;
                                this.filterSector = sector.id;
                            }
                            this.filterBeat = beat.id;
                        }
                    }
                },

                latestRequestId: 0,
                fetchAbortController: null,

                async fetchData() {
                    const currentRequestId = ++this.latestRequestId;

                    if (this.fetchAbortController) {
                        try { this.fetchAbortController.abort(); } catch (e) {}
                    }
                    this.fetchAbortController = new AbortController();
                    const signal = this.fetchAbortController.signal;

                    this.isLoading = true;
                    try {
                        const params = new URLSearchParams();
                        if (this.search) params.set('search', this.search);
                        if (this.filterAssignment) params.set('assignment', this.filterAssignment);
                        if (this.filterGender) params.set('gender', this.filterGender);
                        if (this.filterStatus && this.filterStatus !== 'all') params.set('status', this.filterStatus);
                        if (this.filterType) params.set('call_type_id', this.filterType);
                        if (this.filterSubType) params.set('call_sub_type_id', this.filterSubType);
                        if (this.filterZone) params.set('zone_id', this.filterZone);
                        if (this.filterSector) params.set('sector_id', this.filterSector);
                        if (this.filterBeat) params.set('beat_id', this.filterBeat);
                        if (this.filterAgent) params.set('agent_id', this.filterAgent);
                        if (this.dateFrom) params.set('date_from', this.dateFrom);
                        if (this.dateTo) params.set('date_to', this.dateTo);
                        if (this.timeFrom) params.set('time_from', this.timeFrom);
                        if (this.timeTo) params.set('time_to', this.timeTo);
                        if (this.page > 1) params.set('page', this.page);
                        if (this.perPage != 10) params.set('per_page', this.perPage);
                        if (this.sortBy && this.sortBy !== 'created_at') params.set('sort', this.sortBy);
                        if (this.sortDirection && this.sortDirection !== 'desc') params.set('direction', this.sortDirection);

                        const payload = Object.fromEntries(params.entries());
                        const res = await fetch('{{ route("calls.data") }}', {
                            method: 'POST',
                            signal,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            },
                            body: JSON.stringify(payload)
                        });

                        // Drop stale responses if a newer fetch was initiated while this request was in flight
                        if (currentRequestId !== this.latestRequestId) {
                            return;
                        }

                        if (!res.ok) {
                            const errBody = await res.json().catch(() => null);
                            const errMsg = errBody?.message || errBody?.error || `Server responded with status ${res.status}`;
                            if (window.Notification) {
                                window.Notification.error(errMsg, 'Request Failed');
                            }
                            return;
                        }

                        const data = await res.json();
                        if (currentRequestId !== this.latestRequestId) {
                            return;
                        }

                        if (data && Array.isArray(data.items)) {
                            this.items = data.items;
                            if (data.stats) this.stats = data.stats;
                            this.page = data.current_page || this.page;
                            this.totalPages = data.last_page || 1;
                            this.totalRecords = data.total ?? this.items.length;
                            this.updateBrowserUrl(params);
                            this.syncAlarmState();
                        } else {
                            throw new Error('Malformed payload returned from server');
                        }
                    } catch (e) {
                        if (!e || e.name === 'AbortError' || signal?.aborted || (e.message && e.message.toLowerCase().includes('abort'))) {
                            return; // Stale request cancelled by newer request
                        }
                        console.error('Fetch error:', e);
                        if (currentRequestId === this.latestRequestId && window.Notification) {
                            window.Notification.error(e.message || 'Communications array offline.', 'Connection Error');
                        }
                    } finally {
                        // Only clear loading state if this is the latest initiated request
                        if (currentRequestId === this.latestRequestId) {
                            this.isLoading = false;
                        }
                    }
                },

                updateBrowserUrl(params) {
                    if (window.location.search) {
                        window.history.replaceState({ page: this.page }, document.title, window.location.pathname);
                    }
                },

                async exportData() {
                    if (this.isLoading) return;
                    this.isLoading = true;

                    if (window.Notification) window.Notification.info('Generating CSV package...', 'Exporting Data');

                    try {
                        const params = new URLSearchParams({
                            search: this.search,
                            status: this.filterStatus,
                            zone_id: this.filterZone,
                            sector_id: this.filterSector,
                            beat_id: this.filterBeat,
                            call_type_id: this.filterType,
                            call_sub_type_id: this.filterSubType,
                            agent_id: this.filterAgent,
                            date_from: this.dateFrom,
                            date_to: this.dateTo,
                            time_from: this.timeFrom,
                            time_to: this.timeTo,
                            sort: this.sortBy,
                            direction: this.sortDirection
                        });

                        const res = await fetch(`{{ route('calls.export') }}?${params.toString()}`, {
                            headers: { 'Accept': 'text/csv', 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        if (!res.ok) {
                            const errorData = await res.json().catch(() => ({ error: 'Export server error' }));
                            throw new Error(errorData.error || 'Failed to generate export');
                        }

                        const contentType = res.headers.get('content-type');
                        if (contentType && !contentType.includes('text/csv')) {
                            // If we got HTML or something else, it might be a redirect or error page
                            throw new Error('Server returned an invalid format. Please try again or check your filters.');
                        }

                        const blob = await res.blob();
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = `export_${Date.now()}.csv`;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        if (window.Notification) window.Notification.success('Helps downloaded successfully.', 'Export Complete');
                    } catch (e) {
                        console.error('Export error:', e);
                        if (window.Notification) window.Notification.error(e.message || 'Network error, please try again.', 'Export Failed');
                    } finally {
                        this.isLoading = false;
                    }
                },

                triggerFetch() {
                    if(this.fetchTimeout) clearTimeout(this.fetchTimeout);
                    // Reset to page 1 ONLY IF the change wasn't pagination itself. 
                    // To handle this properly, the watcher for pagination handles itself independently.
                    this.fetchTimeout = setTimeout(() => {
                        this.fetchData();
                    }, 400);
                },

                _initialized: false,
                emergencyPoller: null,
                backgroundPoller: null,

                init() {
                    if (this._initialized) return;
                    this._initialized = true;
                    // Check if the user is a privileged role (Admin, Supervisor, Agent)
                    const isPrivileged = ['super_admin', 'operation_admin', 'agent_supervisor', 'agent'].includes(this.userRole);

                    // Check if specific filter parameters were passed via URL
                    const urlParams = new URLSearchParams(window.location.search);
                    const hasFilterParams = urlParams.has('zone_id') || urlParams.has('sector_id') ||
                                            urlParams.has('beat_id') || urlParams.has('call_type_id') ||
                                            urlParams.has('call_sub_type_id') || urlParams.has('agent_id');

                    // If scoped or filters requested on arrival, load options immediately to hydrate dropdowns
                    if (hasFilterParams || (this.userScope && !isPrivileged)) {
                        this.loadFilterOptions().then(() => {
                            if (this.userScope && !isPrivileged) {
                                this.resolveUserScopeHierarchy();
                            }
                        });
                    } else if (this.userScope && !isPrivileged) {
                        this.resolveUserScopeHierarchy();
                    }

                    // Clean up global bootstrap data object from window namespace
                    if (window._indexData) {
                        try { delete window._indexData; } catch (e) { window._indexData = null; }
                    }

                    // Synchronize on browser history popstate (Back/Forward)
                    window.addEventListener('popstate', () => {
                        const p = new URLSearchParams(window.location.search);
                        this.filterAssignment = p.get('assignment') || '';
                        this.filterGender = p.get('gender') || '';
                        this.filterStatus = p.get('status') || 'all';
                        this.filterType = p.get('call_type_id') || '';
                        this.filterSubType = p.get('call_sub_type_id') || '';
                        this.filterZone = p.get('zone_id') || '';
                        this.filterSector = p.get('sector_id') || '';
                        this.filterBeat = p.get('beat_id') || '';
                        this.filterAgent = p.get('agent_id') || '';
                        this.search = p.get('search') || '';
                        this.page = parseInt(p.get('page')) || 1;
                        this.sortBy = p.get('sort') || 'created_at';
                        this.sortDirection = p.get('direction') || 'desc';
                        this.fetchData();
                    });

                    this.$watch('search', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterStatus', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterAssignment', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterGender', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterZone', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterSector', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterBeat', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterType', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterSubType', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('filterAgent', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('dateFrom', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('dateTo', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('timeFrom', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('timeTo', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('perPage', () => { this.page = 1; this.triggerFetch(); });
                    this.$watch('page', () => { this.triggerFetch(); });

                    this.initAlarm();

                    // Real-Time Emergency Monitor for Pending Women Safety Calls
                    // Strictly scoped to the operator's active beat jurisdiction
                    this.emergencyPoller = typeof window.nhmpPoll === 'function' ? window.nhmpPoll(async () => {
                        if (this.showDetailModal || this.showTransitionModal || this.showConfirmModal) return;
                        try {
                            const p = new URLSearchParams();
                            p.set('last_id', this.lastEmergencySeenId > 0 ? this.lastEmergencySeenId : 0);
                            if (this.filterBeat) p.set('beat_id', this.filterBeat);
                            else if (this.filterSector) p.set('sector_id', this.filterSector);
                            else if (this.filterZone) p.set('zone_id', this.filterZone);

                            const res = await fetch(`/api/v1/realtime/emergency-check?${p.toString()}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (res.ok) {
                                const data = await res.json();
                                if (!this.lastEmergencyCheckInitialized) {
                                    this.lastEmergencyCheckInitialized = true;
                                    if (data.call && data.call.id) {
                                        this.lastEmergencySeenId = data.call.id;
                                    }
                                    if (Number(data.total_pending_women) === 0) {
                                        this.stopAlarm();
                                    }
                                    return res;
                                }

                                if (data.has_new_emergency && data.call && data.call.id > this.lastEmergencySeenId) {
                                    this.lastEmergencySeenId = data.call.id;
                                    // Trigger alert only if operator has pending women safety call
                                    if (this.hasPendingWomen() && this.alarmAudioUnlocked) {
                                        this.playBeepTone();
                                        this.speakVoiceAlert();
                                    }
                                    if (!this.isLoading) {
                                        this.fetchData().catch(() => {});
                                    }
                                } else if (data.total_pending_women > 0 && this.hasPendingWomen()) {
                                    if (!this.alarmTimer) this.syncAlarmState();
                                } else if (data.total_pending_women === 0 || !this.hasPendingWomen()) {
                                    this.stopAlarm();
                                }
                            }
                            return res;
                        } catch (e) {
                            return false;
                        }
                    }, {
                        interval: 5000,        // visible: 5 s (safety feature, pending emergency alerts)
                        hiddenInterval: 30000, // hidden: 30 s per POLL-1
                        jitter: 0.1,
                        backoff: true,
                        initialDelay: 1000
                    }) : null;

                    // Periodic background refresh (30s) to discover general state updates
                    this.backgroundPoller = typeof window.nhmpPoll === 'function' ? window.nhmpPoll(async () => {
                        if (!this.isLoading && !this.showDetailModal && !this.showTransitionModal && !this.showConfirmModal) {
                            return this.fetchData();
                        }
                    }, {
                        interval: 30000,        // visible: 30 s per POLL-1
                        hiddenInterval: 'skip', // hidden: skip per POLL-1
                        jitter: 0.1,
                        backoff: false,
                        initialDelay: 30000
                    }) : null;
                },

                density: 'spacious',
                viewMode: 'table',
                showSidebar: false,
                showDetailModal: false,
                showTransitionModal: false,
                selectedItem: null,
                targetStatus: '',
                transitionRemarks: '',
                selectedTigerId: '',
                isTransiting: false,
                showConfirmModal: false,
                confirmLoading: false,
                confirmConfig: { title: '', message: '', icon: '', isDanger: false },

                get filteredBeats() {
                    if (!this.filterSector) return [];
                    return this.beats.filter(b => b.parent_id == this.filterSector);
                },

                get allSubTypes() {
                    return this.callSubTypes;
                },

                get filteredSubTypesInFilter() {
                    if (!this.filterType) return [];
                    return this.allSubTypes.filter(st => st.call_type_id == this.filterType);
                },

                get filteredSectors() {
                    if (!this.filterZone) return [];
                    return this.sectors.filter(s => s.parent_id == this.filterZone);
                },

                get pagedItems() { return this.items; },

                sortByItem(field) {
                    if (this.sortBy === field) {
                        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortBy = field;
                        this.sortDirection = 'asc';
                    }
                    this.triggerFetch();
                },

                getSortIcon(field) {
                    if (this.sortBy !== field) return 'fa-sort opacity-20';
                    return this.sortDirection === 'asc' ? 'fa-sort-up text-blue-600' : 'fa-sort-down text-blue-600';
                },

                getAllocatedUnit(item) {
                    if (!item) return 'UNALLOCATED';
                    if (item.tiger) return item.tiger.tiger_code;
                    if (item.inprogress_remarks && (item.inprogress_remarks.includes('[Allocated Asset:') || item.inprogress_remarks.includes('[Allocated Static Asset:'))) {
                        const match = item.inprogress_remarks.match(/\[Allocated (?:Static )?Asset: (.*?)\]/);
                        return match ? match[1] : 'UNALLOCATED';
                    }
                    return 'UNALLOCATED';
                },

                formatDate(dateStr) {
                    if (!dateStr) return '---';
                    const d = new Date(dateStr);
                    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
                },

                formatTime(dateStr) {
                    if (!dateStr) return '---';
                    const d = new Date(dateStr);
                    return d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                },

                formatTitleCase(str) {
                    if (!str) return '---';
                    return str.toLowerCase().split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
                },

                getLocationHierarchy(item) {
                    if (!item) return 'OFF-GRID';
                    if (item.location) return item.location;
                    if (!item.office) return 'OFF-GRID';
                    const parts = [];
                    if (item.office.parent?.parent?.name) parts.push(item.office.parent.parent.name);
                    if (item.office.parent?.name) parts.push(item.office.parent.name);
                    if (item.office.name) parts.push(item.office.name);
                    return parts.length > 0 ? parts.join(' / ') : 'OFF-GRID';
                },

                getStatusBadge(status) {
                    const badges = {
                        'pending': '<span class="px-3 py-1 bg-rose-50 text-rose-600 border border-rose-100 rounded-lg text-[9px] font-black uppercase tracking-widest">Pending</span>',
                        'in_progress': '<span class="px-3 py-1 bg-amber-50 text-amber-600 border border-amber-100 rounded-lg text-[9px] font-black uppercase tracking-widest">In-Process</span>',
                        'tiger_reached': '<span class="px-3 py-1 bg-sky-50 text-sky-600 border border-sky-100 rounded-lg text-[9px] font-black uppercase tracking-widest">Tiger Reached</span>',
                        'completed': '<span class="px-3 py-1 bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-lg text-[9px] font-black uppercase tracking-widest">Resolved</span>',
                        'forwarded': '<span class="px-3 py-1 bg-blue-50 text-blue-600 border border-blue-100 rounded-lg text-[9px] font-black uppercase tracking-widest">Forwarded</span>',
                        'junk': '<span class="px-3 py-1 bg-slate-50 text-slate-400 border border-slate-100 rounded-lg text-[9px] font-black uppercase tracking-widest">Junk</span>',
                        'cancelled': '<span class="px-3 py-1 bg-slate-100 text-slate-500 border border-slate-200 rounded-lg text-[9px] font-black uppercase tracking-widest">Cancelled</span>'
                    };
                    return badges[status] || status;
                },

                isPendingWomen(item) {
                    if (!item) return false;
                    const isFemale = item.caller_gender === 'female' || item.caller_gender === 'women' || item.call_type_id === 10;
                    const isPending = item.status && item.status.toLowerCase() === 'pending';
                    return Boolean(isFemale && isPending);
                },

                hasPendingWomen() {
                    // Authoritative check based on current scoped stats from server
                    if (this.stats && typeof this.stats.pending_women !== 'undefined') {
                        return Number(this.stats.pending_women) > 0;
                    }
                    return Array.isArray(this.items) && this.items.some(it => this.isPendingWomen(it));
                },

                initAlarm() {
                    const unlock = () => {
                        this.unlockAudio();
                    };
                    ['click', 'keydown', 'touchstart'].forEach(evt => {
                        document.addEventListener(evt, unlock, { passive: true });
                    });
                    this.syncAlarmState();
                },

                unlockAudio() {
                    try {
                        const AudioCtx = window.AudioContext || window.webkitAudioContext;
                        if (!AudioCtx) return;
                        if (!this.alarmAudioCtx) {
                            this.alarmAudioCtx = new AudioCtx();
                        }
                        if (this.alarmAudioCtx.state === 'suspended') {
                            this.alarmAudioCtx.resume().then(() => {
                                this.alarmAudioUnlocked = true;
                                if (this.hasPendingWomen() && !this.alarmMuted) {
                                    this.playBeepTone();
                                }
                            }).catch(() => {});
                        } else if (this.alarmAudioCtx.state === 'running') {
                            this.alarmAudioUnlocked = true;
                        }
                    } catch (e) {}
                },

                toggleAlarmMute() {
                    this.alarmMuted = !this.alarmMuted;
                    if (!this.alarmMuted) {
                        this.unlockAudio();
                        if (this.alarmAudioUnlocked && this.hasPendingWomen()) {
                            this.playBeepTone();
                        }
                    }
                },

                playBeepTone() {
                    if (this.alarmMuted || !this.alarmAudioUnlocked || !this.hasPendingWomen()) return;
                    try {
                        if (!this.alarmAudioCtx || this.alarmAudioCtx.state !== 'running') return;

                        const now = this.alarmAudioCtx.currentTime;
                        const gainNode = this.alarmAudioCtx.createGain();
                        gainNode.gain.setValueAtTime(0.001, now);
                        gainNode.gain.linearRampToValueAtTime(0.25, now + 0.04);
                        gainNode.gain.exponentialRampToValueAtTime(0.001, now + 0.4);
                        gainNode.connect(this.alarmAudioCtx.destination);

                        const osc = this.alarmAudioCtx.createOscillator();
                        osc.type = 'triangle';
                        osc.frequency.setValueAtTime(880, now);
                        osc.frequency.setValueAtTime(1174.66, now + 0.18);
                        osc.connect(gainNode);

                        osc.start(now);
                        osc.stop(now + 0.4);
                    } catch (e) {}
                },

                speakVoiceAlert() {
                    if (this.alarmMuted || !this.alarmAudioUnlocked || !this.hasPendingWomen()) return;
                    if ('speechSynthesis' in window) {
                        try {
                            const now = Date.now();
                            if (now - this.lastSpokenTime < 10000) return;
                            this.lastSpokenTime = now;
                            window.speechSynthesis.cancel();
                            const utter = new SpeechSynthesisUtterance('Emergency alert: Pending women safety call awaiting initialization.');
                            utter.rate = 1.05;
                            utter.pitch = 1.1;
                            window.speechSynthesis.speak(utter);
                        } catch (e) {}
                    }
                },

                syncAlarmState() {
                    if (this.hasPendingWomen()) {
                        if (!this.alarmTimer) {
                            if (this.alarmAudioUnlocked) {
                                this.playBeepTone();
                                this.speakVoiceAlert();
                            }
                            this.alarmTimer = setInterval(() => {
                                if (this.hasPendingWomen()) {
                                    if (this.alarmAudioUnlocked) {
                                        this.playBeepTone();
                                    }
                                } else {
                                    this.stopAlarm();
                                }
                            }, 3200);
                            this.alarmSpeechTimer = setInterval(() => {
                                if (this.hasPendingWomen()) {
                                    if (this.alarmAudioUnlocked) {
                                        this.speakVoiceAlert();
                                    }
                                } else {
                                    this.stopAlarm();
                                }
                            }, 14000);
                        }
                    } else {
                        this.stopAlarm();
                    }
                },

                stopAlarm() {
                    if (this.alarmTimer) {
                        clearInterval(this.alarmTimer);
                        this.alarmTimer = null;
                    }
                    if (this.alarmSpeechTimer) {
                        clearInterval(this.alarmSpeechTimer);
                        this.alarmSpeechTimer = null;
                    }
                    if ('speechSynthesis' in window) {
                        try { window.speechSynthesis.cancel(); } catch (e) {}
                    }
                },

                openDetailModal(item) {
                    this.selectedItem = item;
                    this.showDetailModal = true;
                },

                openTransitionModal(item, status) {
                    this.selectedItem = item;
                    this.targetStatus = status;
                    this.transitionRemarks = '';
                    this.selectedTigerId = item.tiger_id || '';
                    this.showTransitionModal = true;
                },

                async submitTransition() {
                    if (this.isTransiting) return;
                    this.isTransiting = true;

                    if (window.Notification) window.Notification.info('Updating help status...', 'Processing Help');

                    try {
                        const callIdentifier = this.selectedItem.uuid || this.selectedItem.id;
                        const response = await fetch(`/calls/${callIdentifier}/status`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                status: this.targetStatus,
                                remarks: this.transitionRemarks,
                                tiger_id: this.selectedTigerId
                            })
                        });

                        const result = await response.json();

                        if (response.ok) {
                            const index = this.items.findIndex(i => i.id === this.selectedItem.id);
                            if (index !== -1) {
                                this.items[index].status = this.targetStatus;
                                if (this.selectedTigerId) {
                                    this.items[index].tiger_id = this.selectedTigerId;
                                }
                            }
                            
                            this.showTransitionModal = false;
                            if (window.Notification) window.Notification.success(result.message || 'Help updated successfully.', 'Success');
                            this.fetchData();
                        } else {
                            if (window.Notification) window.Notification.warning(result.message || 'Help not updated.', 'Help Update Failed');
                        }
                    } catch (e) {
                        if (window.Notification) window.Notification.error('Unable to update help status.', 'Connection Error');
                    } finally {
                        this.isTransiting = false;
                    }
                },

                getPriorityBadge(priority, compact = false) {
                    const configs = {
                        1: { label: 'Critical', bg: 'bg-rose-600', text: 'text-white', icon: 'fa-bolt' },
                        2: { label: 'Urgent', bg: 'bg-orange-500', text: 'text-white', icon: 'fa-triangle-exclamation' },
                        3: { label: 'Normal', bg: 'bg-amber-500', text: 'text-white', icon: 'fa-clock' }
                    };
                    const config = configs[priority] || configs[3];
                    if (compact) {
                        return `<span class="px-2 py-0.5 ${config.bg} ${config.text} rounded text-[9px] font-black uppercase flex items-center gap-1 w-fit justify-center mx-auto shadow-sm"><i class="fa-solid ${config.icon} text-[8px]"></i>${config.label}</span>`;
                    }
                    return `<span class="px-3 py-1 ${config.bg} ${config.text} rounded-lg text-[9px] font-black uppercase tracking-widest shadow-sm flex items-center gap-1.5 w-fit"><i class="fa-solid ${config.icon}"></i>${config.label}</span>`;
                },

                clearFilters() {
                    this.filterStatus = 'all';
                    this.filterAssignment = '';
                    this.filterGender = '';
                    this.filterZone = this.userScope?.type === 'zone' ? this.userScope.id : '';
                    this.filterSector = this.userScope?.type === 'sector' ? this.userScope.id : '';
                    this.filterBeat = this.userScope?.type === 'beat' ? this.userScope.id : '';
                    this.filterType = '';
                    this.filterSubType = '';
                    this.filterAgent = '';
                    this.search = '';
                    this.page = 1;
                    this.dateFrom = '';
                    this.dateTo = '';
                    this.timeFrom = '';
                    this.timeTo = '';
                    this.triggerFetch();
                },

                confirmDelete(item) {
                    this.confirmConfig = {
                        title: 'Purge Record',
                        message: `Are you sure you want to permanently purge trace <strong>${item.call_number}</strong>? <br><br>This action cannot be undone.`,
                        icon: 'fa-trash-can',
                        isDanger: true,
                        action: async () => {
                            const callIdentifier = item.uuid || item.id;
                            const response = await fetch(`/calls/${callIdentifier}`, {
                                method: 'DELETE',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json'
                                }
                            });
                            if (response.ok) {
                                this.items = this.items.filter(i => i.id !== item.id);
                                if (window.showSuccess) showSuccess('Trace purged from directory.');
                            } else {
                                if (window.showError) showError('Command execution failed.');
                            }
                        }
                    };
                    this.showConfirmModal = true;
                },

                async executeConfirmAction() {
                    if (typeof this.confirmConfig.action === 'function') {
                        this.confirmLoading = true;
                        try {
                            await this.confirmConfig.action();
                        } finally {
                            this.confirmLoading = false;
                            this.showConfirmModal = false;
                        }
                    }
                },

                openReminderModal(item) {
                    this.confirmConfig = {
                        title: 'Dispatch Reminder',
                        message: `Initialize a reminder protocol for Trace <strong class="text-amber-500">${item.call_number}</strong>? <br><br>This will alert assigned beat operator for immediate action.`,
                        icon: 'fa-bell',
                        isDanger: false,
                        action: async () => {
                            await this.sendReminder(item);
                        }
                    };
                    this.showConfirmModal = true;
                },

                pagesToShow() {
                    const pages = [];
                    const delta = 2;
                    const left = this.page - delta;
                    const right = this.page + delta + 1;
                    const range = [];
                    const rangeWithDots = [];
                    let l;

                    for (let i = 1; i <= this.totalPages; i++) {
                        if (i === 1 || i === this.totalPages || (i >= left && i < right)) {
                            range.push(i);
                        }
                    }

                    for (let i of range) {
                        if (l) {
                            if (i - l === 2) {
                                rangeWithDots.push(l + 1);
                            } else if (i - l !== 1) {
                                rangeWithDots.push('...');
                            }
                        }
                        rangeWithDots.push(i);
                        l = i;
                    }

                    return rangeWithDots;
                },

                async sendReminder(item) {
                    try {
                        const callIdentifier = item.uuid || item.id;
                        const res = await fetch(`/api/calls/${callIdentifier}/reminder`, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'Accept': 'application/json'
                            }
                        });
                        const data = await res.json();
                        if (res.ok) {
                            item.call_reminder_count = data.call.call_reminder_count;
                            item.last_reminder_at = data.call.last_reminder_at;
                            if (window.Notification) window.Notification.success(data.message, 'Protocol Triggered');
                        } else {
                            if (window.Notification) window.Notification.warning(data.message, 'Manual Override Needed');
                        }
                    } catch (e) {
                        if (window.Notification) window.Notification.error('Communications fail-safe triggered.', 'Sync Error');
                    }
                }
            }
        }
    </script>
    @endpush
@endsection

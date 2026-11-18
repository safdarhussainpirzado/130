@extends('layouts.app')

@section('title', 'User Management - NHMP 130')

@section('page-title', 'User Management')

@section('content')




    <script id="users-index-data" type="application/json">
        {!! json_encode([
            'items' => $users,
            'pagination' => $pagination ?? [
                'current_page' => 1,
                'last_page'    => 1,
                'per_page'     => count($users),
                'total'        => count($users),
                'from'         => 1,
                'to'           => count($users)
            ],
            'stats' => $stats ?? [
                'admins'      => 0,
                'zone'        => 0,
                'sector'      => 0,
                'beat'        => 0,
                'call_center' => 0,
                'inactive'    => 0,
                'total'       => 0,
            ],
            'roles' => $roles,
            'designations' => $designations,
            'zones' => $zones,
            'sectors' => $sectors,
            'beats' => $beats,
            'currentUserRole' => auth()->user()->role?->name,
            'currentUserId' => auth()->id(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>

    <div x-data="userManager(JSON.parse(document.getElementById('users-index-data').textContent))" class="space-y-8 relative mt-4 max-w-[1700px] mx-auto" x-cloak>

        <!-- Floating Sidebar Toggle -->
        <button @click="showSidebar = true"
            x-show="!showSidebar"
            x-transition:enter="transition ease-out duration-500 delay-100"
            x-transition:enter-start="translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            class="fixed top-1/2 right-0 -translate-y-1/2 z-40 bg-gradient-to-b from-blue-600 to-blue-800 text-white p-2.5 py-6 rounded-l-2xl shadow-[0_0_30px_-5px_rgba(59,130,246,0.4)] hover:shadow-[-5px_0_40px_-5px_rgba(59,130,246,0.7)] hover:pr-4 transition-all duration-300 flex flex-col items-center gap-4 cursor-pointer" title="Open Filters">
            <i class="fas fa-sliders-h drop-shadow-lg text-sm"></i>
            <span style="writing-mode: vertical-rl;" class="text-[9px] font-black uppercase tracking-[0.3em] rotate-180 drop-shadow-md text-blue-50">User Filters</span>
        </button>

        <!-- STATS CARDS -->
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3.5">
            <!-- HQ Administrators Card -->
            <div @click="filterStatus = (filterStatus === 'admin' ? '' : 'admin')" :class="filterStatus === 'admin' ? 'card-3d-active amber' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-3.5 left-3.5 h-9 w-9 grid place-items-center rounded-xl bg-amber-500 shadow-[0_8px_16px_rgba(245,158,11,0.2)]">
                    <i class="fas fa-building-shield text-[11px] text-white"></i>
                </div>
                <div class="p-4 text-right pt-3">
                    <p class="text-[7.5px] font-black tracking-widest text-amber-500 uppercase">Administrators</p>
                    <h4 class="text-xl font-extrabold text-blue-900 mt-0.5" x-text="stats.admins"></h4>
                </div>
            </div>

            <!-- Zone Authority Card -->
            <div @click="filterStatus = (filterStatus === 'zone_user' ? '' : 'zone_user')" :class="filterStatus === 'zone_user' ? 'card-3d-active blue' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-3.5 left-3.5 h-9 w-9 grid place-items-center rounded-xl bg-blue-600 shadow-[0_8px_16px_rgba(37,99,235,0.2)]">
                    <i class="fas fa-map-location-dot text-[11px] text-white"></i>
                </div>
                <div class="p-4 text-right pt-3">
                    <p class="text-[7.5px] font-black tracking-widest text-blue-500 uppercase">Zone Authority</p>
                    <h4 class="text-xl font-extrabold text-blue-900 mt-0.5" x-text="stats.zone"></h4>
                </div>
            </div>

            <!-- Sector Authority Card -->
            <div @click="filterStatus = (filterStatus === 'sector_user' ? '' : 'sector_user')" :class="filterStatus === 'sector_user' ? 'card-3d-active indigo' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-3.5 left-3.5 h-9 w-9 grid place-items-center rounded-xl bg-indigo-600 shadow-[0_8px_16px_rgba(79,70,229,0.2)]">
                    <i class="fas fa-landmark text-[11px] text-white"></i>
                </div>
                <div class="p-4 text-right pt-3">
                    <p class="text-[7.5px] font-black tracking-widest text-indigo-500 uppercase">Sectors</p>
                    <h4 class="text-xl font-extrabold text-blue-900 mt-0.5" x-text="stats.sector"></h4>
                </div>
            </div>

            <!-- Beat Operations Card -->
            <div @click="filterStatus = (filterStatus === 'beat_user' ? '' : 'beat_user')" :class="filterStatus === 'beat_user' ? 'card-3d-active purple' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-3.5 left-3.5 h-9 w-9 grid place-items-center rounded-xl bg-purple-600 shadow-[0_8px_16px_rgba(147,51,234,0.2)]">
                    <i class="fas fa-tower-broadcast text-[11px] text-white"></i>
                </div>
                <div class="p-4 text-right pt-3">
                    <p class="text-[7.5px] font-black tracking-widest text-purple-500 uppercase">Beats Ops</p>
                    <h4 class="text-xl font-extrabold text-blue-900 mt-0.5" x-text="stats.beat"></h4>
                </div>
            </div>

            <!-- Call Center Card -->
            <div @click="filterStatus = (filterStatus === 'call_centre_user' ? '' : 'call_centre_user')" :class="filterStatus === 'call_centre_user' ? 'card-3d-active emerald' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-3.5 left-3.5 h-9 w-9 grid place-items-center rounded-xl bg-emerald-600 shadow-[0_8px_16px_rgba(5,150,105,0.2)]">
                    <i class="fas fa-headset text-[11px] text-white"></i>
                </div>
                <div class="p-4 text-right pt-3">
                    <p class="text-[7.5px] font-black tracking-widest text-emerald-500 uppercase">Call Center</p>
                    <h4 class="text-xl font-extrabold text-blue-900 mt-0.5" x-text="stats.call_center"></h4>
                </div>
            </div>

            <!-- Patrol Officers (Tigers) Card -->
            <div @click="filterStatus = (filterStatus === 'patrol_user' ? '' : 'patrol_user')" :class="filterStatus === 'patrol_user' ? 'card-3d-active cyan' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-3.5 left-3.5 h-9 w-9 grid place-items-center rounded-xl bg-cyan-600 shadow-[0_8px_16px_rgba(8,145,178,0.2)]">
                    <i class="fas fa-shield-halved text-[11px] text-white"></i>
                </div>
                <div class="p-4 text-right pt-3">
                    <p class="text-[7.5px] font-black tracking-widest text-cyan-600 uppercase">Patrol Tigers</p>
                    <h4 class="text-xl font-extrabold text-blue-900 mt-0.5" x-text="stats.patrol"></h4>
                </div>
            </div>

            <!-- Commuters (Hamsafar) Card -->
            <div @click="filterStatus = (filterStatus === 'commuter_user' ? '' : 'commuter_user')" :class="filterStatus === 'commuter_user' ? 'card-3d-active teal' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-3.5 left-3.5 h-9 w-9 grid place-items-center rounded-xl bg-teal-600 shadow-[0_8px_16px_rgba(13,148,136,0.2)]">
                    <i class="fas fa-mobile-screen-button text-[11px] text-white"></i>
                </div>
                <div class="p-4 text-right pt-3">
                    <p class="text-[7.5px] font-black tracking-widest text-teal-600 uppercase">Commuters</p>
                    <h4 class="text-xl font-extrabold text-blue-900 mt-0.5" x-text="stats.commuter"></h4>
                </div>
            </div>

            <!-- Inactive Card -->
            <div @click="filterStatus = (filterStatus === 'inactive' ? '' : 'inactive')" :class="filterStatus === 'inactive' ? 'card-3d-active rose' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-3.5 left-3.5 h-9 w-9 grid place-items-center rounded-xl bg-rose-600 shadow-[0_8px_16px_rgba(225,29,72,0.2)]">
                    <i class="fas fa-user-lock text-[11px] text-white"></i>
                </div>
                <div class="p-4 text-right pt-3">
                    <p class="text-[7.5px] font-black tracking-widest text-rose-500 uppercase">Inactive</p>
                    <h4 class="text-xl font-extrabold text-blue-900 mt-0.5" x-text="stats.inactive"></h4>
                </div>
            </div>
        </div>

        <!-- MAIN CONTROL PANEL -->
        <div class="grid lg:grid-cols-12 gap-8 relative pb-20 px-4 sm:px-0">
            
            <!-- Left Column Content -->
            <div class="transition-all duration-500" :class="showSidebar ? 'lg:col-span-9' : 'lg:col-span-12'">
                <div class="bg-white rounded-[2rem] shadow-[0_10px_40_rgba(0,0,0,0.03)] border border-gray-100 overflow-hidden flex flex-col relative z-20">
                    
                    <!-- Panel Header -->
                    <div class="bg-blue-50/50 p-8 border-b border-blue-100/50">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded-[1.25rem] bg-white flex items-center justify-center border border-blue-100 shadow-sm transition-transform hover:scale-105 duration-300">
                                    <i class="fas fa-fingerprint text-3xl text-blue-600"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-extrabold text-blue-900 tracking-tight flex items-center gap-3">
                                         User Management <span class="text-lg font-bold text-slate-400" x-text="'(' + (pagination ? pagination.total : items.length) + ' users)'"></span>
                                    </h2>
                                    <p class="text-slate-500 text-sm font-bold mt-1">Manage system access keys and identity parameters</p>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-4 items-center">
                                <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-sm">
                                    <span class="text-[9px] font-black text-slate-400 border-r border-slate-100 pr-2 uppercase font-mono">Row Density</span>
                                    <select x-model.number="perPage" class="bg-transparent text-blue-600 text-[10px] font-black uppercase cursor-pointer outline-none focus:ring-0 border-none p-0 pr-4">
                                        <option value="10">10 Per Page</option>
                                        <option value="25">25 Per Page</option>
                                        <option value="50">50 Per Page</option>
                                        <option value="100">100 Per Page</option>
                                    </select>
                                </div>

                                <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl p-1.5 shadow-sm">
                                    <button @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-blue-600'" class="w-10 h-10 flex items-center justify-center rounded-lg transition-all"><i class="fas fa-list-ul"></i></button>
                                    <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-blue-600'" class="w-10 h-10 flex items-center justify-center rounded-lg transition-all"><i class="fas fa-th-large"></i></button>
                                </div>

                                <button @click="resetForm(); modalMode = 'add'; showModal = true" class="flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-black shadow-[0_8px_20px_rgba(37,99,235,0.3)] transition-all active:scale-95 group">
                                    <i class="fas fa-plus group-hover:rotate-180 transition-transform duration-500"></i> Provision Key
                                </button>
                                
                                <button @click="showSidebar = !showSidebar" class="w-12 h-12 flex items-center justify-center bg-white border border-slate-200 text-blue-600 rounded-xl hover:bg-blue-50 transition-colors shadow-sm" :title="showSidebar ? 'Hide Filters' : 'Show Filters'">
                                    <i class="fas" :class="showSidebar ? 'fa-eye-slash' : 'fa-filter'"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Grid View -->
                    <div x-show="viewMode === 'grid'" x-transition class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                            <template x-for="item in pagedItems" :key="item.id">
                                <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 p-8 hover:-translate-y-2 transition-all duration-300 group relative overflow-hidden">
                                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform duration-500">
                                        <i class="fa-solid fa-id-card-clip text-9xl text-blue-900"></i>
                                    </div>
                                    
                                    <div class="flex items-start justify-between mb-8 relative z-10">
                                        <div class="w-20 h-20 rounded-[1.75rem] bg-blue-900 p-1 flex items-center justify-center shadow-lg group-hover:rotate-6 transition-transform">
                                            <div class="w-full h-full rounded-[1.5rem] bg-white flex items-center justify-center text-blue-900 font-extrabold text-2xl tracking-tight uppercase" x-text="item.username.substring(0, 2)"></div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase mb-1">Access State</div>
                                            <!-- Blocked (Rose / Ban): item.is_blocked -->
                                            <template x-if="item.is_blocked">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase justify-end bg-rose-100 text-rose-800 border border-rose-300 shadow-sm">
                                                    <i class="fa-solid fa-ban text-[9px]"></i> Blocked
                                                </span>
                                            </template>
                                            <!-- Security Locked (Rose / Padlock): !item.is_blocked && locked_until > now() -->
                                            <template x-if="!item.is_blocked && item.is_security_locked">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase justify-end bg-rose-50 text-rose-700 border border-rose-200 shadow-sm">
                                                    <i class="fa-solid fa-lock text-[9px] animate-pulse"></i> Security Locked
                                                </span>
                                            </template>
                                            <!-- Active (Green): !item.is_blocked && !item.is_security_locked && item.is_active -->
                                            <template x-if="!item.is_blocked && !item.is_security_locked && item.is_active">
                                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase justify-end bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Active
                                                </span>
                                            </template>
                                            <!-- Suspended (Slate): !item.is_blocked && !item.is_security_locked && !item.is_active -->
                                            <template x-if="!item.is_blocked && !item.is_security_locked && !item.is_active">
                                                <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase justify-end bg-slate-100 text-slate-600 border border-slate-200">
                                                    <span class="w-2 h-2 rounded-full bg-slate-400"></span> Suspended
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="relative z-10">
                                        <h4 class="text-2xl font-extrabold text-blue-900 tracking-tight leading-tight mb-1" x-text="item.username"></h4>
                                        <div class="flex items-center gap-2 mb-6 text-xs font-black text-blue-600 uppercase tracking-widest" x-text="item.role ? item.role.display_name : 'Sentinel'"></div>

                                        <div class="bg-slate-50 rounded-2xl p-6 border border-slate-100 mb-8">
                                            <div class="space-y-4">
                                                <div class="flex items-center justify-between">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Designation</span>
                                                    <span class="text-xs font-bold text-blue-900" x-text="item.designation ? item.designation.name : 'System Identity'"></span>
                                                </div>
                                                <div class="flex items-center justify-between pt-4 border-t border-slate-200/50">
                                                    <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">CNIC No</span>
                                                    <span class="text-xs font-bold text-slate-600" x-text="item.cnic || 'N/A'"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="pt-5 border-t border-slate-100 space-y-3">
                                            <!-- Provisioned badge — full-width single line -->
                                            <div class="flex items-center gap-2.5">
                                                <div class="w-8 h-8 rounded-xl bg-slate-50 border border-slate-200 text-slate-400 flex items-center justify-center shrink-0">
                                                    <i class="fa-solid fa-key text-xs"></i>
                                                </div>
                                                <span class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Provisioned</span>
                                            </div>

                                            <!-- Action Grid: slim w-9 h-9 matching table style -->
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button @click="viewDetails(item)" title="View Profile" class="w-9 h-9 rounded-xl bg-blue-500 border border-blue-600 text-white hover:bg-blue-600 active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0">
                                                    <i class="fa-solid fa-eye text-xs"></i>
                                                </button>
                                                <button @click="editItem(item)" title="Modify Parameters" class="w-9 h-9 rounded-xl bg-indigo-500 border border-indigo-600 text-white hover:bg-indigo-600 active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0">
                                                    <i class="fa-solid fa-sliders text-xs"></i>
                                                </button>
                                                <template x-if="canManagePassword(item)">
                                                    <button @click="openPasswordReset(item)" title="Force Key Rotation" class="w-9 h-9 rounded-xl bg-slate-700 border border-slate-800 text-white hover:bg-slate-800 active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0">
                                                        <i class="fa-solid fa-key text-xs"></i>
                                                    </button>
                                                </template>
                                                <template x-if="!canManagePassword(item)">
                                                    <div :title="(item.role_name === 'super_admin' || (item.role && item.role.name === 'super_admin')) ? 'Super Admin: CLI SSH Recovery Only' : 'Restricted Authority'" class="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 text-slate-300 flex items-center justify-center aspect-square shrink-0 cursor-not-allowed opacity-50">
                                                        <i class="fa-solid fa-lock text-xs"></i>
                                                    </div>
                                                </template>
                                                <!-- Clear Security Lockout (Next to Edit & Password Reset) -->
                                                <template x-if="item.is_security_locked">
                                                    <button @click="confirmClearLockout(item)" title="Clear Security Lockout & Restore" class="w-9 h-9 rounded-xl bg-rose-600 border border-rose-700 text-white hover:bg-rose-700 active:scale-95 transition-all shadow-md shadow-rose-600/30 flex items-center justify-center aspect-square shrink-0 animate-pulse">
                                                        <i class="fa-solid fa-lock-open text-xs"></i>
                                                    </button>
                                                </template>
                                                <template x-if="!item.is_security_locked">
                                                    <button @click="confirmClearLockout(item)" title="Clear Security Lockout Counters" class="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 text-slate-400 hover:text-slate-700 hover:bg-slate-200 active:scale-95 transition-all flex items-center justify-center aspect-square shrink-0">
                                                        <i class="fa-solid fa-lock-open text-xs"></i>
                                                    </button>
                                                </template>
                                                <button @click="viewAccess(item)" title="View Access Topology" class="w-9 h-9 rounded-xl bg-purple-600 border border-purple-700 text-white hover:bg-purple-700 active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0">
                                                    <i class="fa-solid fa-shield-halved text-xs"></i>
                                                </button>
                                                <a :href="'/mgmt/users/' + (item.uuid || item.id) + '/audit'" title="Activity History & Audit Ledger" class="w-9 h-9 rounded-xl bg-teal-600 border border-teal-700 text-white hover:bg-teal-700 active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0">
                                                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                                </a>
                                                <button @click="confirmStatus(item)" title="Toggle Active State" :class="item.is_active ? 'bg-amber-500 border-amber-600 hover:bg-amber-600' : 'bg-emerald-500 border-emerald-600 hover:bg-emerald-600'" class="w-9 h-9 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0">
                                                    <i class="fa-solid fa-power-off text-xs"></i>
                                                </button>
                                                <button @click="confirmDelete(item)" title="Purge Identity" class="w-9 h-9 rounded-xl bg-rose-600 border border-rose-700 text-white hover:bg-rose-700 active:scale-95 transition-all shadow-sm flex items-center justify-center aspect-square shrink-0">
                                                    <i class="fa-solid fa-trash-alt text-xs"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Table View -->
                    <div x-show="viewMode === 'table'" x-transition class="overflow-x-auto shadow-inner bg-white rounded-3xl border border-slate-100">
                        <table class="w-full text-left" :class="density === 'condensed' ? 'condensed-table' : 'spacious-table'">
                            <thead class="bg-blue-50/95 backdrop-blur-sm border-b border-blue-100 sticky top-0 z-10 shadow-sm">
                                <tr>
                                    <th class="px-4 py-4 border-b border-slate-50">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-tag text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('username')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                Identity Handle
                                                <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('username')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-user-shield text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('role_id')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                Operational Role
                                                <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('role_id')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-id-card text-[10px]"></i>
                                            </div>
                                            <span class="flex items-center gap-1.5 text-slate-400">
                                                User Info
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-map-marked-alt text-[10px]"></i>
                                            </div>
                                            <span class="flex items-center gap-1.5 text-slate-400">
                                                Operational Scope
                                            </span>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50 text-center">
                                        <div class="flex items-center justify-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-power-off text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('is_active')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                State
                                                <i class="fas text-[10px] transition-all duration-300 opacity-0 group-hover:opacity-100 fa-sort text-blue-600" :class="getSortIcon('is_active')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50 text-center">
                                        <span class="text-xs font-black text-slate-500 uppercase tracking-widest">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="item in pagedItems" :key="item.id">
                                    <tr class="hover:bg-blue-50/40 transition-colors group">
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <span class="font-black text-blue-900 text-sm uppercase tracking-wider" x-text="item.username"></span>
                                        </td>
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <span class="text-xs font-black text-blue-600 bg-blue-50 px-2 py-1 rounded" x-text="item.role ? item.role.display_name : 'Standard Operative'"></span>
                                        </td>
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="flex flex-col">
                                                <span class="font-bold text-blue-900 text-sm" x-text="item.full_name || 'Personnel'"></span>
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-tighter" x-text="item.designation ? (item.designation.name + (item.designation.bps ? ' (BPS-' + item.designation.bps + ')' : '')) : 'System Identity'"></span>
                                            </div>
                                        </td>
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-slate-700" x-text="item.scope_unit_name || 'National'"></span>
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest" x-text="item.scope_unit_type || 'Master'"></span>
                                            </div>
                                        </td>
                                        <td class="px-6 text-center" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <!-- Blocked (Rose / Ban): item.is_blocked -->
                                            <template x-if="item.is_blocked">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border bg-rose-100 text-rose-800 border-rose-300 shadow-sm">
                                                    <i class="fa-solid fa-ban text-[8px]"></i> Blocked
                                                </span>
                                            </template>
                                            <!-- Security Locked (Rose / Padlock): !item.is_blocked && locked_until > now() -->
                                            <template x-if="!item.is_blocked && item.is_security_locked">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border bg-rose-50 text-rose-700 border-rose-200 shadow-sm">
                                                    <i class="fa-solid fa-lock text-[8px] animate-pulse"></i> Security Locked
                                                </span>
                                            </template>
                                            <!-- Active (Green): !item.is_blocked && !item.is_security_locked && item.is_active -->
                                            <template x-if="!item.is_blocked && !item.is_security_locked && item.is_active">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border bg-emerald-50 text-emerald-700 border-emerald-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Active
                                                </span>
                                            </template>
                                            <!-- Suspended (Slate): !item.is_blocked && !item.is_security_locked && !item.is_active -->
                                            <template x-if="!item.is_blocked && !item.is_security_locked && !item.is_active">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border bg-slate-100 text-slate-600 border-slate-200">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Suspended
                                                </span>
                                            </template>
                                        </td>
                                        <td class="px-6 text-center" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <!-- Action Grid: icon buttons -->
                                            <div class="flex flex-wrap items-center justify-center gap-1.5 w-[160px] mx-auto">
                                                <button @click="viewDetails(item)" title="View Profile" class="w-9 h-9 bg-blue-500 border border-blue-600 text-white hover:bg-blue-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>
                                                <button @click="editItem(item)" title="Modify Parameters" class="w-9 h-9 bg-indigo-500 border border-indigo-600 text-white hover:bg-indigo-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                    <i class="fas fa-sliders text-xs"></i>
                                                </button>
                                                <template x-if="canManagePassword(item)">
                                                    <button @click="openPasswordReset(item)" title="Rotate Key" class="w-9 h-9 bg-slate-700 border border-slate-800 text-white hover:bg-slate-800 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                        <i class="fas fa-key text-xs"></i>
                                                    </button>
                                                </template>
                                                <template x-if="!canManagePassword(item)">
                                                    <div :title="(item.role_name === 'super_admin' || (item.role && item.role.name === 'super_admin')) ? 'Super Admin: CLI SSH Recovery Only' : 'Restricted'" class="w-9 h-9 bg-slate-50 border border-slate-100 text-slate-300 flex items-center justify-center rounded-xl opacity-50 cursor-not-allowed aspect-square shrink-0">
                                                        <i class="fas fa-lock text-xs"></i>
                                                    </div>
                                                </template>
                                                <!-- Clear Security Lockout (Next to Edit and Password Reset) -->
                                                <template x-if="item.is_security_locked">
                                                    <button @click="confirmClearLockout(item)" title="Clear Security Lockout & Restore" class="w-9 h-9 bg-rose-600 border border-rose-700 text-white hover:bg-rose-700 transition-colors flex items-center justify-center rounded-xl shadow-md shadow-rose-600/30 active:scale-95 aspect-square shrink-0 animate-pulse">
                                                        <i class="fa-solid fa-lock-open text-xs"></i>
                                                    </button>
                                                </template>
                                                <template x-if="!item.is_security_locked">
                                                    <button @click="confirmClearLockout(item)" title="Clear Security Lockout Counters" class="w-9 h-9 bg-slate-100 border border-slate-200 text-slate-400 hover:text-slate-700 hover:bg-slate-200 transition-colors flex items-center justify-center rounded-xl active:scale-95 aspect-square shrink-0">
                                                        <i class="fa-solid fa-lock-open text-xs"></i>
                                                    </button>
                                                </template>
                                                <button @click="viewAccess(item)" title="View Access Topology" class="w-9 h-9 bg-purple-600 border border-purple-700 text-white hover:bg-purple-700 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                    <i class="fas fa-shield-halved text-xs"></i>
                                                </button>
                                                <a :href="'/mgmt/users/' + (item.uuid || item.id) + '/audit'" title="Activity History & Audit Ledger" class="w-9 h-9 bg-teal-600 border border-teal-700 text-white hover:bg-teal-700 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                    <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                                </a>
                                                <button @click="confirmStatus(item)" title="Toggle State" :class="item.is_active ? 'bg-amber-500 border-amber-600 hover:bg-amber-600' : 'bg-emerald-500 border-emerald-600 hover:bg-emerald-600'" class="w-9 h-9 text-white border transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                    <i class="fa-solid fa-power-off text-xs"></i>
                                                </button>
                                                <button @click="confirmDelete(item)" title="Purge Identity" class="w-9 h-9 bg-rose-600 border border-rose-700 text-white hover:bg-rose-700 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95 aspect-square shrink-0">
                                                    <i class="fa-solid fa-trash-alt text-xs"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>

                        <!-- Loading Indicator -->
                        <div x-show="loading" class="py-16 text-center bg-white border-b border-slate-100">
                            <i class="fa-solid fa-circle-notch fa-spin text-3xl text-blue-600 mb-2"></i>
                            <p class="text-xs font-bold text-slate-400 uppercase tracking-widest">Retrieving Identity Directory...</p>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div x-show="totalPages > 1" class="p-8 border-t border-slate-100 bg-slate-50/30 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs font-bold text-slate-400">
                            Showing <span class="text-blue-900" x-text="pagination.from || 0"></span> to <span class="text-blue-900" x-text="pagination.to || 0"></span> of <span class="text-blue-900" x-text="pagination.total || 0"></span> users
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- First/Prev -->
                            <button @click.prevent="goToPage(1)" :disabled="page === 1" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 disabled:opacity-30 hover:bg-slate-50 transition-all font-black text-[10px] uppercase">First</button>
                            <button @click.prevent="goToPage(page - 1)" :disabled="page === 1" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 hover:bg-slate-50 transition-all"><i class="fa-solid fa-chevron-left"></i></button>
                            
                            <!-- Sliding Window -->
                            <template x-for="p in pagesToShow()" :key="p">
                                <div class="flex items-center">
                                    <template x-if="p === '...'">
                                        <span class="px-2 text-slate-300 font-bold">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button @click.prevent="goToPage(p)" :class="page === p ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-500/30' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" class="w-10 h-10 rounded-xl border font-black text-xs transition-all" x-text="p"></button>
                                    </template>
                                </div>
                            </template>

                            <!-- Next/Last -->
                            <button @click.prevent="goToPage(page + 1)" :disabled="page === totalPages" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 hover:bg-slate-50 transition-all"><i class="fa-solid fa-chevron-right"></i></button>
                            <button @click.prevent="goToPage(totalPages)" :disabled="page === totalPages" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 disabled:opacity-30 hover:bg-slate-50 transition-all font-black text-[10px] uppercase">Last</button>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div x-show="!loading && items.length === 0" class="py-32 text-center bg-white">
                        <i class="fa-solid fa-users-slash text-6xl text-slate-100 mb-6 animate-pulse"></i>
                        <h3 class="text-2xl font-black text-blue-900" x-text="search ? 'No identity matches' : 'Identity Registry Empty'"></h3>
                        <p class="text-slate-400 font-bold mt-2" x-text="search ? 'Refine your query parameters.' : 'Database contains no configured users.'"></p>
                    </div>

                </div>
            </div>

            <!-- Right Column - Operational Filters (Sticky Intelligence Sidebar) -->
            <div class="lg:col-span-3 pb-8 transition-all duration-300 self-start lg:sticky lg:top-6 z-30" x-show="showSidebar" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-4" x-cloak>
                <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden flex flex-col max-h-[calc(100vh-3rem)]">
                    <div class="p-6 border-b border-slate-100 bg-slate-50 flex items-center justify-between shrink-0">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-blue-600 shadow-sm">
                                <i class="fas fa-filter"></i>
                            </div>
                            <div>
                                <h2 class="font-extrabold text-blue-900 text-lg tracking-tight">Intelligence</h2>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Refine Access Scope</p>
                            </div>
                        </div>
                        <button @click="showSidebar = false" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-blue-600 shadow-sm flex items-center justify-center">
                            <i class="fas fa-angle-right"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-6 w-full flex-1 overflow-y-auto">
                        <!-- Search -->
                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-search text-blue-500"></i> Localize Identity
                            </label>
                            <div class="relative">
                                <input type="text" x-model="search" placeholder="Search handle/CNIC/Full Name..." class="w-full pl-11 pr-4 py-3 bg-white border-2 border-slate-100 rounded-xl focus:border-blue-500 outline-none font-bold text-slate-700 text-sm transition-all focus:shadow-lg focus:shadow-blue-500/10">
                                <i class="fas fa-id-card absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
                            </div>
                        </div>

                        <!-- Role Filter -->
                        <div class="space-y-3 pt-4">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-user-shield text-amber-500"></i> Authority Level
                            </label>
                            <select x-model="filterRole" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner">
                                <option value="">Global Access</option>
                                <template x-for="r in roles" :key="r.id">
                                    <option :value="r.id" x-text="r.display_name"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Area Filters (Hierarchical) -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-map-marked-alt text-blue-500"></i> Operation Area
                            </label>

                            <!-- Zone Filter -->
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Zone</label>
                                <select x-model="filterZone" @change="filterSector = ''; filterBeat = ''; filterCallCentre = ''" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner">
                                    <option value="">All Zones</option>
                                    <template x-for="z in zones" :key="z.id">
                                        <option :value="z.id" x-text="z.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Sector Filter -->
                            <div class="space-y-2" x-show="filterZone">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Sector</label>
                                <select x-model="filterSector" @change="filterBeat = ''" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner">
                                    <option value="">All Sectors</option>
                                    <template x-for="s in filteredSectorsForFilter" :key="s.id">
                                        <option :value="s.id" x-text="s.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Beat Filter -->
                            <div class="space-y-2" x-show="filterSector">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Beat</label>
                                <select x-model="filterBeat" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner">
                                    <option value="">All Beats</option>
                                    <template x-for="b in filteredBeatsForFilter" :key="b.id">
                                        <option :value="b.id" x-text="b.name"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Call Center Filter -->
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest ml-1">Call Center</label>
                                <select x-model="filterCallCentre" @change="filterZone = ''; filterSector = ''; filterBeat = ''" class="w-full px-4 py-3 bg-slate-50 border-2 border-slate-100 rounded-xl outline-none font-bold text-slate-600 text-xs shadow-inner">
                                    <option value="">Global Call Center</option>
                                    <option value="1">National Call Center (HQ)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="space-y-4 pt-4 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-shield-virus text-emerald-500"></i> Status State
                            </label>
                            <div class="grid grid-cols-1 gap-2">
                                <button @click="filterStatus = ''" :class="filterStatus === '' ?
                                        'bg-blue-600 text-white shadow-md' :
                                        'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Global Directory</span>
                                    <i class="fas fa-globe-americas transition-opacity" :class="filterStatus === '' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'active'" :class="filterStatus === 'active' ?
                                        'bg-emerald-600 text-white shadow-md' :
                                        'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Active (Authorized)</span>
                                    <i class="fas fa-check-circle transition-opacity" :class="filterStatus === 'active' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'patrol_user'" :class="filterStatus === 'patrol_user' ?
                                        'bg-cyan-600 text-white shadow-md' :
                                        'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Patrol Tigers</span>
                                    <i class="fas fa-shield-halved transition-opacity" :class="filterStatus === 'patrol_user' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'commuter_user'" :class="filterStatus === 'commuter_user' ?
                                        'bg-teal-600 text-white shadow-md' :
                                        'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Commuters (Hamsafar)</span>
                                    <i class="fas fa-mobile-screen-button transition-opacity" :class="filterStatus === 'commuter_user' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'suspended'" :class="filterStatus === 'suspended' ?
                                        'bg-slate-700 text-white shadow-md' :
                                        'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Suspended (Disabled)</span>
                                    <i class="fas fa-pause-circle transition-opacity" :class="filterStatus === 'suspended' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                                <button @click="filterStatus = 'security_locked'" :class="filterStatus === 'security_locked' ?
                                        'bg-rose-600 text-white shadow-md' :
                                        'bg-slate-50 text-slate-600 hover:bg-slate-100 border-2 border-slate-100'" class="px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all text-left flex items-center justify-between group">
                                    <span>Security Locked</span>
                                    <i class="fas fa-lock transition-opacity" :class="filterStatus === 'security_locked' ? 'opacity-100' : 'opacity-40'"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Page Density Filter -->
                        <div class="space-y-3 pt-4 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-compress-alt text-blue-500"></i> Page Density
                            </label>
                            <div class="grid grid-cols-2 gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200/50">
                                <button @click="density = 'condensed'" :class="density === 'condensed' ? 'bg-white text-blue-600 shadow-sm' :
                                        'text-slate-500 hover:text-slate-700'" class="py-2 text-[9px] font-black uppercase tracking-widest rounded-lg transition-all text-slate-500 hover:text-slate-700">
                                    Condensed
                                </button>
                                <button @click="density = 'spacious'" :class="density === 'spacious' ? 'bg-white text-blue-600 shadow-sm font-black' :
                                        'text-slate-500 hover:text-slate-700'" class="py-2 text-[9px] font-black uppercase tracking-widest rounded-lg transition-all">
                                    Spacious
                                </button>
                            </div>
                        </div>

                        <!-- Records Per Page Filter -->
                        <div class="space-y-3 pt-4 border-t border-slate-100">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-list-ol text-blue-500"></i> Records Per Page
                            </label>
                            <div class="grid grid-cols-4 gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200/50">
                                <template x-for="size in [10, 25, 50, 100]" :key="size">
                                    <button @click="perPage = size; page = 1" :class="perPage == size ? 'bg-white text-blue-600 shadow-sm font-black' :
                                            'text-slate-500 hover:text-slate-700'" class="py-2 text-[9px] uppercase tracking-widest rounded-lg transition-all" x-text="size">
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Sort Logic -->
                        <div class="space-y-3 pt-6 border-t border-slate-100">
                            <button @click="clearFilters()" class="w-full py-5 bg-rose-600 text-white hover:bg-rose-700 rounded-3xl text-[10px] font-black uppercase tracking-[0.3em] transition-all duration-300 flex items-center justify-center gap-3 active:scale-95 shadow-lg shadow-rose-600/20">
                                <i class="fas fa-broom"></i> Reset Filters
                            </button>
                            <button @click="showSidebar = false" class="w-full py-4 mt-2 bg-blue-600 text-white rounded-3xl font-black text-[10px] uppercase tracking-widest hover:bg-blue-700 transition-all flex items-center justify-between px-6 shadow-md shadow-blue-600/20">
                                <span>Hide Filters</span><i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal (HMS Master Bento Luxury Architecture) -->
        <div x-show="showModal" class="fixed inset-0 z-[100] overflow-y-auto px-4 py-6" x-transition.opacity>
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[90]" @click="showModal = false"></div>
                
                <div class="bg-white rounded-3xl shadow-2xl w-full max-w-5xl z-[100] overflow-hidden relative border border-slate-100 max-h-[92vh] flex flex-col" x-transition.scale.origin.center>
                    {{-- Modal Header --}}
                    <div :class="modalMode === 'add' ? 'from-blue-600 via-blue-700 to-indigo-800' : 'from-indigo-600 via-purple-700 to-blue-800'" class="bg-gradient-to-r p-6 text-white relative flex-shrink-0">
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-md flex items-center justify-center shadow-xl border border-white/20">
                                    <i class="fa-solid fa-user-plus text-xl text-white" x-show="modalMode === 'add'"></i>
                                    <i class="fa-solid fa-user-pen text-xl text-white" x-show="modalMode === 'edit'"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-xl font-extrabold tracking-tight" x-text="modalMode === 'add' ? 'Provision Identity' : 'Modify Identity Parameters'"></h3>
                                        <span class="px-2.5 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-white/20 border border-white/30 text-white"
                                              x-text="modalMode === 'add' ? 'New Officer' : 'Existing Member'"></span>
                                    </div>
                                    <p class="text-blue-100 text-[10px] font-bold tracking-wide mt-0.5" x-text="modalMode === 'add' ? 'Establishing authenticated NHMP 130 helpline personnel profile' : 'Modifying profile for @' + form.username"></p>
                                </div>
                            </div>
                            <button type="button" @click="showModal = false" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                                <i class="fa-solid fa-times text-base"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Form with Two-Column Bento Layout --}}
                    <form @submit.prevent="submitForm" data-no-pjax class="p-6 sm:p-8 overflow-y-auto space-y-6 bg-slate-50/50 flex-1">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-7">
                            
                            {{-- LEFT COLUMN: Basic Information & Credentials (5 Columns) --}}
                            <div class="lg:col-span-5 space-y-5">
                                
                                {{-- 1. Personnel Identity Card --}}
                                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 space-y-4">
                                    <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-2">
                                        <i class="fa-solid fa-id-card text-blue-600"></i> Personnel Identity
                                    </h4>

                                    <!-- Full Name -->
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Full Name *</label>
                                        <input type="text" x-model="form.full_name" required autocomplete="name"
                                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 font-bold text-slate-800 outline-none transition-all shadow-inner text-xs sm:text-sm"
                                               placeholder="e.g. Muhammad Aadil">
                                    </div>

                                    <!-- Identity Handle (Username) -->
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Identity Handle (Username) *</label>
                                        <div class="relative">
                                            <span class="absolute left-3.5 top-2.5 text-slate-400 font-mono text-xs">@</span>
                                            <input type="text" x-model="form.username" required autocomplete="username"
                                                   class="w-full pl-8 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 font-bold font-mono text-slate-800 outline-none transition-all shadow-inner text-xs sm:text-sm"
                                                   placeholder="username">
                                        </div>
                                    </div>

                                    <!-- Email -->
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Email Address *</label>
                                        <div class="relative">
                                            <i class="fa-solid fa-envelope absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                                            <input type="email" x-model="form.email" required autocomplete="email"
                                                   class="w-full pl-9 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 font-bold text-slate-800 outline-none transition-all shadow-inner text-xs sm:text-sm"
                                                   placeholder="officer@nhmp.gov.pk">
                                        </div>
                                    </div>

                                    <!-- Mobile & CNIC -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Mobile Number</label>
                                            <div class="relative">
                                                <i class="fa-solid fa-phone absolute left-3 top-3 text-slate-400 text-xs"></i>
                                                <input type="text" x-model="form.mobile_no" pattern="^03[0-9]{9}$" autocomplete="tel"
                                                       class="w-full pl-8 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 font-bold font-mono text-slate-800 outline-none transition-all shadow-inner text-xs"
                                                       placeholder="03001234567" title="Mobile number must start with 03 and be 11 digits long.">
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">CNIC Identity *</label>
                                            <div class="relative">
                                                <i class="fa-solid fa-address-card absolute left-3 top-3 text-slate-400 text-xs"></i>
                                                <input type="text" x-model="form.cnic" required
                                                       @input="form.cnic = formatCnicInput($event.target.value)"
                                                       maxlength="15" autocomplete="off"
                                                       class="w-full pl-8 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 font-bold font-mono text-slate-800 outline-none transition-all shadow-inner text-xs"
                                                       placeholder="35202-1234567-1">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Rank & Pay Scale (Designation & BPS) -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 border-t border-slate-100">
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Rank (Designation)</label>
                                            <select x-model="tempDesignationName" @change="form.designation_id = ''" required
                                                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 font-bold text-slate-800 outline-none transition-all text-xs">
                                                <option value="">Select Rank...</option>
                                                <template x-for="name in availableDesignationNames" :key="name">
                                                    <option :value="name" x-text="name"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">BPS Grade</label>
                                            <select x-model="form.designation_id" required
                                                    class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-4 focus:ring-blue-500/10 focus:border-blue-500 font-bold text-slate-800 outline-none transition-all text-xs">
                                                <option value="">Select BPS...</option>
                                                <template x-for="d in availableBPS" :key="d.id">
                                                    <option :value="d.id" x-text="'BPS-' + d.bps + ' (' + d.short_code + ')'"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {{-- 2. Pure Blocked vs Unblocked State Toggle Card --}}
                                <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200/80 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <span class="text-xs font-black uppercase tracking-wider block text-slate-700">Account Access State</span>
                                            <span class="text-[11px] font-bold" :class="form.is_blocked ? 'text-rose-600' : 'text-emerald-600'"
                                                  x-text="form.is_blocked ? 'BLOCKED — Credentials revoked' : 'ACTIVE — Operational duty'"></span>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" x-model="form.is_blocked" class="sr-only peer">
                                            <div class="w-11 h-6 bg-emerald-500 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-rose-600"></div>
                                        </label>
                                    </div>
                                    <p class="text-[9px] text-slate-400 font-medium">
                                        Pure status architecture: When blocked, password hash is invalidated and active sessions are terminated.
                                    </p>
                                </div>

                                {{-- 3. Automated Security Key Vault (Strictly System Generated) --}}
                                <div x-show="modalMode === 'add'" class="bg-white p-5 rounded-2xl shadow-sm border-2 border-dashed border-blue-200/80 bg-gradient-to-br from-blue-50/50 to-indigo-50/30 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-lg bg-blue-600 text-white flex items-center justify-center text-xs shadow-sm">
                                                <i class="fa-solid fa-shield-halved"></i>
                                            </div>
                                            <div>
                                                <h4 class="text-xs font-black text-blue-900 uppercase tracking-widest leading-none">Security Key Vault</h4>
                                                <p class="text-[9px] font-bold text-slate-500 mt-0.5">High-entropy cryptographic generation</p>
                                            </div>
                                        </div>
                                        <span class="text-[9px] font-black text-emerald-700 uppercase tracking-wider bg-emerald-100 border border-emerald-200 px-2 py-0.5 rounded-full flex items-center gap-1">
                                            <i class="fa-solid fa-circle-check text-[8px]"></i> System Generated
                                        </span>
                                    </div>

                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                                Generated Master Key (Read-Only)
                                            </label>
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" @click="copyToClipboard(form.password, 'add_pass')" 
                                                    class="text-[10px] font-black px-2 py-0.5 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 flex items-center gap-1 shadow-xs transition-all active:scale-95">
                                                    <i :class="copiedField === 'add_pass' ? 'fa-solid fa-check text-emerald-600' : 'fa-regular fa-copy text-blue-600'"></i>
                                                    <span x-text="copiedField === 'add_pass' ? 'Copied!' : 'Copy'"></span>
                                                </button>
                                                <button type="button" @click="generateFormPassword()" 
                                                    class="text-[10px] font-black px-2 py-0.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white flex items-center gap-1 shadow-xs shadow-blue-500/20 transition-all active:scale-95">
                                                    <i class="fa-solid fa-wand-magic-sparkles text-[9px]"></i>
                                                    <span>Re-Gen</span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="relative">
                                            <input type="text" x-model="form.password" readonly autocomplete="off"
                                                class="w-full px-3.5 py-2.5 bg-slate-100 border border-slate-200 rounded-xl font-mono font-bold text-slate-800 text-xs tracking-wider select-all cursor-not-allowed shadow-inner focus:outline-none">
                                            <input type="hidden" name="password_confirmation" x-model="form.password_confirmation">
                                            <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">
                                                <i class="fa-solid fa-lock"></i>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Strength Meter Bar -->
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-[9px] font-black uppercase tracking-wider">
                                            <span class="text-emerald-700 flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                                Cryptographic Strength: 100% Entropy (16 Chars)
                                            </span>
                                            <span class="text-slate-400">Enterprise Standard</span>
                                        </div>
                                        <div class="w-full h-1.5 rounded-full bg-slate-200 overflow-hidden">
                                            <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 w-full rounded-full"></div>
                                        </div>
                                    </div>
                                </div>

                            </div>

                            {{-- RIGHT COLUMN: Authority Level (Roles) & Operational Scope / Branches (7 Columns) --}}
                            <div class="lg:col-span-7 space-y-5">
                                
                                {{-- 1. Authority Level / Roles 3D Selectable Cards --}}
                                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-2">
                                                <i class="fa-solid fa-shield-halved text-indigo-600"></i> Authority Level (Role) *
                                            </h4>
                                            <p class="text-[11px] font-bold text-slate-400 mt-0.5">Select the operational role determining governance capabilities</p>
                                        </div>
                                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-lg"
                                              :class="form.role_id ? 'bg-indigo-100 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-400'"
                                              x-text="form.role_id ? 'Role Assigned' : 'Required'"></span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 max-h-56 overflow-y-auto pr-1">
                                        <template x-for="r in filteredRolesForAssignment" :key="r.id">
                                            <div @click="form.role_id = r.id"
                                                 :class="form.role_id == r.id ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white shadow-md border-blue-500 scale-[1.01]' : 'bg-slate-50 hover:bg-slate-100/80 border-slate-200 text-slate-700'"
                                                 class="p-3 rounded-xl border transition-all duration-150 cursor-pointer select-none flex flex-col justify-between gap-2 shadow-xs active:scale-95">
                                                <div class="flex items-start justify-between gap-2">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0 text-xs"
                                                             :class="form.role_id == r.id ? 'bg-white/20 text-white' : 'bg-white text-blue-600 shadow-xs border border-slate-100'">
                                                            <i class="fa-solid fa-user-shield"></i>
                                                        </div>
                                                        <span class="text-xs font-bold leading-tight truncate" :class="form.role_id == r.id ? 'text-white' : 'text-slate-800'" x-text="r.display_name"></span>
                                                    </div>
                                                    <div class="w-5 h-5 rounded-lg flex items-center justify-center shrink-0 text-[10px]"
                                                         :class="form.role_id == r.id ? 'bg-white text-blue-600 shadow-xs' : 'border border-slate-300 text-transparent'">
                                                        <i class="fa-solid fa-check"></i>
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between text-[9px] font-black uppercase pt-1 border-t"
                                                     :class="form.role_id == r.id ? 'border-white/20 text-white/80' : 'border-slate-200 text-slate-400'">
                                                    <span x-text="r.name.replace('_', ' ')"></span>
                                                    <span x-text="form.role_id == r.id ? '✓ Selected' : '+ Select'"></span>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </div>

                                {{-- 2. Operational Scope & Branch Deployment --}}
                                <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-200/80 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-2">
                                                <i class="fa-solid fa-network-wired text-emerald-600"></i> Operational Scope & Branch *
                                            </h4>
                                            <p class="text-[11px] font-bold text-slate-400 mt-0.5">Determine geographical jurisdiction and data bounds</p>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            ★ Primary Access
                                        </span>
                                    </div>

                                    <!-- Scope Type Selector Pills -->
                                    <div>
                                        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1.5">Scope Boundary Type</label>
                                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                            <template x-for="st in availableScopeTypes" :key="st.value">
                                                <button type="button" 
                                                        @click="form.scope_unit_type = st.value; form.scope_unit_id = ''; tempZoneId = ''; tempSectorId = ''"
                                                        :class="form.scope_unit_type === st.value ? 'bg-emerald-600 text-white shadow-sm border-emerald-600' : 'bg-slate-50 hover:bg-slate-100 text-slate-700 border-slate-200'"
                                                        class="px-3 py-2 rounded-xl text-xs font-bold border transition-all text-center flex items-center justify-center gap-1.5">
                                                    <i class="fa-solid fa-circle-dot text-[8px]" x-show="form.scope_unit_type === st.value"></i>
                                                    <span x-text="st.label"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Cascading Unit Selections (When not National) -->
                                    <div x-show="form.scope_unit_type !== 'national'" x-transition class="space-y-3 pt-2 border-t border-slate-100">
                                        <!-- Zone Selection (Required for Zone, Sector, Beat) -->
                                        <div x-show="['zone', 'sector', 'beat'].includes(form.scope_unit_type)">
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Select Zone *</label>
                                            <select x-model="tempZoneId" @change="tempSectorId = ''; form.scope_unit_id = (form.scope_unit_type === 'zone' ? $event.target.value : '')"
                                                    :required="['zone', 'sector', 'beat'].includes(form.scope_unit_type)"
                                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:border-emerald-500 font-bold text-slate-800 text-xs">
                                                <option value="">Select Zone...</option>
                                                <template x-for="z in zones" :key="z.id">
                                                    <option :value="z.id" x-text="z.name"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <!-- Sector Selection (Required for Sector, Beat) -->
                                        <div x-show="['sector', 'beat'].includes(form.scope_unit_type)">
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Select Sector *</label>
                                            <select x-model="tempSectorId" @change="form.scope_unit_id = (form.scope_unit_type === 'sector' ? $event.target.value : '')"
                                                    :required="['sector', 'beat'].includes(form.scope_unit_type)"
                                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:border-emerald-500 font-bold text-slate-800 text-xs">
                                                <option value="">Select Sector...</option>
                                                <template x-for="s in filteredSectors" :key="s.id">
                                                    <option :value="s.id" x-text="s.name"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <!-- Beat Selection -->
                                        <div x-show="form.scope_unit_type === 'beat'">
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Select Beat *</label>
                                            <select x-model="form.scope_unit_id" :required="form.scope_unit_type === 'beat'"
                                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:border-emerald-500 font-bold text-slate-800 text-xs">
                                                <option value="">Select Beat...</option>
                                                <template x-for="b in filteredBeats" :key="b.id">
                                                    <option :value="b.id" x-text="b.name"></option>
                                                </template>
                                            </select>
                                        </div>

                                        <!-- Call Center Selection -->
                                        <div x-show="form.scope_unit_type === 'call_centre'">
                                            <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 mb-1">Call Center Facility *</label>
                                            <select x-model="form.scope_unit_id" :required="form.scope_unit_type === 'call_centre'"
                                                    class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl focus:border-emerald-500 font-bold text-slate-800 text-xs">
                                                <option value="">Select Facility...</option>
                                                <option value="1">National Call Center (HQ - Islamabad)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>

                        {{-- Footer Action Buttons (Sticky at modal bottom) --}}
                        <div class="sticky bottom-0 z-20 flex items-center justify-between pt-4 pb-4 border-t border-slate-200/80 bg-white/95 backdrop-blur-md -mx-6 -mb-6 sm:-mx-8 sm:-mb-8 px-6 sm:px-8 shadow-md mt-6">
                            <button type="button" @click="showModal = false"
                                    class="px-6 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-black text-xs uppercase tracking-wider transition-all">
                                Cancel
                            </button>
                            <button type="submit" :disabled="saving"
                                    :class="modalMode === 'add' ? 'bg-gradient-to-r from-blue-600 to-indigo-700 shadow-blue-500/20' : 'bg-gradient-to-r from-emerald-600 to-teal-700 shadow-emerald-500/20'"
                                    class="px-8 py-2.5 text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-lg hover:-translate-y-0.5 transition-all active:scale-95 disabled:opacity-50 flex items-center justify-center gap-2">
                                <span x-show="!saving" x-text="modalMode === 'add' ? 'Provision Identity' : 'Save Changes'"></span>
                                <span x-show="saving"><i class="fa-solid fa-circle-notch animate-spin mr-1.5"></i> Synchronizing...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Password Reset Modal -->
        <div x-show="showPasswordModal" class="fixed inset-0 z-[100] overflow-y-auto px-4 py-6" x-transition.opacity style="display: none;">
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-white/30 backdrop-blur-sm z-[90]" @click="showPasswordModal = false"></div>
                <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-md z-[100] overflow-hidden relative border border-slate-100" x-transition.scale.origin.center>
                    <div class="bg-gradient-to-br from-rose-600 to-rose-700 p-6 text-white relative">
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-4">
                                <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center shadow-xl border border-white/20">
                                    <i class="fa-solid fa-key text-xl text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-xl font-extrabold tracking-tight">Force Key Reset</h3>
                                    <p class="text-emerald-100 text-[9px] font-black uppercase tracking-widest mt-0.5" x-text="'Target Handle: ' + (selectedItem ? selectedItem.username : '')"></p>
                                </div>
                            </div>
                            <button @click="showPasswordModal = false" class="absolute top-6 right-6 w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                                <i class="fa-solid fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>
                    <form @submit.prevent="submitPasswordReset" data-no-pjax class="p-8 space-y-5">
                        <input type="text" autocomplete="username" style="display: none;" x-bind:value="selectedItem ? selectedItem.username : ''">
                        
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-widest">
                                    Generated Password (Read-Only)
                                </label>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="copyToClipboard(passwordForm.password, 'reset_pass')" 
                                        class="text-[10px] font-black px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center gap-1 transition-all active:scale-95">
                                        <i :class="copiedField === 'reset_pass' ? 'fa-solid fa-check text-emerald-600' : 'fa-regular fa-copy text-rose-600'"></i>
                                        <span x-text="copiedField === 'reset_pass' ? 'Copied!' : 'Copy Key'"></span>
                                    </button>
                                    <button type="button" @click="generateCompliantPassword()" 
                                        class="text-[10px] font-black px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-700 text-white flex items-center gap-1 shadow-sm shadow-rose-500/20 transition-all active:scale-95">
                                        <i class="fa-solid fa-arrows-rotate text-[9px]"></i>
                                        <span>Re-Generate</span>
                                    </button>
                                </div>
                            </div>
                            <div class="relative">
                                <input type="text" x-model="passwordForm.password" readonly autocomplete="off" required
                                    class="w-full px-4 py-3.5 bg-slate-100 border-2 border-slate-200 rounded-xl font-mono font-bold text-slate-800 text-sm tracking-widest select-all cursor-not-allowed shadow-inner focus:outline-none">
                                <input type="hidden" x-model="passwordForm.password_confirmation">
                                <div class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">
                                    <i class="fa-solid fa-lock"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Strength Meter Bar -->
                        <div class="space-y-1">
                            <div class="flex items-center justify-between text-[9px] font-black uppercase tracking-wider">
                                <span class="text-emerald-700 flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Strength: 100% Cryptographic Entropy (16 Characters)
                                </span>
                                <span class="text-slate-400">Policy Mandated</span>
                            </div>
                            <div class="w-full h-1.5 rounded-full bg-slate-200 overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-emerald-500 to-teal-500 w-full rounded-full"></div>
                            </div>
                        </div>

                        <!-- Security Notice -->
                        <div class="bg-rose-50/70 rounded-xl p-3 border border-rose-100 text-[10px] text-rose-800 flex items-start gap-2">
                            <i class="fa-solid fa-shield-halved text-rose-500 mt-0.5 shrink-0"></i>
                            <span>Administrators are prohibited from entering custom passwords. Copy the generated key and transmit it to the officer.</span>
                        </div>
                        <div class="flex gap-4 pt-6">
                            <button type="button" @click="showPasswordModal = false" class="flex-1 px-8 py-5 bg-slate-50 text-slate-400 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-100 transition-all">Abort</button>
                            <button type="submit" :disabled="saving" class="flex-[2] px-8 py-5 bg-gradient-to-r from-rose-600 to-rose-700 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-2xl shadow-rose-500/30 hover:-translate-y-1 transition-all active:scale-95 disabled:opacity-50 flex items-center justify-center gap-3">
                                <span x-show="!saving">Execute Override</span>
                                <span x-show="saving"><i class="fa-solid fa-circle-notch animate-spin mr-2"></i> Syncing...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>



        <!-- Access Viewer Modal -->
        <div x-show="showAccessModal" class="fixed inset-0 z-[100] overflow-y-auto px-4 py-6" x-transition.opacity style="display: none;">
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-white/30 backdrop-blur-sm z-[90]" @click="showAccessModal = false"></div>
                <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl z-[100] overflow-hidden relative border border-slate-100" x-transition.scale.origin.center>
                    <!-- Access Topology Header -->
                    <div class="bg-gradient-to-br from-amber-400 via-orange-500 to-rose-500 p-8 relative overflow-hidden text-white">
                        <div class="absolute inset-0 bg-grid-white/[0.1] bg-[size:16px_16px]"></div>
                        <div class="absolute -right-12 -top-12 w-48 h-48 bg-white/20 rounded-full blur-[40px]"></div>
                        <div class="relative z-10 flex items-center gap-5">
                            <div class="w-12 h-12 shrink-0 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white border border-white/30 shadow-xl">
                                <i class="fa-solid fa-shield-halved text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-2xl font-black tracking-widest italic scale-y-110 uppercase leading-none" x-text="(selectedItem ? selectedItem.username : '') + ' - Permissions'"></h3>
                                <p class="text-white/80 text-[10px] font-black uppercase tracking-[0.3em] flex items-center gap-2 mt-2">
                                    <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                    Operational Authorization Protocol
                                </p>
                            </div>
                        </div>
                        <button @click="showAccessModal = false" class="absolute top-6 right-6 w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition-colors">
                            <i class="fa-solid fa-times text-lg text-white"></i>
                        </button>
                    </div>
                    <div class="p-8 bg-white min-h-[300px] max-h-[60vh] overflow-y-auto w-full">
                        <template x-if="selectedItem && selectedItem.role">
                            <div class="space-y-6">
                                <div class="flex items-center gap-3 mb-6">
                                    <span class="px-3 py-1 bg-amber-100 text-amber-700 font-black text-xs uppercase tracking-widest rounded-lg" x-text="'Bound Authority: ' + selectedItem.role.display_name"></span>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    <template x-if="getRolePermissions(selectedItem ? selectedItem.role_id : null).length > 0">
                                        <template x-for="perm in getRolePermissions(selectedItem ? selectedItem.role_id : null)" :key="perm.id">
                                            <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex items-start gap-3">
                                                <div class="mt-1 w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.8)]"></div>
                                                <div>
                                                    <div class="text-sm font-bold text-slate-800" x-text="perm.display_name"></div>
                                                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mt-1" x-text="perm.name"></div>
                                                </div>
                                            </div>
                                        </template>
                                    </template>
                                    <template x-if="getRolePermissions(selectedItem ? selectedItem.role_id : null).length === 0">
                                        <div class="col-span-full py-12 text-center text-slate-400 font-bold">
                                            <i class="fa-solid fa-ban text-4xl mb-4 opacity-50"></i>
                                            <p>No granular capabilities bound to this authority level directly.</p>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex gap-4 pt-8">
                                    <!-- <button @click="showAccessModal = false" class="flex-1 px-8 py-5 bg-slate-50 text-slate-400 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-100 transition-all">Abort Protocol</button> -->
                                    <button @click="showAccessModal = false" class="flex-[2] px-8 py-5 bg-gradient-to-r from-amber-500 to-orange-600 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest shadow-2xl shadow-orange-500/30 hover:-translate-y-1 transition-all active:scale-95">Close</button>
                                </div>
                            </div>
                        </template>
                        <template x-if="!selectedItem || !selectedItem.role">
                            <div class="py-12 text-center text-slate-400 font-bold">
                                <i class="fa-solid fa-user-lock text-4xl mb-4 opacity-50"></i>
                                <p>Identity possesses no explicit authority level assignments.</p>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>        <!-- View Details Modal (Luxury Bento Card Layout matching nhmp_hms) -->
        <div x-show="showViewModal" class="fixed inset-0 z-[100] overflow-y-auto px-4 py-6" x-transition.opacity style="display: none;">
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-[90]" @click="showViewModal = false"></div>
                <div class="bg-white rounded-[2.5rem] shadow-[0_32px_64px_-12px_rgba(0,0,0,0.2)] w-full max-w-2xl z-[100] overflow-hidden relative border border-slate-100 flex flex-col max-h-[90vh]" x-transition.scale.origin.center>
                    
                    <!-- Bento Header -->
                    <div class="bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 p-6 text-white flex justify-between items-center relative overflow-hidden shrink-0">
                        <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
                        <div class="flex items-center gap-4 relative z-10">
                            <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white text-2xl font-black shadow-inner border border-white/30"
                                 x-text="selectedItem ? ((selectedItem.full_name || selectedItem.username || 'US').split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase()) : 'US'"></div>
                            <div>
                                <h3 class="text-xl font-black tracking-tight leading-tight" x-text="selectedItem ? (selectedItem.full_name || selectedItem.username) : 'User Profile'"></h3>
                                <div class="flex items-center gap-2 text-[11px] font-bold text-indigo-100 tracking-wider mt-0.5">
                                    <i class="fa-solid fa-envelope text-[10px]"></i>
                                    <span x-text="selectedItem ? selectedItem.email : ''"></span>
                                </div>
                            </div>
                        </div>
                        <button @click="showViewModal = false" class="w-10 h-10 rounded-2xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all active:scale-95 border border-white/20 relative z-10 cursor-pointer">
                            <i class="fa-solid fa-xmark text-lg"></i>
                        </button>
                    </div>

                    <!-- Bento Content Body -->
                    <div class="p-6 overflow-y-auto space-y-5 scrollbar-hide" style="scrollbar-width: none;">
                        <template x-if="selectedItem">
                            <div class="space-y-5">
                                <!-- Quick Status Bento Row -->
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Account State</span>
                                        <div class="flex items-center gap-2 mt-1.5">
                                            <span class="w-2.5 h-2.5 rounded-full" :class="(!selectedItem.is_blocked && selectedItem.is_active) ? 'bg-emerald-500 animate-pulse' : 'bg-rose-500'"></span>
                                            <span class="text-xs font-black uppercase tracking-wider" :class="(!selectedItem.is_blocked && selectedItem.is_active) ? 'text-emerald-600' : 'text-rose-600'" x-text="(!selectedItem.is_blocked && selectedItem.is_active) ? 'Active' : 'Blocked / Inactive'"></span>
                                        </div>
                                    </div>

                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Two-Factor</span>
                                        <span class="text-xs font-black mt-1.5" :class="selectedItem.totp_secret ? 'text-emerald-600' : 'text-slate-500'" x-text="selectedItem.totp_secret ? 'Enabled' : 'Not Enrolled'"></span>
                                    </div>

                                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-100 flex flex-col col-span-2 sm:col-span-1">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Registration</span>
                                        <span class="text-xs font-bold text-slate-700 mt-1.5 font-mono" x-text="selectedItem.created_at ? new Date(selectedItem.created_at).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : 'N/A'"></span>
                                    </div>
                                </div>

                                <!-- Personal Identity Bento Card -->
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">Mobile Number</span>
                                        <span class="font-bold text-slate-800 font-mono text-sm" x-text="selectedItem.mobile_no || 'N/A'"></span>
                                    </div>
                                    <div class="p-3.5 bg-slate-50 rounded-2xl border border-slate-100">
                                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">CNIC Number</span>
                                        <span class="font-bold text-slate-800 font-mono text-sm" x-text="selectedItem.cnic || 'N/A'"></span>
                                    </div>
                                </div>

                                <!-- Assigned Roles Bento Card -->
                                <div class="p-5 rounded-2xl bg-slate-50/80 border border-slate-100 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                            <i class="fa-solid fa-user-shield text-indigo-500"></i> Assigned System Roles
                                        </label>
                                        <span class="text-[10px] font-bold text-indigo-600 font-mono" x-text="selectedItem.role ? selectedItem.role.display_name : 'Standard Role'"></span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <div class="px-3.5 py-1.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-xs font-bold flex items-center gap-2 shadow-sm">
                                            <i class="fa-solid fa-shield text-indigo-600"></i>
                                            <span x-text="selectedItem.role ? selectedItem.role.display_name : 'No Role Attached'"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Assigned Operational Scope & Branches Bento Card -->
                                <div class="p-5 rounded-2xl bg-slate-50/80 border border-slate-100 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                            <i class="fa-solid fa-building-shield text-blue-500"></i> Branch Authorizations &amp; Scope
                                        </label>
                                        <span class="text-[10px] font-black text-blue-600 font-mono uppercase" x-text="selectedItem.scope_unit_type ? selectedItem.scope_unit_type.replace('_', ' ') : 'National'"></span>
                                    </div>
                                    <div class="p-3.5 rounded-xl bg-white border border-slate-200 shadow-sm flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs">
                                                <i class="fa-solid fa-building"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-slate-800" x-text="selectedItem.scope_unit_name || 'All Jurisdictions / National'"></p>
                                                <p class="text-[9px] font-bold text-slate-400 uppercase" x-text="selectedItem.designation ? selectedItem.designation.name : 'NHMP Personnel'"></p>
                                            </div>
                                        </div>
                                        <span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 text-[9px] font-black uppercase tracking-wider">PRIMARY</span>
                                    </div>
                                </div>

                                <!-- Activity & Access Bento Card -->
                                <div class="p-5 rounded-2xl bg-slate-50/80 border border-slate-100 space-y-3">
                                    <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                        <i class="fa-solid fa-clock-rotate-left text-teal-500"></i> System Activity &amp; Security Log
                                    </label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                                        <div class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                                            <i class="fa-solid fa-right-to-bracket text-indigo-500 text-sm"></i>
                                            <div>
                                                <span class="text-[9px] font-black uppercase text-slate-400 block">Last Active Session</span>
                                                <span class="font-bold text-slate-700" x-text="selectedItem.last_login_at ? new Date(selectedItem.last_login_at).toLocaleString() : 'No active session recorded'"></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 p-3 bg-white rounded-xl border border-slate-200 shadow-sm">
                                            <i class="fa-solid fa-triangle-exclamation text-sm text-slate-400"></i>
                                            <div>
                                                <span class="text-[9px] font-black uppercase text-slate-400 block">Wrong Passwords (24h)</span>
                                                <span class="font-bold text-slate-700" x-text="(selectedItem.failed_attempts || 0) + ' recorded'"></span>
                                            </div>
                                        </div>
                                        <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-slate-200 shadow-sm sm:col-span-2">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <i class="fa-solid fa-fingerprint text-purple-500 text-sm shrink-0"></i>
                                                <div class="min-w-0">
                                                    <span class="text-[9px] font-black uppercase text-slate-400 block">Cryptographic UUID</span>
                                                    <span class="font-bold text-slate-700 font-mono text-[10px] truncate block" x-text="selectedItem.uuid || 'N/A'"></span>
                                                </div>
                                            </div>
                                            <button type="button" @click="navigator.clipboard.writeText(selectedItem.uuid); $dispatch('toast', { message: 'UUID copied to clipboard', type: 'success' })" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-[10px] uppercase tracking-wider shrink-0 transition-all cursor-pointer">
                                                <i class="fa-regular fa-copy mr-1"></i> Copy
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Bento Footer Actions -->
                    <div class="p-5 bg-slate-50 border-t border-slate-100 flex items-center justify-between shrink-0">
                        <a :href="'/mgmt/users/' + (selectedItem ? (selectedItem.uuid || selectedItem.id) : '') + '/audit'"
                           class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white font-black text-xs uppercase tracking-wider rounded-xl shadow-md border-b-4 border-slate-950 active:translate-y-0.5 active:border-b-2 transition-all hover:scale-[1.02] group">
                            <i class="fa-solid fa-clock-rotate-left text-indigo-400 group-hover:rotate-[-30deg] transition-transform"></i>
                            <span>Full Audit Page</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-indigo-300"></i>
                        </a>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="showViewModal = false; editItem(selectedItem)"
                                    class="px-4 py-2.5 bg-indigo-600 text-white border-b-4 border-indigo-800 rounded-xl font-black text-xs uppercase tracking-wider hover:bg-indigo-500 active:translate-y-0.5 active:border-b-2 transition-all cursor-pointer">
                                <i class="fa-solid fa-pen mr-1"></i> Edit
                            </button>
                            <button type="button" @click="showViewModal = false; openPasswordReset(selectedItem)"
                                    class="px-4 py-2.5 bg-amber-500 text-white border-b-4 border-amber-700 rounded-xl font-black text-xs uppercase tracking-wider hover:bg-amber-400 active:translate-y-0.5 active:border-b-2 transition-all cursor-pointer">
                                <i class="fa-solid fa-key mr-1"></i> Reset Password
                            </button>
                            <button type="button" @click="showViewModal = false"
                                    class="px-5 py-2.5 bg-slate-100 text-slate-600 border border-slate-200 border-b-4 border-b-slate-300 rounded-xl font-black text-xs uppercase tracking-wider hover:bg-white active:translate-y-0.5 active:border-b-2 transition-all cursor-pointer">
                                Close
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        @include('components.confirm-modal')

    </div>

    @push('scripts')
    <script>
        window.userManager = function(config) {
            return {
                mapUser(i) {
                    const primaryScope = i.active_scopes?.[0] || i.scopes?.[0];
                    let unitName = 'National';
                    let unitType = 'national';
                    let unitId = null;
                    let parentZoneId = null;
                    let parentSectorId = null;

                    if (primaryScope && primaryScope.office) {
                        const office = primaryScope.office;
                        unitName = office.name;
                        unitType = office.type;
                        unitId = office.id;
                        
                        if (office.type === 'zone') {
                            parentZoneId = office.id;
                        } else if (office.type === 'sector') {
                            parentZoneId = office.parent_id || office.zone_id;
                            parentSectorId = office.id;
                        } else if (office.type === 'beat') {
                            parentSectorId = office.parent_id;
                            const sector = (this.sectors || config.sectors || []).find(s => s.id == office.parent_id);
                            if (sector) parentZoneId = sector.zone_id;
                        }
                    }
                    const isSecurityLocked = Boolean(i.is_security_locked || (i.locked_until && new Date(i.locked_until) > new Date()));
                    const isBlocked = Boolean(i.is_blocked);
                    return { 
                        ...i, 
                        is_blocked: isBlocked,
                        is_security_locked: isSecurityLocked,
                        status_state: isBlocked ? 'blocked' : (isSecurityLocked ? 'security_locked' : (i.is_active ? 'active' : 'suspended')),
                        status_label: isBlocked ? 'Blocked' : (isSecurityLocked ? 'Security Locked' : (i.is_active ? 'Active' : 'Suspended')),
                        scope_unit_type: unitType,
                        scope_unit_name: unitName,
                        scope_unit_id: unitId,
                        parent_zone_id: parentZoneId,
                        parent_sector_id: parentSectorId,
                        role_name: i.role ? i.role.name : (i.role_name || ''),
                        scope_unit_type_raw: (primaryScope && primaryScope.office) ? primaryScope.office.type : 'plhq'
                    };
                },

                items: [],
                pagination: config.pagination || { current_page: 1, last_page: 1, per_page: 25, total: 0, from: 0, to: 0 },
                stats: config.stats || { admins: 0, zone: 0, sector: 0, beat: 0, call_center: 0, inactive: 0, total: 0 },
                roles: config.roles || [],
                designations: config.designations || [],
                zones: config.zones || [],
                sectors: config.sectors || [],
                beats: config.beats || [],
                currentUserRole: config.currentUserRole || '',
                currentUserId: config.currentUserId || null,
                search: '',
                filterRole: '',
                filterStatus: '',
                filterZone: '',
                filterSector: '',
                filterBeat: '',
                filterCallCentre: '',
                density: 'spacious',
                viewMode: 'table',
                sortBy: 'id',
                sortDirection: 'desc',
                page: 1,
                perPage: 25,
                loading: false,
                fetchAbortController: null,
                showModal: false,
                modalMode: 'add',
                showSidebar: false,

                init() {
                    this.items = (config.items || []).map(i => this.mapUser(i));
                    if (config.stats) {
                        this.stats = config.stats;
                    }
                    if (config.pagination) {
                        this.pagination = config.pagination;
                        this.page = config.pagination.current_page || 1;
                        this.perPage = config.pagination.per_page || 25;
                    }

                    this.$watch('search', () => { this.page = 1; this.fetchData(1); });
                    this.$watch('filterRole', () => { this.page = 1; this.fetchData(1); });
                    this.$watch('filterStatus', () => { this.page = 1; this.fetchData(1); });
                    this.$watch('filterZone', () => { this.filterSector = ''; this.filterBeat = ''; this.page = 1; this.fetchData(1); });
                    this.$watch('filterSector', () => { this.filterBeat = ''; this.page = 1; this.fetchData(1); });
                    this.$watch('filterBeat', () => { this.page = 1; this.fetchData(1); });
                    this.$watch('perPage', () => { this.page = 1; this.fetchData(1); });
                },

                async fetchData(targetPage = null) {
                    if (this.fetchAbortController) {
                        this.fetchAbortController.abort();
                    }
                    this.fetchAbortController = new AbortController();
                    this.loading = true;

                    const p = targetPage !== null ? targetPage : this.page;
                    const params = new URLSearchParams({
                        page: p,
                        per_page: this.perPage,
                        sort_by: this.sortBy,
                        sort_dir: this.sortDirection
                    });

                    if (this.search) params.append('search', this.search);
                    if (this.filterRole) params.append('role_id', this.filterRole);
                    if (this.filterStatus) params.append('status', this.filterStatus);
                    if (this.filterZone) params.append('zone_id', this.filterZone);
                    if (this.filterSector) params.append('sector_id', this.filterSector);
                    if (this.filterBeat) params.append('beat_id', this.filterBeat);

                    try {
                        const res = await fetch(`/mgmt/users?${params.toString()}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            signal: this.fetchAbortController.signal
                        });
                        if (res.ok) {
                            const data = await res.json();
                            this.items = (data.items || []).map(i => this.mapUser(i));
                            if (data.pagination) {
                                this.pagination = data.pagination;
                                this.page = data.pagination.current_page;
                            }
                            if (data.stats) {
                                this.stats = data.stats;
                            }
                        }
                    } catch (e) {
                        if (e.name !== 'AbortError') {
                            console.error('User directory sync error:', e);
                        }
                    } finally {
                        this.loading = false;
                    }
                },

                goToPage(p) {
                    if (p < 1 || p > this.totalPages || p === this.page) return;
                    this.page = p;
                    this.fetchData(p);
                },

                get filteredRolesForAssignment() {
                    if (this.currentUserRole === 'operation_admin') {
                        // Restricted: Cannot assign super_admin or operation_admin
                        return this.roles.filter(r => r.name !== 'super_admin' && r.name !== 'operation_admin');
                    }
                    return this.roles;
                },

                get availableScopeTypes() {
                    const allTypes = [
                        { value: 'national', label: 'National (All Access)' },
                        { value: 'zone', label: 'Zone Locked' },
                        { value: 'sector', label: 'Sector Locked' },
                        { value: 'beat', label: 'Beat Locked' },
                        { value: 'call_centre', label: 'Call Center' }
                    ];
                    if (this.currentUserRole === 'operation_admin') {
                        return allTypes.filter(t => t.value !== 'national');
                    }
                    return allTypes;
                },
                saving: false,
                showPasswordModal: false,
                showAccessModal: false,
                showViewModal: false,
                showConfirmModal: false,
                confirmLoading: false,
                selectedItem: null,
                copiedField: null,
                passwordForm: {
                    password: '',
                    password_confirmation: ''
                },
                confirmConfig: {
                    title: '',
                    message: '',
                    icon: '',
                    isDanger: false,
                    action: null
                },
                tempZoneId: '',
                tempSectorId: '',
                tempDesignationName: '',

                get availableDesignationNames() {
                    return [...new Set(this.designations.map(d => d.name))];
                },

                get availableBPS() {
                    if (!this.tempDesignationName) return [];
                    return this.designations.filter(d => d.name === this.tempDesignationName);
                },

                canManageUser(item) {
                    // Users can always manage themselves
                    if (this.currentUserId === item.id) return true;
                    // Super Admin can manage everyone else
                    if (this.currentUserRole === 'super_admin') return true;
                    // Operation Admin cannot manage Super Admins or other Operation Admins
                    if (this.currentUserRole === 'operation_admin') {
                        const targetRole = item.role ? item.role.name : item.role_name;
                        return !['super_admin', 'operation_admin'].includes(targetRole);
                    }
                    return false;
                },

                canManagePassword(item) {
                    const targetRole = item.role ? item.role.name : item.role_name;
                    // Super admin can NEVER be reset via web application (Root recovery is CLI SSH only)
                    if (targetRole === 'super_admin') return false;

                    // Super admin can manage anyone else
                    if (this.currentUserRole === 'super_admin') return true;

                    // Operation admin can reset peer operation_admins (not self), and other operator roles
                    if (this.currentUserRole === 'operation_admin') {
                        return this.currentUserId !== item.id;
                    }

                    return false;
                },
                get filteredSectors() {
                    if (!this.tempZoneId) return [];
                    return this.sectors.filter(s => s.zone_id == this.tempZoneId);
                },

                get filteredBeats() {
                    if (!this.tempSectorId) return [];
                    return this.beats.filter(b => b.sector_id == this.tempSectorId);
                },

                get filteredSectorsForFilter() {
                    if (!this.filterZone) return [];
                    return this.sectors.filter(s => s.zone_id == this.filterZone);
                },

                get filteredBeatsForFilter() {
                    if (!this.filterSector) return [];
                    return this.beats.filter(b => b.sector_id == this.filterSector);
                },
                form: {
                    id: null,
                    username: '',
                    full_name: '',
                    email: '',
                    mobile_no: '',
                    cnic: '',
                    scope_unit_type: (config.currentUserRole === 'operation_admin') ? 'zone' : 'national',
                    scope_unit_id: '',
                    role_id: '',
                    designation_id: '',
                    password: '',
                    password_confirmation: ''
                },

                get filteredItems() {
                    return this.items;
                },

                get pagedItems() {
                    return this.items;
                },

                get totalPages() {
                    return this.pagination ? (this.pagination.last_page || 1) : 1;
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


                formatCnicInput(val) {
                    if (!val) return '';
                    let digits = val.replace(/\D/g, '').substring(0, 13);
                    let formatted = '';
                    if (digits.length > 5) {
                        formatted += digits.substring(0, 5) + '-';
                        if (digits.length > 12) {
                            formatted += digits.substring(5, 12) + '-' + digits.substring(12, 13);
                        } else {
                            formatted += digits.substring(5);
                        }
                    } else {
                        formatted = digits;
                    }
                    return formatted;
                },

                resetForm() {
                    this.form = { 
                        id: null, 
                        username: '', 
                        full_name: '', 
                        email: '', 
                        mobile_no: '', 
                        cnic: '',
                        is_blocked: false,
                        is_active: true,
                        scope_unit_type: this.currentUserRole === 'operation_admin' ? 'zone' : 'national', 
                        scope_unit_id: '', 
                        role_id: '', 
                        designation_id: '', 
                        password: '', 
                        password_confirmation: '' 
                    };
                    this.tempZoneId = '';
                    this.tempSectorId = '';
                    this.copiedField = null;
                    this.generateFormPassword();
                },

                editItem(user) {
                    this.modalMode = 'edit';
                    const item = JSON.parse(JSON.stringify(user));
                    this.form = { ...item, is_blocked: Boolean(user.is_blocked), password: '', password_confirmation: '' };
                    
                    // Pre-populate designation name to show BPS options
                    if (user.designation) {
                        this.tempDesignationName = user.designation.name;
                        this.form.designation_id = user.designation_id;
                    } else {
                        this.tempDesignationName = '';
                    }
                    this.tempZoneId = '';
                    this.tempSectorId = '';

                    const scopes = item.active_scopes || item.scopes || [];
                    if (scopes.length > 0) {
                        const scope = scopes[0];
                        const office = scope.office;
                        this.form.scope_unit_type = office ? office.type : 'national';
                        this.form.scope_unit_id = scope.office_id;

                        if (office) {
                            if (office.type === 'zone') {
                                this.tempZoneId = scope.office_id;
                            } else if (office.type === 'sector') {
                                this.tempZoneId = office.parent_id;
                                setTimeout(() => { 
                                    this.tempSectorId = scope.office_id; 
                                    this.form.scope_unit_id = scope.office_id;
                                }, 150);
                            } else if (office.type === 'beat') {
                                const beat = office;
                                const sector = this.sectors.find(s => s.id == beat.parent_id);
                                if (sector) {
                                    this.tempZoneId = sector.zone_id;
                                    setTimeout(() => { 
                                        this.tempSectorId = beat.parent_id; 
                                        setTimeout(() => { 
                                            this.form.scope_unit_id = scope.office_id; 
                                        }, 150);
                                    }, 150);
                                }
                            }
                        }
                    }
                    this.modalMode = 'edit';
                    this.showModal = true;
                },

                openPasswordReset(item) {
                    this.selectedItem = item;
                    this.passwordForm = { password: '', password_confirmation: '' };
                    this.copiedField = null;
                    this.generateCompliantPassword();
                    this.showPasswordModal = true;
                },

                generateFormPassword() {
                    const upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
                    const lower = "abcdefghijkmnopqrstuvwxyz";
                    const numbers = "23456789";
                    const symbols = "!@#$%^&*";
                    const all = upper + lower + numbers + symbols;

                    let chars = [
                        upper[Math.floor(Math.random() * upper.length)],
                        lower[Math.floor(Math.random() * lower.length)],
                        numbers[Math.floor(Math.random() * numbers.length)],
                        symbols[Math.floor(Math.random() * symbols.length)]
                    ];
                    for (let i = 0; i < 12; i++) {
                        chars.push(all[Math.floor(Math.random() * all.length)]);
                    }
                    const pass = chars.sort(() => 0.5 - Math.random()).join('');
                    this.form.password = pass;
                    this.form.password_confirmation = pass;
                },

                generateCompliantPassword() {
                    const upper = "ABCDEFGHJKLMNPQRSTUVWXYZ";
                    const lower = "abcdefghijkmnopqrstuvwxyz";
                    const numbers = "23456789";
                    const symbols = "!@#$%^&*";
                    const all = upper + lower + numbers + symbols;

                    let chars = [
                        upper[Math.floor(Math.random() * upper.length)],
                        lower[Math.floor(Math.random() * lower.length)],
                        numbers[Math.floor(Math.random() * numbers.length)],
                        symbols[Math.floor(Math.random() * symbols.length)]
                    ];
                    for (let i = 0; i < 12; i++) {
                        chars.push(all[Math.floor(Math.random() * all.length)]);
                    }
                    const pass = chars.sort(() => 0.5 - Math.random()).join('');
                    this.passwordForm.password = pass;
                    this.passwordForm.password_confirmation = pass;
                },

                copyToClipboard(text, id) {
                    if (!text) return;
                    navigator.clipboard.writeText(text).then(() => {
                        this.copiedField = id;
                        setTimeout(() => {
                            if (this.copiedField === id) this.copiedField = null;
                        }, 2000);
                    }).catch(() => {});
                },

                viewDetails(item) {
                    this.selectedItem = item;
                    this.showViewModal = true;
                },

                viewAccess(item) {
                    this.selectedItem = item;
                    this.showAccessModal = true;
                },

                getRolePermissions(roleId) {
                    if (!roleId) return [];
                    const role = this.roles.find(r => r.id === roleId);
                    return role && role.permissions ? role.permissions : [];
                },

                clearFilters() {
                    this.search = '';
                    this.filterRole = '';
                    this.filterStatus = '';
                    this.filterZone = '';
                    this.filterSector = '';
                    this.filterBeat = '';
                    this.filterCallCentre = '';
                    this.density = 'spacious';
                    this.page = 1;
                    this.perPage = 10;
                    this.sortBy = 'username';
                    this.sortDirection = 'asc';
                },

                sortByItem(field) {
                    if (this.sortBy === field) {
                        this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortBy = field;
                    }
                },

                getSortIcon(field) {
                    if (this.sortBy !== field) return 'fa-sort opacity-40 text-blue-300';
                    return this.sortDirection === 'asc' 
                        ? 'fa-sort-up opacity-100 text-blue-600 scale-125' 
                        : 'fa-sort-down opacity-100 text-blue-600 scale-125';
                },

                confirmStatus(item) {
                    this.selectedItem = item;
                    const willBeActive = !item.is_active;
                    this.confirmConfig = {
                        title: willBeActive ? 'Activate Identity' : 'Deactivate Identity',
                        message: willBeActive 
                            ? `Are you sure you want to activate access for <strong>${item.username}</strong>? They will regain system capabilities.`
                            : `Are you sure you want to suspend access for <strong>${item.username}</strong>? Active sessions will be terminated.`,
                        icon: 'fa-power-off',
                        isDanger: !willBeActive,
                        action: async () => {
                            try {
                                const response = await fetch(`/mgmt/users/${item.uuid || item.id}/toggle-status`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    }
                                });
                                const result = await response.json();
                                if (result.success) {
                                    item.is_active = (typeof result.is_active !== 'undefined' ? result.is_active : (result.status === 'active'));
                                    if (typeof result.is_security_locked !== 'undefined') item.is_security_locked = result.is_security_locked;
                                    if (typeof result.locked_until !== 'undefined') item.locked_until = result.locked_until;
                                    item.status_state = result.status_state || (item.is_security_locked ? 'security_locked' : (item.is_active ? 'active' : 'suspended'));
                                    item.status_label = result.status_label || (item.is_security_locked ? 'Security Locked' : (item.is_active ? 'Active' : 'Suspended'));
                                    showSuccess(`Identity status updated to ${item.status_label}`);
                                    await this.fetchData();
                                } else {
                                    showError("Status synchronization failed");
                                }
                            } catch (error) {
                                showError("Status synchronization failed");
                            }
                        }
                    };
                    this.showConfirmModal = true;
                },

                confirmClearLockout(item) {
                    this.selectedItem = item;
                    this.confirmConfig = {
                        title: 'Clear Security Lockout',
                        message: `Are you sure you want to clear the security lockout for <strong>${item.username}</strong>?<br><br>Lockout timers will be reset, failed attempts cleared, and the identity restored to <strong>Active</strong> duty.`,
                        icon: 'fa-lock-open',
                        isDanger: false,
                        action: async () => {
                            try {
                                const response = await fetch(`/mgmt/users/${item.uuid || item.id}/clear-lockout`, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    }
                                });
                                const result = await response.json();
                                if (response.ok && result.success) {
                                    item.is_active = true;
                                    item.locked_until = null;
                                    item.is_security_locked = false;
                                    item.status_state = 'active';
                                    item.status_label = 'Active';
                                    showSuccess(result.message || `Security lockout cleared for ${item.username}. Account restored to Active.`);
                                    await this.fetchData();
                                } else {
                                    showError(result.message || "Failed to clear security lockout.");
                                }
                            } catch (error) {
                                showError("Network or server failure while clearing security lockout.");
                            }
                        }
                    };
                    this.showConfirmModal = true;
                },

                confirmDelete(item) {
                    this.selectedItem = item;
                    this.confirmConfig = {
                        title: 'Purge Identity',
                        message: `WARNING: You are about to permanently purge the identity <strong>${item.username}</strong>.<br><br>This action cannot be undone.`,
                        icon: 'fa-trash-alt',
                        isDanger: true,
                        action: async () => {
                            try {
                                const response = await fetch(`/mgmt/users/${item.uuid || item.id}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                    }
                                });
                                if (response.ok) {
                                    this.items = this.items.filter(i => (item.uuid ? i.uuid !== item.uuid : i.id !== item.id));
                                    showSuccess("Identity purged from system.");
                                } else {
                                    showError("Delete command failure.");
                                }
                            } catch (error) {
                                showError("Delete command failure.");
                            }
                        }
                    };
                    this.showConfirmModal = true;
                },

                async executeConfirmAction() {
                    if (typeof this.confirmConfig.action === 'function') {
                        this.confirmLoading = true;
                        await this.confirmConfig.action();
                        this.confirmLoading = false;
                        this.showConfirmModal = false;
                    }
                },

                async submitPasswordReset() {
                    this.saving = true;
                    try {
                        const response = await fetch(`/mgmt/users/${this.selectedItem.uuid || this.selectedItem.id}/reset-password`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.passwordForm)
                        });
                        const result = await response.json();
                        if (response.ok) {
                            showSuccess(result.message || "Security key overridden.");
                            this.showPasswordModal = false;
                        } else {
                            if (result.errors) {
                                showError(Object.values(result.errors).flat(), "Policy Violation");
                            } else {
                                showError(result.message || "Key synchronization error");
                            }
                        }
                    } catch(e) {
                         showError("Key synchronization error");
                    } finally {
                         this.saving = false;
                    }
                },

                async submitForm() {
                    this.saving = true;
                    const url = this.modalMode === 'add' ? '/mgmt/users' : `/mgmt/users/${this.form.uuid || this.form.id}`;
                    const method = this.modalMode === 'add' ? 'POST' : 'PUT';
                    
                    try {
                        const payload = { ...this.form };
                        
                        // Handle current user scope assignments (simplified assume one primary scope from the modal)
                        payload.scopes = [{
                            office_id: payload.scope_unit_id || null,
                            access_level: 'full'
                        }];

                        const response = await fetch(url, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(payload)
                        });
                        
                        const result = await response.json();
                        
                        if (response.ok) {
                            const mappedUser = this.mapUser(result.user);
                            if (this.modalMode === 'add') {
                                this.items.unshift(mappedUser);
                                showSuccess("Identity integrated into registry");
                            } else {
                                const index = this.items.findIndex(i => (this.form.uuid ? i.uuid === this.form.uuid : i.id === this.form.id));
                                if (index !== -1) this.items[index] = mappedUser;
                                showSuccess("Identity parameters updated");
                            }
                            this.showModal = false;
                            this.resetForm();
                            await this.fetchData();
                        } else {
                            console.error('User Registry Error Payload:', result);
                            if (result.errors) {
                                showError(Object.values(result.errors).flat(), "Grid Linkage Error");
                            } else {
                                showError(result.message || "Identity integration error");
                            }
                        }
                    } catch (error) {
                        showError("Grid communication failure");
                    } finally {
                        this.saving = false;
                    }
                }
            };
        }
    </script>
    @endpush

@endsection

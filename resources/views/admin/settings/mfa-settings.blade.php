@extends('layouts.app')

@section('title', 'MFA Settings - NHMP 130')
@section('page-title', 'Multi-Factor Authentication (MFA) Settings')

@section('content')
<div x-data="mfaManager(@js([
    'items'        => $allStaff,
    'roles'        => $roles,
    'stats'        => $stats,
    'csrfToken'    => csrf_token(),
]))" class="space-y-8 relative mt-4 max-w-[1700px] mx-auto pb-24" x-cloak>

    <!-- Floating Sidebar Toggle -->
        <button @click="showSidebar = true"
            x-show="!showSidebar"
            x-transition:enter="transition ease-out duration-500 delay-100"
            x-transition:enter-start="translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            class="fixed top-1/2 right-0 -translate-y-1/2 z-40 bg-gradient-to-b from-blue-600 to-blue-800 text-white p-2.5 py-6 rounded-l-2xl shadow-[0_0_30px_-5px_rgba(59,130,246,0.4)] hover:shadow-[-5px_0_40px_-5px_rgba(59,130,246,0.7)] hover:pr-4 transition-all duration-300 flex flex-col items-center gap-4 cursor-pointer" title="Open Security Filters">
            <i class="fas fa-sliders-h drop-shadow-lg text-sm"></i>
            <span style="writing-mode: vertical-rl;" class="text-[9px] font-black uppercase tracking-[0.3em] rotate-180 drop-shadow-md text-blue-50">MFA Filters</span>
        </button>

        <!-- STATS CARDS (Matching User Management 3D Elevated Style) -->
        <div class="grid grid-cols-2 md:grid-cols-6 lg:grid-cols-6 gap-4">
            <!-- Total Active Card -->
            <div @click="filterStatus = ''; filterMethod = ''" :class="(filterStatus === '' && filterMethod === '') ? 'card-3d-active blue' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-blue-600 shadow-[0_8px_16px_rgba(37,99,235,0.2)]">
                    <i class="fas fa-users text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-blue-500 uppercase">Active Staff</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="items.length"></h4>
                </div>
            </div>

            <!-- Enrolled (Protected) Card -->
            <div @click="filterStatus = (filterStatus === 'enrolled' ? '' : 'enrolled'); filterMethod = ''" :class="filterStatus === 'enrolled' ? 'card-3d-active emerald' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-emerald-600 shadow-[0_8px_16px_rgba(5,150,105,0.2)]">
                    <i class="fas fa-shield-check text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-emerald-500 uppercase">2FA Enrolled</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="items.filter(i => i.is_enrolled).length"></h4>
                </div>
            </div>

            <!-- Authenticator App (TOTP) Card -->
            <div @click="filterMethod = (filterMethod === 'totp' ? '' : 'totp'); filterStatus = ''" :class="filterMethod === 'totp' ? 'card-3d-active indigo' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-indigo-600 shadow-[0_8px_16px_rgba(79,70,229,0.2)]">
                    <i class="fas fa-mobile-screen-button text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-indigo-500 uppercase">Mobile App</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="items.filter(i => i.has_totp).length"></h4>
                </div>
            </div>

            <!-- Hardware Security Keys Card -->
            <div @click="filterMethod = (filterMethod === 'webauthn' ? '' : 'webauthn'); filterStatus = ''" :class="filterMethod === 'webauthn' ? 'card-3d-active purple' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-purple-600 shadow-[0_8px_16px_rgba(147,51,234,0.2)]">
                    <i class="fas fa-key text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-purple-500 uppercase">Hardware Keys</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="items.filter(i => i.has_webauthn).length"></h4>
                </div>
            </div>

            <!-- Password Only (Exempt) Card -->
            <div @click="filterStatus = (filterStatus === 'exempt' ? '' : 'exempt'); filterMethod = ''" :class="filterStatus === 'exempt' ? 'card-3d-active amber' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-amber-500 shadow-[0_8px_16px_rgba(245,158,11,0.2)]">
                    <i class="fas fa-lock-open text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-amber-500 uppercase">Password Only</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="items.filter(i => i.is_exempt).length"></h4>
                </div>
            </div>

            <!-- Pending Enrollment Card -->
            <div @click="filterStatus = (filterStatus === 'unenrolled' ? '' : 'unenrolled'); filterMethod = ''" :class="filterStatus === 'unenrolled' ? 'card-3d-active rose' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-rose-600 shadow-[0_8px_16px_rgba(225,29,72,0.2)]">
                    <i class="fas fa-hourglass-half text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-rose-500 uppercase">Pending Setup</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="items.filter(i => !i.is_enrolled && !i.is_exempt).length"></h4>
                </div>
            </div>
        </div>

        <!-- MAIN CONTROL PANEL (With User Management Grid/Table System) -->
        <div class="grid lg:grid-cols-12 gap-8 relative pb-20 px-4 sm:px-0">
            
            <!-- Left Column Content -->
            <div class="transition-all duration-500" :class="showSidebar ? 'lg:col-span-9' : 'lg:col-span-12'">
                <div class="bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-gray-100 overflow-hidden flex flex-col relative z-20">
                    
                    <!-- Panel Header -->
                    <div class="bg-blue-50/50 p-8 border-b border-blue-100/50">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded-[1.25rem] bg-white flex items-center justify-center border border-blue-100 shadow-sm transition-transform hover:scale-105 duration-300">
                                    <i class="fas fa-fingerprint text-3xl text-blue-600"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-extrabold text-blue-900 tracking-tight flex items-center gap-3">
                                        Staff Multi-Factor Authentication <span class="text-lg font-bold text-slate-400" x-text="'(' + filteredItems.length + ' accounts)'"></span>
                                    </h2>
                                    <p class="text-slate-500 text-sm font-bold mt-1">Manage device pairing, authentication methods, and individual access controls</p>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-4 items-center">
                                <!-- Row Density Selector -->
                                <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-sm">
                                    <span class="text-[9px] font-black text-slate-400 border-r border-slate-100 pr-2 uppercase font-mono">Row Density</span>
                                    <select x-model.number="perPage" class="bg-transparent text-blue-600 text-[10px] font-black uppercase cursor-pointer outline-none focus:ring-0 border-none p-0 pr-4">
                                        <option value="10">10 Per Page</option>
                                        <option value="25">25 Per Page</option>
                                        <option value="50">50 Per Page</option>
                                        <option value="100">100 Per Page</option>
                                    </select>
                                </div>

                                <!-- View Mode Toggles (Table vs Grid) -->
                                <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl p-1.5 shadow-sm">
                                    <button @click="viewMode = 'table'" :class="viewMode === 'table' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-blue-600'" class="w-10 h-10 flex items-center justify-center rounded-lg transition-all" title="Table View">
                                        <i class="fas fa-list-ul"></i>
                                    </button>
                                    <button @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-blue-600 text-white shadow-md' : 'text-slate-400 hover:text-blue-600'" class="w-10 h-10 flex items-center justify-center rounded-lg transition-all" title="Grid View">
                                        <i class="fas fa-th-large"></i>
                                    </button>
                                </div>

                                <!-- Toggle Sidebar Filter -->
                                <button @click="showSidebar = !showSidebar" class="w-12 h-12 flex items-center justify-center bg-white border border-slate-200 text-blue-600 rounded-xl hover:bg-blue-50 transition-colors shadow-sm" :title="showSidebar ? 'Hide Filters' : 'Show Filters'">
                                    <i class="fas" :class="showSidebar ? 'fa-eye-slash' : 'fa-filter'"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- GRID VIEW (Matching User Management Cards) -->
                    <div x-show="viewMode === 'grid'" x-transition class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-8">
                            <template x-for="item in pagedItems" :key="item.id">
                                <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 p-8 hover:-translate-y-2 transition-all duration-300 group relative overflow-hidden">
                                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform duration-500">
                                        <i class="fa-solid fa-shield-halved text-9xl text-blue-900"></i>
                                    </div>
                                    
                                    <!-- Card Header -->
                                    <div class="flex items-start justify-between mb-6 relative z-10">
                                        <div class="w-16 h-16 rounded-[1.5rem] bg-blue-900 p-1 flex items-center justify-center shadow-lg group-hover:rotate-6 transition-transform">
                                            <div class="w-full h-full rounded-[1.25rem] bg-white flex items-center justify-center text-blue-900 font-extrabold text-xl tracking-tight uppercase" x-text="item.username.substring(0, 2)"></div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase mb-1">MFA State</div>
                                            <template x-if="item.is_enrolled">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 font-black text-[10px] tracking-wider uppercase border border-emerald-200">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> 2FA Ready
                                                </span>
                                            </template>
                                            <template x-if="item.is_exempt">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 text-amber-700 font-black text-[10px] tracking-wider uppercase border border-amber-200">
                                                    Password Only
                                                </span>
                                            </template>
                                            <template x-if="!item.is_enrolled && !item.is_exempt">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-rose-50 text-rose-700 font-black text-[10px] tracking-wider uppercase border border-rose-200">
                                                    Pending Setup
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Card Body -->
                                    <div class="relative z-10">
                                        <h4 class="text-xl font-extrabold text-blue-900 tracking-tight leading-tight mb-0.5" x-text="item.full_name"></h4>
                                        <div class="text-xs font-bold text-slate-500 font-mono mb-2" x-text="'@' + item.username"></div>
                                        <div class="flex items-center gap-2 mb-6">
                                            <span class="text-xs font-black text-blue-600 bg-blue-50 px-2.5 py-1 rounded-lg uppercase tracking-wider" x-text="item.role_display"></span>
                                        </div>

                                        <!-- Credentials Info Box -->
                                        <div class="bg-slate-50 rounded-2xl p-5 border border-slate-100 mb-6 space-y-3">
                                            <div class="flex items-center justify-between text-xs">
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Active Method</span>
                                                <span class="font-bold text-blue-900 flex items-center gap-1.5">
                                                    <template x-if="item.has_totp"><i class="fa-solid fa-mobile-screen text-indigo-500 text-xs"></i></template>
                                                    <template x-if="item.has_webauthn"><i class="fa-solid fa-key text-purple-500 text-xs"></i></template>
                                                    <span x-text="item.method_label"></span>
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between text-xs pt-3 border-t border-slate-200/50">
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">CNIC No</span>
                                                <span class="font-bold text-slate-700" x-text="item.cnic"></span>
                                            </div>
                                            <div class="flex items-center justify-between text-xs pt-3 border-t border-slate-200/50">
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Designation</span>
                                                <span class="font-bold text-slate-700" x-text="item.designation_name"></span>
                                            </div>
                                        </div>

                                        <!-- Action Buttons Grid (Matching User Management Style) -->
                                        <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-2">
                                            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Actions</span>
                                            <div class="flex items-center gap-2">
                                                <!-- View Staff Profile Details -->
                                                <button @click="viewDetails(item)" title="View MFA Details" class="w-9 h-9 rounded-xl bg-blue-500 border border-blue-600 text-white hover:bg-blue-600 active:scale-95 transition-all shadow-sm flex items-center justify-center">
                                                    <i class="fa-solid fa-eye text-xs"></i>
                                                </button>

                                                <!-- Reset 2FA (Lost Device) -->
                                                <button @click="confirmAction('reset', item)" title="Reset Authenticator Phone (Lost Device)" class="w-9 h-9 rounded-xl bg-amber-500 border border-amber-600 text-white hover:bg-amber-600 active:scale-95 transition-all shadow-sm flex items-center justify-center">
                                                    <i class="fa-solid fa-arrows-rotate text-xs"></i>
                                                </button>

                                                <!-- Require 2FA (if currently exempt) -->
                                                <template x-if="item.is_exempt">
                                                    <button @click="confirmAction('enforce', item)" title="Require Multi-Factor Authentication" class="w-9 h-9 rounded-xl bg-indigo-600 border border-indigo-700 text-white hover:bg-indigo-700 active:scale-95 transition-all shadow-sm flex items-center justify-center">
                                                        <i class="fa-solid fa-shield text-xs"></i>
                                                    </button>
                                                </template>

                                                <!-- Turn Off 2FA (Allow Password Login) -->
                                                <template x-if="!item.is_exempt">
                                                    <template x-if="!item.is_privileged">
                                                        <button @click="confirmAction('revoke', item)" title="Turn Off 2FA (Permit Password Only Login)" class="w-9 h-9 rounded-xl bg-rose-600 border border-rose-700 text-white hover:bg-rose-700 active:scale-95 transition-all shadow-sm flex items-center justify-center">
                                                            <i class="fa-solid fa-power-off text-xs"></i>
                                                        </button>
                                                    </template>
                                                    <template x-if="item.is_privileged">
                                                        <div title="Privileged Administrator: Cannot be exempted" class="w-9 h-9 rounded-xl bg-slate-100 border border-slate-200 text-slate-300 flex items-center justify-center cursor-not-allowed opacity-50">
                                                            <i class="fa-solid fa-lock text-xs"></i>
                                                        </div>
                                                    </template>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- TABLE VIEW (Matching User Management Table) -->
                    <div x-show="viewMode === 'table'" x-transition class="overflow-x-auto shadow-inner bg-white rounded-3xl border border-slate-100">
                        <table class="w-full text-left" :class="density === 'condensed' ? 'condensed-table' : 'spacious-table'">
                            <thead class="bg-blue-50 border-b border-blue-100">
                                <tr>
                                    <th class="px-6 py-4">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-user-shield text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('username')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                Staff Member
                                                <i class="fas text-[10px] text-blue-600" :class="getSortIcon('username')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-id-badge text-[10px]"></i>
                                            </div>
                                            <button @click="sortByItem('role_display')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">
                                                Operational Role
                                                <i class="fas text-[10px] text-blue-600" :class="getSortIcon('role_display')"></i>
                                            </button>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-shield text-[10px]"></i>
                                            </div>
                                            <span>2FA Method</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-center">
                                        <div class="flex items-center justify-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100">
                                                <i class="fas fa-signal text-[10px]"></i>
                                            </div>
                                            <span>Security State</span>
                                        </div>
                                    </th>
                                    <th class="px-6 py-4 text-center">
                                        <span class="text-xs font-black text-slate-500 uppercase tracking-widest">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="item in pagedItems" :key="item.id">
                                    <tr class="hover:bg-blue-50/40 transition-colors group">
                                        <!-- Staff Details -->
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="flex items-center gap-3">
                                                <div class="w-10 h-10 rounded-xl bg-blue-900 text-white flex items-center justify-center font-black text-xs uppercase shadow-sm shrink-0" x-text="item.username.substring(0, 2)"></div>
                                                <div class="flex flex-col">
                                                    <span class="font-bold text-blue-900 text-sm" x-text="item.full_name"></span>
                                                    <span class="text-xs text-slate-500 font-mono" x-text="'@' + item.username"></span>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Role & Designation -->
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-black text-blue-700 bg-blue-50 px-2 py-1 rounded-md inline-block w-fit uppercase" x-text="item.role_display"></span>
                                                <span class="text-[10px] font-bold text-slate-500 mt-1" x-text="item.designation_name"></span>
                                            </div>
                                        </td>

                                        <!-- 2FA Method -->
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="flex items-center gap-2">
                                                <template x-if="item.has_totp">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-bold" title="Mobile Authenticator App Active">
                                                        <i class="fa-solid fa-mobile-screen text-xs"></i> App
                                                    </span>
                                                </template>
                                                <template x-if="item.has_webauthn">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-purple-50 text-purple-700 border border-purple-200 text-xs font-bold" title="Hardware Security Key Active">
                                                        <i class="fa-solid fa-key text-xs"></i> Key
                                                    </span>
                                                </template>
                                                <template x-if="!item.has_totp && !item.has_webauthn">
                                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 text-xs font-bold">
                                                        None
                                                    </span>
                                                </template>
                                            </div>
                                        </td>

                                        <!-- Status Badge -->
                                        <td class="px-6 text-center" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <template x-if="item.is_enrolled">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> 2FA Ready
                                                </span>
                                            </template>
                                            <template x-if="item.is_exempt">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-amber-50 text-amber-700 border border-amber-200">
                                                    Password Only
                                                </span>
                                            </template>
                                            <template x-if="!item.is_enrolled && !item.is_exempt">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                                    Pending Setup
                                                </span>
                                            </template>
                                        </td>

                                        <!-- Actions Grid -->
                                        <td class="px-6 text-center" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="flex items-center justify-center gap-2">
                                                <!-- View Details -->
                                                <button @click="viewDetails(item)" title="View Credentials" class="w-9 h-9 bg-blue-500 border border-blue-600 text-white hover:bg-blue-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95">
                                                    <i class="fas fa-eye text-xs"></i>
                                                </button>

                                                <!-- Reset 2FA Device Pairing -->
                                                <button @click="confirmAction('reset', item)" title="Reset Authenticator Phone" class="w-9 h-9 bg-amber-500 border border-amber-600 text-white hover:bg-amber-600 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95">
                                                    <i class="fa-solid fa-arrows-rotate text-xs"></i>
                                                </button>

                                                <!-- Require 2FA (if currently exempt) -->
                                                <template x-if="item.is_exempt">
                                                    <button @click="confirmAction('enforce', item)" title="Require 2FA" class="w-9 h-9 bg-indigo-600 border border-indigo-700 text-white hover:bg-indigo-700 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95">
                                                        <i class="fa-solid fa-shield text-xs"></i>
                                                    </button>
                                                </template>

                                                <!-- Turn Off 2FA (Allow Password Login) -->
                                                <template x-if="!item.is_exempt">
                                                    <template x-if="!item.is_privileged">
                                                        <button @click="confirmAction('revoke', item)" title="Turn Off 2FA (Permit Password Only)" class="w-9 h-9 bg-rose-600 border border-rose-700 text-white hover:bg-rose-700 transition-colors flex items-center justify-center rounded-xl shadow-sm active:scale-95">
                                                            <i class="fa-solid fa-power-off text-xs"></i>
                                                        </button>
                                                    </template>
                                                    <template x-if="item.is_privileged">
                                                        <div title="Privileged Administrator: Cannot be exempted" class="w-9 h-9 bg-slate-50 border border-slate-100 text-slate-300 flex items-center justify-center rounded-xl opacity-50 cursor-not-allowed">
                                                            <i class="fas fa-lock text-xs"></i>
                                                        </div>
                                                    </template>
                                                </template>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Bar -->
                    <div x-show="totalPages > 1" class="p-8 border-t border-slate-100 bg-slate-50/30 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs font-bold text-slate-500">
                            Showing <span class="text-blue-900" x-text="((page - 1) * perPage) + 1"></span> to <span class="text-blue-900" x-text="Math.min(page * perPage, filteredItems.length)"></span> of <span class="text-blue-900" x-text="filteredItems.length"></span> accounts
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="page = 1" :disabled="page === 1" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 hover:bg-slate-50 transition-all font-black text-[10px] uppercase">First</button>
                            <button @click="page--" :disabled="page === 1" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 hover:bg-slate-50 transition-all"><i class="fa-solid fa-chevron-left"></i></button>
                            
                            <template x-for="p in pagesToShow()" :key="p">
                                <div class="flex items-center">
                                    <template x-if="p === '...'">
                                        <span class="px-2 text-slate-300 font-bold">...</span>
                                    </template>
                                    <template x-if="p !== '...'">
                                        <button @click="page = p" :class="page === p ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/20' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" class="w-10 h-10 rounded-xl border font-black text-xs transition-all" x-text="p"></button>
                                    </template>
                                </div>
                            </template>

                            <button @click="page++" :disabled="page === totalPages" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 hover:bg-slate-50 transition-all"><i class="fa-solid fa-chevron-right"></i></button>
                            <button @click="page = totalPages" :disabled="page === totalPages" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 hover:bg-slate-50 transition-all font-black text-[10px] uppercase">Last</button>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div x-show="filteredItems.length === 0" class="py-28 text-center bg-white">
                        <i class="fa-solid fa-user-slash text-6xl text-slate-200 mb-4 animate-pulse"></i>
                        <h3 class="text-xl font-black text-blue-900" x-text="search ? 'No staff match your search' : 'No staff accounts found'"></h3>
                        <p class="text-slate-400 font-bold text-sm mt-1">Try adjusting your search keywords or clearing active filters.</p>
                        <button @click="resetFilters()" class="mt-5 px-6 py-2.5 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-100 font-black text-xs uppercase tracking-wider transition-all">
                            Reset All Filters
                        </button>
                    </div>

                </div>
            </div>

            <!-- Right Column - Sliding Security Filters Sidebar (Matching User Management) -->
            <div class="lg:col-span-3 pb-8 transition-all duration-300" x-show="showSidebar" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-4" x-cloak>
                <div class="lg:sticky lg:top-8 bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-blue-600 shadow-sm">
                                <i class="fas fa-filter"></i>
                            </div>
                            <div>
                                <h2 class="font-extrabold text-blue-900 text-lg tracking-tight">Security Filters</h2>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Filter Staff by MFA State</p>
                            </div>
                        </div>
                        <button @click="showSidebar = false" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-blue-600 shadow-sm flex items-center justify-center">
                            <i class="fas fa-angle-right"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-6 w-full flex-1">
                        <!-- Search Keyword -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-search text-blue-500"></i> Search Staff
                            </label>
                            <div class="relative">
                                <input type="text" x-model="search" placeholder="Name, username, CNIC..." class="w-full pl-10 pr-4 py-3 bg-white border-2 border-slate-100 rounded-xl focus:border-blue-500 outline-none font-bold text-slate-700 text-sm transition-all focus:shadow-md">
                                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3.5 text-slate-400 text-xs"></i>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-shield-halved text-indigo-500"></i> Security Status
                            </label>
                            <div class="space-y-1.5">
                                <button @click="filterStatus = ''; filterMethod = ''" :class="(filterStatus === '' && filterMethod === '') ? 'bg-blue-600 text-white font-bold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'" class="w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between">
                                    <span>All Accounts</span>
                                    <span class="text-[10px] opacity-75 font-mono" x-text="items.length"></span>
                                </button>
                                <button @click="filterStatus = 'enrolled'; filterMethod = ''" :class="filterStatus === 'enrolled' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'" class="w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between">
                                    <span>Enrolled (Protected)</span>
                                    <span class="text-[10px] opacity-75 font-mono" x-text="items.filter(i => i.is_enrolled).length"></span>
                                </button>
                                <button @click="filterStatus = 'exempt'; filterMethod = ''" :class="filterStatus === 'exempt' ? 'bg-amber-500 text-white font-bold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'" class="w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between">
                                    <span>Password Only (Exempt)</span>
                                    <span class="text-[10px] opacity-75 font-mono" x-text="items.filter(i => i.is_exempt).length"></span>
                                </button>
                                <button @click="filterStatus = 'unenrolled'; filterMethod = ''" :class="filterStatus === 'unenrolled' ? 'bg-rose-600 text-white font-bold' : 'bg-slate-50 text-slate-700 hover:bg-slate-100'" class="w-full text-left px-3 py-2 rounded-xl text-xs transition-all flex items-center justify-between">
                                    <span>Pending Setup</span>
                                    <span class="text-[10px] opacity-75 font-mono" x-text="items.filter(i => !i.is_enrolled && !i.is_exempt).length"></span>
                                </button>
                            </div>
                        </div>

                        <!-- Role Filter -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-users-gear text-purple-500"></i> Role Filter
                            </label>
                            <select x-model="filterRole" class="w-full p-3 bg-white border-2 border-slate-100 rounded-xl focus:border-blue-500 outline-none font-bold text-slate-700 text-xs">
                                <option value="">All Operational Roles</option>
                                <template x-for="r in roles" :key="r.id">
                                    <option :value="r.id" x-text="r.display_name || r.name"></option>
                                </template>
                            </select>
                        </div>

                        <!-- Method Filter -->
                        <div class="space-y-2">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-microchip text-blue-500"></i> Authentication Method
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button @click="filterMethod = (filterMethod === 'totp' ? '' : 'totp'); filterStatus = ''" :class="filterMethod === 'totp' ? 'bg-indigo-600 text-white font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200/80'" class="px-3 py-2.5 rounded-xl text-[11px] font-bold text-center transition-all">
                                    <i class="fa-solid fa-mobile-screen block mb-1"></i> Mobile App
                                </button>
                                <button @click="filterMethod = (filterMethod === 'webauthn' ? '' : 'webauthn'); filterStatus = ''" :class="filterMethod === 'webauthn' ? 'bg-purple-600 text-white font-bold' : 'bg-slate-50 text-slate-700 border border-slate-200/80'" class="px-3 py-2.5 rounded-xl text-[11px] font-bold text-center transition-all">
                                    <i class="fa-solid fa-key block mb-1"></i> Security Key
                                </button>
                            </div>
                        </div>

                        <!-- Reset All Button -->
                        <button @click="resetFilters()" class="w-full py-3 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2">
                            <i class="fas fa-rotate-left"></i>
                            <span>Reset Filters</span>
                        </button>
                    </div>
                </div>
            </div>

        </div>

    <!-- MODAL 1: CONFIRM ACTION MODAL (For Reset Phone, Turn Off, Enforce) -->
    <div x-show="showConfirmModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showConfirmModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-[2rem] shadow-2xl border border-slate-100 max-w-lg w-full p-8 text-left overflow-hidden animate-scale-up">
                
                <div class="flex items-start gap-4">
                    <div :class="confirmData.isDanger ? 'bg-rose-100 text-rose-600' : (confirmData.isWarning ? 'bg-amber-100 text-amber-600' : 'bg-blue-100 text-blue-600')" class="w-14 h-14 rounded-2xl flex items-center justify-center text-2xl shrink-0 shadow-sm">
                        <i :class="confirmData.icon"></i>
                    </div>
                    <div>
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Security Confirmation</span>
                        <h3 class="text-xl font-black text-blue-900 mt-0.5" x-text="confirmData.title"></h3>
                        <p class="text-xs text-slate-600 mt-2 font-medium leading-relaxed" x-text="confirmData.message"></p>
                    </div>
                </div>

                <div class="mt-6 p-4 rounded-xl bg-slate-50 border border-slate-200/80 text-xs">
                    <div class="flex items-center justify-between font-bold text-slate-700">
                        <span>Staff Member:</span>
                        <span class="text-blue-900" x-text="selectedUser ? selectedUser.full_name : ''"></span>
                    </div>
                    <div class="flex items-center justify-between font-bold text-slate-700 mt-1.5">
                        <span>Username:</span>
                        <span class="font-mono text-slate-600" x-text="selectedUser ? '@' + selectedUser.username : ''"></span>
                    </div>
                    <div class="flex items-center justify-between font-bold text-slate-700 mt-1.5">
                        <span>Role:</span>
                        <span class="text-blue-700" x-text="selectedUser ? selectedUser.role_display : ''"></span>
                    </div>
                </div>

                <form :action="confirmData.actionUrl" method="POST" class="mt-8 flex items-center justify-end gap-3">
                    <input type="hidden" name="_token" :value="csrfToken">
                    <button type="button" @click="showConfirmModal = false" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs transition-all">
                        Cancel
                    </button>
                    <button type="submit" :class="confirmData.isDanger ? 'bg-rose-600 hover:bg-rose-700 text-white' : (confirmData.isWarning ? 'bg-amber-500 hover:bg-amber-600 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white')" class="px-6 py-2.5 rounded-xl font-black text-xs uppercase tracking-wider shadow-md transition-all active:scale-95">
                        <span x-text="confirmData.buttonText"></span>
                    </button>
                </form>

            </div>
        </div>
    </div>

    <!-- MODAL 2: STAFF MFA PROFILE DETAILS MODAL -->
    <div x-show="showDetailsModal" class="fixed inset-0 z-50 overflow-y-auto" x-cloak>
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showDetailsModal = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-[2rem] shadow-2xl border border-slate-100 max-w-xl w-full p-8 text-left overflow-hidden">
                
                <div class="flex items-center justify-between pb-6 border-b border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center text-xl shadow-sm">
                            <i class="fa-solid fa-id-card"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-black text-blue-900">Staff Security Profile</h3>
                            <p class="text-xs text-slate-500 font-medium">Multi-Factor credentials and enrollment status</p>
                        </div>
                    </div>
                    <button @click="showDetailsModal = false" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-all">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <template x-if="selectedUser">
                    <div class="mt-6 space-y-6">
                        <!-- User Head -->
                        <div class="flex items-center gap-4 bg-slate-50 p-4 rounded-2xl border border-slate-100">
                            <div class="w-14 h-14 rounded-2xl bg-blue-900 text-white flex items-center justify-center font-extrabold text-xl uppercase shadow-md" x-text="selectedUser.username.substring(0, 2)"></div>
                            <div>
                                <h4 class="text-base font-black text-blue-900" x-text="selectedUser.full_name"></h4>
                                <div class="text-xs text-slate-500 font-mono" x-text="'@' + selectedUser.username + ' &bull; ' + selectedUser.role_display"></div>
                                <span class="text-[10px] font-bold text-slate-500" x-text="selectedUser.designation_name"></span>
                            </div>
                        </div>

                        <!-- Credentials Grid -->
                        <div class="grid grid-cols-2 gap-4 text-xs">
                            <div class="bg-white p-4 rounded-xl border border-slate-200/80">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">CNIC Number</span>
                                <span class="font-bold text-slate-800 font-mono" x-text="selectedUser.cnic"></span>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-slate-200/80">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Official Mobile</span>
                                <span class="font-bold text-slate-800 font-mono" x-text="selectedUser.mobile_no"></span>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-slate-200/80">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Email Address</span>
                                <span class="font-bold text-slate-800 truncate block" x-text="selectedUser.email"></span>
                            </div>
                            <div class="bg-white p-4 rounded-xl border border-slate-200/80">
                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-1">Primary Scope</span>
                                <span class="font-bold text-blue-700 block" x-text="selectedUser.scope_name + ' (' + selectedUser.scope_type + ')'"></span>
                            </div>
                        </div>

                        <!-- 2FA Methods Status -->
                        <div class="p-5 rounded-2xl border border-slate-200/80 space-y-3">
                            <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest">Enrolled Authentication Methods</h5>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs p-2.5 rounded-xl bg-slate-50">
                                    <span class="flex items-center gap-2 font-bold text-slate-700">
                                        <i class="fa-solid fa-mobile-screen text-indigo-500"></i> Mobile Authenticator App (TOTP)
                                    </span>
                                    <span :class="selectedUser.has_totp ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : 'text-slate-400 bg-slate-100 border-slate-200'" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase border" x-text="selectedUser.has_totp ? 'Configured' : 'Not Setup'"></span>
                                </div>
                                <div class="flex items-center justify-between text-xs p-2.5 rounded-xl bg-slate-50">
                                    <span class="flex items-center gap-2 font-bold text-slate-700">
                                        <i class="fa-solid fa-key text-purple-500"></i> Hardware Security Key (FIDO2)
                                    </span>
                                    <span :class="selectedUser.has_webauthn ? 'text-emerald-600 bg-emerald-50 border-emerald-200' : 'text-slate-400 bg-slate-100 border-slate-200'" class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase border" x-text="selectedUser.has_webauthn ? 'Registered' : 'Not Setup'"></span>
                                </div>
                            </div>
                        </div>

                    </div>
                </template>

                <div class="mt-8 pt-4 border-t border-slate-100 flex items-center justify-end">
                    <button type="button" @click="showDetailsModal = false" class="px-6 py-2.5 rounded-xl bg-slate-900 text-white font-bold text-xs uppercase tracking-wider hover:bg-slate-800 transition-all">
                        Close Profile
                    </button>
                </div>

            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
    window.mfaManager = function(config) {
        return {
            items: config.items || [],
            roles: config.roles || [],
            stats: config.stats || {},
            csrfToken: config.csrfToken || '',
            viewMode: 'table',
            density: 'spacious',
            search: '',
            filterRole: '',
            filterStatus: '',
            filterMethod: '',
            showSidebar: false,
            page: 1,
            perPage: 10,
            sortBy: 'username',
            sortDirection: 'asc',

            // Modals
            showConfirmModal: false,
            showDetailsModal: false,
            selectedUser: null,

            confirmData: {
                title: '',
                message: '',
                icon: '',
                buttonText: '',
                actionUrl: '',
                isDanger: false,
                isWarning: false,
            },

            get filteredItems() {
                const searchLower = (this.search || '').toLowerCase().trim();

                return this.items.filter(item => {
                    // Search filter
                    if (searchLower !== '') {
                        const match = (item.username || '').toLowerCase().includes(searchLower) ||
                                      (item.full_name || '').toLowerCase().includes(searchLower) ||
                                      (item.cnic || '').toLowerCase().includes(searchLower) ||
                                      (item.email || '').toLowerCase().includes(searchLower) ||
                                      (item.designation_name || '').toLowerCase().includes(searchLower);
                        if (!match) return false;
                    }

                    // Role filter
                    if (this.filterRole && item.role_id != this.filterRole) {
                        return false;
                    }

                    // Status filter
                    if (this.filterStatus === 'enrolled' && !item.is_enrolled) return false;
                    if (this.filterStatus === 'exempt' && !item.is_exempt) return false;
                    if (this.filterStatus === 'unenrolled' && (item.is_enrolled || item.is_exempt)) return false;

                    // Method filter
                    if (this.filterMethod === 'totp' && !item.has_totp) return false;
                    if (this.filterMethod === 'webauthn' && !item.has_webauthn) return false;

                    return true;
                }).sort((a, b) => {
                    let fieldA = a[this.sortBy] ?? '';
                    let fieldB = b[this.sortBy] ?? '';

                    if (typeof fieldA === 'string') fieldA = fieldA.toLowerCase();
                    if (typeof fieldB === 'string') fieldB = fieldB.toLowerCase();

                    if (fieldA < fieldB) return this.sortDirection === 'asc' ? -1 : 1;
                    if (fieldA > fieldB) return this.sortDirection === 'asc' ? 1 : -1;
                    return 0;
                });
            },

            get totalPages() {
                return Math.max(1, Math.ceil(this.filteredItems.length / this.perPage));
            },

            get pagedItems() {
                if (this.page > this.totalPages) this.page = this.totalPages;
                const start = (this.page - 1) * this.perPage;
                return this.filteredItems.slice(start, start + this.perPage);
            },

            pagesToShow() {
                const pages = [];
                const max = this.totalPages;
                const current = this.page;

                if (max <= 7) {
                    for (let i = 1; i <= max; i++) pages.push(i);
                } else {
                    pages.push(1);
                    if (current > 3) pages.push('...');
                    for (let i = Math.max(2, current - 1); i <= Math.min(max - 1, current + 1); i++) {
                        pages.push(i);
                    }
                    if (current < max - 2) pages.push('...');
                    pages.push(max);
                }
                return pages;
            },

            sortByItem(field) {
                if (this.sortBy === field) {
                    this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.sortBy = field;
                    this.sortDirection = 'asc';
                }
            },

            getSortIcon(field) {
                if (this.sortBy !== field) return 'fa-sort opacity-40';
                return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
            },

            resetFilters() {
                this.search = '';
                this.filterRole = '';
                this.filterStatus = '';
                this.filterMethod = '';
                this.page = 1;
            },

            viewDetails(user) {
                this.selectedUser = user;
                this.showDetailsModal = true;
            },

            confirmAction(type, user) {
                this.selectedUser = user;

                if (type === 'reset') {
                    this.confirmData = {
                        title: 'Reset Authenticator Device',
                        message: 'This will disconnect the officer’s current authenticator app or hardware key. On their next sign in, they will be required to scan a new QR code.',
                        icon: 'fa-solid fa-arrows-rotate',
                        buttonText: 'Reset Device Pairing',
                        actionUrl: user.reset_url,
                        isDanger: false,
                        isWarning: true,
                    };
                } else if (type === 'revoke') {
                    this.confirmData = {
                        title: 'Turn Off 2FA for Officer',
                        message: 'This will exempt this officer from multi-factor authentication. They will be permitted to log in with their username and password only.',
                        icon: 'fa-solid fa-power-off',
                        buttonText: 'Turn Off 2FA',
                        actionUrl: user.revoke_url,
                        isDanger: true,
                        isWarning: false,
                    };
                } else if (type === 'enforce') {
                    this.confirmData = {
                        title: 'Require 2FA for Officer',
                        message: 'This officer will be required to set up and verify a two-factor authenticator app or hardware security key upon their next sign in.',
                        icon: 'fa-solid fa-shield',
                        buttonText: 'Require 2FA',
                        actionUrl: user.enforce_url,
                        isDanger: false,
                        isWarning: false,
                    };
                }

                this.showConfirmModal = true;
            }
        };
    };
</script>
@endpush

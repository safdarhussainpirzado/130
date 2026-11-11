@extends('layouts.app')

@section('title', 'Roles Management - NHMP 130')

@section('page-title', 'Roles Management')

@section('content')


    <div x-data="roleManager(@js([
        'items' => $roles,
        'permissions' => $permissions
    ]))" class="space-y-8 relative mt-4 w-full max-w-full pb-32" @keydown.escape="showDetailsModal = false; showViewModal = false; showConfirmModal = false; showMfaModal = false" x-cloak>


        <!-- Floating Sidebar Toggle -->
        <button @click="showSidebar = true"
            x-show="!showSidebar && pageMode === 'index'"
            x-transition:enter="transition ease-out duration-500 delay-100"
            x-transition:enter-start="translate-x-full opacity-0"
            x-transition:enter-end="translate-x-0 opacity-100"
            class="fixed top-1/2 right-0 -translate-y-1/2 z-[100] bg-gradient-to-b from-blue-500 to-blue-700 text-white p-2.5 py-6 rounded-l-2xl shadow-[0_0_30px_-5px_rgba(79,70,229,0.4)] hover:shadow-[-5px_0_40px_-5px_rgba(79,70,229,0.7)] hover:pr-4 transition-all duration-300 flex flex-col items-center gap-4 cursor-pointer" title="Open Filters">
            <i class="fas fa-sliders-h drop-shadow-lg text-sm"></i>
            <span style="writing-mode: vertical-rl;" class="text-[9px] font-black uppercase tracking-[0.3em] rotate-180 drop-shadow-md text-blue-50 border-r-0">Management Filters</span>
        </button>

        <!-- Page Mode: Index -->
        <div x-show="pageMode === 'index'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-8">

        <!-- STATS CARDS -->
        <div class="grid grid-cols-2 md:grid-cols-6 lg:grid-cols-6 gap-4">
            <!-- Defined Roles Card -->
            <div @click="filterStatus = ''" :class="filterStatus === '' ? 'card-3d-active blue' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer col-span-2">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-blue-600 shadow-[0_8px_16px_rgba(37,99,235,0.2)]">
                    <i class="fas fa-shield-alt text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-blue-500 uppercase mt-2">Defined Roles</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="items.length"></h4>
                </div>
            </div>

            <!-- Active Controls Card -->
            <div @click="filterStatus = (filterStatus === 'active' ? '' : 'active')" :class="filterStatus === 'active' ? 'card-3d-active emerald' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer col-span-2">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-emerald-500 shadow-[0_8px_16px_rgba(16,185,129,0.2)]">
                    <i class="fas fa-check-circle text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-emerald-500 uppercase mt-2">Active Controls</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="items.length"></h4>
                </div>
            </div>

            <!-- Permission Matrix Card -->
            <div @click="filterStatus = (filterStatus === 'matrix' ? '' : 'matrix')" :class="filterStatus === 'matrix' ? 'card-3d-active purple' : ''" class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer col-span-2">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-purple-500 shadow-[0_8px_16px_rgba(168,85,247,0.2)]">
                    <i class="fas fa-layer-group text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-purple-500 uppercase mt-2">Permission Matrix</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="Object.values(permissions).flat().length"></h4>
                </div>
            </div>
        </div>

        <!-- MAIN CONTROL PANEL -->
        <div class="grid lg:grid-cols-12 gap-8 relative pb-20 px-4 sm:px-0">
            
            <!-- Left Column Content -->
            <div class="transition-all duration-500" :class="showSidebar ? 'lg:col-span-9' : 'lg:col-span-12'">
                <div class="bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-gray-100 overflow-hidden flex flex-col relative z-20">
                    
                    <!-- Panel Header -->
                    <div class="bg-blue-50/50 p-8 border-b border-blue-100/50">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                            <div class="flex items-center gap-4">
                                <div class="w-16 h-16 rounded-[1.25rem] bg-white flex items-center justify-center border border-blue-100 shadow-sm transition-transform hover:scale-105 duration-300">
                                    <i class="fas fa-shield-alt text-3xl text-blue-600"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-extrabold text-blue-900 tracking-tight flex items-center gap-3">
                                        Roles Registry <span class="text-lg font-bold text-slate-400" x-text="'(' + filteredItems.length + ' records)'"></span>
                                    </h2>
                                    <p class="text-slate-500 text-sm font-bold mt-1">Manage system-wide access and authority levels</p>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap gap-4 items-center">
                                <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-sm">
                                    <span class="text-[9px] font-black text-slate-400 border-r border-slate-100 pr-2 uppercase font-mono">Row Density</span>
                                    <select x-model="perPage" class="bg-transparent text-blue-600 text-[10px] font-black uppercase cursor-pointer outline-none focus:ring-0 border-none p-0 pr-4">
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

                                <button @click="addRole()" class="flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-black shadow-[0_8px_20px_rgba(37,99,235,0.3)] transition-all active:scale-95 group">
                                    <i class="fas fa-plus group-hover:rotate-180 transition-transform duration-500"></i> Create Role
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
                                <div class="bg-white border-2 border-slate-200 shadow-md rounded-[2.5rem] p-8 transition-all group relative overflow-hidden">
                                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform duration-500">
                                        <i class="fa-solid fa-shield-halved text-9xl text-blue-900"></i>
                                    </div>
                                    
                                    <div class="flex items-start justify-between mb-8 relative z-10">
                                        <div class="w-16 h-16 rounded-[1.25rem] bg-blue-600 text-white flex items-center justify-center shadow-lg group-hover:rotate-6 transition-transform">
                                            <i class="fas fa-shield-alt text-2xl"></i>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-[10px] font-black tracking-[0.2em] text-slate-400 uppercase mb-1">Access State</div>
                                            <template x-if="item.status === 'active'">
                                                <span class="flex items-center gap-2 text-blue-600 font-black text-[10px] tracking-widest uppercase justify-end">
                                                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span> Active
                                                </span>
                                            </template>
                                            <template x-if="item.status !== 'active'">
                                                <span class="flex items-center gap-2 text-rose-600 font-black text-[10px] tracking-widest uppercase justify-end">
                                                    <span class="w-2 h-2 rounded-full bg-rose-500"></span> Blocked
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    <div class="relative z-10">
                                        <h4 class="text-2xl font-extrabold text-blue-900 tracking-tight leading-tight mb-2" x-text="item.display_name"></h4>
                                        <div class="text-[10px] font-mono font-bold text-slate-400 uppercase tracking-widest mb-6" x-text="item.name"></div>

                                        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-100 mb-8 max-h-[100px] overflow-y-auto no-scrollbar">
                                            <div class="flex flex-wrap gap-1">
                                                <template x-for="perm in (item.permissions || [])" :key="perm.id">
                                                    <span class="px-2 py-1 bg-slate-200 text-slate-600 font-bold text-[9px] rounded-md uppercase tracking-wider block whitespace-nowrap mb-1 mr-1" x-text="perm.name.split('.')[1]"></span>
                                                </template>
                                                <template x-if="!(item.permissions && item.permissions.length)">
                                                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest p-2">No rights bound.</span>
                                                </template>
                                            </div>
                                        </div>

                                        <div class="pt-5 border-t border-slate-100 space-y-3 w-full">
                                            <!-- Action Grid: 6 icon buttons -->
                                            <div class="grid grid-cols-6 gap-1.5">
                                                <button @click="viewItem(item)" title="View Capabilities" class="w-8 h-8 rounded-xl bg-blue-600 border border-blue-700 text-white hover:bg-blue-700 active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                    <i class="fa-solid fa-eye text-xs"></i>
                                                </button>
                                                <button @click="configurePermissions(item)" title="Configure Permissions" class="w-8 h-8 rounded-xl bg-indigo-600 border border-indigo-700 text-white hover:bg-indigo-700 active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                    <i class="fa-solid fa-microchip text-xs"></i>
                                                </button>
                                                <button @click="editDetails(item)" title="Edit Basic Details" class="w-8 h-8 rounded-xl bg-sky-500 border border-sky-600 text-white hover:bg-sky-600 active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                    <i class="fa-solid fa-pen-to-square text-xs"></i>
                                                </button>
                                                <template x-if="item.is_mfa_protected">
                                                    <button type="button" disabled title="Protected Role: 2FA is permanently enforced" class="w-8 h-8 rounded-xl bg-slate-100 border border-slate-200 text-slate-400 cursor-not-allowed shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                        <i class="fa-solid fa-shield-halved text-xs"></i>
                                                    </button>
                                                </template>
                                                <template x-if="!item.is_mfa_protected">
                                                    <button @click="openMfaModal(item)" :title="item.is_mfa_enforced ? '2FA Enforced (Click to configure/exempt)' : '2FA Exempt (Click to enforce)'" :class="item.is_mfa_enforced ? 'bg-emerald-600 border-emerald-700 hover:bg-emerald-700' : 'bg-amber-500 border-amber-600 hover:bg-amber-600'" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                        <i class="fa-solid fa-shield-halved text-xs"></i>
                                                    </button>
                                                </template>
                                                <button @click="confirmStatus(item)" title="Toggle State" :class="item.status === 'active' ? 'bg-slate-800 border-slate-900 hover:bg-slate-950' : 'bg-rose-500 border-rose-600 hover:bg-rose-600'" class="w-8 h-8 rounded-xl text-white border active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
                                                    <i class="fa-solid fa-power-off text-xs"></i>
                                                </button>
                                                <button @click="confirmDelete(item)" title="Delete Role" class="w-8 h-8 rounded-xl bg-rose-600 border border-rose-700 text-white hover:bg-rose-700 active:scale-95 transition-all shadow-sm flex items-center justify-center mx-auto aspect-square shrink-0">
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
                            <thead class="bg-blue-50 border-b border-blue-100">
                                <tr>
                                    <th class="px-4 py-4 border-b border-slate-50" style="width:48px">
                                        <div class="flex items-center justify-center">
                                            <div class="w-8 h-8 rounded-lg bg-white text-blue-500 border border-blue-100 flex items-center justify-center"><i class="fas fa-cube text-xs"></i></div>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100"><i class="fas fa-shield-halved text-[10px]"></i></div>
                                            <button @click="sortByField('display_name')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">Role Label <i class="fas text-[10px] transition-all opacity-0 group-hover:opacity-100 text-blue-600" :class="getSortIcon('display_name')"></i></button>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100"><i class="fas fa-id-badge text-[10px]"></i></div>
                                            <button @click="sortByField('name')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">System ID <i class="fas text-[10px] transition-all opacity-0 group-hover:opacity-100 text-blue-600" :class="getSortIcon('name')"></i></button>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100"><i class="fas fa-toggle-on text-[10px]"></i></div>
                                            <button @click="sortByField('status')" class="flex items-center gap-1.5 hover:text-blue-700 transition-colors group">State <i class="fas text-[10px] transition-all opacity-0 group-hover:opacity-100 text-blue-600" :class="getSortIcon('status')"></i></button>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50">
                                        <div class="flex items-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100"><i class="fas fa-link text-[10px]"></i></div>
                                            <span>Bindings</span>
                                        </div>
                                    </th>
                                    <th class="px-4 py-4 border-b border-slate-50 text-center">
                                        <div class="flex items-center justify-center gap-2.5 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">
                                            <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shadow-sm border border-blue-100"><i class="fas fa-bolt text-[10px]"></i></div>
                                            <span>Actions</span>
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="item in pagedItems" :key="item.id">
                                    <tr class="hover:bg-blue-50/40 transition-colors group">
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center font-black shadow-sm group-hover:scale-110 group-hover:bg-blue-600 group-hover:text-white transition-colors" x-text="item.id"></div>
                                        </td>
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="flex flex-col">
                                                <span class="font-extrabold text-blue-900 text-base mb-0.5" x-text="item.display_name"></span>
                                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-400" x-text="item.name === 'admin' ? 'Root Administrator' : 'Standard Role'"></span>
                                            </div>
                                        </td>
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <span class="text-xs font-black uppercase tracking-widest text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100" x-text="item.name"></span>
                                        </td>
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <span :class="item.status === 'active' ? 'bg-blue-50 text-blue-600 border-blue-100' : 'bg-rose-50 text-rose-600 border-rose-100'" class="px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest border" x-text="item.status === 'active' ? 'Active' : 'Blocked'"></span>
                                        </td>
                                        <td class="px-6" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <span class="text-xs font-extrabold text-slate-600 bg-slate-100 px-3 py-1.5 rounded-lg border border-slate-200 shadow-inner block w-fit" x-text="(item.permissions ? item.permissions.length : 0) + ' bindings'"></span>
                                        </td>
                                        <td class="px-6 text-center" :class="density === 'condensed' ? 'py-3' : 'py-5'">
                                            <div class="flex items-center justify-center gap-1.5 whitespace-nowrap mx-auto">
                                                <button @click="viewItem(item)" title="View Capabilities" class="w-9 h-9 bg-blue-600 border border-blue-700 text-white hover:bg-blue-700 transition-all flex items-center justify-center rounded-xl shadow-sm active:scale-95 group/btn">
                                                    <i class="fa-solid fa-eye text-[10px]"></i>
                                                </button>
                                                <button @click="configurePermissions(item)" title="Configure Permissions" class="w-9 h-9 bg-indigo-600 border border-indigo-700 text-white hover:bg-indigo-700 transition-all flex items-center justify-center rounded-xl shadow-sm active:scale-95 group/btn">
                                                    <i class="fa-solid fa-microchip text-[10px] group-hover:rotate-12 transition-transform"></i>
                                                </button>
                                                <button @click="editDetails(item)" title="Edit Basic Details" class="w-9 h-9 bg-sky-500 border border-sky-600 text-white hover:bg-sky-600 transition-all flex items-center justify-center rounded-xl shadow-sm active:scale-95 group/btn">
                                                    <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                                                </button>
                                                <template x-if="item.is_mfa_protected">
                                                    <button type="button" disabled title="Protected Role: 2FA is permanently enforced" class="w-9 h-9 bg-slate-100 border border-slate-200 text-slate-400 cursor-not-allowed flex items-center justify-center rounded-xl shadow-sm">
                                                        <i class="fa-solid fa-shield-halved text-[10px]"></i>
                                                    </button>
                                                </template>
                                                <template x-if="!item.is_mfa_protected">
                                                    <button @click="openMfaModal(item)" :title="item.is_mfa_enforced ? '2FA Enforced (Click to configure/exempt)' : '2FA Exempt (Click to enforce)'" :class="item.is_mfa_enforced ? 'bg-emerald-600 border-emerald-700 hover:bg-emerald-700' : 'bg-amber-500 border-amber-600 hover:bg-amber-600'" class="w-9 h-9 text-white border transition-all flex items-center justify-center rounded-xl shadow-sm active:scale-95 group/btn">
                                                        <i class="fa-solid fa-shield-halved text-[10px]"></i>
                                                    </button>
                                                </template>
                                                <button @click="confirmStatus(item)" title="Toggle State" :class="item.status === 'active' ? 'bg-slate-800 border-slate-900 hover:bg-slate-950' : 'bg-rose-500 border-rose-600 hover:bg-rose-600'" class="w-9 h-9 text-white border transition-all flex items-center justify-center rounded-xl shadow-sm active:scale-95">
                                                    <i class="fa-solid fa-power-off text-[10px]"></i>
                                                </button>
                                                <button @click="confirmDelete(item)" title="Delete Role" class="w-9 h-9 bg-rose-600 border border-rose-700 text-white hover:bg-rose-700 transition-all flex items-center justify-center rounded-xl shadow-sm active:scale-95">
                                                    <i class="fa-solid fa-trash-alt text-[10px]"></i>
                                                </button>
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
                            Showing <span class="text-blue-900" x-text="((page - 1) * perPage) + 1"></span> to <span class="text-blue-900" x-text="Math.min(page * perPage, filteredItems.length)"></span> of <span class="text-blue-900" x-text="filteredItems.length"></span> Points
                        </div>
                        <div class="flex items-center gap-2">
                            <button @click="page--" :disabled="page === 1" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-slate-50 transition-all"><i class="fa-solid fa-chevron-left"></i></button>
                            <template x-for="p in totalPages" :key="p">
                                <button @click="page = p" :class="page === p ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-500/30' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'" class="w-10 h-10 rounded-xl border font-black text-xs transition-all" x-text="p"></button>
                            </template>
                            <button @click="page++" :disabled="page === totalPages" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 disabled:opacity-30 disabled:cursor-not-allowed hover:bg-slate-50 transition-all"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div x-show="filteredItems.length === 0" class="py-32 text-center bg-white">
                        <i class="fa-solid fa-shield-virus text-6xl text-slate-100 mb-6 animate-pulse"></i>
                        <h3 class="text-2xl font-black text-blue-900" x-text="search ? 'No access points match' : 'Registry Empty'"></h3>
                        <p class="text-slate-400 font-bold mt-2" x-text="search ? 'Refine your query parameters.' : 'Database contains no configured authorization layers.'"></p>
                    </div>

                </div>
            </div>

            <!-- Right Column - Operational Filters -->
            <div class="lg:col-span-3 pb-8 transition-all duration-300" x-show="showSidebar" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100 translate-x-0" x-transition:leave-end="opacity-0 translate-x-4" x-cloak>
                <div class="lg:sticky lg:top-4 lg:max-h-[calc(100vh-100px)] lg:overflow-y-auto bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden flex flex-col">
                    <div class="p-6 border-b border-slate-100 bg-slate-50 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-white border border-slate-200 flex items-center justify-center text-blue-600 shadow-sm">
                                <i class="fas fa-filter"></i>
                            </div>
                            <div>
                                <h2 class="font-extrabold text-blue-900 text-lg tracking-tight">Intelligence</h2>
                                <p class="text-[9px] font-black text-slate-400 uppercase tracking-widest mt-0.5">Filter Records</p>
                            </div>
                        </div>
                        <button @click="showSidebar = false" class="w-8 h-8 rounded-lg bg-white border border-slate-200 text-slate-400 hover:text-blue-600 shadow-sm flex items-center justify-center">
                            <i class="fas fa-angle-right"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-6">
                        <!-- Search Localize Point -->
                        <div class="space-y-3">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-search text-blue-500"></i> Localize Role
                            </label>
                            <div class="relative">
                                <input type="text" x-model="search" placeholder="Search Role Name..." class="w-full pl-11 pr-4 py-3 bg-white border-2 border-slate-100 rounded-xl focus:border-blue-500 outline-none font-bold text-slate-700 text-sm transition-all focus:shadow-lg focus:shadow-blue-500/10">
                                <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-300"></i>
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
                        <div class="space-y-3 border-t border-slate-100 pt-6">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                <i class="fas fa-sort-amount-down text-blue-500"></i> Sequencing
                            </label>
                            <div class="grid grid-cols-1 gap-2">
                                <button @click="sortBy = 'display_name'" :class="sortBy === 'display_name' ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 '" class="px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest text-left flex items-center justify-between transition-all">
                                    <span>Identity Handle</span>
                                    <i class="fas fa-signature opacity-40"></i>
                                </button>
                                <button @click="sortBy = 'name'" :class="sortBy === 'name' ? 'bg-blue-600 text-white shadow-md' : 'bg-slate-50 text-slate-600 '" class="px-4 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest text-left flex items-center justify-between transition-all">
                                    <span>System Code</span>
                                    <i class="fas fa-code opacity-40"></i>
                                </button>
                                <button @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'" class="mt-2 px-4 py-2 bg-blue-50 text-blue-600 rounded-lg text-[9px] font-black uppercase tracking-widest hover:bg-blue-100 transition-colors flex items-center justify-center gap-2">
                                    <i class="fas" :class="sortDirection === 'asc' ? 'fa-sort-alpha-up' : 'fa-sort-alpha-down'"></i>
                                    <span x-text="sortDirection === 'asc' ? 'Forward Flow' : 'Reverse Flow'"></span>
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3 pt-6 border-t border-slate-100">
                            <button @click="search = ''; sortBy = 'display_name'; sortDirection = 'asc'; filterStatus = '';" class="w-full py-5 bg-rose-600 text-white hover:bg-rose-700 rounded-3xl text-[10px] font-black uppercase tracking-[0.3em] transition-all duration-300 flex items-center justify-center gap-3 active:scale-95 shadow-lg shadow-rose-600/20">
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
    </div> <!-- End Page Mode: Index -->

        <!-- Page Mode: Permissions Matrix (100% Full Width 3D Bento Layout) -->
        <div x-show="pageMode === 'permissions'" 
             x-transition:enter="transition ease-out duration-300" 
             x-transition:enter-start="opacity-0 translate-y-2" 
             x-transition:enter-end="opacity-100 translate-y-0"
             class="space-y-6 pb-32 relative z-[10] w-full max-w-full" x-cloak>
            
            <!-- 3D Bento Header & Authority Context Card -->
            <div class="w-full bg-white rounded-[2rem] shadow-[0_12px_40px_rgba(0,0,0,0.04)] border-2 border-slate-200/90 p-6 md:p-8 relative overflow-hidden">
                <div class="absolute inset-0 bg-grid-slate-900/[0.02] bg-[size:24px_24px] pointer-events-none"></div>
                <div class="absolute -right-20 -top-20 w-80 h-80 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -left-20 -bottom-20 w-80 h-80 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none"></div>

                <div class="relative z-10 flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6">
                    <!-- Title & Role Info -->
                    <div class="flex items-center gap-5">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 flex items-center justify-center text-white shadow-xl shadow-blue-600/30 border-2 border-white/60 shrink-0 transform hover:scale-105 transition-all">
                            <i class="fas fa-microchip text-2xl drop-shadow"></i>
                        </div>
                        <div>
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="px-2.5 py-0.5 rounded-lg bg-blue-100/90 text-blue-800 text-[10px] font-black uppercase tracking-widest border border-blue-200">Logic Matrix</span>
                                <span class="px-2.5 py-0.5 rounded-lg bg-indigo-100/90 text-indigo-800 text-[10px] font-mono font-black uppercase tracking-widest border border-indigo-200" x-text="form.name"></span>
                                <span class="px-2.5 py-0.5 rounded-lg bg-slate-100 text-slate-700 text-[10px] font-black uppercase tracking-widest border border-slate-200" x-text="'Scope: ' + (form.scope_level || 'National')"></span>
                            </div>
                            <h2 class="text-2xl md:text-3xl font-black text-navy-900 tracking-tight" x-text="form.display_name"></h2>
                            <p class="text-xs font-bold text-slate-500 mt-0.5 flex items-center gap-2">
                                <i class="fas fa-shield-halved text-blue-500"></i>
                                <span>Refine operational permissions and role access boundaries across all modules</span>
                            </p>
                        </div>
                    </div>

                    <!-- Global Action Controls & Live Bound Progress Gauge -->
                    <div class="flex flex-wrap items-center gap-3 w-full xl:w-auto justify-start xl:justify-end">
                        <!-- Bound Metric Chip -->
                        <div class="flex items-center gap-3 px-4 py-2.5 rounded-2xl bg-gradient-to-br from-blue-50/90 to-indigo-50/90 border-2 border-blue-200/80 shadow-sm">
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black text-blue-600 uppercase tracking-widest">Active Matrix</span>
                                <span class="text-sm font-black text-blue-950" x-text="form.permissions.length + ' / ' + totalPermissionsCount + ' Bound'"></span>
                            </div>
                            <div class="w-24 bg-blue-200/60 h-2.5 rounded-full overflow-hidden border border-blue-300/40">
                                <div class="bg-gradient-to-r from-blue-600 to-indigo-600 h-full rounded-full transition-all duration-300" :style="'width: ' + boundPercentage + '%'"></div>
                            </div>
                            <span class="text-[11px] font-black text-indigo-700 font-mono" x-text="boundPercentage + '%'"></span>
                        </div>

                        <!-- Global Select All / Deselect All Toggle -->
                        <button type="button" @click="allPermissionsSelected() ? clearMatrix() : selectAllPermissions()"
                            class="px-4 py-2.5 rounded-xl border-2 font-black text-xs uppercase tracking-wider transition-all duration-200 shadow-sm active:scale-95 cursor-pointer flex items-center gap-2"
                            :class="allPermissionsSelected() 
                                ? 'border-indigo-300 bg-indigo-50 hover:bg-indigo-100 text-indigo-700' 
                                : 'border-blue-300 bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 text-blue-700'">
                            <i class="fas fa-check-double text-xs"></i>
                            <span x-text="allPermissionsSelected() ? 'Deselect All' : 'Select All'"></span>
                        </button>

                        <!-- Clear Matrix Button -->
                        <button type="button" @click="clearMatrix()"
                            class="px-3.5 py-2.5 rounded-xl border-2 border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 font-black text-xs uppercase tracking-wider transition-all shadow-sm active:scale-95 cursor-pointer flex items-center gap-1.5" title="Clear all permission selections">
                            <i class="fas fa-eraser text-xs"></i>
                            <span>Clear Matrix</span>
                        </button>

                        <!-- Cancel Button -->
                        <button type="button" @click="pageMode = 'index'"
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-black text-xs uppercase tracking-wider transition-all active:scale-95 cursor-pointer border-2 border-slate-200">
                            Cancel
                        </button>

                        <!-- Save Changes Primary 3D Button -->
                        <button type="button" @click="submitForm()" :disabled="saving"
                            class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-500 hover:to-indigo-600 text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-lg shadow-blue-500/25 hover:-translate-y-0.5 active:scale-95 transition-all flex items-center gap-2 cursor-pointer border border-blue-400/40">
                            <span x-show="!saving" class="flex items-center gap-1.5">
                                <i class="fas fa-floppy-disk text-xs"></i>
                                <span>Save Changes</span>
                            </span>
                            <span x-show="saving" class="flex items-center gap-1.5">
                                <i class="fas fa-circle-notch animate-spin text-xs"></i>
                                <span>Synchronizing...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Group-Wise Module Navigator & Instant Search Bar (100% Full Width) -->
            <div class="w-full bg-white rounded-2xl shadow-sm border-2 border-slate-200/90 p-3 md:p-4 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-3">
                <!-- Group-Wise Filter Pills / Module Navigator -->
                <div class="flex items-center gap-2 overflow-x-auto custom-scrollbar pb-1 lg:pb-0 flex-1">
                    <!-- All Modules Tab -->
                    <button type="button" @click="activeModuleTab = 'all'"
                        class="px-3.5 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all duration-200 shrink-0 cursor-pointer flex items-center gap-2 shadow-sm border-2"
                        :class="activeModuleTab === 'all' 
                            ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white border-blue-600 shadow-blue-500/25 ring-2 ring-blue-500/20' 
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300'">
                        <i class="fas fa-layer-group text-xs"></i>
                        <span>All Modules</span>
                        <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black"
                            :class="activeModuleTab === 'all' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-600'" 
                            x-text="totalPermissionsCount"></span>
                    </button>

                    <!-- Individual Module Tabs -->
                    <template x-for="(perms, moduleName) in permissions" :key="moduleName">
                        <button type="button" @click="activeModuleTab = moduleName"
                            class="px-3 py-2 rounded-xl text-xs font-black uppercase tracking-wider transition-all duration-200 shrink-0 cursor-pointer flex items-center gap-2 shadow-sm border-2"
                            :class="activeModuleTab === moduleName 
                                ? 'bg-gradient-to-r from-blue-600 to-indigo-600 text-white border-blue-600 shadow-blue-500/25 ring-2 ring-blue-500/20' 
                                : (allModuleSelected(perms) ? 'bg-emerald-50 text-emerald-800 border-emerald-300 hover:bg-emerald-100' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100 hover:border-slate-300')">
                            <i class="fas text-xs" :class="{
                                'calls': 'fa-phone',
                                'users': 'fa-users',
                                'reports': 'fa-chart-pie',
                                'dashboard': 'fa-gauge-high',
                                'support': 'fa-headset',
                                'analytics': 'fa-chart-line',
                                'settings': 'fa-gear',
                                'system': 'fa-server',
                                'roles': 'fa-shield-halved',
                                'permissions': 'fa-key',
                                'geography': 'fa-map-location-dot',
                                'security': 'fa-lock',
                                'profile': 'fa-user'
                            }[moduleName] || 'fa-cube'"></i>
                            <span x-text="moduleName.replace('_', ' ')"></span>
                            <span class="px-1.5 py-0.5 rounded-md text-[9px] font-black"
                                :class="activeModuleTab === moduleName ? 'bg-white/20 text-white' : (allModuleSelected(perms) ? 'bg-emerald-200 text-emerald-900' : 'bg-slate-200 text-slate-600')"
                                x-text="moduleActiveCount(perms) + '/' + perms.length"></span>
                        </button>
                    </template>
                </div>

                <!-- Instant Permission Search Box -->
                <div class="relative w-full lg:w-72 shrink-0">
                    <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" x-model="matrixSearch" placeholder="Search permissions..."
                        class="w-full pl-9 pr-8 py-2 rounded-xl border-2 border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all outline-none">
                    <button type="button" x-show="matrixSearch" @click="matrixSearch = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                        <i class="fas fa-times-circle text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- Single-Group Focused View (Active when a specific module is selected) -->
            <template x-if="activeModuleTab !== 'all' && permissions[activeModuleTab]">
                <div class="w-full bg-white rounded-[2rem] shadow-[0_12px_40px_rgba(0,0,0,0.04)] border-2 border-slate-200/90 p-6 md:p-8 relative overflow-hidden">
                    <!-- Focused Group Header -->
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center pb-6 mb-6 border-b-2 border-slate-100 gap-4">
                        <div class="flex items-center gap-4">
                            <span class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white flex items-center justify-center text-2xl shadow-lg shadow-blue-500/25">
                                <i class="fas" :class="{
                                    'calls': 'fa-phone',
                                    'users': 'fa-users',
                                    'reports': 'fa-chart-pie',
                                    'dashboard': 'fa-gauge-high',
                                    'support': 'fa-headset',
                                    'analytics': 'fa-chart-line',
                                    'settings': 'fa-gear',
                                    'system': 'fa-server',
                                    'roles': 'fa-shield-halved',
                                    'permissions': 'fa-key',
                                    'geography': 'fa-map-location-dot',
                                    'security': 'fa-lock',
                                    'profile': 'fa-user'
                                }[activeModuleTab] || 'fa-cube'"></i>
                            </span>
                            <div>
                                <h3 class="text-xl md:text-2xl font-black text-navy-900 uppercase tracking-wider" x-text="activeModuleTab.replace('_', ' ') + ' Control Module'"></h3>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="text-xs font-black text-blue-700 px-3 py-1 bg-blue-50 rounded-xl border border-blue-200"
                                        x-text="moduleActiveCount(permissions[activeModuleTab]) + ' of ' + permissions[activeModuleTab].length + ' Active'"></span>
                                    <button type="button" @click="activeModuleTab = 'all'" class="text-xs font-black text-slate-500 hover:text-blue-600 transition-colors flex items-center gap-1">
                                        <i class="fas fa-arrow-left text-[10px]"></i>
                                        <span>View All Modules</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Single-Group Dedicated Action Buttons -->
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="selectModule(permissions[activeModuleTab])"
                                class="px-4 py-2.5 rounded-xl border-2 border-blue-300 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-black uppercase tracking-wider transition-all shadow-sm active:scale-95 cursor-pointer flex items-center gap-2">
                                <i class="fas fa-check-circle text-xs"></i>
                                <span x-text="'Select All in ' + activeModuleTab.replace('_', ' ')"></span>
                            </button>
                            <button type="button" @click="deselectModule(permissions[activeModuleTab])"
                                class="px-4 py-2.5 rounded-xl border-2 border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-black uppercase tracking-wider transition-all shadow-sm active:scale-95 cursor-pointer flex items-center gap-2">
                                <i class="fas fa-ban text-xs"></i>
                                <span>Deselect All</span>
                            </button>
                        </div>
                    </div>

                    <!-- Focused Group Chips in Spacious Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4 w-full">
                        <template x-for="perm in (matrixSearch ? permissions[activeModuleTab].filter(p => (p.name||'').toLowerCase().includes(matrixSearch.toLowerCase()) || (p.display_name||'').toLowerCase().includes(matrixSearch.toLowerCase())) : permissions[activeModuleTab])" :key="perm.id">
                            <div @click="togglePermission(perm.id)"
                                :class="form.permissions.includes(parseInt(perm.id)) 
                                    ? 'border-blue-500 bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 text-white shadow-[0_8px_20px_rgba(37,99,235,0.35),inset_0_1px_1px_rgba(255,255,255,0.3)] ring-2 ring-blue-400/40 -translate-y-0.5' 
                                    : 'border-slate-200/90 bg-slate-50/80 hover:bg-white text-slate-700 hover:border-blue-300 hover:shadow-md hover:-translate-y-0.5'"
                                class="flex items-center justify-between p-4 rounded-2xl border-2 transition-all duration-200 cursor-pointer select-none group/chip">
                                
                                <div class="flex flex-col min-w-0 pr-3">
                                    <span class="text-xs font-black tracking-wider truncate"
                                        :class="form.permissions.includes(parseInt(perm.id)) ? 'text-white' : 'text-slate-800 group-hover/chip:text-blue-900'"
                                        x-text="(perm.name.split('.')[1] || perm.name).toUpperCase()"></span>
                                    <span class="text-[10px] font-bold font-mono truncate mt-0.5"
                                        :class="form.permissions.includes(parseInt(perm.id)) ? 'text-blue-100' : 'text-slate-400'"
                                        x-text="perm.name"></span>
                                </div>

                                <!-- 3D Tactile Check Circle -->
                                <div class="w-6 h-6 rounded-xl flex items-center justify-center shrink-0 transition-all font-black text-xs"
                                    :class="form.permissions.includes(parseInt(perm.id)) 
                                        ? 'bg-white text-blue-600 shadow-md scale-105' 
                                        : 'border-2 border-slate-300 bg-white text-transparent group-hover/chip:border-blue-400'">
                                    <i class="fa-solid fa-check text-xs"></i>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- All Modules Multi-Column Bento 3D Grid (100% Full Width) -->
            <div x-show="activeModuleTab === 'all'" class="w-full">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-4 gap-6 w-full">
                    <template x-for="(perms, moduleName) in filteredModules()" :key="moduleName">
                        <!-- Bento 3D Module Card -->
                        <div class="bg-white rounded-[1.75rem] p-5 md:p-6 border-2 border-slate-200/90 shadow-[0_8px_30px_rgba(0,0,0,0.03)] hover:shadow-[0_16px_40px_rgba(37,99,235,0.08)] hover:border-blue-300 transition-all duration-300 flex flex-col justify-between relative overflow-hidden">
                            
                            <!-- Top Decorative Accent -->
                            <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r" :class="{
                                'calls': 'from-blue-500 to-indigo-600',
                                'users': 'from-indigo-500 to-purple-600',
                                'reports': 'from-purple-500 to-pink-600',
                                'dashboard': 'from-cyan-500 to-blue-600',
                                'support': 'from-emerald-500 to-teal-600',
                                'analytics': 'from-amber-500 to-orange-600',
                                'settings': 'from-slate-500 to-slate-700',
                                'system': 'from-rose-500 to-red-600',
                                'roles': 'from-blue-600 to-blue-800',
                                'permissions': 'from-violet-500 to-indigo-600',
                                'geography': 'from-teal-500 to-emerald-600',
                                'security': 'from-rose-600 to-rose-800'
                            }[moduleName] || 'from-blue-600 to-indigo-600'"></div>

                            <div>
                                <!-- Module Header -->
                                <div class="flex items-center justify-between pb-4 mb-4 border-b-2 border-slate-100 gap-2">
                                    <div class="flex items-center gap-3">
                                        <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-50 to-indigo-50 text-blue-600 border border-blue-200 flex items-center justify-center text-sm font-bold shadow-sm shrink-0">
                                            <i class="fas" :class="{
                                                'calls': 'fa-phone',
                                                'users': 'fa-users',
                                                'reports': 'fa-chart-pie',
                                                'dashboard': 'fa-gauge-high',
                                                'support': 'fa-headset',
                                                'analytics': 'fa-chart-line',
                                                'settings': 'fa-gear',
                                                'system': 'fa-server',
                                                'roles': 'fa-shield-halved',
                                                'permissions': 'fa-key',
                                                'geography': 'fa-map-location-dot',
                                                'security': 'fa-lock',
                                                'profile': 'fa-user'
                                            }[moduleName] || 'fa-cube'"></i>
                                        </span>
                                        <div>
                                            <h5 class="text-xs font-black text-navy-900 uppercase tracking-wider" x-text="moduleName.replace('_', ' ') + ' Control'"></h5>
                                            <!-- Mini Progress Bar -->
                                            <div class="flex items-center gap-2 mt-1">
                                                <div class="w-16 bg-slate-100 h-1.5 rounded-full overflow-hidden border border-slate-200">
                                                    <div class="bg-blue-600 h-full rounded-full transition-all duration-300" 
                                                        :style="'width: ' + ((moduleActiveCount(perms) / perms.length) * 100) + '%'"></div>
                                                </div>
                                                <span class="text-[9px] font-black text-slate-500 font-mono" x-text="moduleActiveCount(perms) + '/' + perms.length"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Group-Wise Select / Deselect 3D Button -->
                                    <button type="button" @click="toggleModule(perms)" 
                                        class="px-2.5 py-1.5 rounded-xl text-[10px] font-black uppercase tracking-wider transition-all active:scale-95 cursor-pointer shadow-sm border-2 shrink-0 flex items-center gap-1.5"
                                        :class="allModuleSelected(perms) 
                                            ? 'bg-rose-50 text-rose-700 hover:bg-rose-100 border-rose-200' 
                                            : 'bg-blue-50 text-blue-700 hover:bg-blue-100 border-blue-200'">
                                        <i class="fas" :class="allModuleSelected(perms) ? 'fa-ban text-[9px]' : 'fa-check text-[9px]'"></i>
                                        <span x-text="allModuleSelected(perms) ? 'Deselect' : 'Select All'"></span>
                                    </button>
                                </div>

                                <!-- Bento 3D Permission Chips -->
                                <div class="grid grid-cols-1 gap-2.5">
                                    <template x-for="perm in perms" :key="perm.id">
                                        <div @click="togglePermission(perm.id)"
                                            :class="form.permissions.includes(parseInt(perm.id)) 
                                                ? 'border-blue-500 bg-gradient-to-r from-blue-600 via-blue-700 to-indigo-700 text-white shadow-[0_6px_18px_rgba(37,99,235,0.3)] ring-2 ring-blue-400/30 -translate-y-0.5' 
                                                : 'border-slate-200/90 bg-slate-50/70 hover:bg-white text-slate-700 hover:border-blue-300 hover:shadow-md hover:-translate-y-0.5'"
                                            class="flex items-center justify-between px-3.5 py-2.5 rounded-xl border-2 transition-all duration-200 cursor-pointer group/chip select-none">
                                            
                                            <div class="flex flex-col min-w-0 pr-2">
                                                <span class="text-[11px] font-black tracking-wide truncate transition-colors"
                                                    :class="form.permissions.includes(parseInt(perm.id)) ? 'text-white' : 'text-slate-800 group-hover/chip:text-blue-900'"
                                                    x-text="(perm.name.split('.')[1] || perm.name).toUpperCase()"></span>
                                                <span class="text-[9px] font-bold font-mono truncate mt-0.5"
                                                    :class="form.permissions.includes(parseInt(perm.id)) ? 'text-blue-100' : 'text-slate-400'"
                                                    x-text="perm.name"></span>
                                            </div>

                                            <!-- 3D Tactile Check Circle -->
                                            <div class="w-5 h-5 rounded-lg flex items-center justify-center shrink-0 transition-all font-black text-[10px]"
                                                :class="form.permissions.includes(parseInt(perm.id)) 
                                                    ? 'bg-white text-blue-600 shadow-md scale-105' 
                                                    : 'border-2 border-slate-300 bg-white text-transparent group-hover/chip:border-blue-400'">
                                                <i class="fa-solid fa-check"></i>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                        </div>
                    </template>
                </div>
            </div>

            <!-- Bottom Action & Synchronization Bar (100% Full Width) -->
            <div class="w-full bg-white rounded-2xl shadow-sm border-2 border-slate-200/90 p-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-sm border border-blue-200">
                        <i class="fas fa-shield-halved"></i>
                    </span>
                    <div>
                        <p class="text-xs font-black text-navy-900 uppercase tracking-wider">Capability Matrix Synchronization</p>
                        <p class="text-[11px] font-bold text-slate-500">Authority points apply immediately across the network to all active identities upon synchronization.</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" @click="pageMode = 'index'" class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-black text-xs uppercase tracking-wider transition-all active:scale-95 cursor-pointer border border-slate-200">
                        Cancel
                    </button>
                    <button type="button" @click="submitForm()" :disabled="saving" class="px-8 py-2.5 bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-500 hover:to-indigo-600 text-white rounded-xl font-black text-xs uppercase tracking-wider shadow-xl shadow-blue-500/25 hover:-translate-y-0.5 active:scale-95 transition-all flex items-center gap-2 cursor-pointer border border-blue-400/30">
                        <span x-show="!saving" class="flex items-center gap-2">
                            <i class="fas fa-check-to-slot"></i>
                            <span>Save Changes</span>
                        </span>
                        <span x-show="saving" class="flex items-center gap-2">
                            <i class="fas fa-circle-notch animate-spin"></i>
                            <span>Harmonizing...</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>




        <!-- Modals moved to bottom for stacking priority -->
        <div>
            <!-- View Capabilities Modal (Modernized) -->
            <div x-show="showViewModal" class="fixed inset-0 z-[10000] overflow-y-auto px-4 py-6" x-transition.opacity x-cloak>
                <div class="flex items-center justify-center min-h-screen">
                    <div class="fixed inset-0 bg-slate-900/90 backdrop-blur-md z-[9999]" @click="showViewModal = false"></div>
                    
                    <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-4xl z-[10000] overflow-hidden relative border border-slate-100" x-transition.scale.origin.center>
                    <!-- Header with Dynamic Gradient -->
                    <div class="bg-gradient-to-br from-indigo-600 via-blue-700 to-blue-900 p-10 text-white relative overflow-hidden">
                        <div class="absolute inset-0 bg-grid-white/[0.08] bg-[size:20px_20px]"></div>
                        <div class="absolute -right-20 -top-20 w-80 h-80 bg-white/10 rounded-full blur-3xl"></div>
                        
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-8">
                                <div class="w-20 h-20 rounded-[1.5rem] bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30 shadow-2xl">
                                    <i class="fas fa-shield-halved text-4xl text-white drop-shadow-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-4xl font-black tracking-tight" x-text="viewingItem ? viewingItem.display_name : 'Role Details'"></h3>
                                    <div class="flex items-center gap-4 mt-3">
                                        <span class="px-3 py-1 bg-white/20 rounded-lg text-[10px] font-black uppercase tracking-widest border border-white/20" x-text="viewingItem ? viewingItem.name : ''"></span>
                                        <div class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse"></div>
                                        <p class="text-blue-100 text-xs font-bold uppercase tracking-widest" x-text="viewingItem ? (viewingItem.permissions ? viewingItem.permissions.length : 0) + ' active logic points' : ''"></p>
                                    </div>
                                </div>
                            </div>
                            <button @click="showViewModal = false" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white/10 hover:bg-white/20 transition-all border border-white/10 group">
                                <i class="fas fa-times text-xl group-hover:rotate-90 transition-transform duration-300"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-10 bg-slate-50/50">
                        <!-- Summary Cards -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Authority Bound</div>
                                <div class="text-xl font-bold text-blue-900 uppercase" x-text="viewingItem ? viewingItem.scope_level : 'N/A'"></div>
                            </div>
                            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Integration State</div>
                                <div :class="viewingItem && viewingItem.status === 'active' ? 'text-emerald-600' : 'text-rose-600'" class="text-xl font-bold uppercase" x-text="viewingItem ? viewingItem.status : 'N/A'"></div>
                            </div>
                            <div class="bg-white p-6 rounded-3xl border border-slate-100 shadow-sm">
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Direct Bindings</div>
                                <div class="text-xl font-bold text-indigo-600" x-text="viewingItem ? (viewingItem.permissions ? viewingItem.permissions.length : 0) + ' Points' : '0'"></div>
                            </div>
                        </div>

                        <label class="block text-xs font-black text-blue-900 uppercase tracking-[0.2em] mb-6 flex items-center gap-2">
                            <i class="fas fa-microchip text-blue-500"></i> Logic Matrix Distribution
                        </label>

                        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 max-h-[500px] overflow-y-auto pr-4 custom-scrollbar">
                            <template x-for="(perms, module) in (viewingItem ? groupPermissions(viewingItem.permissions) : {})" :key="module">
                                <div class="bg-white rounded-3xl p-6 border border-slate-100 shadow-sm hover:border-blue-200 transition-colors">
                                    <h5 class="text-[10px] font-black text-blue-800 uppercase tracking-[0.15em] mb-5 pb-3 border-b border-slate-100" x-text="module + ' Module'"></h5>
                                    <div class="flex flex-wrap gap-2">
                                        <template x-for="perm in perms" :key="perm.id">
                                            <span class="px-3 py-2 bg-blue-50/50 border border-blue-100/50 text-blue-700 font-bold text-[10px] rounded-xl flex items-center gap-2 transition-all hover:bg-blue-100">
                                                <i class="fas fa-check-circle text-blue-500 text-[8px]"></i>
                                                <span x-text="perm.name.split('.')[1] || perm.name"></span>
                                            </span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                        
                        <div class="mt-10 pt-8 border-t border-slate-200 flex justify-end">
                            <button @click="showViewModal = false" class="px-12 py-5 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-2xl font-black text-xs uppercase tracking-widest hover:shadow-2xl hover:shadow-blue-500/30 transition-all active:scale-95 shadow-xl">Close Definition</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Role Details Modal (Focused for Add/Edit Basic Info) -->
        <div x-show="showDetailsModal" class="fixed inset-0 z-[10000] overflow-y-auto px-4 py-6" x-transition.opacity x-cloak>
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-md z-[9999]" @click="showDetailsModal = false"></div>
                
                <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-xl z-[10000] overflow-hidden relative border border-slate-100" x-transition.scale.origin.center>
                    <div class="bg-gradient-to-br from-blue-600 to-indigo-800 p-8 text-white relative">
                        <div class="absolute inset-0 bg-grid-white/[0.08] bg-[size:16px_16px]"></div>
                        <div class="flex items-center justify-between relative z-10">
                            <div class="flex items-center gap-5">
                                <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center border border-white/30 shadow-xl">
                                    <i class="fas fa-id-card text-2xl text-white"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-black tracking-tight" x-text="modalMode === 'add' ? 'Establish New Identity' : 'Update Identity Parameters'"></h3>
                                    <p class="text-blue-100 text-[10px] font-black uppercase tracking-[0.2em] mt-1" x-text="modalMode === 'add' ? 'Initializing role protocol' : 'Refining authority bounds'"></p>
                                </div>
                            </div>
                            <button @click="showDetailsModal = false" class="w-10 h-10 flex items-center justify-center rounded-xl bg-white/10 hover:bg-white/20 transition-all border border-white/10">
                                <i class="fas fa-times text-lg"></i>
                            </button>
                        </div>
                    </div>

                    <div class="p-8 space-y-6">
                        <div class="space-y-6">
                            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-3 ml-1">Identity Display Name</label>
                                <input type="text" x-model="form.display_name" class="w-full bg-white border-slate-200 rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:ring-blue-600 focus:border-blue-600 transition-all shadow-sm" placeholder="e.g. Operation Commander">
                            </div>
                            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-3 ml-1">System Machine Name</label>
                                <input type="text" x-model="form.name" :disabled="modalMode === 'edit'" class="w-full bg-white border-slate-200 rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:ring-blue-600 focus:border-blue-600 transition-all shadow-sm disabled:opacity-50" placeholder="e.g. operation_commander">
                            </div>
                            <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100">
                                <label class="text-[10px] font-black text-slate-400 uppercase tracking-widest block mb-3 ml-1">Geospatial Scope</label>
                                <select x-model="form.scope_level" class="w-full bg-white border-slate-200 rounded-2xl py-4 px-6 text-sm font-bold text-slate-700 focus:ring-blue-600 focus:border-blue-600 transition-all shadow-sm appearance-none cursor-pointer">
                                    <option value="national">National (Full Access)</option>
                                    <option value="zone">Zone (Regional)</option>
                                    <option value="sector">Sector (Granular)</option>
                                    <option value="beat">Beat (Operational)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="pt-6 border-t border-slate-100 flex justify-end gap-3">
                            <button @click="showDetailsModal = false" class="px-8 py-4 bg-slate-100 text-slate-600 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:bg-slate-200 transition-all">Cancel</button>
                            <button @click="submitForm()" :disabled="saving" class="px-10 py-4 bg-gradient-to-r from-blue-600 to-indigo-700 text-white rounded-2xl font-black text-[10px] uppercase tracking-widest hover:shadow-lg hover:shadow-blue-500/30 transition-all active:scale-95 flex items-center gap-2">
                                <span x-show="!saving" x-text="modalMode === 'add' ? 'Create Role' : 'Save Details'"></span>
                                <span x-show="saving"><i class="fas fa-circle-notch animate-spin"></i> Processing...</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MFA Policy Enforcement Modal -->
        <div x-show="showMfaModal" class="fixed inset-0 z-[10000] overflow-y-auto px-4 py-6" x-transition.opacity x-cloak>
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="closeMfaModal()"></div>
            <div class="flex min-h-full items-center justify-center p-4">
                <div class="relative bg-white rounded-[2rem] shadow-2xl border border-slate-100 max-w-lg w-full p-8 text-left overflow-hidden animate-scale-up">
                    <div class="flex items-center gap-3.5 pb-5 border-b border-slate-100">
                        <div class="w-12 h-12 rounded-2xl bg-blue-50 border border-blue-100 text-blue-600 flex items-center justify-center text-xl shadow-sm shrink-0">
                            <i class="fa-solid fa-shield-halved"></i>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400">Security Governance</span>
                            <h3 class="text-lg font-black text-blue-900">Role 2FA Policy</h3>
                            <p class="text-xs text-slate-500 font-medium" x-text="selectedMfaRole ? selectedMfaRole.display_name + ' (' + selectedMfaRole.name + ')' : ''"></p>
                        </div>
                    </div>

                    <form @submit.prevent="submitRoleMfa()" class="mt-6 space-y-4">
                        <div class="p-4 rounded-xl border text-xs" :class="mfaTargetRequired ? 'bg-emerald-50/70 border-emerald-200 text-emerald-900' : 'bg-amber-50/70 border-amber-200 text-amber-900'">
                            <p class="font-bold flex items-center gap-2">
                                <i :class="mfaTargetRequired ? 'fa-solid fa-circle-check text-emerald-600' : 'fa-solid fa-triangle-exclamation text-amber-600'"></i>
                                <span x-text="mfaTargetRequired ? 'Enforce Two-Factor Authentication' : 'Set Operational Exemption'"></span>
                            </p>
                            <p class="mt-1 opacity-90 leading-relaxed font-medium" x-text="mfaTargetRequired ? 'All staff assigned to this role will be strictly required to configure and verify 2FA upon sign in.' : 'Staff assigned to this role will be permitted to sign in with their username and password only.'"></p>
                        </div>

                        <div class="space-y-1.5" x-show="!mfaTargetRequired">
                            <label class="block text-xs font-bold text-slate-700">
                                Reason for Exemption <span class="text-red-500">*</span>
                            </label>
                            <textarea x-model="mfaReason" rows="3" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500 font-medium p-3" placeholder="State operational reason (e.g., Highway dispatch patrol deployment, temporary wireless console coverage)..."></textarea>
                        </div>

                        <div class="space-y-1.5" x-show="!mfaTargetRequired">
                            <label class="block text-xs font-bold text-slate-700">Scheduled Re-Enable Date (Optional)</label>
                            <input type="datetime-local" x-model="mfaScheduledDate" class="w-full rounded-xl border-slate-200 text-xs focus:ring-blue-500 focus:border-blue-500 p-2.5">
                        </div>

                        <div class="pt-5 border-t border-slate-100 flex items-center justify-end gap-3">
                            <button type="button" @click="closeMfaModal()" class="px-5 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 font-bold text-xs transition-all">Cancel</button>
                            <button type="submit" :disabled="(!mfaTargetRequired && !mfaReason.trim()) || mfaSaving" :class="mfaTargetRequired ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-amber-500 hover:bg-amber-600 text-white'" class="px-6 py-2.5 rounded-xl font-black text-xs uppercase tracking-wider transition-all shadow-md disabled:opacity-40 flex items-center gap-2">
                                <i x-show="mfaSaving" class="fas fa-circle-notch animate-spin text-xs"></i>
                                <span x-text="mfaTargetRequired ? 'Enforce 2FA' : 'Confirm Exemption'"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @include('components.confirm-modal')
        </div>

    </div>

    @push('scripts')
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f8fafc; 
            border-radius: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1; 
            border-radius: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8; 
        }
    </style>
    <script>
        (function() {
            window.roleManager = function(config) {
                return {
                    // Core State
                    items: config.items || [],
                    permissions: config.permissions || {},
                    pageMode: 'index', // 'index' or 'permissions'
                    
                    // UI State
                    search: '',
                    filterStatus: '',
                    density: 'spacious',
                    viewMode: 'table',
                    sortBy: 'display_name',
                    sortDirection: 'asc',
                    page: 1,
                    perPage: 9,
                    showSidebar: false,
                    saving: false,
                    activeModuleTab: 'all',
                    matrixSearch: '',
                    
                    // Modals State
                    showViewModal: false,
                    showDetailsModal: false,
                    showConfirmModal: false,
                    showMfaModal: false,
                    selectedMfaRole: null,
                    mfaTargetRequired: true,
                    mfaReason: '',
                    mfaScheduledDate: '',
                    mfaSaving: false,
                    modalMode: 'add', // 'add' or 'edit'
                    viewingItem: null,
                    selectedItem: null,
                    confirmLoading: false,
                    confirmConfig: { title: '', message: '', icon: '', isDanger: false, action: null },
                    
                    // Form Data
                    form: {
                        id: null,
                        name: '',
                        display_name: '',
                        scope_level: 'national',
                        permissions: []
                    },

                    // Computed Getters
                    get filteredItems() {
                        let filtered = this.items.filter(item => {
                            const searchLower = (this.search || '').toLowerCase();
                            const matchesSearch = (item.display_name || '').toLowerCase().includes(searchLower) || 
                                                 (item.name || '').toLowerCase().includes(searchLower);
                            
                            let matchesStatus = true;
                            if (this.filterStatus === 'active') {
                                matchesStatus = item.status === 'active';
                            } else if (this.filterStatus === 'matrix') {
                                matchesStatus = item.permissions && item.permissions.length > 0;
                            }
                            
                            return matchesSearch && matchesStatus;
                        });

                        return filtered.sort((a, b) => {
                            let fieldA = (a[this.sortBy] || '').toString().toLowerCase();
                            let fieldB = (b[this.sortBy] || '').toString().toLowerCase();
                            if (fieldA < fieldB) return this.sortDirection === 'asc' ? -1 : 1;
                            if (fieldA > fieldB) return this.sortDirection === 'asc' ? 1 : -1;
                            return 0;
                        });
                    },

                    get totalPages() {
                        return Math.ceil(this.filteredItems.length / this.perPage);
                    },

                    get pagedItems() {
                        if (this.page > this.totalPages) this.page = 1;
                        const start = (this.page - 1) * this.perPage;
                        return this.filteredItems.slice(start, start + this.perPage);
                    },

                    // Helper Methods
                    getSortIcon(field) {
                        if (this.sortBy !== field) return 'fa-sort';
                        return this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
                    },

                    sortByField(field) {
                        if (this.sortBy === field) {
                            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
                        } else {
                            this.sortBy = field;
                            this.sortDirection = 'asc';
                        }
                        this.page = 1;
                    },

                    resetForm() {
                        this.form = { id: null, name: '', display_name: '', scope_level: 'national', permissions: [] };
                    },

                    groupPermissions(perms) {
                        if (!perms) return {};
                        return perms.reduce((acc, p) => {
                            const mod = p.name.split('.')[0] || 'other';
                            if (!acc[mod]) acc[mod] = [];
                            acc[mod].push(p);
                            return acc;
                        }, {});
                    },

                    allModuleSelected(perms) {
                        if (!perms || perms.length === 0) return false;
                        return perms.every(p => this.form.permissions.includes(parseInt(p.id)));
                    },

                    moduleActiveCount(perms) {
                        if (!perms) return 0;
                        return perms.filter(p => this.form.permissions.includes(parseInt(p.id))).length;
                    },

                    toggleModule(perms) {
                        if (this.allModuleSelected(perms)) {
                            this.deselectModule(perms);
                        } else {
                            this.selectModule(perms);
                        }
                    },

                    selectModule(perms) {
                        if (!perms) return;
                        const permIds = perms.map(p => parseInt(p.id));
                        permIds.forEach(id => {
                            if (!this.form.permissions.includes(id)) {
                                this.form.permissions.push(id);
                            }
                        });
                    },

                    deselectModule(perms) {
                        if (!perms) return;
                        const permIds = perms.map(p => parseInt(p.id));
                        this.form.permissions = this.form.permissions.filter(id => !permIds.includes(id));
                    },

                    togglePermission(id) {
                        const numId = parseInt(id);
                        const index = this.form.permissions.indexOf(numId);
                        if (index > -1) {
                            this.form.permissions.splice(index, 1);
                        } else {
                            this.form.permissions.push(numId);
                        }
                    },

                    selectAllPermissions() {
                        const allIds = [];
                        Object.values(this.permissions).forEach(perms => {
                            perms.forEach(p => allIds.push(parseInt(p.id)));
                        });
                        this.form.permissions = allIds;
                    },

                    clearMatrix() {
                        this.form.permissions = [];
                    },

                    allPermissionsSelected() {
                        const allIds = [];
                        Object.values(this.permissions).forEach(perms => {
                            perms.forEach(p => allIds.push(parseInt(p.id)));
                        });
                        return allIds.length > 0 && allIds.every(id => this.form.permissions.includes(id));
                    },

                    get totalPermissionsCount() {
                        let count = 0;
                        Object.values(this.permissions).forEach(perms => {
                            count += perms.length;
                        });
                        return count;
                    },

                    get boundPercentage() {
                        if (this.totalPermissionsCount === 0) return 0;
                        return Math.round((this.form.permissions.length / this.totalPermissionsCount) * 100);
                    },

                    filteredModules() {
                        let result = {};
                        const search = (this.matrixSearch || '').toLowerCase().trim();
                        const targetModule = this.activeModuleTab;

                        Object.entries(this.permissions).forEach(([moduleName, perms]) => {
                            if (targetModule !== 'all' && moduleName !== targetModule) {
                                return;
                            }
                            if (!search) {
                                result[moduleName] = perms;
                                return;
                            }
                            const matched = perms.filter(p => 
                                (p.name || '').toLowerCase().includes(search) || 
                                (p.display_name || '').toLowerCase().includes(search)
                            );
                            if (matched.length > 0) {
                                result[moduleName] = matched;
                            }
                        });
                        return result;
                    },

                    // Action Triggers
                    addRole() {
                        this.showViewModal = false;
                        this.showMfaModal = false;
                        this.showConfirmModal = false;
                        this.resetForm();
                        this.modalMode = 'add';
                        this.showDetailsModal = true;
                    },

                    editDetails(item) {
                        this.showViewModal = false;
                        this.showMfaModal = false;
                        this.showConfirmModal = false;
                        this.form = { 
                            id: item.id,
                            name: item.name,
                            display_name: item.display_name,
                            scope_level: item.scope_level || 'national',
                            permissions: item.permissions ? item.permissions.map(p => p.id) : [] 
                        };
                        this.modalMode = 'edit';
                        this.showDetailsModal = true;
                    },

                    editItem(item) {
                        this.editDetails(item);
                    },

                    configurePermissions(item) {
                        this.showViewModal = false;
                        this.showDetailsModal = false;
                        this.showMfaModal = false;
                        this.showConfirmModal = false;
                        this.activeModuleTab = 'all';
                        this.matrixSearch = '';
                        this.form = { 
                            id: item.id,
                            name: item.name,
                            display_name: item.display_name,
                            scope_level: item.scope_level || 'national',
                            permissions: item.permissions ? item.permissions.map(p => parseInt(p.id)) : [] 
                        };
                        this.pageMode = 'permissions';
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    },

                    viewItem(item) {
                        this.showDetailsModal = false;
                        this.showMfaModal = false;
                        this.showConfirmModal = false;
                        this.viewingItem = item;
                        this.showViewModal = true;
                    },

                    confirmStatus(item) {
                        this.showViewModal = false;
                        this.showDetailsModal = false;
                        this.showMfaModal = false;
                        this.selectedItem = item;
                        const willBeActive = item.status !== 'active';
                        this.confirmConfig = {
                            title: willBeActive ? 'Activate Role Point' : 'Deactivate Role Point',
                            message: willBeActive 
                                ? `Are you sure you want to activate the <strong>${item.display_name}</strong> authority layer? Linked identities will regain capabilities.`
                                : `Are you sure you want to lock the <strong>${item.display_name}</strong> authority layer? All identities utilizing this role will immediately lose associated capabilities.`,
                            icon: 'fa-power-off',
                            isDanger: !willBeActive,
                            action: async () => {
                                try {
                                    const response = await fetch(`/mgmt/roles/${item.id}/toggle-status`, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                        }
                                    });
                                    const result = await response.json();
                                    if (result.success) {
                                        item.status = result.status;
                                        showSuccess(`Role module state updated to ${result.status}`);
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

                    openMfaModal(role) {
                        if (role.is_mfa_protected) return;
                        this.showViewModal = false;
                        this.showDetailsModal = false;
                        this.showConfirmModal = false;
                        this.selectedMfaRole = role;
                        this.mfaTargetRequired = !role.is_mfa_enforced;
                        this.mfaReason = '';
                        this.mfaScheduledDate = '';
                        this.mfaSaving = false;
                        this.showMfaModal = true;
                    },

                    closeMfaModal() {
                        this.showMfaModal = false;
                        this.selectedMfaRole = null;
                        this.mfaReason = '';
                        this.mfaScheduledDate = '';
                        this.mfaSaving = false;
                    },

                    async submitRoleMfa() {
                        if (!this.selectedMfaRole) return;
                        if (!this.mfaTargetRequired && !this.mfaReason.trim()) {
                            showError("Please state an operational reason for the exemption.");
                            return;
                        }

                        this.mfaSaving = true;
                        try {
                            const response = await fetch(`/mgmt/mfa-settings/roles/${this.selectedMfaRole.id}/toggle`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify({
                                    mfa_required: this.mfaTargetRequired ? 1 : 0,
                                    reason: this.mfaReason,
                                    scheduled_re_enable_at: this.mfaScheduledDate || null
                                })
                            });

                            const result = await response.json();
                            if (response.ok && result.success) {
                                this.selectedMfaRole.is_mfa_enforced = this.mfaTargetRequired;
                                const idx = this.items.findIndex(i => i.id === this.selectedMfaRole.id);
                                if (idx !== -1) {
                                    this.items[idx].is_mfa_enforced = this.mfaTargetRequired;
                                }
                                showSuccess(result.message);
                                this.closeMfaModal();
                            } else {
                                showError(result.message || "Failed to update 2FA policy.");
                            }
                        } catch (err) {
                            showError("Network error while updating 2FA policy.");
                        } finally {
                            this.mfaSaving = false;
                        }
                    },

                    confirmDelete(item) {
                        this.showViewModal = false;
                        this.showDetailsModal = false;
                        this.showMfaModal = false;
                        this.selectedItem = item;
                        this.confirmConfig = {
                            title: 'Purge Role Point',
                            message: `WARNING: You are about to completely purge the role <strong>${item.display_name}</strong>.<br><br>Any identities still bound to this role will lose their granted capabilities. This action cannot be undone.`,
                            icon: 'fa-trash-alt',
                            isDanger: true,
                            action: async () => {
                                try {
                                    const response = await fetch(`/mgmt/roles/${item.id}`, {
                                        method: 'DELETE',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                        }
                                    });
                                    const result = await response.json();
                                    if (response.ok || (result && result.success)) {
                                        this.items = this.items.filter(i => i.id !== item.id);
                                        showSuccess('Role execution matrix purged from root memory.');
                                    } else {
                                        showError("Matrix deletion failure");
                                    }
                                } catch (error) {
                                    showError("Matrix deletion failure");
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

                    async submitForm() {
                        this.saving = true;
                        // Force the data arrays to be numeric if possible.
                        this.form.permissions = (this.form.permissions || []).map(x => parseInt(x));
                        
                        const isModalSubmission = this.showDetailsModal;
                        const url = (this.modalMode === 'add' && isModalSubmission) ? '/mgmt/roles' : `/mgmt/roles/${this.form.id}`;
                        const method = (this.modalMode === 'add' && isModalSubmission) ? 'POST' : 'PUT';
                        
                        try {
                            const response = await fetch(url, {
                                method: method,
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                                },
                                body: JSON.stringify(this.form)
                            });
                            
                            const result = await response.json();
                            
                            if (response.ok) {
                                if (this.modalMode === 'add' && isModalSubmission) {
                                    this.items.unshift(result.role);
                                    showSuccess("Role successfully created");
                                } else {
                                    const index = this.items.findIndex(i => i.id === this.form.id);
                                    if (index !== -1) this.items[index] = result.role;
                                    showSuccess(isModalSubmission ? "Role details updated" : "Role configuration updated");
                                }
                                this.pageMode = 'index';
                                this.showDetailsModal = false;
                                this.resetForm();
                            } else {
                                if (result.errors) {
                                    Object.values(result.errors).forEach(err => showError(err[0]));
                                } else {
                                    showError(result.message || "Logic binding error");
                                }
                            }
                        } catch (error) {
                            showError("Grid communication failure");
                        } finally {
                            this.saving = false;
                        }
                    }
                };
            };
        })();
    </script>
    @endpush
@endsection

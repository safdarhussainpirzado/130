@extends('layouts.app')

@section('title', 'Audit Vault - NHMP 130')

@section('page-title', 'Audit Vault')

@section('content')

    <div class="py-12" x-data="{ showSidebar: false, viewMode: 'table' }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-8">

            <!-- Floating Sidebar Toggle -->
            <button @click="showSidebar = true"
                x-show="!showSidebar"
                x-transition:enter="transition ease-out duration-500 delay-100"
                x-transition:enter-start="translate-x-full opacity-0"
                x-transition:enter-end="translate-x-0 opacity-100"
                class="fixed top-1/2 right-0 -translate-y-1/2 z-40 bg-gradient-to-b from-blue-600 to-blue-800 text-white p-2.5 py-6 rounded-l-2xl shadow-[0_0_30px_-5px_rgba(37,99,235,0.4)] hover:shadow-[-5px_0_40px_-5px_rgba(37,99,235,0.7)] hover:pr-4 transition-all duration-300 flex flex-col items-center gap-4 cursor-pointer" title="Open Filters">
                <i class="fas fa-sliders-h drop-shadow-lg text-sm"></i>
                <span style="writing-mode: vertical-rl;" class="text-[9px] font-black uppercase tracking-[0.3em] rotate-180 drop-shadow-md text-blue-50">Audit Filters</span>
            </button>

            <!-- STATS CARDS -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mt-8">
                <div class="relative flex flex-col bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-2 transition-all duration-300">
                    <div class="absolute -top-6 left-6 h-14 w-14 grid place-items-center rounded-[1.25rem] bg-blue-600 shadow-[0_10px_20px_rgba(37,99,235,0.3)]">
                        <i class="fas fa-list-alt text-xl text-white"></i>
                    </div>
                    <div class="p-6 text-right pt-6">
                        <p class="text-[10px] font-black tracking-widest text-blue-500 uppercase mt-2">Total Events</p>
                        <h4 class="text-4xl font-extrabold text-blue-900">{{ $logs->total() }}</h4>
                    </div>
                    <div class="mx-6 mb-6 border-t border-slate-100 pt-3">
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tight">All Time Log Volume</span>
                        </div>
                    </div>
                </div>

                <div class="relative flex flex-col bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-2 transition-all duration-300">
                    <div class="absolute -top-6 left-6 h-14 w-14 grid place-items-center rounded-[1.25rem] bg-emerald-500 shadow-[0_10px_20px_rgba(16,185,129,0.3)]">
                        <i class="fas fa-plus-circle text-xl text-white"></i>
                    </div>
                    <div class="p-6 text-right pt-6">
                        <p class="text-[10px] font-black tracking-widest text-emerald-500 uppercase mt-2">Created</p>
                        <h4 class="text-4xl font-extrabold text-blue-900">{{ $logs->total() > 0 ? $logs->where('event', 'created')->count() : 0 }}</h4>
                    </div>
                    <div class="mx-6 mb-6 border-t border-slate-100 pt-3">
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tight">Creation Events</span>
                        </div>
                    </div>
                </div>

                <div class="relative flex flex-col bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-2 transition-all duration-300">
                    <div class="absolute -top-6 left-6 h-14 w-14 grid place-items-center rounded-[1.25rem] bg-amber-500 shadow-[0_10px_20px_rgba(245,158,11,0.3)]">
                        <i class="fas fa-pen text-xl text-white"></i>
                    </div>
                    <div class="p-6 text-right pt-6">
                        <p class="text-[10px] font-black tracking-widest text-amber-500 uppercase mt-2">Updated</p>
                        <h4 class="text-4xl font-extrabold text-blue-900">{{ $logs->total() > 0 ? $logs->where('event', 'updated')->count() : 0 }}</h4>
                    </div>
                    <div class="mx-6 mb-6 border-t border-slate-100 pt-3">
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tight">Modification Events</span>
                        </div>
                    </div>
                </div>

                <div class="relative flex flex-col bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-2 transition-all duration-300">
                    <div class="absolute -top-6 left-6 h-14 w-14 grid place-items-center rounded-[1.25rem] bg-rose-600 shadow-[0_10px_20px_rgba(225,29,72,0.3)]">
                        <i class="fas fa-trash-alt text-xl text-white"></i>
                    </div>
                    <div class="p-6 text-right pt-6">
                        <p class="text-[10px] font-black tracking-widest text-rose-500 uppercase mt-2">Deleted</p>
                        <h4 class="text-4xl font-extrabold text-blue-900">{{ $logs->total() > 0 ? $logs->where('event', 'deleted')->count() : 0 }}</h4>
                    </div>
                    <div class="mx-6 mb-6 border-t border-slate-100 pt-3">
                        <div class="flex items-center gap-2">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                            <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tight">Deletion Events</span>
                        </div>
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
                                        <i class="fas fa-box-archive text-3xl text-blue-600"></i>
                                    </div>
                                    <div class="relative z-10">
                                        <h2 class="text-2xl font-extrabold text-blue-900 tracking-tight flex items-center gap-3">
                                            Operational Telemetry <span class="text-lg font-bold text-slate-400">({{ $logs->total() }} records)</span>
                                        </h2>
                                        <p class="text-slate-500 text-sm font-bold mt-1">Complete chronological trace of all system mutations</p>
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-4 items-center">
                                    <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-sm">
                                        <span class="text-[9px] font-black text-slate-400 border-r border-slate-100 pr-2 uppercase font-mono">Rows</span>
                                        <span class="text-blue-600 text-[10px] font-black uppercase font-mono">{{ $logs->perPage() }}/Page</span>
                                    </div>

                                    <button @click="showSidebar = !showSidebar" class="w-12 h-12 flex items-center justify-center bg-white border border-slate-200 text-blue-600 rounded-xl hover:bg-blue-50 transition-colors shadow-sm" :title="showSidebar ? 'Hide Filters' : 'Show Filters'">
                                        <i class="fas" :class="showSidebar ? 'fa-eye-slash' : 'fa-filter'"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="overflow-x-auto shadow-inner bg-white rounded-3xl border border-slate-100">
                            <table class="w-full text-left border-collapse">
                                <thead class="bg-blue-50 border-b border-blue-100">
                                    <tr class="text-[10px] font-black text-slate-500 uppercase tracking-[0.2em]">
                                        <th class="px-6 py-5 whitespace-nowrap">Temporal Stamp</th>
                                        <th class="px-6 py-5 whitespace-nowrap">Subject / Entity</th>
                                        <th class="px-6 py-5 whitespace-nowrap">Action Matrix</th>
                                        <th class="px-6 py-5 whitespace-nowrap">Initiating Node</th>
                                        <th class="px-6 py-5 whitespace-nowrap">Registry Data</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($logs as $log)
                                    <tr class="hover:bg-blue-50/40 transition-colors group">
                                        <td class="px-6 py-5">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-blue-900 mb-0.5">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                                                <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest">{{ $log->created_at->diffForHumans() }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <div class="flex flex-col">
                                                <span class="text-xs font-black text-blue-600 uppercase tracking-tight">{{ class_basename($log->subject_type) }}</span>
                                                <span class="text-[9px] font-bold text-slate-400">ID: {{ $log->subject_id }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5">
                                            @php
                                                $eventColors = [
                                                    'created' => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                                    'updated' => 'bg-amber-50 text-amber-600 border-amber-100',
                                                    'deleted' => 'bg-rose-50 text-rose-600 border-rose-100',
                                                ];
                                                $color = $eventColors[$log->event] ?? 'bg-slate-50 text-slate-600 border-slate-100';
                                            @endphp
                                            <span class="px-3 py-1.5 rounded-full {{ $color }} border text-[9px] font-black uppercase tracking-widest">
                                                {{ $log->event }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-5">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2 h-2 rounded-full bg-blue-900"></div>
                                                <span class="text-xs font-bold text-slate-600 uppercase">{{ $log->causer->username ?? 'SYSTEM' }}</span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-5">
                                            <div class="max-w-xs overflow-hidden text-ellipsis whitespace-nowrap">
                                                <code class="text-[10px] bg-slate-50 p-2 rounded block border border-slate-100 text-slate-500 font-mono">
                                                    {{ json_encode($log->properties) }}
                                                </code>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="py-32 text-center bg-white">
                                            <div class="flex flex-col items-center">
                                                <i class="fa-solid fa-box-archive text-6xl text-slate-100 mb-6 animate-pulse"></i>
                                                <h3 class="text-xl font-black text-slate-300 uppercase tracking-widest">Archive Empty</h3>
                                                <p class="text-xs text-slate-400 mt-2">No audit events have been recorded yet</p>
                                            </div>
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        @if($logs->hasPages())
                        <div class="p-8 border-t border-slate-100 bg-slate-50/30 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="text-xs font-bold text-slate-400">
                                Showing <span class="text-blue-900">{{ $logs->firstItem() }}</span> to <span class="text-blue-900">{{ $logs->lastItem() }}</span> of <span class="text-blue-900">{{ $logs->total() }}</span> events
                            </div>
                            <div>
                                {{ $logs->links() }}
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Right Sidebar (Filter Panel) -->
                <div x-show="showSidebar" x-transition class="lg:col-span-3 pb-8 lg:sticky lg:top-4 lg:self-start lg:max-h-[calc(100vh-100px)] lg:overflow-y-auto" x-cloak>
                    <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 p-8 space-y-8">

                        <!-- Sidebar Header -->
                        <div class="flex items-center justify-between">
                            <div>
                                <h4 class="text-xs font-black text-blue-900 uppercase tracking-widest">Audit Filters</h4>
                                <p class="text-[9px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Refine event log</p>
                            </div>
                            <button @click="showSidebar = false" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-rose-100 text-slate-400 hover:text-rose-600 flex items-center justify-center transition-all">
                                <i class="fa-solid fa-xmark text-xs"></i>
                            </button>
                        </div>

                        <!-- Filter Form -->
                        <form method="GET" action="{{ route('admin.audit.index') }}" class="space-y-6">
                            <!-- Event Type Filter -->
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Event Type</label>
                                <select name="event" class="w-full rounded-xl border border-slate-200 text-xs font-bold text-blue-900 focus:ring-2 focus:ring-blue-100 focus:border-blue-300 p-3 bg-white">
                                    <option value="">All Events</option>
                                    <option value="created" {{ request('event') === 'created' ? 'selected' : '' }}>Created</option>
                                    <option value="updated" {{ request('event') === 'updated' ? 'selected' : '' }}>Updated</option>
                                    <option value="deleted" {{ request('event') === 'deleted' ? 'selected' : '' }}>Deleted</option>
                                </select>
                            </div>

                            <!-- Subject Type Filter -->
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Subject Type</label>
                                <input type="text" name="subject_type" value="{{ request('subject_type') }}"
                                    placeholder="e.g. User, Role, Beat…"
                                    class="w-full rounded-xl border border-slate-200 text-xs font-bold text-blue-900 placeholder:text-slate-300 focus:ring-2 focus:ring-blue-100 focus:border-blue-300 p-3 bg-white" />
                            </div>

                            <!-- Actor / Causer Filter -->
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Actor (Username)</label>
                                <input type="text" name="causer" value="{{ request('causer') }}"
                                    placeholder="e.g. admin, system…"
                                    class="w-full rounded-xl border border-slate-200 text-xs font-bold text-blue-900 placeholder:text-slate-300 focus:ring-2 focus:ring-blue-100 focus:border-blue-300 p-3 bg-white" />
                            </div>

                            <!-- Date From -->
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Date From</label>
                                <input type="date" name="from" value="{{ request('from') }}"
                                    class="w-full rounded-xl border border-slate-200 text-xs font-bold text-blue-900 focus:ring-2 focus:ring-blue-100 focus:border-blue-300 p-3 bg-white" />
                            </div>

                            <!-- Date To -->
                            <div class="space-y-2">
                                <label class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Date To</label>
                                <input type="date" name="to" value="{{ request('to') }}"
                                    class="w-full rounded-xl border border-slate-200 text-xs font-bold text-blue-900 focus:ring-2 focus:ring-blue-100 focus:border-blue-300 p-3 bg-white" />
                            </div>

                            <!-- Action Buttons -->
                            <div class="space-y-3 pt-4 border-t border-slate-100">
                                <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black uppercase tracking-widest transition-all active:scale-95 shadow-md shadow-blue-100">
                                    <i class="fas fa-search mr-2"></i> Apply Filters
                                </button>
                                <a href="{{ route('admin.audit.index') }}" class="rose-reset-btn w-full flex items-center justify-center gap-2 py-3 bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white rounded-xl text-xs font-black uppercase tracking-widest border border-rose-100 hover:border-rose-600 transition-all active:scale-95">
                                    <i class="fas fa-rotate-left text-xs"></i> Reset Filters
                                </a>
                            </div>
                        </form>

                        <!-- Quick Links -->
                        <div class="pt-4 border-t border-slate-100 space-y-3">
                            <h4 class="text-[9px] font-black text-slate-400 uppercase tracking-widest">Quick Links</h4>
                            <a href="{{ route('admin.carriageways.index') }}" class="flex items-center gap-4 p-4 rounded-2xl bg-blue-50 text-blue-600 border border-blue-100 hover:bg-blue-600 hover:text-white transition-all group active:scale-95 shadow-sm">
                                <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shadow-sm group-hover:bg-blue-500/20 transition-colors">
                                    <i class="fa-solid fa-road text-xs"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black uppercase tracking-widest">Highway Registry</span>
                                    <span class="text-[8px] font-bold opacity-60 uppercase">Manage Arterial Routes</span>
                                </div>
                            </a>
                            <a href="{{ route('admin.geospatial-markers.index') }}" class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 text-slate-600 border border-slate-100 hover:bg-slate-900 hover:text-white transition-all group active:scale-95 shadow-sm">
                                <div class="w-10 h-10 rounded-xl bg-white flex items-center justify-center shadow-sm group-hover:bg-slate-700 transition-colors">
                                    <i class="fa-solid fa-location-dot text-xs"></i>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-[10px] font-black uppercase tracking-widest">Geospatial Grid</span>
                                    <span class="text-[8px] font-bold opacity-60 uppercase">Operational KM Markers</span>
                                </div>
                            </a>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $data */
@endphp
@extends('layouts.app')

@section('title', 'Help Response-Time Report - NHMP 130')

@section('page-title', 'Help Response-Time Monitoring')

@section('content')
    <div x-data="reportFilters" class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Filters partial --}}
        @include('reports.partials.filters')

        <div id="report-content" class="space-y-6">
            <span id="report-has-data" data-value="{{ count($data) > 0 ? 'true' : 'false' }}" class="hidden"></span>

            {{-- KPI Summary Cards --}}
            @if(!$isInitial && count($data) > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
                {{-- Card 1: Total Records --}}
                <div class="bg-white rounded-[1.75rem] p-6 border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Total Helps Audited</p>
                            <h4 class="text-2xl font-black text-navy-900 mt-1">{{ number_format($summary['total_records'] ?? 0) }}</h4>
                            <p class="text-[11px] font-bold text-slate-500 mt-0.5">
                                <span class="text-emerald-600">{{ $summary['completed_count'] ?? 0 }}</span> Resolved &bull; 
                                <span class="text-amber-600">{{ $summary['active_count'] ?? 0 }}</span> In-Field
                            </p>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shadow-inner group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-headset"></i>
                        </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-blue-500 to-indigo-500"></div>
                </div>

                {{-- Card 2: Avg Tiger Response Time --}}
                <div class="bg-white rounded-[1.75rem] p-6 border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Avg Tiger Response Time</p>
                            <h4 class="text-2xl font-black text-sky-600 mt-1">{{ $summary['avg_response'] ?? '--' }}</h4>
                            <p class="text-[11px] font-bold text-slate-500 mt-0.5">Initiated &rarr; Tiger Reached</p>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl shadow-inner group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-truck-fast"></i>
                        </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-sky-400 to-sky-600"></div>
                </div>

                {{-- Card 3: Avg Assistance Duration --}}
                <div class="bg-white rounded-[1.75rem] p-6 border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Avg Assistance Duration</p>
                            <h4 class="text-2xl font-black text-emerald-600 mt-1">{{ $summary['avg_assistance'] ?? '--' }}</h4>
                            <p class="text-[11px] font-bold text-slate-500 mt-0.5">Tiger Reached &rarr; Resolved</p>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shadow-inner group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-handshake-angle"></i>
                        </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-400 to-emerald-600"></div>
                </div>

                {{-- Card 4: Avg Total Resolution Time --}}
                <div class="bg-white rounded-[1.75rem] p-6 border border-slate-100 shadow-sm relative overflow-hidden group hover:shadow-md transition-all">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">Avg Total Resolution Time</p>
                            <h4 class="text-2xl font-black text-indigo-600 mt-1">{{ $summary['avg_resolution'] ?? '--' }}</h4>
                            <p class="text-[11px] font-bold text-slate-500 mt-0.5">Help Created &rarr; Resolved</p>
                        </div>
                        <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shadow-inner group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-flag-checkered"></i>
                        </div>
                    </div>
                    <div class="absolute bottom-0 left-0 right-0 h-1 bg-gradient-to-r from-indigo-500 to-purple-600"></div>
                </div>
            </div>
            @endif

            {{-- Toolbar: Show Rows and Export Buttons --}}
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4" x-show="!initialEmpty">
                {{-- Records Per Page selection --}}
                <div class="flex items-center gap-3 bg-white px-4 py-2.5 rounded-2xl border border-slate-100 shadow-sm w-fit">
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Show</span>
                    <select name="per_page" form="filterForm" onchange="document.getElementById('filterForm').requestSubmit()" 
                            class="bg-slate-50 border-none rounded-xl px-3 py-1.5 text-xs font-black text-navy-900 focus:ring-2 focus:ring-emerald-500/20 transition-all cursor-pointer">
                        <option value="10" {{ request('per_page', 25) == 10 ? 'selected' : '' }}>10 Rows</option>
                        <option value="25" {{ request('per_page', 25) == 25 ? 'selected' : '' }}>25 Rows</option>
                        <option value="50" {{ request('per_page', 25) == 50 ? 'selected' : '' }}>50 Rows</option>
                        <option value="100" {{ request('per_page', 25) == 100 ? 'selected' : '' }}>100 Rows</option>
                    </select>
                </div>

                {{-- Export Buttons --}}
                <div class="flex justify-end gap-3" x-show="Array.isArray(reportData) ? reportData.length > 0 : (reportData && reportData.data && reportData.data.length > 0)">
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" id="export-excel" data-no-pjax
                       @click="exporting = 'excel'; setTimeout(() => exporting = null, 4000)"
                       :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': exporting }"
                       class="h-11 px-5 rounded-2xl bg-gradient-to-b from-emerald-500 to-emerald-600 border-b-4 border-emerald-800 text-white flex items-center justify-center font-black text-xs uppercase tracking-widest hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-lg shadow-emerald-500/30 gap-2">
                        <i class="fa-solid text-sm" :class="exporting === 'excel' ? 'fa-spinner fa-spin' : 'fa-file-excel'"></i> <span x-text="exporting === 'excel' ? 'Exporting...' : 'Excel'"></span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" id="export-pdf" data-no-pjax
                       @click="exporting = 'pdf'; setTimeout(() => exporting = null, 8000)"
                       :class="{ 'opacity-50 cursor-not-allowed pointer-events-none': exporting }"
                       class="h-11 px-5 rounded-2xl bg-gradient-to-b from-rose-500 to-rose-600 border-b-4 border-rose-800 text-white flex items-center justify-center font-black text-xs uppercase tracking-widest hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-lg shadow-rose-500/30 gap-2">
                        <i class="fa-solid text-sm" :class="exporting === 'pdf' ? 'fa-spinner fa-spin' : 'fa-file-pdf'"></i> <span x-text="exporting === 'pdf' ? 'Generating...' : 'PDF'"></span>
                    </a>
                </div>
            </div>

            {{-- Report Table Panel --}}
            <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden" x-show="!initialEmpty">
                {{-- Report Header --}}
                <div class="text-center py-5 border-b border-slate-200 bg-slate-50/50 space-y-1">
                    <h3 class="text-base font-black text-navy-900 tracking-tight uppercase leading-tight">Help Response-Time Monitoring Report</h3>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Tiger Arrival, Assistance Duration & Resolution Performance Auditing</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-slate-100 border-b-2 border-slate-300">
                                <th class="px-3 py-3 text-center font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 w-12">Sr. No</th>
                                <th x-show="visibleColumns.call_number" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Call ID</th>
                                <th x-show="visibleColumns.created_at" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Help Created</th>
                                <th x-show="visibleColumns.zone" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Zone</th>
                                <th x-show="visibleColumns.sector" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Sector</th>
                                <th x-show="visibleColumns.beat" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Beat</th>
                                <th x-show="visibleColumns.category_full" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Category</th>
                                <th x-show="visibleColumns.initiated_at" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Help Initiated</th>
                                <th x-show="visibleColumns.tiger_reached_at" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Tiger Reached</th>
                                <th x-show="visibleColumns.completed_at" class="px-3 py-3 text-left font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap">Help Completed</th>
                                <th x-show="visibleColumns.tiger_response_time" class="px-3 py-3 text-center font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap bg-sky-50/50">Tiger Response</th>
                                <th x-show="visibleColumns.assistance_duration" class="px-3 py-3 text-center font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap bg-emerald-50/50">Assistance Dur.</th>
                                <th x-show="visibleColumns.total_resolution_time" class="px-3 py-3 text-center font-black text-navy-900 text-xs uppercase tracking-wide border-r border-slate-200 whitespace-nowrap bg-indigo-50/50">Resolution Time</th>
                                <th x-show="visibleColumns.status" class="px-3 py-3 text-center font-black text-navy-900 text-xs uppercase tracking-wide whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data as $i => $row)
                            <tr class="{{ $i % 2 === 0 ? 'bg-white' : 'bg-slate-50/60' }} hover:bg-sky-50/40 transition-colors border-b border-slate-100">
                                {{-- Sr. No --}}
                                <td class="px-3 py-2.5 text-center border-r border-slate-200 text-slate-500 font-semibold text-xs">
                                    {{ $data->firstItem() ? ($data->firstItem() + $i) : ($i + 1) }}
                                </td>

                                {{-- Call ID --}}
                                <td x-show="visibleColumns.call_number" class="px-3 py-2.5 border-r border-slate-200 whitespace-nowrap">
                                    <a href="{{ route('calls.show', $row['id']) }}" target="_blank" class="font-mono font-black text-blue-600 hover:text-blue-800 hover:underline">
                                        #{{ $row['call_number'] }}
                                    </a>
                                </td>

                                {{-- Help Created --}}
                                <td x-show="visibleColumns.created_at" class="px-3 py-2.5 border-r border-slate-200 font-semibold text-slate-700 whitespace-nowrap text-xs">
                                    {{ $row['created_at'] }}
                                </td>

                                {{-- Zone --}}
                                <td x-show="visibleColumns.zone" class="px-3 py-2.5 border-r border-slate-200 font-semibold text-slate-700 whitespace-nowrap text-xs">
                                    {{ $row['zone'] }}
                                </td>

                                {{-- Sector --}}
                                <td x-show="visibleColumns.sector" class="px-3 py-2.5 border-r border-slate-200 font-semibold text-slate-700 whitespace-nowrap text-xs">
                                    {{ $row['sector'] }}
                                </td>

                                {{-- Beat --}}
                                <td x-show="visibleColumns.beat" class="px-3 py-2.5 border-r border-slate-200 font-semibold text-slate-700 whitespace-nowrap text-xs">
                                    <span class="px-2 py-0.5 rounded-lg bg-slate-100 font-mono font-bold text-slate-700">{{ $row['beat'] }}</span>
                                </td>

                                {{-- Category --}}
                                <td x-show="visibleColumns.category_full" class="px-3 py-2.5 border-r border-slate-200 font-semibold text-slate-700 whitespace-nowrap text-xs">
                                    {{ $row['category_full'] }}
                                </td>

                                {{-- Help Initiated --}}
                                <td x-show="visibleColumns.initiated_at" class="px-3 py-2.5 border-r border-slate-200 font-semibold text-slate-600 whitespace-nowrap text-xs">
                                    {{ $row['initiated_at'] }}
                                </td>

                                {{-- Tiger Reached --}}
                                <td x-show="visibleColumns.tiger_reached_at" class="px-3 py-2.5 border-r border-slate-200 whitespace-nowrap text-xs">
                                    @if($row['tiger_reached_at'] !== '---')
                                        <span class="font-semibold text-sky-700">{{ $row['tiger_reached_at'] }}</span>
                                    @else
                                        <span class="text-slate-400">---</span>
                                    @endif
                                </td>

                                {{-- Help Completed --}}
                                <td x-show="visibleColumns.completed_at" class="px-3 py-2.5 border-r border-slate-200 whitespace-nowrap text-xs">
                                    @if($row['completed_at'] !== '---')
                                        <span class="font-semibold text-emerald-700">{{ $row['completed_at'] }}</span>
                                    @else
                                        <span class="text-slate-400">---</span>
                                    @endif
                                </td>

                                {{-- Tiger Response Time --}}
                                <td x-show="visibleColumns.tiger_response_time" class="px-3 py-2.5 border-r border-slate-200 text-center font-mono font-bold text-xs bg-sky-50/30">
                                    @if($row['tiger_response_time'] !== '--')
                                        <span class="px-2 py-0.5 rounded-md bg-sky-100 text-sky-800 border border-sky-200">
                                            {{ $row['tiger_response_time'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">--</span>
                                    @endif
                                </td>

                                {{-- Assistance Duration --}}
                                <td x-show="visibleColumns.assistance_duration" class="px-3 py-2.5 border-r border-slate-200 text-center font-mono font-bold text-xs bg-emerald-50/30">
                                    @if($row['assistance_duration'] !== '--')
                                        <span class="px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 border border-emerald-200">
                                            {{ $row['assistance_duration'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">--</span>
                                    @endif
                                </td>

                                {{-- Total Resolution Time --}}
                                <td x-show="visibleColumns.total_resolution_time" class="px-3 py-2.5 border-r border-slate-200 text-center font-mono font-bold text-xs bg-indigo-50/30">
                                    @if($row['total_resolution_time'] !== '--')
                                        <span class="px-2 py-0.5 rounded-md bg-indigo-100 text-indigo-800 border border-indigo-200">
                                            {{ $row['total_resolution_time'] }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">--</span>
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td x-show="visibleColumns.status" class="px-3 py-2.5 text-center whitespace-nowrap">
                                    @php
                                        $statusClass = match(strtolower($row['status_raw'] ?? '')) {
                                            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                            'tiger_reached' => 'bg-sky-50 text-sky-700 border-sky-200',
                                            'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200',
                                            'pending' => 'bg-rose-50 text-rose-700 border-rose-200',
                                            default => 'bg-slate-50 text-slate-700 border-slate-200',
                                        };
                                    @endphp
                                    <span class="px-2.5 py-1 rounded-lg font-black text-[10px] uppercase tracking-wide border {{ $statusClass }}">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="14" class="px-4 py-16 text-center">
                                    <div class="inline-flex flex-col items-center">
                                        <div class="w-14 h-14 rounded-2xl bg-slate-50 text-slate-300 flex items-center justify-center text-2xl mb-3">
                                            <i class="fa-solid fa-stopwatch-20"></i>
                                        </div>
                                        <p class="font-bold text-slate-500 text-sm">No Help Response-Time records found</p>
                                        <p class="text-xs text-slate-400 mt-1">Adjust the date range, zones, or filter criteria above</p>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>

                        {{-- Footer Summary Row --}}
                        @if(count($data) > 0)
                        <tfoot>
                            <tr class="bg-slate-100 border-t-2 border-slate-400">
                                <td class="px-3 py-3 font-black text-navy-900 text-center border-r border-slate-200 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.call_number" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">AVERAGE / SUMMARY</td>
                                <td x-show="visibleColumns.created_at" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.zone" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.sector" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.beat" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.category_full" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.initiated_at" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.tiger_reached_at" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.completed_at" class="px-3 py-3 border-r border-slate-200 font-black text-navy-900 uppercase text-xs tracking-wide">-</td>
                                <td x-show="visibleColumns.tiger_response_time" class="px-3 py-3 border-r border-slate-200 text-center font-black text-sky-800 bg-sky-100/50 font-mono text-xs">
                                    {{ $summary['avg_response'] ?? '--' }}
                                </td>
                                <td x-show="visibleColumns.assistance_duration" class="px-3 py-3 border-r border-slate-200 text-center font-black text-emerald-800 bg-emerald-100/50 font-mono text-xs">
                                    {{ $summary['avg_assistance'] ?? '--' }}
                                </td>
                                <td x-show="visibleColumns.total_resolution_time" class="px-3 py-3 border-r border-slate-200 text-center font-black text-indigo-800 bg-indigo-100/50 font-mono text-xs">
                                    {{ $summary['avg_resolution'] ?? '--' }}
                                </td>
                                <td x-show="visibleColumns.status" class="px-3 py-3 text-center font-black text-navy-900 uppercase text-xs tracking-wide">
                                    Total: {{ number_format($summary['total_records'] ?? 0) }}
                                </td>
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>

                {{-- Pagination Panel --}}
                @if($data->hasPages())
                    <div class="p-8 border-t border-slate-100 bg-slate-50/30 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="text-xs font-bold text-slate-400">
                            Showing <span class="text-blue-900">{{ $data->firstItem() ?? 0 }}</span> to <span class="text-blue-900">{{ $data->lastItem() ?? 0 }}</span> of <span class="text-blue-900">{{ $data->total() }}</span> records
                        </div>
                        <div class="flex items-center gap-2">
                            <!-- First/Prev -->
                            <a href="{{ $data->url(1) }}" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 hover:bg-slate-50 transition-all font-black text-[10px] uppercase flex items-center justify-center {{ $data->onFirstPage() ? 'opacity-30 pointer-events-none' : '' }}">First</a>
                            <a href="{{ $data->previousPageUrl() ?? '#' }}" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition-all flex items-center justify-center {{ $data->onFirstPage() ? 'opacity-30 pointer-events-none' : '' }}"><i class="fa-solid fa-chevron-left"></i></a>

                            <!-- Page Numbers -->
                            @php
                                $currentPage = $data->currentPage();
                                $lastPage = $data->lastPage();
                                $start = max(1, $currentPage - 2);
                                $end = min($lastPage, $currentPage + 2);
                                if ($end - $start < 4) {
                                    if ($start == 1) {
                                        $end = min($lastPage, $start + 4);
                                    } else {
                                        $start = max(1, $end - 4);
                                    }
                                }
                            @endphp

                            @if($start > 1)
                                <a href="{{ $data->url(1) }}" class="bg-white text-slate-600 border-slate-200 hover:bg-slate-50 w-10 h-10 rounded-xl border font-black text-xs transition-all flex items-center justify-center">1</a>
                                @if($start > 2)
                                    <span class="px-2 text-slate-300 font-bold">...</span>
                                @endif
                            @endif

                            @for($p = $start; $p <= $end; $p++)
                                <a href="{{ $data->url($p) }}" class="w-10 h-10 rounded-xl border font-black text-xs transition-all flex items-center justify-center {{ $currentPage === $p ? 'bg-blue-600 text-white border-blue-600 shadow-lg shadow-blue-500/30' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' }}">
                                    {{ $p }}
                                </a>
                            @endfor

                            @if($end < $lastPage)
                                @if($end < $lastPage - 1)
                                    <span class="px-2 text-slate-300 font-bold">...</span>
                                @endif
                                <a href="{{ $data->url($lastPage) }}" class="bg-white text-slate-600 border-slate-200 hover:bg-slate-50 w-10 h-10 rounded-xl border font-black text-xs transition-all flex items-center justify-center">{{ $lastPage }}</a>
                            @endif

                            <!-- Next/Last -->
                            <a href="{{ $data->nextPageUrl() ?? '#' }}" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 transition-all flex items-center justify-center {{ !$data->hasMorePages() ? 'opacity-30 pointer-events-none' : '' }}"><i class="fa-solid fa-chevron-right"></i></a>
                            <a href="{{ $data->url($lastPage) }}" class="w-10 h-10 rounded-xl border border-slate-200 bg-white text-slate-400 hover:bg-slate-50 transition-all font-black text-[10px] uppercase flex items-center justify-center {{ !$data->hasMorePages() ? 'opacity-30 pointer-events-none' : '' }}">Last</a>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Initial Empty State --}}
            <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-12 text-center" x-show="initialEmpty">
                <div class="flex flex-col items-center justify-center space-y-4 max-w-md mx-auto">
                    <div class="w-16 h-16 rounded-2xl bg-blue-50 text-blue-500 flex items-center justify-center text-2xl shadow-inner">
                        <i class="fa-solid fa-stopwatch-20"></i>
                    </div>
                    <h3 class="text-lg font-black text-navy-900 uppercase tracking-tight">Help Response-Time Monitoring</h3>
                    <p class="text-sm text-slate-500 font-semibold leading-relaxed">
                        Please select your date range, zone/sector/beat, and filter criteria, then click <span class="text-blue-600 font-bold">"Generate Report"</span> to analyze Tiger response benchmarks and assistance durations.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection

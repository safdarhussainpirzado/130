@extends('layouts.app')

@section('title', 'Junk Calls Frequency - NHMP 130')

@section('page-title', 'Junk Calls Frequency')

@section('content')
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12">
        <!-- ── Filters ─────────────────────────────────────────────── -->
        @include('reports.partials.filters')

        <div id="report-content">
            <div class="flex justify-end gap-3 mb-6">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" id="export-excel" class="h-12 px-6 rounded-xl bg-gradient-to-b from-emerald-400 to-emerald-500 border-b-4 border-emerald-700 text-white flex items-center justify-center font-black text-xs uppercase tracking-widest hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-lg shadow-emerald-500/30">
                    <i class="fa-solid fa-file-excel mr-2"></i> Excel
                </a>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" id="export-pdf" class="h-12 px-6 rounded-xl bg-gradient-to-b from-rose-400 to-rose-500 border-b-4 border-rose-700 text-white flex items-center justify-center font-black text-xs uppercase tracking-widest hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-lg shadow-rose-500/30">
                    <i class="fa-solid fa-file-pdf mr-2"></i> PDF
                </a>
            </div>

            <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden p-8">
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h3 class="text-2xl font-extrabold text-navy-900 tracking-tight">Agent-Wise Junk Analysis</h3>
                        <p class="text-sm font-medium text-slate-500 mt-1">Review the frequency of Junk/Silent calls handled by personnel</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                            @if(isset($visibleColumns['username'])) <th class="p-6">Agent Identity</th> @endif
                            @if(isset($visibleColumns['total_calls'])) <th class="p-6 text-center">Total Volume</th> @endif
                            @if(isset($visibleColumns['junk_count'])) <th class="p-6 text-center">Junk Volume</th> @endif
                            @if(isset($visibleColumns['percentage'])) <th class="p-6">Junk Percentage</th> @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse($data as $row)
                        <tr class="hover:bg-rose-50/50 transition-all group">
                            @if(isset($visibleColumns['username']))
                            <td class="p-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-slate-100 to-slate-200 flex items-center justify-center text-slate-600 font-black shadow-sm uppercase">
                                        {{ substr($row['username'], 0, 2) }}
                                    </div>
                                    <div class="font-extrabold text-navy-900 uppercase tracking-tight">{{ $row['username'] }}</div>
                                </div>
                            </td>
                            @endif

                            @if(isset($visibleColumns['total_calls']))
                            <td class="p-6 text-center">
                                <span class="text-sm font-bold text-slate-500">{{ $row['total_calls'] }}</span>
                            </td>
                            @endif

                            @if(isset($visibleColumns['junk_count']))
                            <td class="p-6 text-center">
                                <span class="px-4 py-1.5 bg-rose-50 text-rose-600 font-black rounded-xl text-xs shadow-sm border border-rose-100">{{ $row['junk_count'] }}</span>
                            </td>
                            @endif

                            @if(isset($visibleColumns['percentage']))
                            <td class="p-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden w-24 shadow-inner">
                                        <div class="h-full bg-gradient-to-r from-rose-400 to-rose-600 transition-all duration-1000" style="width: {{ $row['percentage'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-black text-rose-600">{{ $row['percentage'] }}%</span>
                                </div>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="p-12 text-center">
                                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-slate-50 text-slate-300 mb-4 text-2xl">
                                    <i class="fa-solid fa-ghost"></i>
                                </div>
                                <h3 class="text-lg font-bold text-slate-600">No data found</h3>
                                <p class="text-sm text-slate-400 mt-1">Adjust filters to see junk call analytics.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
@endsection

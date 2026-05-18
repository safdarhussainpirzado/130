@extends('layouts.app')

@section('title', 'Agent Performance - NHMP 130')

@section('page-title', 'Agent Performance')

@section('content')
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12">
        <!-- ── Filters ─────────────────────────────────────────────── -->
        @include('reports.partials.filters')

        <!-- ── Report Table ────────────────────────────────────────── -->
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
                <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                            @if(isset($visibleColumns['username'])) <th class="p-6">Agent Identity</th> @endif
                            @if(isset($visibleColumns['total'])) <th class="p-6">Logged Executions</th> @endif
                            @if(isset($visibleColumns['avg_duration'])) <th class="p-6">Avg Handle Time</th> @endif
                            @if(isset($visibleColumns['efficiency'])) <th class="p-6">Efficiency Matrix</th> @endif
                            <th class="p-6 text-center">Controls</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($data as $row)
                        <tr class="hover:bg-emerald-50/50 transition-all group">
                            @if(isset($visibleColumns['username']))
                            <td class="p-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-100 to-emerald-200 flex items-center justify-center text-emerald-700 font-black shadow-sm uppercase">
                                        {{ substr($row['username'], 0, 2) }}
                                    </div>
                                    <div class="font-extrabold text-navy-900 uppercase tracking-tight">{{ $row['username'] }}</div>
                                </div>
                            </td>
                            @endif

                            @if(isset($visibleColumns['total']))
                            <td class="p-6">
                                <span class="px-4 py-1.5 bg-emerald-50 text-emerald-700 font-black rounded-xl text-xs shadow-sm shadow-emerald-500/10">{{ $row['total'] }} logs</span>
                            </td>
                            @endif

                            @if(isset($visibleColumns['avg_duration']))
                            <td class="p-6">
                                <div class="text-sm font-bold text-slate-600">{{ round($row['avg_duration'] / 60, 1) }} <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Minutes</span></div>
                            </td>
                            @endif

                            @if(isset($visibleColumns['efficiency']))
                            <td class="p-6">
                                <div class="flex items-center gap-2">
                                    @php $efficiency = min(100, (1 / (max(1, $row['avg_duration'] / 300))) * 100); @endphp
                                    <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden w-24">
                                        <div class="h-full bg-emerald-500" style="width: {{ $efficiency }}%"></div>
                                    </div>
                                    <span class="text-[10px] font-black text-emerald-600">{{ round($efficiency) }}%</span>
                                </div>
                            </td>
                            @endif

                            <td class="p-6 text-center">
                                <button class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-emerald-600 hover:border-emerald-400 transition-all active:scale-95 shadow-sm">
                                    <i class="fa-solid fa-user-shield"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
@endsection

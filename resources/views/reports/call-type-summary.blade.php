@extends('layouts.app')

@section('title', 'Call Type Summary - NHMP 130')

@section('page-title', 'Call Type Summary')

@section('content')
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12">
        <!-- ── Filters ─────────────────────────────────────────────── -->
        @include('reports.partials.filters')

        <div id="report-content">
            <div class="flex justify-end gap-3 mb-6">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" id="export-excel" class="h-12 px-6 rounded-xl bg-gradient-to-b from-emerald-400 to-emerald-500 border-b-4 border-emerald-700 text-white flex items-center justify-center font-black text-xs uppercase tracking-widest hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-lg shadow-emerald-500/30" title="Export Excel">
                    <i class="fa-solid fa-file-excel mr-2"></i> Excel
                </a>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" id="export-pdf" class="h-12 px-6 rounded-xl bg-gradient-to-b from-rose-400 to-rose-500 border-b-4 border-rose-700 text-white flex items-center justify-center font-black text-xs uppercase tracking-widest hover:brightness-110 hover:border-b-[3px] hover:translate-y-[1px] active:border-b-0 active:translate-y-1 transition-all shadow-lg shadow-rose-500/30" title="Export PDF">
                    <i class="fa-solid fa-file-pdf mr-2"></i> PDF
                </a>
            </div>

            <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden p-8">
                <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                            @if(isset($visibleColumns['type'])) <th class="p-6">Category / Type</th> @endif
                            @if(isset($visibleColumns['sub_type'])) <th class="p-6">Sub-Type Label</th> @endif
                            @if(isset($visibleColumns['total'])) <th class="p-6">Deployment Vol.</th> @endif
                            @if(isset($visibleColumns['percentage'])) <th class="p-6">Percentage (%)</th> @endif
                            @if(isset($visibleColumns['emergency'])) <th class="p-6">Emergencies (P1)</th> @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($data as $row)
                        <tr class="hover:bg-blue-50/50 transition-all group">
                            @if(isset($visibleColumns['type']))
                            <td class="p-6 font-extrabold text-navy-900 uppercase tracking-tight">{{ $row['type'] }}</td>
                            @endif

                            @if(isset($visibleColumns['sub_type']))
                            <td class="p-6 font-bold text-slate-500 uppercase text-xs">{{ $row['sub_type'] }}</td>
                            @endif

                            @if(isset($visibleColumns['total']))
                            <td class="p-6">
                                <span class="px-4 py-1.5 bg-blue-50 text-blue-700 font-black rounded-xl text-xs shadow-sm shadow-blue-500/10">{{ $row['total'] }}</span>
                            </td>
                            @endif

                            @if(isset($visibleColumns['percentage']))
                            <td class="p-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden w-24">
                                        <div class="h-full bg-blue-600 transition-all duration-1000" style="width: {{ $row['percentage'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-black text-slate-600">{{ $row['percentage'] }}%</span>
                                </div>
                            </td>
                            @endif

                            @if(isset($visibleColumns['emergency']))
                            <td class="p-6">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-triangle-exclamation text-rose-500 {{ $row['emergency'] > 0 ? '' : 'opacity-20' }}"></i>
                                    <span class="text-xs font-bold text-slate-500">{{ $row['emergency'] }} cases</span>
                                </div>
                            </td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
@endsection

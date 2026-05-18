@extends('layouts.app')

@section('title', 'Beat-wise Analysis - NHMP 130')

@section('page-title', 'Beat-wise Analysis')

@section('content')
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12">
        <!-- ── Filters ─────────────────────────────────────────────── -->
        @include('reports.partials.filters')

        <!-- ── Quick Summary ───────────────────────────────────────── -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            @foreach(array_slice($data, 0, 4) as $top)
            <div class="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-sm flex flex-col items-center justify-center text-center">
                <div class="text-3xl font-extrabold text-navy-900 mb-1">{{ $top->total }}</div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400">{{ $top->name }}</div>
            </div>
            @endforeach
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden p-8">
            <div class="flex justify-end mb-6">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white font-black text-[10px] px-6 py-4 rounded-2xl shadow-xl transition-all uppercase tracking-widest flex items-center gap-3">
                    <i class="fa-solid fa-file-excel text-lg"></i>
                    <span>Export Results</span>
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                            @if(isset($visibleColumns['name'])) <th class="p-6">Operational Beat</th> @endif
                            @if(isset($visibleColumns['total'])) <th class="p-6">Incident Density</th> @endif
                            @if(isset($visibleColumns['status'])) <th class="p-6">Status Overlay</th> @endif
                            <th class="p-6 text-center">Controls</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($data as $row)
                        <tr class="hover:bg-indigo-50/50 transition-all group">
                            @if(isset($visibleColumns['name']))
                            <td class="p-6">
                                <div class="font-extrabold text-navy-900 uppercase tracking-tight text-lg">{{ $row->name }}</div>
                            </td>
                            @endif

                            @if(isset($visibleColumns['total']))
                            <td class="p-6">
                                <div class="flex items-center gap-4">
                                     <span class="text-xl font-black text-indigo-600">{{ $row->total }}</span>
                                     <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden w-48">
                                        <div class="h-full bg-gradient-to-r from-indigo-500 to-blue-600" style="width: {{ min(($row->total / (max(1, ($data[0]->total ?? 1)))) * 100, 100) }}%"></div>
                                     </div>
                                </div>
                            </td>
                            @endif

                            @if(isset($visibleColumns['status']))
                            <td class="p-6">
                                <span class="flex items-center gap-2 text-emerald-600 font-bold text-[10px] tracking-widest uppercase">
                                    <span class="w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]"></span> Optimal
                                </span>
                            </td>
                            @endif

                            <td class="p-6 text-center">
                                <button class="w-10 h-10 rounded-xl bg-white border border-slate-200 text-slate-400 hover:text-indigo-600 hover:border-indigo-400 transition-all active:scale-95 shadow-sm">
                                    <i class="fa-solid fa-chart-line"></i>
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

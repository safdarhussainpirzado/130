@extends('layouts.app')

@section('title', 'Category Analysis - NHMP 130')

@section('page-title', 'Category Analysis (Primary vs Secondary)')

@section('content')
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12">
        <!-- ── Filters ─────────────────────────────────────────────── -->
        @include('reports.partials.filters')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-8">
            @foreach($data as $row)
            <div class="bg-white rounded-[2rem] p-10 border border-slate-100 shadow-xl flex flex-col items-center text-center group hover:border-emerald-200 transition-all">
                <div class="w-20 h-20 rounded-3xl mb-6 flex items-center justify-center text-3xl shadow-lg
                    {{ $row['category_label'] === 'Primary' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-400' }}">
                    <i class="fa-solid {{ $row['category_label'] === 'Primary' ? 'fa-shield-heart' : 'fa-circle-info' }}"></i>
                </div>
                <div class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">{{ $row['category_label'] }} Call Volume</div>
                <div class="text-6xl font-black text-navy-900 mb-4">{{ $row['total'] }}</div>
                <div class="flex items-center gap-2 px-6 py-2 rounded-full bg-slate-50 text-slate-600 font-bold text-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    {{ $row['percentage'] }}% of Total Load
                </div>
            </div>
            @endforeach
        </div>

        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden p-8">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                            @if(isset($visibleColumns['category_label'])) <th class="p-6">Major Category</th> @endif
                            @if(isset($visibleColumns['total'])) <th class="p-6">Total Volume</th> @endif
                            @if(isset($visibleColumns['percentage'])) <th class="p-6">Share %</th> @endif
                            @if(isset($visibleColumns['avg_duration'])) <th class="p-6">Avg Duration</th> @endif
                            <th class="p-6 text-center">Trend</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @foreach($data as $row)
                        <tr class="hover:bg-slate-50 transition-all">
                            @if(isset($visibleColumns['category_label']))
                            <td class="p-6">
                                <div class="font-extrabold text-navy-900 uppercase tracking-tight">{{ $row['category_label'] }}</div>
                            </td>
                            @endif

                            @if(isset($visibleColumns['total']))
                            <td class="p-6">
                                <span class="px-4 py-1.5 bg-slate-100 text-navy-900 font-black rounded-xl text-xs">{{ $row['total'] }} logs</span>
                            </td>
                            @endif

                            @if(isset($visibleColumns['percentage']))
                            <td class="p-6">
                                <div class="flex items-center gap-3">
                                    <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden w-24">
                                        <div class="h-full {{ $row['category_label'] === 'Primary' ? 'bg-emerald-600' : 'bg-slate-400' }}" style="width: {{ $row['percentage'] }}%"></div>
                                    </div>
                                    <span class="text-xs font-black text-slate-600">{{ $row['percentage'] }}%</span>
                                </div>
                            </td>
                            @endif

                            @if(isset($visibleColumns['avg_duration']))
                            <td class="p-6">
                                <div class="text-sm font-bold text-slate-600">{{ round($row['avg_duration'] / 60, 1) }} <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Min</span></div>
                            </td>
                            @endif

                            <td class="p-6 text-center">
                                <div class="text-emerald-500">
                                    <i class="fa-solid fa-arrow-trend-up"></i>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

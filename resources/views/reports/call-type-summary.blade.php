@extends('layouts.app')

@section('title', 'Call Type Summary - NHMP 130')

@section('page-title', 'Call Type Summary')

@section('content')


    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden p-8">
                <div class="mb-8 flex justify-between items-center bg-slate-50 p-6 rounded-3xl border border-slate-100">
                    <div class="flex items-center gap-4">
                         <div class="w-12 h-12 rounded-2xl bg-blue-600 flex items-center justify-center text-white shadow-lg">
                            <i class="fa-solid fa-filter"></i>
                         </div>
                         <div class="text-sm font-bold text-slate-600 uppercase tracking-tight">Active Filters: Global Dataset</div>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2 mr-4 border-r border-slate-200 pr-4">
                            <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm" title="Export Excel">
                                <i class="fa-solid fa-file-excel"></i>
                            </a>
                            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm" title="Export PDF">
                                <i class="fa-solid fa-file-pdf"></i>
                            </a>
                        </div>
                        <input type="date" class="px-4 py-2 rounded-xl border-slate-200 text-sm font-bold text-slate-700 bg-white">
                        <span class="text-slate-400 font-bold text-xs uppercase">to</span>
                        <input type="date" class="px-4 py-2 rounded-xl border-slate-200 text-sm font-bold text-slate-700 bg-white">
                        <button class="bg-navy-900 text-white px-6 py-2 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-black transition-all">Apply</button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                                <th class="p-6">Category / Type</th>
                                <th class="p-6">Sub-Type Label</th>
                                <th class="p-6">Deployment Vol.</th>
                                <th class="p-6">Percentage (%)</th>
                                <th class="p-6">Emergencies (P1)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($data as $row)
                            <tr class="hover:bg-blue-50/50 transition-all group">
                                <td class="p-6 font-extrabold text-navy-900 uppercase tracking-tight">{{ $row['type'] }}</td>
                                <td class="p-6 font-bold text-slate-500 uppercase text-xs">{{ $row['sub_type'] }}</td>
                                <td class="p-6">
                                    <span class="px-4 py-1.5 bg-blue-50 text-blue-700 font-black rounded-xl text-xs shadow-sm shadow-blue-500/10">{{ $row['total'] }}</span>
                                </td>
                                <td class="p-6">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden w-24">
                                            <div class="h-full bg-blue-600 transition-all duration-1000" style="width: {{ $row['percentage'] }}%"></div>
                                        </div>
                                        <span class="text-xs font-black text-slate-600">{{ $row['percentage'] }}%</span>
                                    </div>
                                </td>
                                <td class="p-6">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-triangle-exclamation text-rose-500 {{ $row['emergency'] > 0 ? '' : 'opacity-20' }}"></i>
                                        <span class="text-xs font-bold text-slate-500">{{ $row['emergency'] }} cases</span>
                                    </div>
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

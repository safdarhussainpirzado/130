@extends('layouts.app')

@section('title', 'Agent Performance - NHMP 130')

@section('page-title', 'Agent Performance')

@section('content')


    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden p-8">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-100 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] bg-slate-50/50">
                                <th class="p-6">Agent Identity</th>
                                <th class="p-6">Logged Executions</th>
                                <th class="p-6">Avg Handle Time</th>
                                <th class="p-6">Efficiency Matrix</th>
                                <th class="p-6 text-center">Controls</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($data as $row)
                            <tr class="hover:bg-emerald-50/50 transition-all group">
                                <td class="p-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-100 to-emerald-200 flex items-center justify-center text-emerald-700 font-black shadow-sm uppercase">
                                            {{ substr($row->username, 0, 2) }}
                                        </div>
                                        <div class="font-extrabold text-navy-900 uppercase tracking-tight">{{ $row->username }}</div>
                                    </div>
                                </td>
                                <td class="p-6">
                                    <span class="px-4 py-1.5 bg-emerald-50 text-emerald-700 font-black rounded-xl text-xs shadow-sm shadow-emerald-500/10">{{ $row->total }} logs</span>
                                </td>
                                <td class="p-6">
                                    <div class="text-sm font-bold text-slate-600">{{ round($row->avg_duration / 60, 1) }} <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Minutes</span></div>
                                </td>
                                <td class="p-6">
                                    <div class="flex items-center gap-2">
                                        @php $efficiency = min(100, (1 / (max(1, $row->avg_duration / 300))) * 100); @endphp
                                        <div class="flex-1 h-1.5 bg-slate-100 rounded-full overflow-hidden w-24">
                                            <div class="h-full bg-emerald-500" style="width: {{ $efficiency }}%"></div>
                                        </div>
                                        <span class="text-[10px] font-black text-emerald-600">{{ round($efficiency) }}%</span>
                                    </div>
                                </td>
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

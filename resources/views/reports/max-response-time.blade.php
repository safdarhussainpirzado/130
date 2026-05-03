@extends('layouts.app')

@section('title', 'Max Response Analysis - NHMP 130')

@section('page-title', 'Max Response Analysis')

@section('content')


    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 overflow-hidden mb-10">
                <div class="bg-gradient-to-r from-rose-500 to-rose-700 p-8 text-white">
                    <div class="flex justify-between items-center text-white/80 font-black text-[10px] uppercase tracking-[0.2em]">
                        <span>Beat Identity</span>
                        <span>Peak Latency (Sec)</span>
                    </div>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($data as $row)
                    <div class="p-8 flex items-center justify-between group hover:bg-rose-50/30 transition-all">
                        <div class="flex items-center gap-6">
                            <div class="w-14 h-14 rounded-2xl bg-slate-50 border border-slate-100 flex items-center justify-center text-navy-900 font-black text-lg shadow-sm group-hover:bg-white group-hover:border-rose-100 transition-all">
                                {{ $row['beat'] }}
                            </div>
                            <div>
                                <h4 class="font-extrabold text-navy-900 text-xl tracking-tight uppercase leading-none mb-1">{{ $row['beat'] }}</h4>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">AOR Deployment Grid</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-black text-rose-600 tracking-tighter">{{ number_format($row['max_response']) }}<span class="text-sm ml-1 opacity-60">s</span></div>
                            <div class="w-32 h-1.5 bg-slate-100 rounded-full mt-2 overflow-hidden shadow-inner">
                                <div class="h-full bg-rose-500 rounded-full" style="width: {{ min(100, ($row['max_response'] / 1800) * 100) }}%"></div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="p-20 text-center text-slate-400 font-bold border-2 border-dashed border-slate-100 m-8 rounded-[2rem]">No response benchmarks recorded for this period.</div>
                    @endforelse
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] p-10 border border-slate-100 shadow-xl shadow-slate-200/20 relative overflow-hidden">
                <div class="flex items-start gap-8">
                    <div class="w-20 h-20 rounded-3xl bg-rose-50 flex items-center justify-center text-rose-500 text-4xl shrink-0">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h3 class="text-2xl font-extrabold text-navy-900 tracking-tight mb-3">Latency Outlier Monitoring</h3>
                        <p class="text-slate-500 font-medium leading-relaxed mb-6">This report identifies the maximum response time (from call pickup to agent resolution) recorded per beat. Beats exceeding the 30-minute critical threshold (1800s) are automatically flagged for operational review.</p>
                        <div class="flex gap-4">
                            <span class="px-4 py-2 bg-rose-50 text-rose-600 rounded-xl text-[10px] font-black uppercase tracking-widest border border-rose-100">Threshold: 1800s</span>
                            <span class="px-4 py-2 bg-emerald-50 text-emerald-600 rounded-xl text-[10px] font-black uppercase tracking-widest border border-emerald-100">Target: < 600s</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

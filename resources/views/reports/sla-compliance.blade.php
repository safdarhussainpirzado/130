@extends('layouts.app')

@section('title', 'SLA Compliance - NHMP 130')

@section('page-title', 'SLA Compliance Monitoring')

@section('content')


    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-10">
                @foreach($data as $priority => $row)
                <div class="bg-white rounded-[2rem] border border-slate-100 p-8 shadow-xl shadow-slate-200/20 relative overflow-hidden group hover:border-{{ $row['percentage'] >= 90 ? 'emerald' : ($row['percentage'] >= 70 ? 'amber' : 'rose') }}-500 transition-all">
                    <div class="absolute -right-4 -bottom-4 opacity-5 group-hover:scale-110 transition-transform duration-500">
                        <i class="fa-solid fa-stopwatch text-8xl"></i>
                    </div>
                    
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <span class="px-3 py-1 bg-slate-900 text-white rounded-lg text-[10px] font-black tracking-widest uppercase">{{ $row['priority'] }}</span>
                            <div class="text-xs font-bold text-slate-400 mt-2 uppercase tracking-widest">Operational Target</div>
                        </div>
                        <div class="text-4xl font-extrabold text-navy-900">{{ $row['percentage'] }}%</div>
                    </div>

                    <div class="h-3 bg-slate-100 rounded-full overflow-hidden mb-6 shadow-inner">
                        <div class="h-full rounded-full transition-all duration-1000 bg-{{ $row['percentage'] >= 90 ? 'emerald' : ($row['percentage'] >= 70 ? 'amber' : 'rose') }}-500" style="width: {{ $row['percentage'] }}%"></div>
                    </div>

                    <div class="flex justify-between items-center text-[10px] font-black uppercase tracking-widest">
                        <div class="text-slate-400">{{ $row['within'] }} Success</div>
                        <div class="text-slate-400">{{ $row['total'] }} Deployments</div>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="bg-navy-900 rounded-[2.5rem] p-10 text-white shadow-2xl relative overflow-hidden">
                <div class="absolute -right-20 -top-20 w-80 h-80 bg-blue-600 rounded-full blur-[100px] opacity-30"></div>
                <div class="relative z-10 flex flex-col md:flex-row justify-between items-center gap-8">
                    <div class="max-w-xl">
                        <h3 class="text-3xl font-extrabold tracking-tight mb-4">SLA Threshold Enforcement</h3>
                        <p class="text-blue-100/70 font-medium leading-relaxed">Compliance is measured as the ratio of calls resolved within original target windows (P1: 10m, P2: 20m, P3: 30m). Current global aggregate identifies a need for optimized Tiger deployment during peak transition hours.</p>
                    </div>
                    <button class="px-10 py-4 bg-white text-navy-900 rounded-2xl font-black text-xs uppercase tracking-widest shadow-xl hover:bg-blue-50 transition-all active:scale-95 whitespace-nowrap">
                        Configure Thresholds
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

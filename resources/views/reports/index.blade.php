@extends('layouts.app')

@section('title', 'Dispatch Analytics - NHMP 130')

@section('page-title', 'Dispatch Analytics')

@section('content')


    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Call Type Summary Card -->
                <a href="{{ route('reports.call-type-summary') }}" class="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-xl shadow-slate-200/20 hover:border-blue-500 hover:-translate-y-1 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 flex items-center justify-center text-blue-600 mb-4 group-hover:bg-blue-600 group-hover:text-white transition-all">
                        <i class="fa-solid fa-list-check text-xl"></i>
                    </div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Categorical</div>
                    <div class="text-xl font-extrabold text-navy-900">Call Type Summary</div>
                </a>

                <!-- Beat-wise Card -->
                <a href="{{ route('reports.beat-wise') }}" class="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-xl shadow-slate-200/20 hover:border-indigo-500 hover:-translate-y-1 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-600 mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                        <i class="fa-solid fa-map-location-dot text-xl"></i>
                    </div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Geospatial</div>
                    <div class="text-xl font-extrabold text-navy-900">Beat-wise Analysis</div>
                </a>

                <!-- Agent performance Card -->
                <a href="{{ route('reports.agent-wise') }}" class="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-xl shadow-slate-200/20 hover:border-emerald-500 hover:-translate-y-1 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 mb-4 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <i class="fa-solid fa-headset text-xl"></i>
                    </div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Performance</div>
                    <div class="text-xl font-extrabold text-navy-900">Agent Performance</div>
                </a>

                <!-- SLA Card -->
                <a href="{{ route('reports.sla-compliance') }}" class="bg-white rounded-[2rem] p-6 border border-slate-100 shadow-xl shadow-slate-200/20 hover:border-rose-500 hover:-translate-y-1 transition-all group">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600 mb-4 group-hover:bg-rose-600 group-hover:text-white transition-all">
                        <i class="fa-solid fa-stopwatch text-xl"></i>
                    </div>
                    <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Compliance</div>
                    <div class="text-xl font-extrabold text-navy-900">SLA Monitoring</div>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Max Response Card -->
                <a href="{{ route('reports.max-response-time') }}" class="bg-gradient-to-br from-rose-600 to-rose-800 rounded-[2rem] p-8 text-white shadow-xl shadow-rose-200/30 hover:-translate-y-1 transition-all group relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i class="fa-solid fa-gauge-high text-9xl"></i>
                    </div>
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div>
                            <div class="text-[10px] font-black text-rose-200 uppercase tracking-widest mb-2">Benchmark Monitoring</div>
                            <h3 class="text-2xl font-extrabold tracking-tight mb-4 leading-tight">Max Response Benchmarking</h3>
                            <p class="text-rose-100/70 text-sm font-medium leading-relaxed max-w-sm">Identify operational bottlenecks by tracking peak response times per beat grid.</p>
                        </div>
                        <div class="mt-6 flex items-center gap-3">
                            <span class="px-3 py-1 bg-white/20 rounded-lg text-[9px] font-black uppercase tracking-widest border border-white/20">Operational Outliers</span>
                            <i class="fa-solid fa-arrow-right text-white/50 group-hover:translate-x-2 transition-transform"></i>
                        </div>
                    </div>
                </a>

                <!-- Predictive Card -->
                <a href="{{ route('reports.predictive-analysis') }}" class="bg-gradient-to-br from-indigo-600 to-blue-700 rounded-[2rem] p-8 text-white shadow-xl shadow-blue-200/30 hover:-translate-y-1 transition-all group relative overflow-hidden">
                    <div class="absolute -right-6 -bottom-6 opacity-10 group-hover:scale-110 transition-transform duration-500">
                        <i class="fa-solid fa-brain text-9xl"></i>
                    </div>
                    <div class="relative z-10 flex flex-col h-full justify-between">
                        <div>
                            <div class="text-[10px] font-black text-blue-200 uppercase tracking-widest mb-2">Machine Intelligence</div>
                            <h3 class="text-2xl font-extrabold tracking-tight mb-4 leading-tight">Predictive Load Forecasting</h3>
                            <p class="text-blue-100/70 text-sm font-medium leading-relaxed max-w-sm">Forecast incident density patterns based on historical temporal benchmarks.</p>
                        </div>
                        <div class="mt-6 flex items-center gap-3">
                            <span class="px-3 py-1 bg-white/20 rounded-lg text-[9px] font-black uppercase tracking-widest border border-white/20">Operational Readiness</span>
                            <i class="fa-solid fa-arrow-right text-white/50 group-hover:translate-x-2 transition-transform"></i>
                        </div>
                    </div>
                </a>
            </div>


            <!-- Complex Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Daily Dist -->
                <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 p-8">
                    <h3 class="font-black text-sm tracking-widest text-slate-400 mb-6">30-DAY PIPELINE DISTRIBUTION</h3>
                    <div class="space-y-4">
                        @forelse($dailyCalls as $day)
                        <div class="flex items-center justify-between group">
                            <span class="font-bold text-navy-900 text-sm w-32">{{ \Carbon\Carbon::parse($day->date)->format('M d, Y') }}</span>
                            <div class="flex-1 mx-4 h-3 bg-slate-100 rounded-full overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-400 to-blue-600 rounded-full transition-all group-hover:from-indigo-400 group-hover:to-indigo-600" style="width: {{ min(($day->total / 50) * 100, 100) }}%"></div>
                            </div>
                            <span class="font-black text-blue-600 bg-blue-50 px-3 py-1 rounded-lg text-xs shadow-sm">{{ $day->total }} traces</span>
                        </div>
                        @empty
                        <div class="text-center text-slate-400 font-bold py-8">Insufficient trace logs derived.</div>
                        @endforelse
                    </div>
                </div>

                <!-- Type Dist -->
                <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 p-8 h-fit">
                    <h3 class="font-black text-sm tracking-widest text-slate-400 mb-6">INCIDENT CLASSIFICATION MATRICES</h3>
                    <div class="grid grid-cols-2 gap-4">
                        @forelse($typeDistribution as $type)
                        <div class="bg-slate-50 border border-slate-200 rounded-2xl p-4 flex flex-col items-center justify-center text-center cursor-pointer hover:border-blue-500 hover:shadow-md transition-all group">
                            <div class="text-3xl font-black text-navy-900 mb-1 group-hover:text-blue-600 transition-colors">{{ $type->total }}</div>
                            <div class="text-[10px] font-bold uppercase tracking-widest text-slate-500">{{ $type->name }}</div>
                        </div>
                        @empty
                        <div class="col-span-2 text-center text-slate-400 font-bold py-8">Insufficient trace logs derived.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

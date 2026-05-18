@extends('layouts.app')

@section('title', 'Call Type Summary - NHMP 130')

@section('page-title', 'Call Type Summary')

@section('content')
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 py-12">
        <!-- ── Filters ─────────────────────────────────────────────── -->
        @include('reports.partials.filters')

        <div class="bg-white rounded-[2rem] shadow-xl border border-slate-100 overflow-hidden p-8">
            <div class="flex justify-end gap-3 mb-6">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center hover:bg-emerald-600 hover:text-white transition-all shadow-sm" title="Export Excel">
                    <i class="fa-solid fa-file-excel"></i>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center hover:bg-rose-600 hover:text-white transition-all shadow-sm" title="Export PDF">
                    <i class="fa-solid fa-file-pdf"></i>
                </a>
            </div>

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
@endsection

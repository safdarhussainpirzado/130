@extends('layouts.app')

@section('title', 'Support Center - 130 NHMP Helpline')
@section('page-title', 'Support Center')

@section('content')
<script id="support-center-config" type="application/json">
    {!! json_encode(['filterKey' => $filterKey ?? 'all', 'kpis' => $kpis]) !!}
</script>
<script>
    function supportManager() {
        const configEl = document.getElementById('support-center-config');
        const config = configEl ? JSON.parse(configEl.textContent || '{}') : {};

        return {
            activeModal: null,
            selectedRequest: null,
            filterKey: config.filterKey || 'all',
            loading: false,
            isAjaxLoaded: false,
            requestsList: [],
            kpiCounts: config.kpis || {},
            openModal(modalName, req) {
                this.activeModal = modalName;
                this.selectedRequest = req;
            },
            closeModal() {
                this.activeModal = null;
                this.selectedRequest = null;
            },
            async applyCardFilter(key) {
                if (this.loading) return;
                const targetFilter = (this.filterKey === key && key !== 'all') ? 'all' : key;
                this.filterKey = targetFilter;
                this.loading = true;

                const currentUrl = new URL(window.location.href);
                if (targetFilter === 'all') {
                    currentUrl.searchParams.delete('filter');
                } else {
                    currentUrl.searchParams.set('filter', targetFilter);
                }
                currentUrl.searchParams.delete('page');
                window.history.pushState({}, '', currentUrl.toString());

                try {
                    const resp = await fetch(currentUrl.toString(), {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });
                    if (resp.ok) {
                        const res = await resp.json();
                        if (res.success) {
                            this.requestsList = res.data || [];
                            if (res.kpis) {
                                this.kpiCounts = res.kpis;
                            }
                            this.isAjaxLoaded = true;
                        }
                    } else {
                        window.location.href = currentUrl.toString();
                    }
                } catch (e) {
                    console.error('Support filter fetch error:', e);
                    window.location.href = currentUrl.toString();
                } finally {
                    this.loading = false;
                }
            }
        };
    }
</script>

<div x-data="supportManager()" class="space-y-8 relative mt-2 max-w-[1700px] mx-auto pb-16" x-cloak>


    {{-- Flash Token Display (For newly authorized password recovery) --}}
    @if(session('status_token'))
    <div class="p-6 rounded-3xl bg-gradient-to-r from-amber-500/15 via-amber-500/5 to-white border-2 border-amber-300 space-y-3 shadow-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2.5 text-amber-900 font-black text-sm">
                <div class="w-8 h-8 rounded-xl bg-amber-500 text-white flex items-center justify-center shadow-sm">
                    <i class="fa-solid fa-key text-xs"></i>
                </div>
                <span>Single-Use Password Reset Token Issued</span>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                Zero-Knowledge Authorization
            </span>
        </div>
        <p class="text-xs text-slate-600">
            Provide the following high-entropy token or direct recovery link to the verified officer. Passwords are <strong>never</strong> set or visible to administrators; the user will establish their own credentials.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
            <div class="p-3 bg-white rounded-2xl border border-amber-200 space-y-1">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Single-Use Security Token</span>
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono text-xs font-black text-amber-900 select-all break-all">{{ session('status_token') }}</span>
                    <button type="button" @click="navigator.clipboard.writeText('{{ session('status_token') }}')" class="p-1.5 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 text-xs transition-colors" title="Copy Token">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>
            </div>

            <div class="p-3 bg-white rounded-2xl border border-amber-200 space-y-1">
                <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Direct Reset Link</span>
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono text-xs font-bold text-blue-700 select-all break-all truncate">{{ session('reset_url') }}</span>
                    <button type="button" @click="navigator.clipboard.writeText('{{ session('reset_url') }}')" class="p-1.5 rounded-lg bg-blue-50 text-blue-700 hover:bg-blue-100 text-xs transition-colors" title="Copy Link">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- TOP 10 3D STATUS FILTER CARDS (2 Rows of 5 Matching User Management Style) --}}
    <div class="space-y-4">
        {{-- Row 1: Workflow Lifecycle States --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            {{-- 1. Total Requests --}}
            <div @click="applyCardFilter('all')" 
                 :class="(filterKey === 'all' || !filterKey) ? 'card-3d-active slate' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-slate-700 shadow-[0_8px_16px_rgba(51,65,85,0.25)]">
                    <i class="fa-solid fa-headset text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-slate-500 uppercase">Total Requests</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['total'] !== undefined ? kpiCounts['total'].toLocaleString() : '{{ number_format($kpis['total']) }}'">{{ number_format($kpis['total']) }}</h4>
                </div>
            </div>

            {{-- 2. Pending Total --}}
            <div @click="applyCardFilter('pending')" 
                 :class="filterKey === 'pending' ? 'card-3d-active amber' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-amber-500 shadow-[0_8px_16px_rgba(245,158,11,0.25)]">
                    <i class="fa-solid fa-clock text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-amber-500 uppercase">Pending Total</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['pending'] !== undefined ? kpiCounts['pending'].toLocaleString() : '{{ number_format($kpis['pending']) }}'">{{ number_format($kpis['pending']) }}</h4>
                </div>
            </div>

            {{-- 3. Awaiting Source --}}
            <div @click="applyCardFilter('awaiting_source')" 
                 :class="filterKey === 'awaiting_source' ? 'card-3d-active blue' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-blue-600 shadow-[0_8px_16px_rgba(37,99,235,0.25)]">
                    <i class="fa-solid fa-plane-departure text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-blue-500 uppercase">Awaiting Source</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['awaiting_source'] !== undefined ? kpiCounts['awaiting_source'].toLocaleString() : '{{ number_format($kpis['awaiting_source']) }}'">{{ number_format($kpis['awaiting_source']) }}</h4>
                </div>
            </div>

            {{-- 4. Awaiting Destination --}}
            <div @click="applyCardFilter('awaiting_destination')" 
                 :class="filterKey === 'awaiting_destination' ? 'card-3d-active indigo' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-indigo-600 shadow-[0_8px_16px_rgba(79,70,229,0.25)]">
                    <i class="fa-solid fa-plane-arrival text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-indigo-500 uppercase">Awaiting Destination</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['awaiting_destination'] !== undefined ? kpiCounts['awaiting_destination'].toLocaleString() : '{{ number_format($kpis['awaiting_destination']) }}'">{{ number_format($kpis['awaiting_destination']) }}</h4>
                </div>
            </div>

            {{-- 5. Completed --}}
            <div @click="applyCardFilter('completed')" 
                 :class="filterKey === 'completed' ? 'card-3d-active emerald' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-emerald-600 shadow-[0_8px_16px_rgba(5,150,105,0.25)]">
                    <i class="fa-solid fa-circle-check text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-emerald-500 uppercase">Completed</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['completed'] !== undefined ? kpiCounts['completed'].toLocaleString() : '{{ number_format($kpis['completed']) }}'">{{ number_format($kpis['completed']) }}</h4>
                </div>
            </div>
        </div>

        {{-- Row 2: Operational Categories & Support Types --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            {{-- 6. Rejected --}}
            <div @click="applyCardFilter('rejected')" 
                 :class="filterKey === 'rejected' ? 'card-3d-active rose' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-rose-600 shadow-[0_8px_16px_rgba(225,29,72,0.25)]">
                    <i class="fa-solid fa-circle-xmark text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-rose-500 uppercase">Rejected</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['rejected'] !== undefined ? kpiCounts['rejected'].toLocaleString() : '{{ number_format($kpis['rejected']) }}'">{{ number_format($kpis['rejected']) }}</h4>
                </div>
            </div>

            {{-- 7. Account Blocking --}}
            <div @click="applyCardFilter('account_block')" 
                 :class="filterKey === 'account_block' ? 'card-3d-active rose' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-rose-700 shadow-[0_8px_16px_rgba(190,18,60,0.25)]">
                    <i class="fa-solid fa-user-lock text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-rose-600 uppercase">Account Blocking</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['account_blocking'] !== undefined ? kpiCounts['account_blocking'].toLocaleString() : '{{ number_format($kpis['account_blocking']) }}'">{{ number_format($kpis['account_blocking']) }}</h4>
                </div>
            </div>

            {{-- 8. Password Recovery --}}
            <div @click="applyCardFilter('password_recovery')" 
                 :class="filterKey === 'password_recovery' ? 'card-3d-active amber' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-amber-600 shadow-[0_8px_16px_rgba(217,119,6,0.25)]">
                    <i class="fa-solid fa-key text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-amber-600 uppercase">Password Recovery</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['password_recovery'] !== undefined ? kpiCounts['password_recovery'].toLocaleString() : '{{ number_format($kpis['password_recovery']) }}'">{{ number_format($kpis['password_recovery']) }}</h4>
                </div>
            </div>

            {{-- 9. MFA Requests --}}
            <div @click="applyCardFilter('mfa_support')" 
                 :class="filterKey === 'mfa_support' ? 'card-3d-active sky' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-sky-600 shadow-[0_8px_16px_rgba(2,132,199,0.25)]">
                    <i class="fa-solid fa-mobile-screen-button text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-sky-500 uppercase">MFA Requests</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['mfa_requests'] !== undefined ? kpiCounts['mfa_requests'].toLocaleString() : '{{ number_format($kpis['mfa_requests']) }}'">{{ number_format($kpis['mfa_requests']) }}</h4>
                </div>
            </div>

            {{-- 10. Transfer Requests --}}
            <div @click="applyCardFilter('org_transfer')" 
                 :class="filterKey === 'org_transfer' ? 'card-3d-active emerald' : ''" 
                 class="relative flex flex-col bg-white rounded-3xl shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-slate-100 hover:-translate-y-1 transition-all duration-300 cursor-pointer">
                <div class="absolute -top-4 left-4 h-10 w-10 grid place-items-center rounded-xl bg-emerald-600 shadow-[0_8px_16px_rgba(5,150,105,0.25)]">
                    <i class="fa-solid fa-people-arrows text-xs text-white"></i>
                </div>
                <div class="p-5 text-right pt-4">
                    <p class="text-[8px] font-black tracking-widest text-emerald-500 uppercase">Transfer Requests</p>
                    <h4 class="text-2xl font-extrabold text-blue-900 mt-1" x-text="kpiCounts?.['transfer_requests'] !== undefined ? kpiCounts['transfer_requests'].toLocaleString() : '{{ number_format($kpis['transfer_requests']) }}'">{{ number_format($kpis['transfer_requests']) }}</h4>
                </div>
            </div>
        </div>
    </div>

    {{-- MAIN CONTROL PANEL & REGISTRY (Matching User Management Styling) --}}
    <div class="bg-white rounded-[2rem] shadow-[0_10px_40px_rgba(0,0,0,0.03)] border border-gray-100 overflow-hidden flex flex-col relative z-20">
        
        {{-- Panel Header --}}
        <div class="bg-blue-50/50 p-6 sm:p-8 border-b border-blue-100/50">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-[1.25rem] bg-white flex items-center justify-center border border-blue-100 shadow-sm transition-transform hover:scale-105 duration-300">
                        <i class="fa-solid fa-headset text-3xl text-blue-600"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-extrabold text-blue-900 tracking-tight flex items-center gap-3">
                            Support Center <span class="text-lg font-bold text-slate-400">({{ $requests->total() }} requests)</span>
                        </h2>
                        <p class="text-slate-500 text-sm font-bold mt-1">Single-page operations control: transfers, password recovery, MFA & security unlocks</p>
                    </div>
                </div>
                
                {{-- Row Density & Quick Controls --}}
                <div class="flex flex-wrap gap-3 items-center">
                    <form method="GET" action="{{ route('admin.support.index') }}" id="densityForm" class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3 py-1.5 shadow-sm">
                        @if($filterKey)<input type="hidden" name="filter" value="{{ $filterKey }}">@endif
                        @if($typeFilter && $typeFilter !== 'all')<input type="hidden" name="type" value="{{ $typeFilter }}">@endif
                        @if($statusFilter && $statusFilter !== 'all')<input type="hidden" name="status" value="{{ $statusFilter }}">@endif
                        @if($search)<input type="hidden" name="search" value="{{ $search }}">@endif
                        
                        <span class="text-[9px] font-black text-slate-400 border-r border-slate-100 pr-2 uppercase font-mono">Row Density</span>
                        <select name="perPage" onchange="document.getElementById('densityForm').submit()" class="bg-transparent text-blue-600 text-[10px] font-black uppercase cursor-pointer outline-none focus:ring-0 border-none p-0 pr-4">
                            <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10 Per Page</option>
                            <option value="15" {{ $perPage == 15 ? 'selected' : '' }}>15 Per Page</option>
                            <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25 Per Page</option>
                            <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50 Per Page</option>
                            <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100 Per Page</option>
                        </select>
                    </form>
                </div>
            </div>

            {{-- Integrated Filters Form --}}
            <form method="GET" action="{{ route('admin.support.index') }}" class="mt-6 pt-6 border-t border-blue-100/60">
                @if($filterKey)<input type="hidden" name="filter" value="{{ $filterKey }}">@endif
                <input type="hidden" name="perPage" value="{{ $perPage }}">

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    {{-- Search Input --}}
                    <div class="lg:col-span-2">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Search Identifier / Requester</label>
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                            <input type="text" name="search" value="{{ $search }}" placeholder="UUID, requester name, username, subject..."
                                class="w-full pl-9 text-xs rounded-xl border-slate-200 bg-white focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                        </div>
                    </div>

                    {{-- Request Type Filter --}}
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Request Type</label>
                        <select name="type" class="w-full text-xs rounded-xl border-slate-200 bg-white focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                            <option value="all">All Types</option>
                            <option value="org_transfer" {{ $typeFilter === 'org_transfer' ? 'selected' : '' }}>Organizational Transfer</option>
                            <option value="password_recovery" {{ $typeFilter === 'password_recovery' ? 'selected' : '' }}>Password Recovery</option>
                            <option value="mfa_support" {{ $typeFilter === 'mfa_support' ? 'selected' : '' }}>MFA Assistance</option>
                            <option value="account_block" {{ $typeFilter === 'account_block' ? 'selected' : '' }}>Account Blocking</option>
                            <option value="other_account" {{ $typeFilter === 'other_account' ? 'selected' : '' }}>Other Issues</option>
                        </select>
                    </div>

                    {{-- Status Filter --}}
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Workflow Status</label>
                        <select name="status" class="w-full text-xs rounded-xl border-slate-200 bg-white focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                            <option value="all">All Statuses</option>
                            <option value="submitted" {{ $statusFilter === 'submitted' ? 'selected' : '' }}>Submitted</option>
                            <option value="pending_source_approval" {{ $statusFilter === 'pending_source_approval' ? 'selected' : '' }}>Pending Source Approval</option>
                            <option value="pending_destination_confirmation" {{ $statusFilter === 'pending_destination_confirmation' ? 'selected' : '' }}>Pending Destination Confirmation</option>
                            <option value="pending_manager_review" {{ $statusFilter === 'pending_manager_review' ? 'selected' : '' }}>Pending Manager Review</option>
                            <option value="completed" {{ $statusFilter === 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="rejected" {{ $statusFilter === 'rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>

                    {{-- Zone Filter --}}
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Zone</label>
                        <select name="zone_id" class="w-full text-xs rounded-xl border-slate-200 bg-white focus:border-blue-500 focus:ring-blue-500 shadow-sm">
                            <option value="">All Zones</option>
                            @foreach($zones as $z)
                                <option value="{{ $z->id }}" {{ $zoneId == $z->id ? 'selected' : '' }}>{{ $z->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-end gap-2">
                        <button type="submit" class="flex-1 py-2 px-3 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-filter text-xs"></i> Filter
                        </button>
                        <a href="{{ route('admin.support.index') }}" class="py-2 px-3 bg-white hover:bg-slate-100 text-slate-600 border border-slate-200 rounded-xl text-xs font-bold transition-colors" title="Reset Filters">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Master Registry Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50/80 border-b border-slate-200/80 text-slate-400 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3.5 px-4 font-black">Tracking ID</th>
                        <th class="py-3.5 px-4 font-black">Requester</th>
                        <th class="py-3.5 px-4 font-black">Type & Purpose</th>
                        <th class="py-3.5 px-4 font-black">Routing / Assignment</th>
                        <th class="py-3.5 px-4 font-black">Status</th>
                        <th class="py-3.5 px-4 font-black">Submitted</th>
                        <th class="py-3.5 px-4 text-right font-black">Action Operations</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" :class="loading ? 'opacity-50 pointer-events-none transition-opacity' : ''">
                    {{-- Dynamic Loading Indicator --}}
                    <tr x-show="loading">
                        <td colspan="7" class="py-8 text-center text-slate-400">
                            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-blue-50 text-blue-600 font-bold text-xs">
                                <i class="fa-solid fa-circle-notch animate-spin"></i>
                                <span>Synchronizing Support Registry...</span>
                            </div>
                        </td>
                    </tr>

                    @forelse($requests as $req)
                    @php
                        // Prepare self-contained JSON representation for Alpine modals
                        $requestData = [
                            'id'                 => $req->id,
                            'public_id'          => $req->public_id,
                            'subject'            => $req->subject,
                            'description'        => $req->description,
                            'request_type'       => $req->request_type,
                            'type_label'         => $req->getTypeLabel(),
                            'type_badge'         => $req->getTypeBadgeClass(),
                            'current_status'     => $req->current_status,
                            'status_label'       => $req->getStatusLabel(),
                            'status_badge'       => $req->getStatusBadgeClass(),
                            'priority'           => $req->priority,
                            'priority_badge'     => $req->getPriorityBadgeClass(),
                            'submitted_at'       => $req->submitted_at?->format('M d, Y H:i:s') ?? 'N/A',
                            'applicant_name'     => $req->requester?->full_name ?: ($req->requester?->name ?: 'System / Unknown Requester'),
                            'applicant_username' => $req->requester?->username ?: ($req->requester?->employee_id ?: 'N/A'),
                            'applicant_email'    => $req->requester?->email ?? 'N/A',
                            'applicant_role'     => $req->requester?->getRoleTitle() ?: ($req->requester?->role?->display_name ?: 'Officer'),
                            'applicant_office'   => $req->requester?->office?->name ?? '',
                            'source_office'      => $req->sourceOffice?->name ?? 'National / Unassigned',
                            'source_parent'      => $req->sourceOffice?->parent?->name ?? '',
                            'destination_office' => $req->destinationOffice?->name ?? 'N/A',
                            'destination_parent' => $req->destinationOffice?->parent?->name ?? '',
                            'actions'            => $req->actions->map(fn($a) => [
                                'type'    => $a->getActionLabel(),
                                'badge'   => $a->getActionBadgeClass(),
                                'actor'   => ($a->actor?->full_name ?? 'System') . ' (' . ($a->actor?->username ?? 'Automated') . ')',
                                'role'    => $a->actor?->getRoleTitle() ?? 'System',
                                'remarks' => $a->remarks,
                                'date'    => $a->created_at->format('M d, Y H:i:s'),
                                'auth'    => $a->getAuthMethodLabel(),
                                'ip'      => $a->ip_address,
                            ])->values()->all(),
                        ];
                    @endphp
                    <tr x-show="!isAjaxLoaded" class="hover:bg-blue-50/20 transition-colors">
                        {{-- Tracking ID --}}
                        <td class="py-3.5 px-4 font-mono">
                            <button type="button" @click='openModal("details", @json($requestData))' class="font-bold text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1">
                                <span>#{{ substr($req->public_id, 0, 8) }}</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-slate-400"></i>
                            </button>
                            <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] mt-1 border {{ $req->getPriorityBadgeClass() }}">
                                {{ ucfirst($req->priority) }}
                            </span>
                        </td>

                        {{-- Requester --}}
                        <td class="py-3.5 px-4">
                            @if($req->requester)
                                <div class="font-black text-navy-900">{{ $req->requester->full_name ?: ($req->requester->name ?: 'System / Unknown Requester') }}</div>
                                <div class="text-[10px] text-slate-400 font-mono flex flex-wrap items-center gap-1.5 mt-0.5">
                                    <span>{{ $req->requester->username ?: ($req->requester->employee_id ?: 'N/A') }}</span>
                                    <span>&bull;</span>
                                    <span class="text-blue-600 font-semibold">{{ $req->requester->getRoleTitle() ?: ($req->requester->role?->display_name ?: 'Officer') }}</span>
                                    @if($req->requester->office)
                                        <span>&bull;</span>
                                        <span class="text-slate-500 font-medium" title="{{ $req->requester->office->name }}">
                                            <i class="fa-solid fa-building text-[8px] mr-0.5"></i>{{ $req->requester->office->name }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                <div class="font-bold text-slate-500 italic">System / Unknown Requester</div>
                                <div class="text-[10px] text-slate-400 font-mono">Legacy Record</div>
                            @endif
                        </td>

                        {{-- Type & Purpose --}}
                        <td class="py-3.5 px-4 max-w-xs">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $req->getTypeBadgeClass() }}">
                                {{ $req->getTypeLabel() }}
                            </span>
                            <div class="font-bold text-navy-900 truncate mt-1 text-[11px]" title="{{ $req->subject }}">
                                {{ $req->subject }}
                            </div>
                        </td>

                        {{-- Routing / Assignment --}}
                        <td class="py-3.5 px-4 text-xs">
                            @if($req->isTransfer())
                                <div class="flex items-center gap-1.5">
                                    <span class="font-medium text-slate-700" title="{{ $req->sourceOffice?->parent?->name }}">{{ $req->sourceOffice?->name ?? 'Unassigned' }}</span>
                                    <i class="fa-solid fa-arrow-right text-[9px] text-slate-400"></i>
                                    <span class="font-bold text-emerald-700" title="{{ $req->destinationOffice?->parent?->name }}">{{ $req->destinationOffice?->name ?? 'N/A' }}</span>
                                </div>
                            @else
                                <span class="text-slate-600 font-medium">{{ $req->sourceOffice?->name ?? 'National / Operations' }}</span>
                            @endif
                        </td>

                        {{-- Status --}}
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $req->getStatusBadgeClass() }}">
                                {{ $req->getStatusLabel() }}
                            </span>
                        </td>

                        {{-- Submitted --}}
                        <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                            {{ $req->submitted_at?->format('M d, Y') }}
                            <span class="block text-[10px] text-slate-400 font-mono">{{ $req->submitted_at?->format('H:i') }}</span>
                        </td>

                        {{-- Action Operations Buttons (Triggers Modals on this same page) --}}
                        <td class="py-3.5 px-4 text-right">
                            <div class="inline-flex items-center gap-1.5 justify-end">
                                @php
                                    $canActOnReq = $transferService->canUserActOnCurrentStep($currentUser, $req);
                                    $stepDetails = $transferService->getCurrentStepDetails($req);
                                    $actionCategory = $stepDetails['action_category'] ?? null;
                                    $nextActorLabel = $transferService->getNextActorLabel($req);
                                @endphp

                                {{-- 1. Inspect Details Button --}}
                                <button type="button" @click='openModal("details", @json($requestData))' 
                                    class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" title="Inspect Request Details">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                </button>

                                @if($canActOnReq)
                                    {{-- Transfer Actions --}}
                                    @if($req->isTransfer())
                                        @if(in_array($actionCategory, ['departure', 'endorsement']))
                                            <button type="button" @click='openModal("departure", @json($requestData))'
                                                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-blue-500/20 transition-all flex items-center gap-1.5">
                                                <i class="fa-solid fa-plane-departure text-[10px]"></i> 
                                                {{ $actionCategory === 'endorsement' ? 'Endorse Transfer' : 'Verify Departure' }}
                                            </button>
                                            <button type="button" @click='openModal("reject", @json($requestData))'
                                                class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Decline Transfer">
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                        @elseif(in_array($actionCategory, ['clearance', 'arrival']))
                                            <button type="button" @click='openModal("arrival", @json($requestData))'
                                                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-emerald-500/20 transition-all flex items-center gap-1.5">
                                                <i class="fa-solid fa-user-check text-[10px]"></i> 
                                                {{ $actionCategory === 'clearance' ? 'Clear Arrival' : 'Confirm Arrival' }}
                                            </button>
                                            <button type="button" @click='openModal("reject", @json($requestData))'
                                                class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Decline Transfer">
                                                <i class="fa-solid fa-xmark text-xs"></i>
                                            </button>
                                        @endif
                                    @elseif($req->isPasswordRecovery() && $req->isPending())
                                        <button type="button" @click='openModal("passwordReset", @json($requestData))'
                                            class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-amber-500/20 transition-all flex items-center gap-1.5">
                                            <i class="fa-solid fa-key text-[10px]"></i> Reset Password
                                        </button>
                                        <button type="button" @click='openModal("reject", @json($requestData))'
                                            class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Decline">
                                            <i class="fa-solid fa-xmark text-xs"></i>
                                        </button>
                                    @elseif($req->isAccountBlock() && $req->isPending())
                                        <button type="button" @click='openModal("accountUnlock", @json($requestData))'
                                            class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-emerald-500/20 transition-all flex items-center gap-1.5">
                                            <i class="fa-solid fa-lock-open text-[10px]"></i> Unlock Account
                                        </button>
                                        <button type="button" @click='openModal("reject", @json($requestData))'
                                            class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Decline">
                                            <i class="fa-solid fa-xmark text-xs"></i>
                                        </button>
                                    @elseif($req->isMfaSupport() && $req->isPending())
                                        <button type="button" @click='openModal("mfa", @json($requestData))'
                                            class="px-3 py-1.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-sky-500/20 transition-all flex items-center gap-1.5">
                                            <i class="fa-solid fa-mobile-screen-button text-[10px]"></i> Reset MFA
                                        </button>
                                        <button type="button" @click='openModal("reject", @json($requestData))'
                                            class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Decline">
                                            <i class="fa-solid fa-xmark text-xs"></i>
                                        </button>
                                    @endif
                                @else
                                    {{-- Read-Only View for unauthorized or non-pending requests --}}
                                    @if($req->isPending())
                                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100/80 px-2 py-1 rounded-lg">
                                            {{ $nextActorLabel }}
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr x-show="!isAjaxLoaded">
                        <td colspan="7" class="py-16 text-center text-slate-400">
                            <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                <i class="fa-solid fa-inbox text-2xl"></i>
                            </div>
                            <h4 class="font-bold text-slate-700 text-sm">No support requests found</h4>
                            <p class="text-xs text-slate-400 mt-1">Try adjusting your filters or search keywords.</p>
                            <a href="{{ route('admin.support.index') }}" class="text-xs text-blue-600 font-bold hover:underline mt-2 inline-block">
                                Clear all active filters
                            </a>
                        </td>
                    </tr>
                    @endforelse

                    {{-- SPA Dynamic Rows (Rendered seamlessly on 3D filter card clicks without reloading) --}}
                    <template x-if="isAjaxLoaded">
                        <template x-for="req in requestsList" :key="req.id">
                            <tr class="hover:bg-blue-50/20 transition-colors">
                                {{-- Tracking ID --}}
                                <td class="py-3.5 px-4 font-mono">
                                    <button type="button" @click="openModal('details', req)" class="font-bold text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1">
                                        <span x-text="'#' + (req.public_id ? req.public_id.slice(0, 8) : '')"></span>
                                        <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-slate-400"></i>
                                    </button>
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[9px] mt-1 border" :class="req.priority_badge" x-text="req.priority ? req.priority.charAt(0).toUpperCase() + req.priority.slice(1) : ''"></span>
                                </td>

                                {{-- Applicant Officer --}}
                                <td class="py-3.5 px-4">
                                    <div class="font-black text-navy-900" x-text="req.applicant_name"></div>
                                    <div class="text-[10px] text-slate-400 font-mono flex items-center gap-1.5 mt-0.5">
                                        <span x-text="req.applicant_username"></span>
                                        <span>&bull;</span>
                                        <span class="text-blue-600 font-semibold" x-text="req.applicant_role"></span>
                                    </div>
                                </td>

                                {{-- Type & Purpose --}}
                                <td class="py-3.5 px-4 max-w-xs">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border" :class="req.type_badge" x-text="req.type_label"></span>
                                    <div class="font-bold text-navy-900 truncate mt-1 text-[11px]" :title="req.subject" x-text="req.subject"></div>
                                </td>

                                {{-- Routing / Assignment --}}
                                <td class="py-3.5 px-4 text-xs">
                                    <template x-if="req.request_type === 'org_transfer'">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-medium text-slate-700" :title="req.source_parent" x-text="req.source_office"></span>
                                            <i class="fa-solid fa-arrow-right text-[9px] text-slate-400"></i>
                                            <span class="font-bold text-emerald-700" :title="req.destination_parent" x-text="req.destination_office"></span>
                                        </div>
                                    </template>
                                    <template x-if="req.request_type !== 'org_transfer'">
                                        <span class="text-slate-600 font-medium" x-text="req.source_office"></span>
                                    </template>
                                </td>

                                {{-- Status --}}
                                <td class="py-3.5 px-4">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border" :class="req.status_badge" x-text="req.status_label"></span>
                                </td>

                                {{-- Submitted --}}
                                <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                                    <span x-text="req.submitted_at"></span>
                                </td>

                                {{-- Action Operations Buttons --}}
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <button type="button" @click="openModal('details', req)" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" title="Inspect Request Details">
                                            <i class="fa-solid fa-eye text-xs"></i>
                                        </button>

                                        <template x-if="req.can_act">
                                            <div class="inline-flex items-center gap-1.5">
                                                <template x-if="req.request_type === 'org_transfer' && ['departure', 'endorsement'].includes(req.action_category)">
                                                    <div class="inline-flex items-center gap-1.5">
                                                        <button type="button" @click="openModal('departure', req)" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-blue-500/20 transition-all flex items-center gap-1.5">
                                                            <i class="fa-solid fa-plane-departure text-[10px]"></i>
                                                            <span x-text="req.action_category === 'endorsement' ? 'Endorse Transfer' : 'Verify Departure'"></span>
                                                        </button>
                                                        <button type="button" @click="openModal('reject', req)" class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Reject Transfer">
                                                            <i class="fa-solid fa-xmark text-xs"></i>
                                                        </button>
                                                    </div>
                                                </template>

                                                <template x-if="req.request_type === 'org_transfer' && ['clearance', 'arrival'].includes(req.action_category)">
                                                    <div class="inline-flex items-center gap-1.5">
                                                        <button type="button" @click="openModal('arrival', req)" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-emerald-500/20 transition-all flex items-center gap-1.5">
                                                            <i class="fa-solid fa-user-check text-[10px]"></i>
                                                            <span x-text="req.action_category === 'clearance' ? 'Clear Arrival' : 'Confirm Arrival'"></span>
                                                        </button>
                                                        <button type="button" @click="openModal('reject', req)" class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Reject Transfer">
                                                            <i class="fa-solid fa-xmark text-xs"></i>
                                                        </button>
                                                    </div>
                                                </template>

                                                <template x-if="req.request_type === 'password_recovery' && ['submitted', 'pending_manager_review'].includes(req.current_status)">
                                                    <div class="inline-flex items-center gap-1.5">
                                                        <button type="button" @click="openModal('passwordReset', req)" class="px-3 py-1.5 bg-amber-500 hover:bg-amber-400 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-amber-500/20 transition-all flex items-center gap-1.5">
                                                            <i class="fa-solid fa-key text-[10px]"></i> Reset Password
                                                        </button>
                                                        <button type="button" @click="openModal('reject', req)" class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Decline">
                                                            <i class="fa-solid fa-xmark text-xs"></i>
                                                        </button>
                                                    </div>
                                                </template>

                                                <template x-if="req.request_type === 'account_block' && ['submitted', 'pending_manager_review'].includes(req.current_status)">
                                                    <div class="inline-flex items-center gap-1.5">
                                                        <button type="button" @click="openModal('accountUnlock', req)" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-emerald-500/20 transition-all flex items-center gap-1.5">
                                                            <i class="fa-solid fa-lock-open text-[10px]"></i> Unlock Account
                                                        </button>
                                                        <button type="button" @click="openModal('reject', req)" class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Decline">
                                                            <i class="fa-solid fa-xmark text-xs"></i>
                                                        </button>
                                                    </div>
                                                </template>

                                                <template x-if="req.request_type === 'mfa_support' && ['submitted', 'pending_manager_review'].includes(req.current_status)">
                                                    <div class="inline-flex items-center gap-1.5">
                                                        <button type="button" @click="openModal('mfa', req)" class="px-3 py-1.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl font-bold text-[11px] shadow-sm hover:shadow-sky-500/20 transition-all flex items-center gap-1.5">
                                                            <i class="fa-solid fa-mobile-screen-button text-[10px]"></i> Reset MFA
                                                        </button>
                                                        <button type="button" @click="openModal('reject', req)" class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 transition-colors" title="Decline">
                                                            <i class="fa-solid fa-xmark text-xs"></i>
                                                        </button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>

                                        <template x-if="!req.can_act && ['submitted', 'pending_source_approval', 'pending_source_zone_approval', 'pending_national_review', 'pending_destination_zone_approval', 'pending_destination_sector_approval', 'pending_destination_confirmation', 'pending_manager_review'].includes(req.current_status)">
                                            <span class="text-[10px] font-bold text-slate-400 bg-slate-100/80 px-2 py-1 rounded-lg" x-text="req.next_actor_label"></span>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </template>

                    {{-- Empty State for SPA dynamic list --}}
                    <template x-if="isAjaxLoaded && requestsList.length === 0">
                        <tr>
                            <td colspan="7" class="py-16 text-center text-slate-400">
                                <div class="w-16 h-16 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                                    <i class="fa-solid fa-inbox text-2xl"></i>
                                </div>
                                <h4 class="font-bold text-slate-700 text-sm">No support requests found</h4>
                                <p class="text-xs text-slate-400 mt-1">There are no records matching the selected filter.</p>
                                <button type="button" @click="applyCardFilter('all')" class="text-xs text-blue-600 font-bold hover:underline mt-2 inline-block">
                                    Show all requests
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        {{-- Pagination Footer --}}
        @if($requests->hasPages())
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            {{ $requests->links() }}
        </div>
        @endif
    </div>

    {{-- MODAL DIALOGS PARTIALS --}}
    @include('admin.support.modals.departure_modal')
    @include('admin.support.modals.arrival_modal')
    @include('admin.support.modals.password_reset_modal')
    @include('admin.support.modals.account_unlock_modal')
    @include('admin.support.modals.mfa_modal')
    @include('admin.support.modals.reject_modal')
    @include('admin.support.modals.details_modal')

</div>
@endsection

@extends('layouts.app')

@section('title', 'Request #' . substr($request->public_id, 0, 8) . ' - 130 NHMP Helpline')
@section('page-title', 'Support Request Details')

@php
    $requester = $request->requester ?? Auth::user();
    $requesterName = $requester->full_name ?: $requester->name ?: $requester->username;
    $words = explode(' ', trim($requesterName));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
    }
    if (empty($initials)) $initials = 'US';
@endphp

@section('content')
<div x-data="{ cancelModalOpen: false, copied: false }" class="max-w-[1440px] mx-auto pb-2">

    {{-- Main Two-Column Luxury Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

        {{-- ========================================================== --}}
        {{-- LEFT COLUMN: Request Overview, Office Route & Audit Log    --}}
        {{-- ========================================================== --}}
        <div class="lg:col-span-7 space-y-3">

            {{-- Card 1: Request Core Details --}}
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $request->getTypeBadgeClass() }}">
                            {{ $request->getTypeLabel() }}
                        </span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] border {{ $request->getPriorityBadgeClass() }}">
                            Priority: {{ ucfirst($request->priority) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-black border {{ $request->getStatusBadgeClass() }}">
                            {{ $request->getStatusLabel() }}
                        </span>
                    </div>
                </div>

                {{-- Subject Title & Public ID --}}
                <div>
                    <h2 class="text-base font-black text-navy-900 tracking-tight">{{ $request->subject }}</h2>
                    <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 font-mono">
                        <span>Public ID:</span>
                        <span class="font-bold text-slate-800 bg-slate-50 px-2 py-0.5 rounded border border-slate-200 select-all">{{ $request->public_id }}</span>
                        <button type="button" 
                            @click="navigator.clipboard.writeText('{{ $request->public_id }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="text-blue-600 hover:text-blue-800 font-sans font-bold text-[10px] flex items-center gap-1">
                            <i class="fa-solid" :class="copied ? 'fa-check text-emerald-600' : 'fa-copy'"></i>
                            <span x-text="copied ? 'Copied' : 'Copy'"></span>
                        </button>
                    </div>
                </div>

                {{-- Applicant Justification Narrative --}}
                <div>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Applicant Justification / Narrative</span>
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/80 text-xs text-slate-700 leading-relaxed whitespace-pre-line font-medium">
                        {{ $request->description }}
                    </div>
                </div>

                {{-- Transfer Specific Route Visualizer --}}
                @if($request->isTransfer())
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1">
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-xs shrink-0">
                            <i class="fa-solid fa-building"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider block">Source Unit</span>
                            <span class="text-xs font-black text-navy-900 truncate block">
                                {{ $request->sourceOffice?->name ?? 'Unassigned Scope' }}
                            </span>
                            <span class="text-[9px] text-slate-500 font-semibold">
                                Type: {{ ucfirst($request->sourceOffice?->type ?? 'National') }}
                            </span>
                        </div>
                    </div>

                    <div class="p-2.5 rounded-xl bg-emerald-50/50 border border-emerald-200 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs shrink-0">
                            <i class="fa-solid fa-location-dot"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[9px] font-bold text-emerald-700 uppercase tracking-wider block">Destination Target</span>
                            <span class="text-xs font-black text-emerald-950 truncate block">
                                {{ $request->destinationOffice?->name ?? 'Unassigned' }}
                            </span>
                            <span class="text-[9px] text-emerald-600 font-semibold">
                                Type: {{ ucfirst($request->destinationOffice?->type ?? 'Beat') }}
                            </span>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Card 2: Immutable Lifecycle Audit History Timeline --}}
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-4 space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-2">
                        <i class="fa-solid fa-timeline text-blue-600"></i>
                        Lifecycle Audit History
                    </h3>
                    <span class="text-[9px] font-bold text-slate-400 uppercase">Cryptographic Audit Trail</span>
                </div>

                <div class="relative pl-5 space-y-3.5 before:absolute before:left-2 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
                    @forelse($request->actions as $act)
                    <div class="relative flex items-start gap-3">
                        <div class="absolute -left-5 top-1 w-4 h-4 rounded-full bg-white border-2 {{ str_contains($act->action_type, 'rejected') || str_contains($act->action_type, 'cancelled') ? 'border-rose-500 text-rose-500' : (str_contains($act->action_type, 'approved') || str_contains($act->action_type, 'confirmed') || str_contains($act->action_type, 'completed') ? 'border-emerald-500 text-emerald-500' : 'border-blue-500 text-blue-500') }} flex items-center justify-center text-[7px]">
                            <i class="fa-solid fa-circle"></i>
                        </div>

                        <div class="flex-1 bg-slate-50 border border-slate-200/80 rounded-xl p-3 space-y-1.5">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold border {{ $act->getActionBadgeClass() }}">
                                        {{ $act->getActionLabel() }}
                                    </span>
                                    <span class="text-[11px] font-black text-navy-900">
                                        {{ $act->actor?->full_name ?? 'System' }} ({{ $act->actor?->username ?? 'Automated' }})
                                    </span>
                                </div>
                                <span class="text-[10px] text-slate-400 font-mono">
                                    {{ $act->created_at->format('M d, Y H:i:s') }}
                                </span>
                            </div>

                            @if($act->remarks)
                            <p class="text-xs text-slate-700 bg-white p-2 rounded-lg border border-slate-100 font-medium">
                                {{ $act->remarks }}
                            </p>
                            @endif

                            <div class="flex items-center gap-3 text-[10px] text-slate-400 pt-1 border-t border-slate-200/50">
                                <span><i class="fa-solid fa-shield text-blue-500 text-[9px]"></i> {{ $act->getAuthMethodLabel() }}</span>
                                @if($act->ip_address)
                                    <span>&bull; IP: {{ $act->ip_address }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4 text-xs text-slate-400">
                        No audit actions recorded yet.
                    </div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- ========================================================== --}}
        {{-- RIGHT COLUMN: Sequence Stepper & Dark Luxury Console       --}}
        {{-- ========================================================== --}}
        <div class="lg:col-span-5 space-y-3">

            {{-- Card 1: Transfer Workflow Sequence Stepper --}}
            @if($request->isTransfer())
            <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-3.5 space-y-2.5">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                        Transfer Workflow Sequence
                    </span>
                    <span class="text-[9px] font-black text-blue-600 uppercase tracking-wider">
                        3-Stage Chain
                    </span>
                </div>

                <div class="space-y-2">
                    {{-- Step 1 --}}
                    <div class="p-2.5 rounded-xl border flex items-center justify-between {{ $request->current_status !== 'submitted' ? 'border-emerald-200 bg-emerald-50/40 text-emerald-800' : 'border-blue-300 bg-blue-50/50 text-blue-900' }}">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-black {{ $request->current_status !== 'submitted' ? 'bg-emerald-600 text-white' : 'bg-blue-600 text-white' }}">
                                <i class="fa-solid fa-check"></i>
                            </span>
                            <div>
                                <span class="text-xs font-black block">Stage 1: Submitted</span>
                                <span class="text-[10px] text-slate-500 block">Applicant initiated transfer with step-up credentials</span>
                            </div>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ $request->current_status !== 'submitted' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                            {{ $request->current_status !== 'submitted' ? 'Passed' : 'Active' }}
                        </span>
                    </div>

                    {{-- Step 2 --}}
                    <div class="p-2.5 rounded-xl border flex items-center justify-between {{ in_array($request->current_status, ['pending_destination_confirmation', 'completed']) ? 'border-emerald-200 bg-emerald-50/40 text-emerald-800' : ($request->current_status === 'pending_source_approval' ? 'border-amber-300 bg-amber-50/60 text-amber-900 ring-1 ring-amber-400/30' : 'border-slate-200 bg-slate-50 text-slate-400') }}">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-black {{ in_array($request->current_status, ['pending_destination_confirmation', 'completed']) ? 'bg-emerald-600 text-white' : ($request->current_status === 'pending_source_approval' ? 'bg-amber-500 text-white animate-pulse' : 'bg-slate-200 text-slate-500') }}">
                                <i class="fa-solid {{ in_array($request->current_status, ['pending_destination_confirmation', 'completed']) ? 'fa-check' : ($request->current_status === 'pending_source_approval' ? 'fa-hourglass-half' : 'fa-lock') }}"></i>
                            </span>
                            <div>
                                <span class="text-xs font-black block">Stage 2: Source Verification</span>
                                <span class="text-[10px] text-slate-500 block">Sector Wireless Incharge reviews and authorizes departure</span>
                            </div>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ in_array($request->current_status, ['pending_destination_confirmation', 'completed']) ? 'bg-emerald-100 text-emerald-800' : ($request->current_status === 'pending_source_approval' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-400') }}">
                            {{ in_array($request->current_status, ['pending_destination_confirmation', 'completed']) ? 'Authorized' : ($request->current_status === 'pending_source_approval' ? 'In Progress' : 'Pending') }}
                        </span>
                    </div>

                    {{-- Step 3 --}}
                    <div class="p-2.5 rounded-xl border flex items-center justify-between {{ $request->current_status === 'completed' ? 'border-emerald-200 bg-emerald-50/40 text-emerald-800' : ($request->current_status === 'pending_destination_confirmation' ? 'border-indigo-300 bg-indigo-50/60 text-indigo-900 ring-1 ring-indigo-400/30' : 'border-slate-200 bg-slate-50 text-slate-400') }}">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg flex items-center justify-center text-xs font-black {{ $request->current_status === 'completed' ? 'bg-emerald-600 text-white' : ($request->current_status === 'pending_destination_confirmation' ? 'bg-indigo-600 text-white animate-pulse' : 'bg-slate-200 text-slate-500') }}">
                                <i class="fa-solid {{ $request->current_status === 'completed' ? 'fa-check' : ($request->current_status === 'pending_destination_confirmation' ? 'fa-truck-fast' : 'fa-lock') }}"></i>
                            </span>
                            <div>
                                <span class="text-xs font-black block">Stage 3: Destination Arrival</span>
                                <span class="text-[10px] text-slate-500 block">Receiving Beat confirms arrival &amp; auto-syncs role scope</span>
                            </div>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full {{ $request->current_status === 'completed' ? 'bg-emerald-100 text-emerald-800' : ($request->current_status === 'pending_destination_confirmation' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-400') }}">
                            {{ $request->current_status === 'completed' ? 'Completed' : ($request->current_status === 'pending_destination_confirmation' ? 'Awaiting Arrival' : 'Pending') }}
                        </span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Card 2: DARK LUXURY TELEMETRY & ACTION CONSOLE --}}
            <div class="bg-[#0A101D] text-white rounded-2xl p-4 border border-slate-800 shadow-2xl relative overflow-hidden space-y-3">
                
                {{-- Ambient Background Glow --}}
                <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -left-10 -top-10 w-40 h-40 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

                {{-- Console Header --}}
                <div class="relative z-10 flex items-center justify-between border-b border-slate-800 pb-2">
                    <span class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-400">
                        Request Telemetry Console
                    </span>
                    <div class="flex items-center gap-1.5 text-[9px] font-bold text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                        LIVE SYNC
                    </div>
                </div>

                {{-- Requester Profile Card --}}
                <div class="relative z-10 flex items-center gap-2.5 p-2 rounded-xl bg-slate-900/90 border border-slate-800">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 text-white font-black text-xs flex items-center justify-center shadow-md shadow-blue-500/30 shrink-0">
                        {{ $initials }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="text-xs font-black text-white truncate">{{ $requesterName }}</span>
                            <span class="text-[9px] font-mono text-slate-400">({{ $requester->username }})</span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-blue-500/20 text-blue-300 border border-blue-500/30">
                                {{ $request->getTypeLabel() }}
                            </span>
                            <span class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-slate-800 text-slate-300">
                                {{ $requester->getRoleTitle() }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Telemetry Data Table --}}
                <div class="relative z-10 space-y-1.5 text-[11px] font-medium">
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Current Status:</span>
                        <span class="font-bold text-amber-400">{{ $request->getStatusLabel() }}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Submitted At:</span>
                        <span class="text-slate-200 font-mono text-[10px]">{{ $request->submitted_at?->format('M d, Y H:i:s') ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Source Station:</span>
                        <span class="text-slate-200 font-semibold">{{ $request->sourceOffice?->name ?? 'National Scope' }}</span>
                    </div>
                    @if($request->destinationOffice)
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Target Beat:</span>
                        <span class="font-bold text-emerald-400 truncate max-w-[180px]">{{ $request->destinationOffice->name }}</span>
                    </div>
                    @endif
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Assigned Approver:</span>
                        <span class="text-slate-200 font-semibold truncate max-w-[180px]">
                            {{ $request->assignedApprover?->full_name ?? 'Automated Queue' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-slate-400">
                        <span>Chain Security:</span>
                        <span class="text-emerald-400 font-bold flex items-center gap-1 text-[10px]">
                            <i class="fa-solid fa-lock text-[8px]"></i> Cryptographic Logged
                        </span>
                    </div>
                </div>

                {{-- Actions Area --}}
                <div class="relative z-10 pt-1 space-y-2">
                    @if($request->canBeCancelledBy(Auth::user()))
                    <button type="button" @click="cancelModalOpen = true"
                        class="w-full py-2.5 px-4 rounded-xl font-black text-xs uppercase tracking-wider text-white bg-gradient-to-r from-rose-600 via-rose-700 to-red-800 hover:from-rose-500 hover:to-red-700 shadow-lg shadow-rose-600/30 transition-all flex items-center justify-center gap-2 cursor-pointer hover:scale-[1.01] active:scale-[0.99]">
                        <i class="fa-solid fa-ban text-xs"></i>
                        <span>Cancel Support Request</span>
                    </button>
                    @endif

                    <a href="{{ route('portal.support.index') }}" 
                        class="w-full py-2 px-4 rounded-xl font-bold text-xs text-center text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 transition-colors block">
                        <i class="fa-solid fa-arrow-left mr-1.5 text-[10px]"></i> Return to Support Center
                    </a>
                </div>

            </div>

        </div>

    </div>

    {{-- Cancellation Modal (Teleported to body with z-[99999] backdrop-blur-md) --}}
    @if($request->canBeCancelledBy(Auth::user()))
    <template x-teleport="body">
        <div x-show="cancelModalOpen" x-cloak
             class="fixed inset-0 z-[99999] overflow-y-auto bg-white/70 backdrop-blur-md flex items-center justify-center p-4"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">
            <div @click.away="cancelModalOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-[0_25px_70px_rgba(15,23,42,0.25)] border border-slate-200/80 space-y-4 relative overflow-hidden">
                <div class="flex items-center gap-3 text-rose-600">
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600 text-lg">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-navy-900">Cancel Support Request</h3>
                        <p class="text-[11px] text-slate-500">Voluntary Cancellation Authorization</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    Are you sure you want to cancel this pending request? This will mark the request as cancelled and archive it permanently in the audit trail.
                </p>

                <form method="POST" action="{{ route('portal.support.cancel', $request) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Cancellation Reason <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="cancellation_reason" rows="2" required placeholder="State reason for voluntary cancellation"
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-rose-500 focus:ring-rose-500 p-2.5"></textarea>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-1">
                        <button type="button" @click="cancelModalOpen = false" 
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                            Keep Request
                        </button>
                        <button type="submit" 
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-lg shadow-rose-500/25 transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-ban"></i> Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>
    @endif

</div>
@endsection

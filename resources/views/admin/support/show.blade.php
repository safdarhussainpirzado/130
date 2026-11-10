@extends('layouts.app')

@section('title', 'Review Request #' . substr($request->public_id, 0, 8) . ' - 130 NHMP Helpline')
@section('page-title', 'Support Request Review')

@section('content')
<div x-data="{
    activeModal: null, // 'approveSource', 'confirmDest', 'reject', 'unlock', 'issueToken', 'resolveGeneral'
    modalTitle: '',
    modalActionUrl: '',
    modalButtonText: 'Confirm & Authorize',
    modalButtonClass: 'bg-blue-600 hover:bg-blue-700',
    openAction(type, title, url, btnText, btnClass) {
        this.activeModal = type;
        this.modalTitle = title;
        this.modalActionUrl = url;
        this.modalButtonText = btnText || 'Confirm & Authorize';
        this.modalButtonClass = btnClass || 'bg-blue-600 hover:bg-blue-700';
    }
}" class="max-w-6xl mx-auto space-y-6">

    {{-- Breadcrumb & Top Bar --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('admin.support.requests.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-blue-600 transition-colors">
            <i class="fa-solid fa-arrow-left"></i> Back to Requests Queue
        </a>

        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-400 font-mono">UUID: {{ $request->public_id }}</span>
        </div>
    </div>

    {{-- Flash Token Display (For newly authorized password recovery) --}}
    @if(session('status_token'))
    <div class="p-6 rounded-2xl bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-white border-2 border-amber-300 space-y-3 shadow-lg">
        <div class="flex items-center gap-2 text-amber-800 font-black text-sm">
            <i class="fa-solid fa-key text-amber-600 text-lg"></i> Single-Use Password Reset Token Issued
        </div>
        <p class="text-xs text-slate-700">
            Provide the following high-entropy token or direct recovery link to the verified officer. Passwords are <strong>never</strong> shown to administrators; the user will establish their own new password.
        </p>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-1">
            <div class="p-3 bg-white rounded-xl border border-amber-200">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Plaintext Single-Use Token</span>
                <span class="font-mono text-xs font-black text-amber-900 select-all block mt-1 break-all">{{ session('status_token') }}</span>
            </div>

            <div class="p-3 bg-white rounded-xl border border-amber-200">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Direct Reset Link</span>
                <span class="font-mono text-xs font-bold text-blue-700 select-all block mt-1 break-all">{{ session('reset_url') }}</span>
            </div>
        </div>
    </div>
    @endif

    {{-- Request Overview Header Card --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $request->getTypeBadgeClass() }}">
                        {{ $request->getTypeLabel() }}
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] border {{ $request->getPriorityBadgeClass() }}">
                        Priority: {{ ucfirst($request->priority) }}
                    </span>
                </div>
                <h1 class="text-xl font-black text-navy-900 tracking-tight">{{ $request->subject }}</h1>
                <div class="text-xs text-slate-400 font-mono mt-1">
                    Public Request ID: <span class="font-bold text-slate-700 select-all">{{ $request->public_id }}</span>
                </div>
            </div>

            <div class="flex flex-col items-start md:items-end">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Workflow State</span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold border mt-1 {{ $request->getStatusBadgeClass() }}">
                    {{ $request->getStatusLabel() }}
                </span>
                <span class="text-[10px] text-slate-400 mt-1">
                    Submitted: {{ $request->submitted_at?->format('M d, Y H:i:s') }}
                </span>
            </div>
        </div>

        {{-- Applicant & Assignment Profile Cards --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- Applicant Details --}}
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Applicant Profile</span>
                <div class="font-black text-navy-900 text-sm">{{ $request->requester?->full_name }}</div>
                <div class="text-xs text-slate-500 font-mono">{{ $request->requester?->username }}</div>
                <div class="text-[11px] text-blue-600 font-bold mt-1">{{ $request->requester?->getRoleTitle() }}</div>
                <div class="text-[10px] text-slate-400">Email: {{ $request->requester?->email }}</div>
            </div>

            {{-- Source Office --}}
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Current Source Unit</span>
                <div class="font-black text-navy-900 text-sm">{{ $request->sourceOffice?->name ?? 'National / Unassigned' }}</div>
                <div class="text-xs text-slate-500 uppercase font-bold text-[10px]">
                    Type: {{ $request->sourceOffice?->type ?? 'National' }}
                </div>
                @if($request->sourceOffice?->parent)
                    <div class="text-[10px] text-slate-400">Parent: {{ $request->sourceOffice->parent->name }}</div>
                @endif
            </div>

            {{-- Destination Office (for transfers) --}}
            <div class="p-4 rounded-xl {{ $request->destinationOffice ? 'bg-emerald-50/50 border border-emerald-200' : 'bg-slate-50 border border-slate-200' }} space-y-1">
                <span class="text-[10px] font-bold {{ $request->destinationOffice ? 'text-emerald-700' : 'text-slate-400' }} uppercase tracking-wider block">Requested Destination</span>
                <div class="font-black text-navy-900 text-sm">{{ $request->destinationOffice?->name ?? 'N/A (Account Issue)' }}</div>
                @if($request->destinationOffice)
                    <div class="text-xs text-emerald-600 uppercase font-bold text-[10px]">
                        Type: {{ $request->destinationOffice->type }}
                    </div>
                    @if($request->destinationOffice->parent)
                        <div class="text-[10px] text-slate-500">Parent: {{ $request->destinationOffice->parent->name }}</div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Request Narrative --}}
        <div>
            <h4 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-1">Request Description / Operational Justification</h4>
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 leading-relaxed whitespace-pre-line">
                {{ $request->description }}
            </div>
        </div>
    </div>

    {{-- Operational Action Command Center --}}
    @if($request->isPending())
    <div class="bg-gradient-to-r from-blue-900 via-indigo-950 to-slate-900 rounded-2xl p-6 text-white shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="font-black text-base flex items-center gap-2">
                    <i class="fa-solid fa-stamp text-teal-400"></i> Operational Decision & Action Panel
                </h3>
                <p class="text-xs text-slate-300 mt-0.5">Execute authorized stage verification, arrival confirmation, or administrative resolution.</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-white/10 border border-white/20 text-white">
                Requires Step-Up Authentication
            </span>
        </div>

        <div class="flex flex-wrap items-center gap-3 pt-2">
            {{-- Stage 1: Source Verification Actions --}}
            @if($request->isTransfer() && in_array($request->current_status, ['submitted', 'pending_source_approval', 'pending_manager_review']) && $canApproveSource)
                <button type="button"
                    @click="openAction('approveSource', 'Approve Source Verification', '{{ route('admin.support.requests.approveSource', $request) }}', 'Approve & Forward', 'bg-emerald-600 hover:bg-emerald-700')"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-check"></i> Approve Departure (Stage 1)
                </button>

                <button type="button"
                    @click="openAction('reject', 'Reject Transfer Request', '{{ route('admin.support.requests.reject', $request) }}', 'Reject Request', 'bg-rose-600 hover:bg-rose-700')"
                    class="px-4 py-2.5 bg-rose-600/30 hover:bg-rose-600 border border-rose-500/40 text-rose-200 hover:text-white rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                    <i class="fa-solid fa-xmark"></i> Reject Departure
                </button>
            @endif

            {{-- Stage 2: Destination Arrival Confirmation Actions --}}
            @if($request->isTransfer() && $request->current_status === 'pending_destination_confirmation' && $canConfirmDestination)
                <button type="button"
                    @click="openAction('confirmDest', 'Confirm Destination Arrival & Execute Assignment', '{{ route('admin.support.requests.confirmDestination', $request) }}', 'Confirm Arrival & Update Assignment', 'bg-emerald-600 hover:bg-emerald-700')"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-user-check"></i> Confirm Physical Arrival (Stage 2)
                </button>

                <button type="button"
                    @click="openAction('reject', 'Reject Destination Arrival', '{{ route('admin.support.requests.reject', $request) }}', 'Reject Arrival', 'bg-rose-600 hover:bg-rose-700')"
                    class="px-4 py-2.5 bg-rose-600/30 hover:bg-rose-600 border border-rose-500/40 text-rose-200 hover:text-white rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                    <i class="fa-solid fa-xmark"></i> Reject Arrival
                </button>
            @endif

            {{-- Emergency Account Unlock --}}
            @if($request->isAccountBlock() && $isManager)
                <button type="button"
                    @click="openAction('unlock', 'Authorize & Unlock Account', '{{ route('admin.support.requests.unlock', $request) }}', 'Unlock & Restore Access', 'bg-emerald-600 hover:bg-emerald-700')"
                    class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-lock-open"></i> Unlock & Restore Account
                </button>
            @endif

            {{-- Password Recovery Authorization --}}
            @if($request->isPasswordRecovery() && $isManager)
                <button type="button"
                    @click="openAction('issueToken', 'Authorize Password Recovery Token', '{{ route('admin.support.requests.issuePasswordToken', $request) }}', 'Issue Single-Use Token', 'bg-amber-600 hover:bg-amber-700')"
                    class="px-4 py-2.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl font-bold text-xs shadow-md transition-all flex items-center gap-2">
                    <i class="fa-solid fa-key"></i> Issue Password Reset Token
                </button>
            @endif

            {{-- General Other Account Issue Actions --}}
            @if(!$request->isTransfer() && $isManager)
                <button type="button"
                    @click="openAction('reject', 'Decline Request', '{{ route('admin.support.requests.reject', $request) }}', 'Decline Request', 'bg-rose-600 hover:bg-rose-700')"
                    class="px-4 py-2.5 bg-rose-600/30 hover:bg-rose-600 border border-rose-500/40 text-rose-200 hover:text-white rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                    <i class="fa-solid fa-xmark"></i> Decline Request
                </button>
            @endif
        </div>
    </div>
    @endif

    {{-- Immutable Audit Timeline of Actions --}}
    <div class="bg-white rounded-2xl border border-slate-200 p-6 shadow-sm space-y-4">
        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Complete Lifecycle Audit History</h3>

        <div class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200">
            @foreach($request->actions as $act)
            <div class="relative flex items-start gap-4">
                <div class="absolute -left-6 top-1 w-5 h-5 rounded-full bg-white border-2 {{ str_contains($act->action_type, 'rejected') ? 'border-rose-500 text-rose-500' : (str_contains($act->action_type, 'approved') || str_contains($act->action_type, 'confirmed') || str_contains($act->action_type, 'unlocked') ? 'border-emerald-500 text-emerald-500' : 'border-blue-500 text-blue-500') }} flex items-center justify-center text-[8px]">
                    <i class="fa-solid fa-circle"></i>
                </div>

                <div class="flex-1 bg-slate-50 border border-slate-200/80 rounded-xl p-4 space-y-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border {{ $act->getActionBadgeClass() }}">
                                {{ $act->getActionLabel() }}
                            </span>
                            <span class="text-xs font-black text-navy-900">
                                {{ $act->actor?->full_name ?? 'System' }} ({{ $act->actor?->username ?? 'Automated' }})
                            </span>
                            <span class="text-[10px] text-slate-400 font-bold">
                                [{{ $act->actor?->getRoleTitle() ?? 'System' }}]
                            </span>
                        </div>
                        <span class="text-[10px] text-slate-400 font-mono">
                            {{ $act->created_at->format('M d, Y H:i:s') }}
                        </span>
                    </div>

                    @if($act->remarks)
                    <p class="text-xs text-slate-700 bg-white p-2.5 rounded-lg border border-slate-100 font-sans">
                        {{ $act->remarks }}
                    </p>
                    @endif

                    <div class="flex items-center gap-3 text-[10px] text-slate-400 pt-1 border-t border-slate-200/50">
                        <span><i class="fa-solid fa-shield text-blue-500"></i> {{ $act->getAuthMethodLabel() }}</span>
                        @if($act->ip_address)
                            <span>&bull; IP: {{ $act->ip_address }}</span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Reusable Step-Up Authentication Modal --}}
    <template x-teleport="body">
        <div x-show="activeModal !== null" x-cloak
             class="fixed inset-0 z-[99999] overflow-y-auto bg-white/70 backdrop-blur-md flex items-center justify-center p-4"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            <div @click.away="activeModal = null" 
                 class="bg-white rounded-3xl max-w-lg w-full p-6 sm:p-8 shadow-[0_25px_70px_rgba(15,23,42,0.25)] space-y-4 border border-slate-200/80 relative overflow-hidden">

                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h3 class="text-base font-black text-navy-900" x-text="modalTitle"></h3>
                    <button type="button" @click="activeModal = null" class="text-slate-400 hover:text-slate-600">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>

                <form :action="modalActionUrl" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Decision Remarks / Verification Notes <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="remarks" rows="2" required placeholder="Enter remarks, posting order reference, or justification"
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    {{-- Step-up credentials --}}
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-navy-900">
                            <i class="fa-solid fa-lock text-blue-600"></i> Approver Step-Up Re-Authentication
                        </div>
                        <p class="text-[10px] text-slate-500 leading-tight">
                            Every approval and workflow transition requires personal authentication of the deciding officer.
                        </p>

                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                Your Current Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" name="password" required autocomplete="current-password" placeholder="Enter your current password"
                                class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        @if(Auth::user()->hasMfaEnrolled())
                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                Your Authenticator TOTP Code <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000"
                                class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500 font-mono tracking-widest text-center text-sm font-bold">
                        </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="activeModal = null" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100">
                            Cancel
                        </button>
                        <button type="submit" :class="modalButtonClass" class="px-5 py-2 text-white rounded-xl text-xs font-bold shadow-md transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-shield-check"></i> <span x-text="modalButtonText"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection

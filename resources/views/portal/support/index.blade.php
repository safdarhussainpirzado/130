@extends('layouts.app')

@section('title', 'Self-Service Support Center - 130 NHMP Helpline')
@section('page-title', 'Support Center & Self-Service')

@section('content')
<div x-data="{ lockModalOpen: false }" class="space-y-6">

    {{-- Top Action & Welcome Banner --}}
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-blue-950 rounded-2xl p-6 text-white shadow-xl relative overflow-hidden">
        <div class="absolute -right-10 -bottom-10 opacity-10 pointer-events-none">
            <i class="fa-solid fa-headset text-9xl"></i>
        </div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 text-xs font-bold text-blue-400 uppercase tracking-widest mb-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    NHMP Operational Self-Service
                </div>
                <h1 class="text-2xl font-black tracking-tight">Support & Transfer Portal</h1>
                <p class="text-sm text-slate-300 mt-1 max-w-2xl">
                    Submit and track organizational transfers, report account issues, request MFA assistance, or initiate emergency account blocking with full audit integrity.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('portal.support.create') }}" 
                    class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl font-bold text-xs shadow-lg shadow-blue-500/25 transition-all flex items-center gap-2">
                    <i class="fa-solid fa-plus"></i> New Request
                </a>

                <button @click="lockModalOpen = true" type="button"
                    class="px-4 py-2.5 bg-rose-600/20 hover:bg-rose-600 border border-rose-500/30 text-rose-300 hover:text-white rounded-xl font-bold text-xs transition-all flex items-center gap-2">
                    <i class="fa-solid fa-shield-halved"></i> Lock My Account
                </button>
            </div>
        </div>
    </div>

    {{-- KPI Status Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200/80 p-4 shadow-sm">
            <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Total Submitted</div>
            <div class="text-2xl font-black text-navy-900 mt-1">{{ number_format($counts['total']) }}</div>
        </div>

        <div class="bg-white rounded-xl border border-amber-200/80 p-4 shadow-sm bg-gradient-to-br from-white to-amber-50/30">
            <div class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">In Progress / Pending</div>
            <div class="text-2xl font-black text-amber-700 mt-1">{{ number_format($counts['pending']) }}</div>
        </div>

        <div class="bg-white rounded-xl border border-emerald-200/80 p-4 shadow-sm bg-gradient-to-br from-white to-emerald-50/30">
            <div class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider">Completed / Approved</div>
            <div class="text-2xl font-black text-emerald-700 mt-1">{{ number_format($counts['completed']) }}</div>
        </div>

        <div class="bg-white rounded-xl border border-rose-200/80 p-4 shadow-sm bg-gradient-to-br from-white to-rose-50/30">
            <div class="text-[10px] font-bold text-rose-600 uppercase tracking-wider">Rejected</div>
            <div class="text-2xl font-black text-rose-700 mt-1">{{ number_format($counts['rejected']) }}</div>
        </div>
    </div>

    {{-- Filter Tabs --}}
    <div class="bg-white rounded-xl border border-slate-200 p-2 flex flex-wrap items-center gap-2 text-xs">
        <a href="{{ route('portal.support.index') }}" 
            class="px-3 py-1.5 rounded-lg font-bold transition-all {{ empty($typeFilter) && empty($statusFilter) ? 'bg-navy-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            All Requests
        </a>
        <a href="{{ route('portal.support.index', ['status' => 'pending_source_approval']) }}" 
            class="px-3 py-1.5 rounded-lg font-bold transition-all {{ $statusFilter === 'pending_source_approval' ? 'bg-amber-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            Pending Source
        </a>
        <a href="{{ route('portal.support.index', ['status' => 'pending_destination_confirmation']) }}" 
            class="px-3 py-1.5 rounded-lg font-bold transition-all {{ $statusFilter === 'pending_destination_confirmation' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            Pending Destination
        </a>
        <a href="{{ route('portal.support.index', ['type' => 'org_transfer']) }}" 
            class="px-3 py-1.5 rounded-lg font-bold transition-all {{ $typeFilter === 'org_transfer' ? 'bg-emerald-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            Transfers
        </a>
        <a href="{{ route('portal.support.index', ['type' => 'mfa_support']) }}" 
            class="px-3 py-1.5 rounded-lg font-bold transition-all {{ $typeFilter === 'mfa_support' ? 'bg-cyan-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            MFA Issues
        </a>
        <a href="{{ route('portal.support.index', ['type' => 'password_recovery']) }}" 
            class="px-3 py-1.5 rounded-lg font-bold transition-all {{ $typeFilter === 'password_recovery' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
            Password Recovery
        </a>
    </div>

    {{-- Requests Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                    <tr>
                        <th class="py-3 px-4">Request Public ID</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Subject / Details</th>
                        <th class="py-3 px-4">Current Stage</th>
                        <th class="py-3 px-4">Priority</th>
                        <th class="py-3 px-4">Submitted</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requests as $req)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        <td class="py-3.5 px-4 font-mono font-bold text-navy-900">
                            <a href="{{ route('portal.support.show', $req) }}" class="text-blue-600 hover:underline">
                                {{ substr($req->public_id, 0, 8) }}...{{ substr($req->public_id, -4) }}
                            </a>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $req->getTypeBadgeClass() }}">
                                {{ $req->getTypeLabel() }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 max-w-xs">
                            <div class="font-bold text-navy-900 truncate">{{ $req->subject }}</div>
                            @if($req->isTransfer() && $req->destinationOffice)
                                <div class="text-[10px] text-slate-500 flex items-center gap-1 mt-0.5">
                                    <span>{{ $req->sourceOffice?->name ?? 'Unassigned' }}</span>
                                    <i class="fa-solid fa-arrow-right text-[8px] text-slate-400"></i>
                                    <span class="font-bold text-emerald-600">{{ $req->destinationOffice->name }}</span>
                                </div>
                            @endif
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold border {{ $req->getStatusBadgeClass() }}">
                                {{ $req->getStatusLabel() }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] border {{ $req->getPriorityBadgeClass() }}">
                                {{ ucfirst($req->priority) }}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-slate-500 text-[11px]">
                            {{ $req->submitted_at?->format('M d, Y H:i') ?? '—' }}
                        </td>
                        <td class="py-3.5 px-4 text-right">
                            <a href="{{ route('portal.support.show', $req) }}" 
                                class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 hover:bg-blue-600 hover:text-white rounded-lg text-slate-700 font-bold text-[11px] transition-colors">
                                View <i class="fa-solid fa-chevron-right text-[9px]"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-inbox text-4xl mb-3 block text-slate-300"></i>
                            <p class="font-bold text-slate-600">No support requests found</p>
                            <p class="text-xs text-slate-400 mt-1">Submit an organizational transfer or account support request above.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
        <div class="p-4 border-t border-slate-100 bg-slate-50/50">
            {{ $requests->links() }}
        </div>
        @endif
    </div>

    {{-- Emergency Account Lock Modal --}}
    <template x-teleport="body">
        <div x-show="lockModalOpen" 
             x-cloak 
             class="fixed inset-0 z-[99999] overflow-y-auto bg-white/70 backdrop-blur-md flex items-center justify-center p-4"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            <div @click.away="lockModalOpen = false" 
                 class="bg-white rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-[0_25px_70px_rgba(15,23,42,0.25)] border border-slate-200/80 space-y-4 relative overflow-hidden">

                <div class="flex items-center gap-3 text-rose-600">
                    <div class="w-10 h-10 rounded-2xl bg-rose-50 flex items-center justify-center text-rose-600 text-lg">
                        <i class="fa-solid fa-user-slash"></i>
                    </div>
                    <div>
                        <h3 class="font-black text-navy-900 text-base">Lock My Account Immediately</h3>
                        <p class="text-[11px] text-slate-500">Emergency Security Self-Service</p>
                    </div>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    If you suspect your credentials have been compromised or unauthorized activity is detected, this action will <strong>instantly terminate all active sessions</strong>, invalidate access, and prevent subsequent logins. Only an authorized Operation Manager can unlock your account after identity verification.
                </p>

                <form method="POST" action="{{ route('portal.support.lock-account') }}" class="space-y-4">
                    @csrf
                    <input type="text" name="username" value="{{ Auth::user()->username }}" autocomplete="username" class="sr-only" tabindex="-1" aria-hidden="true">

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Reason for Emergency Lock <span class="text-rose-500">*</span></label>
                        <textarea name="reason" rows="2" required placeholder="Describe reason (e.g., suspicious activity, lost phone, unauthorized alerts)"
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-rose-500 focus:ring-rose-500"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Current Password (Step-Up Verification) <span class="text-rose-500">*</span></label>
                        <input type="password" name="password" required autocomplete="current-password" placeholder="Enter your current password"
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-rose-500 focus:ring-rose-500">
                    </div>

                    @if(Auth::user()->hasMfaEnrolled())
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">Authenticator Code (TOTP 6-Digits) <span class="text-rose-500">*</span></label>
                        <input type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000"
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-rose-500 focus:ring-rose-500 font-mono tracking-widest text-center text-sm font-bold">
                    </div>
                    @endif

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" @click="lockModalOpen = false"
                            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                            Cancel
                        </button>
                        <button type="submit"
                            class="px-4 py-2 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-lg shadow-rose-500/25 transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-lock"></i> Confirm & Lock Immediately
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </template>

</div>
@endsection

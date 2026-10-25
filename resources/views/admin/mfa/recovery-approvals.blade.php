@extends('layouts.app')

@section('title', 'MFA Recovery & Authorizations - NHMP 130')
@section('page-title', 'MFA Recovery Queue & Dual-Control Approvals')

@section('content')
<div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 font-inter">
    @if (session('status'))
        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-xs flex items-center justify-between">
            <span>{{ session('status') }}</span>
        </div>
    @endif
    @if (session('raw_token'))
        <div class="mb-6 p-6 rounded-3xl bg-blue-500/20 border border-blue-500/40 text-white shadow-2xl relative">
            <span class="text-[10px] font-black uppercase tracking-widest text-cyan-300 block mb-1">New Enrollment Authorization Token Generated</span>
            <p class="text-xs text-slate-200 mb-3">Copy this single-use authorization token and securely deliver it to the staff member. It expires in 10 minutes and will NOT be shown again:</p>
            <div class="flex items-center space-x-3 bg-[#0B132B] p-3 rounded-2xl border border-blue-500/40">
                <code class="font-mono text-sm text-cyan-300 font-bold select-all flex-1 break-all">{{ session('raw_token') }}</code>
            </div>
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-6 p-4 rounded-2xl bg-red-500/15 border border-red-500/30 text-red-200 text-xs">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-8">
        <!-- Pending Approvals (Dual Control) -->
        <div class="bg-[#1C2541] border border-slate-700/60 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="flex items-center justify-between pb-6 border-b border-slate-700/60">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-amber-400">Governance &amp; Dual Control</span>
                    <h2 class="text-xl font-black text-white mt-1">Pending Lost-Device Recovery Requests</h2>
                    <p class="text-xs text-slate-400 mt-1">Privileged accounts require independent approval from two distinct administrators before an enrollment token is issued.</p>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-700/60 text-slate-400 text-[10px] uppercase tracking-wider font-bold">
                            <th class="py-3 px-4">Staff Member</th>
                            <th class="py-3 px-4">Role / Level</th>
                            <th class="py-3 px-4">Reason</th>
                            <th class="py-3 px-4">Approval State</th>
                            <th class="py-3 px-4">Expires</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60 text-slate-300">
                        @forelse ($pendingApprovals as $approval)
                            <tr class="hover:bg-slate-800/30 transition-colors">
                                <td class="py-4 px-4 font-bold text-white">
                                    {{ $approval->user->name ?? 'Unknown' }}
                                    <span class="block text-[11px] font-mono text-slate-400 font-normal">{{ $approval->user->username ?? '' }}</span>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $approval->user->isPrivileged() ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-blue-500/20 text-blue-300 border border-blue-500/30' }}">
                                        {{ $approval->user->role->name ?? 'Staff' }}
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-slate-300 max-w-xs truncate" title="{{ $approval->reason }}">
                                    {{ $approval->reason }}
                                </td>
                                <td class="py-4 px-4">
                                    @if ($approval->requiresDualControl())
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono font-bold {{ $approval->approver1_id ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700/60 text-slate-400' }}">
                                                1: {{ $approval->approver1->username ?? 'Pending' }}
                                            </span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono font-bold {{ $approval->approver2_id ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700/60 text-slate-400' }}">
                                                2: {{ $approval->approver2->username ?? 'Pending' }}
                                            </span>
                                        </div>
                                    @else
                                        <span class="text-[10px] text-slate-400">Single Approval Required</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 font-mono text-slate-400">
                                    {{ \Carbon\Carbon::parse($approval->expires_at)->diffForHumans() }}
                                </td>
                                <td class="py-4 px-4 text-right space-x-2">
                                    @if ($approval->user_id !== $currentUser->id && $approval->approver1_id !== $currentUser->id)
                                        <form method="POST" action="{{ route('admin.mfa.recovery.approve', $approval) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition-all">
                                                Approve
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.mfa.recovery.reject', $approval) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 bg-red-600/60 hover:bg-red-600 text-white rounded-lg text-xs font-bold transition-all">
                                            Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500">
                                    No pending recovery approval requests.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($pendingApprovals->hasPages())
                <div class="mt-4">
                    {{ $pendingApprovals->links() }}
                </div>
            @endif
        </div>

        <!-- Issue Direct Single-Use Enrollment Token (For Staff Onboarding) -->
        <div class="bg-[#1C2541] border border-slate-700/60 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="flex items-center space-x-4 pb-6 border-b border-slate-700/60">
                <div class="w-10 h-10 rounded-xl bg-blue-500/20 text-blue-400 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white">Generate Enrollment Authorization Token</h3>
                    <p class="text-xs text-slate-400">Issue an authorization token for staff onboarding. Single use, 10-minute expiry.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.mfa.issue-token', ['user' => '__USER_ID__']) }}" id="issueTokenForm" class="mt-6 flex flex-col sm:flex-row items-center gap-4" onsubmit="event.preventDefault(); const sel = document.getElementById('targetUserSelect'); if(!sel.value) return; this.action = this.action.replace('__USER_ID__', sel.value); this.submit();">
                @csrf
                <div class="w-full sm:w-80">
                    <select id="targetUserSelect" required class="w-full bg-[#0B132B] border border-slate-700 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- Select Staff Account --</option>
                        @foreach (\App\Models\User::where('is_active', true)->orderBy('name')->get() as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->username }}) - {{ $u->role->name ?? 'Staff' }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-black uppercase tracking-wider shadow-lg shadow-blue-600/30 transition-all">
                    Issue Authorization Token
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

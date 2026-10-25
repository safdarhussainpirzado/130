@extends('layouts.app')

@section('title', 'MFA Enforcement by Role - NHMP 130')
@section('page-title', 'Multi-Factor Authentication Enforcement by Role')

@section('content')
<div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 font-inter" x-data="{
    modalOpen: false,
    selectedRole: null,
    targetRequired: true,
    reason: '',
    scheduledDate: '',
    isSubmitting: false,
    openModal(item, required) {
        this.selectedRole = item;
        this.targetRequired = required;
        this.reason = '';
        this.scheduledDate = '';
        this.modalOpen = true;
    },
    closeModal() {
        if (this.isSubmitting) return;
        this.modalOpen = false;
        this.selectedRole = null;
    }
}">
    <!-- Status & Error Notifications -->
    @if (session('status'))
        <div class="mb-6 p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-xs flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('status') }}</span>
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

    <!-- Main Container Card -->
    <div class="bg-[#1C2541] border border-slate-700/60 rounded-3xl p-8 shadow-2xl relative overflow-hidden">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-700/60">
            <div>
                <div class="flex items-center space-x-3">
                    <span class="text-[10px] font-black uppercase tracking-widest text-cyan-400">Security Governance &amp; Rollout</span>
                    <a href="{{ route('admin.settings.index') }}" class="text-[10px] text-slate-400 hover:text-cyan-300 font-bold transition-colors">
                        &larr; System Configuration
                    </a>
                </div>
                <h1 class="text-xl font-black text-white mt-1">MFA Enforcement Policy by Operational Role</h1>
                <p class="text-xs text-slate-400 mt-1">
                    Manage mandatory second-factor authentication per role. Privileged accounts remain strictly protected.
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-3 py-1.5 bg-amber-500/10 border border-amber-500/30 rounded-xl text-[11px] font-bold text-amber-300 flex items-center space-x-1.5">
                    <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>Temporary Wireless Exemptions Active</span>
                </span>
            </div>
        </div>

        <!-- Role Policies Table -->
        <div class="mt-8 overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-400 text-[10px] uppercase tracking-wider font-bold">
                        <th class="py-3 px-4">Role Identifier</th>
                        <th class="py-3 px-4">Enforcement State</th>
                        <th class="py-3 px-4">Active Staff</th>
                        <th class="py-3 px-4">Enrollment Readiness</th>
                        <th class="py-3 px-4">Last Governance Change</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 text-slate-300">
                    @foreach ($roleData as $item)
                        <tr class="hover:bg-slate-800/30 transition-colors">
                            <!-- Role Info -->
                            <td class="py-4 px-4">
                                <div class="font-bold text-white text-sm">
                                    {{ $item['role']->display_name }}
                                </div>
                                <div class="text-[11px] font-mono text-slate-400 flex items-center space-x-2 mt-0.5">
                                    <span>{{ $item['role']->name }}</span>
                                    <span>&bull;</span>
                                    <span class="capitalize text-slate-500">{{ str_replace('_', ' ', $item['role']->scope_level) }}</span>
                                </div>
                            </td>

                            <!-- Enforcement State -->
                            <td class="py-4 px-4">
                                @if ($item['is_protected'])
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                        Protected Privileged (Immutable)
                                    </span>
                                @elseif ($item['is_enforced'])
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        MFA Required
                                    </span>
                                @else
                                    <div>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                            Temporarily Exempt (Password Only)
                                        </span>
                                        @if ($item['scheduled_re_enable_at'])
                                            <span class="block text-[10px] text-slate-400 mt-1 font-mono">
                                                Re-enable: {{ \Carbon\Carbon::parse($item['scheduled_re_enable_at'])->format('d M Y H:i') }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <!-- Active Accounts -->
                            <td class="py-4 px-4">
                                <span class="text-sm font-mono font-bold text-white">{{ $item['active_users_count'] }}</span>
                                <span class="text-[10px] text-slate-400 block">active account(s)</span>
                            </td>

                            <!-- Enrollment Readiness -->
                            <td class="py-4 px-4">
                                <div class="w-48">
                                    <div class="flex justify-between text-[10px] font-mono mb-1">
                                        <span class="text-emerald-400 font-bold">{{ $item['enrolled_users_count'] }} Enrolled</span>
                                        <span class="{{ $item['unenrolled_users_count'] > 0 ? 'text-amber-400' : 'text-slate-500' }}">
                                            {{ $item['unenrolled_users_count'] }} Unenrolled
                                        </span>
                                    </div>
                                    <div class="w-full bg-[#0B132B] rounded-full h-2 overflow-hidden border border-slate-700/60">
                                        @php
                                            $pct = $item['active_users_count'] > 0 ? round(($item['enrolled_users_count'] / $item['active_users_count']) * 100) : 100;
                                        @endphp
                                        <div class="bg-gradient-to-r from-emerald-500 to-teal-400 h-2 rounded-full" :style="'width: {{ $pct }}%'"></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Last Governance Change -->
                            <td class="py-4 px-4">
                                @if ($item['last_reason'])
                                    <p class="text-xs text-slate-300 max-w-xs truncate" title="{{ $item['last_reason'] }}">
                                        {{ $item['last_reason'] }}
                                    </p>
                                    <span class="text-[10px] text-slate-500 font-mono block mt-0.5">
                                        By {{ $item['updated_by'] ?: 'System' }} &bull; {{ $item['updated_at'] ? \Carbon\Carbon::parse($item['updated_at'])->diffForHumans() : 'Initial' }}
                                    </span>
                                @else
                                    <span class="text-slate-500 text-[10px]">Initial system baseline</span>
                                @endif
                            </td>

                            <!-- Action Buttons -->
                            <td class="py-4 px-4 text-right">
                                @if ($item['is_protected'])
                                    <span class="text-[11px] text-slate-500 italic">Enforced by Core Policy</span>
                                @elseif ($item['is_enforced'])
                                    <button type="button"
                                            @click="openModal({{ json_encode($item) }}, false)"
                                            class="px-3.5 py-1.5 bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border border-amber-500/40 rounded-xl text-xs font-bold transition-all">
                                        Exempt Role...
                                    </button>
                                @else
                                    <button type="button"
                                            @click="openModal({{ json_encode($item) }}, true)"
                                            class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-600/30 transition-all">
                                        Enforce MFA...
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Governance Confirmation Dialog Modal -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="bg-[#1C2541] border border-slate-700/80 rounded-3xl p-8 max-w-lg w-full shadow-2xl relative" @click.away="closeModal">
            <!-- Modal Header -->
            <div class="flex items-center space-x-4 pb-6 border-b border-slate-700/60">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center"
                     :class="targetRequired ? 'bg-emerald-500/20 text-emerald-400' : 'bg-amber-500/20 text-amber-400'">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase tracking-widest text-cyan-400">MFA Policy Governance</span>
                    <h3 class="text-lg font-black text-white" x-text="targetRequired ? 'Enforce Mandatory MFA' : 'Grant Temporary MFA Exemption'"></h3>
                </div>
            </div>

            <!-- Confirmation Info -->
            <div class="mt-6 space-y-4 text-xs text-slate-300 leading-relaxed">
                <div class="p-4 rounded-2xl bg-[#0B132B] border border-slate-700/60">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-slate-400 text-[11px] font-bold">Target Role:</span>
                        <span class="text-white font-black text-sm" x-text="selectedRole ? selectedRole.role.display_name : ''"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-slate-400 text-[11px] font-bold">Affected Active Accounts:</span>
                        <span class="text-cyan-300 font-mono font-bold" x-text="selectedRole ? selectedRole.active_users_count + ' accounts' : ''"></span>
                    </div>
                    <template x-if="selectedRole && targetRequired && selectedRole.unenrolled_users_count > 0">
                        <div class="mt-3 pt-3 border-t border-slate-700/60 text-amber-300 text-[11px]">
                            <strong>Notice:</strong> <span x-text="selectedRole.unenrolled_users_count"></span> accounts in this role have not yet enrolled an authenticator. When enabled, their next password login will require an admin-issued authorization token to complete enrollment.
                        </div>
                    </template>
                </div>

                <template x-if="!targetRequired">
                    <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-200">
                        <strong>Security Advisory:</strong> Disabling enforcement permits accounts with this role to authenticate using passwords alone without a second factor. Existing enrolled credentials and recovery codes are preserved but will not be challenged.
                    </div>
                </template>

                <!-- Submission Form -->
                <form :action="'{{ url('mgmt/settings/mfa-roles') }}/' + (selectedRole ? selectedRole.role.id : 0) + '/toggle'"
                      method="POST"
                      @submit="isSubmitting = true"
                      class="space-y-4 pt-2">
                    @csrf
                    <input type="hidden" name="mfa_required" :value="targetRequired ? '1' : '0'">

                    <div>
                        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-300 block mb-1">
                            Governance Reason <span x-show="!targetRequired" class="text-red-400">* (Mandatory)</span>
                        </label>
                        <textarea name="reason"
                                  x-model="reason"
                                  :required="!targetRequired"
                                  rows="2"
                                  placeholder="Document the operational rationale for this policy change..."
                                  class="w-full bg-[#0B132B] border border-slate-700 rounded-xl p-3 text-xs text-white focus:outline-none focus:ring-2 focus:ring-cyan-500"></textarea>
                    </div>

                    <div x-show="!targetRequired">
                        <label class="text-[11px] font-bold uppercase tracking-wider text-slate-300 block mb-1">
                            Scheduled Re-Enablement Date <span class="text-slate-500">(Optional)</span>
                        </label>
                        <input type="datetime-local"
                               name="scheduled_re_enable_at"
                               x-model="scheduledDate"
                               class="w-full bg-[#0B132B] border border-slate-700 rounded-xl p-2.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-cyan-500">
                        <span class="text-[10px] text-slate-500 block mt-1">When this date arrives, MFA enforcement will automatically reactivate for this role.</span>
                    </div>

                    <div class="pt-4 flex items-center justify-end space-x-3">
                        <button type="button"
                                @click="closeModal"
                                :disabled="isSubmitting"
                                class="px-5 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold rounded-xl transition-all">
                            Cancel
                        </button>
                        <button type="submit"
                                :disabled="isSubmitting || (!targetRequired && !reason.trim())"
                                :class="targetRequired ? 'bg-emerald-600 hover:bg-emerald-500 text-white' : 'bg-amber-600 hover:bg-amber-500 text-white'"
                                class="px-6 py-2.5 text-xs font-black uppercase tracking-wider rounded-xl shadow-lg transition-all flex items-center space-x-2 disabled:opacity-50 disabled:cursor-not-allowed">
                            <span x-text="isSubmitting ? 'Applying Policy...' : (targetRequired ? 'Enforce MFA Policy' : 'Exempt Role')"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

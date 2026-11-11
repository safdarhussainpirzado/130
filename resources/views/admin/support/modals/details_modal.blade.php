{{-- Request Details & Lifecycle Audit Inspection Modal --}}
<template x-teleport="body">
    <div x-show="activeModal === 'details'" x-cloak
         class="fixed inset-0 z-[99999] overflow-y-auto bg-white/70 backdrop-blur-md flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div @click.away="closeModal()" 
             class="bg-white rounded-3xl max-w-2xl w-full p-6 sm:p-8 shadow-[0_25px_70px_rgba(15,23,42,0.25)] border border-slate-200/80 space-y-6 relative overflow-hidden max-h-[90vh] flex flex-col">
            
            {{-- Header --}}
            <div class="flex items-start justify-between pb-4 border-b border-slate-100 flex-shrink-0">
                <div>
                    <div class="flex items-center gap-2 mb-1" x-show="selectedRequest">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold border"
                              :class="selectedRequest?.type_badge"
                              x-text="selectedRequest?.type_label"></span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] border"
                              :class="selectedRequest?.priority_badge"
                              x-text="'Priority: ' + (selectedRequest?.priority ? selectedRequest.priority.toUpperCase() : '')"></span>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                              :class="selectedRequest?.status_badge"
                              x-text="selectedRequest?.status_label"></span>
                    </div>
                    <h3 class="text-lg font-black text-navy-900 tracking-tight" x-text="selectedRequest?.subject"></h3>
                    <div class="text-xs text-slate-400 font-mono mt-0.5">
                        UUID: <span class="font-bold text-slate-700 select-all" x-text="selectedRequest?.public_id"></span>
                    </div>
                </div>
                <button type="button" @click="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            {{-- Scrollable Content --}}
            <div class="space-y-5 overflow-y-auto pr-1 flex-1 text-xs" x-show="selectedRequest">
                {{-- Applicant & Routing Cards --}}
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    {{-- Applicant Card --}}
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Applicant Officer</span>
                        <div class="font-black text-navy-900 text-xs" x-text="selectedRequest?.applicant_name"></div>
                        <div class="text-[10px] text-slate-500 font-mono" x-text="selectedRequest?.applicant_username"></div>
                        <div class="text-[10px] text-blue-600 font-bold" x-text="selectedRequest?.applicant_role"></div>
                        <div class="text-[9px] text-slate-400 truncate" x-text="selectedRequest?.applicant_email"></div>
                    </div>

                    {{-- Source Unit --}}
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Current Source Unit</span>
                        <div class="font-black text-navy-900 text-xs" x-text="selectedRequest?.source_office"></div>
                        <div class="text-[10px] text-slate-500" x-text="selectedRequest?.source_parent"></div>
                    </div>

                    {{-- Destination Unit --}}
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-1">
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-wider block">Destination Unit</span>
                        <div class="font-black text-navy-900 text-xs" x-text="selectedRequest?.destination_office"></div>
                        <div class="text-[10px] text-slate-500" x-text="selectedRequest?.destination_parent"></div>
                    </div>
                </div>

                {{-- Request Description Narrative --}}
                <div>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Operational Justification / Narrative</span>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 text-slate-700 leading-relaxed whitespace-pre-line"
                         x-text="selectedRequest?.description || 'No operational justification notes provided.'">
                    </div>
                </div>

                {{-- Lifecycle Audit History Timeline --}}
                <div>
                    <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-2">Lifecycle Audit History</span>

                    <template x-if="!selectedRequest?.actions || selectedRequest.actions.length === 0">
                        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-slate-400 text-center text-xs">
                            No operational actions recorded yet.
                        </div>
                    </template>

                    <div class="space-y-3 relative pl-4 before:absolute before:left-1.5 before:top-2 before:bottom-2 before:w-0.5 before:bg-slate-200"
                         x-show="selectedRequest?.actions && selectedRequest.actions.length > 0">
                        <template x-for="(act, idx) in (selectedRequest?.actions || [])" :key="idx">
                            <div class="relative flex items-start gap-3">
                                <div class="absolute -left-4 top-1 w-3.5 h-3.5 rounded-full bg-blue-600 border-2 border-white shadow-sm flex items-center justify-center"></div>
                                
                                <div class="flex-1 bg-slate-50/80 border border-slate-200 rounded-xl p-3 space-y-1">
                                    <div class="flex items-center justify-between text-[11px]">
                                        <div class="flex items-center gap-1.5">
                                            <span class="px-2 py-0.5 rounded text-[9px] font-bold border" :class="act.badge" x-text="act.type"></span>
                                            <span class="font-bold text-navy-900" x-text="act.actor"></span>
                                            <span class="text-[10px] text-slate-400 font-medium" x-text="'[' + act.role + ']'"></span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 font-mono" x-text="act.date"></span>
                                    </div>
                                    <div class="text-xs text-slate-700 bg-white p-2 rounded-lg border border-slate-100" x-show="act.remarks" x-text="act.remarks"></div>
                                    <div class="text-[9px] text-slate-400 flex items-center gap-2 pt-0.5">
                                        <span x-text="'Auth: ' + act.auth"></span>
                                        <span x-show="act.ip" x-text="'• IP: ' + act.ip"></span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Footer --}}
            <div class="pt-3 border-t border-slate-100 flex items-center justify-between flex-shrink-0">
                <span class="text-[11px] text-slate-400 font-mono" x-text="'Submitted: ' + selectedRequest?.submitted_at"></span>

                <div class="flex items-center gap-2">
                    <button type="button" @click="closeModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                        Close
                    </button>

                    {{-- Action trigger button from details modal (Strictly gated by can_act) --}}
                    <template x-if="selectedRequest?.can_act && selectedRequest?.request_type === 'org_transfer' && ['departure', 'endorsement'].includes(selectedRequest?.action_category)">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="openModal('departure', selectedRequest)" class="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-plane-departure"></i>
                                <span x-text="selectedRequest?.action_category === 'endorsement' ? 'Endorse Transfer' : 'Verify Departure'"></span>
                            </button>
                            <button type="button" @click="openModal('reject', selectedRequest)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition-all" title="Decline Transfer">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </template>

                    <template x-if="selectedRequest?.can_act && selectedRequest?.request_type === 'org_transfer' && ['clearance', 'arrival'].includes(selectedRequest?.action_category)">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="openModal('arrival', selectedRequest)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-user-check"></i>
                                <span x-text="selectedRequest?.action_category === 'clearance' ? 'Clear Arrival' : 'Confirm Arrival'"></span>
                            </button>
                            <button type="button" @click="openModal('reject', selectedRequest)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition-all" title="Decline Transfer">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </template>

                    <template x-if="selectedRequest?.can_act && selectedRequest?.request_type === 'password_recovery' && ['submitted', 'pending_manager_review'].includes(selectedRequest?.current_status)">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="openModal('passwordReset', selectedRequest)" class="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-key"></i> Issue Token
                            </button>
                            <button type="button" @click="openModal('reject', selectedRequest)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition-all" title="Decline">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </template>

                    <template x-if="selectedRequest?.can_act && selectedRequest?.request_type === 'account_block' && ['submitted', 'pending_manager_review'].includes(selectedRequest?.current_status)">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="openModal('accountUnlock', selectedRequest)" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-lock-open"></i> Unlock Account
                            </button>
                            <button type="button" @click="openModal('reject', selectedRequest)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition-all" title="Decline">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </template>

                    <template x-if="selectedRequest?.can_act && selectedRequest?.request_type === 'mfa_support' && ['submitted', 'pending_manager_review'].includes(selectedRequest?.current_status)">
                        <div class="flex items-center gap-1.5">
                            <button type="button" @click="openModal('mfa', selectedRequest)" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-check"></i> Resolve MFA
                            </button>
                            <button type="button" @click="openModal('reject', selectedRequest)" class="px-3 py-2 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-xl text-xs font-bold transition-all" title="Decline">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

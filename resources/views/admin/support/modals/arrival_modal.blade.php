{{-- Stage 2: Transfer Arrival Confirmation Modal --}}
<template x-teleport="body">
    <div x-show="activeModal === 'arrival'" x-cloak
         class="fixed inset-0 z-[99999] overflow-y-auto bg-white/70 backdrop-blur-md flex items-center justify-center p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div @click.away="closeModal()" 
             class="bg-white rounded-3xl max-w-xl w-full p-6 sm:p-8 shadow-[0_25px_70px_rgba(15,23,42,0.25)] border border-slate-200/80 space-y-6 relative overflow-hidden">
        
        {{-- Header --}}
        <div class="flex items-start justify-between pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-md shadow-emerald-500/20">
                    <i class="fa-solid fa-user-check text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-navy-900 tracking-tight">Confirm Physical Arrival</h3>
                    <p class="text-xs text-slate-500">Stage 2 & 3: Arrival Verification & Atomic Registry Assignment</p>
                </div>
            </div>
            <button type="button" @click="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        {{-- Applicant & Arrival Briefing --}}
        <template x-if="selectedRequest">
            <div class="space-y-4">
                <div class="p-4 rounded-2xl bg-emerald-50/40 border border-emerald-200/70 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-emerald-700 font-bold uppercase tracking-wider text-[10px]">Reporting Officer</span>
                        <span class="font-mono text-[10px] text-emerald-800 font-bold" x-text="selectedRequest.public_id"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-black text-navy-900 text-sm block" x-text="selectedRequest.applicant_name"></span>
                            <span class="text-xs text-slate-500 font-mono" x-text="selectedRequest.applicant_username"></span>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 text-[10px] font-bold" x-text="selectedRequest.applicant_role"></span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-emerald-200 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center flex-shrink-0">
                            <i class="fa-solid fa-building-circle-check text-xs"></i>
                        </div>
                        <div class="text-xs">
                            <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block">New Assigned Duty Post</span>
                            <span class="font-bold text-navy-900" x-text="selectedRequest.destination_office"></span>
                            <span class="text-slate-500" x-text="' (' + selectedRequest.destination_parent + ')'"></span>
                        </div>
                    </div>

                    <p class="text-[10px] text-emerald-800 leading-relaxed">
                        <i class="fa-solid fa-circle-info text-emerald-600"></i>
                        Confirming arrival immediately updates this officer's office hierarchy, shifts active duties to the destination unit, and synchronizes access capability matrix in real-time.
                    </p>
                </div>

                {{-- Form submission --}}
                <form :action="'{{ url('/mgmt/support/requests') }}/' + selectedRequest.public_id + '/confirm-destination'" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Arrival Verification Remarks / Duty Roster Entry <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="remarks" rows="2" required placeholder="Enter reporting order number, arrival date/time, and physical verification notes"
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                    </div>

                    {{-- Approver Step-Up Authentication / Supervisory Override --}}
                    @if($isManager)
                    <div class="p-3.5 rounded-2xl bg-gradient-to-r from-emerald-500/10 via-emerald-500/5 to-white border border-emerald-200 flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center shadow-sm flex-shrink-0">
                            <i class="fa-solid fa-shield-halved text-xs"></i>
                        </div>
                        <div>
                            <span class="text-xs font-black text-emerald-900 block">Supervisory Administrative Override</span>
                            <span class="text-[10px] text-emerald-700">Privileged administrative session active. Re-prompt waived.</span>
                        </div>
                    </div>
                    @else
                    <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200 space-y-3">
                        <div class="flex items-center gap-2 text-xs font-bold text-navy-900">
                            <i class="fa-solid fa-lock text-emerald-600"></i> Destination Authority Step-Up Authentication
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                Your Current Password <span class="text-rose-500">*</span>
                            </label>
                            <input type="password" name="password" required autocomplete="current-password" placeholder="Enter your current password"
                                class="w-full text-xs rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500">
                        </div>

                        @if(Auth::user()->hasMfaEnrolled())
                        <div>
                            <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                Your Authenticator TOTP Code <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000"
                                class="w-full text-xs rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500 font-mono tracking-widest text-center text-sm font-bold">
                        </div>
                        @endif
                    </div>
                    @endif

                    <div class="flex items-center justify-between pt-2">
                        <button type="button" @click="openModal('reject', selectedRequest)" class="text-rose-600 hover:text-rose-700 text-xs font-bold flex items-center gap-1.5 transition-colors">
                            <i class="fa-solid fa-ban"></i> Reject Arrival
                        </button>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="closeModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-md hover:shadow-emerald-500/25 transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-check"></i> Confirm Arrival & Update Assignment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
        </div>
    </div>
</template>

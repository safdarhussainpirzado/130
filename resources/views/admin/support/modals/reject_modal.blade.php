{{-- Rejection / Decline Modal --}}
<template x-teleport="body">
    <div x-show="activeModal === 'reject'" x-cloak
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
                    <div class="w-12 h-12 rounded-2xl bg-rose-600 text-white flex items-center justify-center shadow-md shadow-rose-500/20">
                        <i class="fa-solid fa-ban text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black text-navy-900 tracking-tight">Decline / Reject Support Request</h3>
                        <p class="text-xs text-slate-500">Administrative Rejection Confirmation</p>
                    </div>
                </div>
                <button type="button" @click="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </div>

            {{-- Applicant Briefing --}}
            <template x-if="selectedRequest">
                <div class="space-y-4">
                    <div class="p-4 rounded-2xl bg-rose-50/50 border border-rose-200 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-rose-800 font-bold uppercase tracking-wider text-[10px]">Target Request</span>
                            <span class="font-mono text-[10px] text-rose-900 font-bold" x-text="selectedRequest.public_id"></span>
                        </div>
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-black text-navy-900 text-sm block" x-text="selectedRequest.applicant_name"></span>
                                <span class="text-xs text-slate-500" x-text="selectedRequest.type_label + ' &bull; ' + selectedRequest.subject"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Form submission --}}
                    <form :action="'{{ url('/mgmt/support/requests') }}/' + selectedRequest.public_id + '/reject'" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="remarks" value="Support request declined by administrative authority.">

                        <div class="p-4 rounded-2xl bg-rose-50/70 border border-rose-200 text-rose-900 text-xs flex items-center gap-3">
                            <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-600 flex items-center justify-center shrink-0">
                                <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                            </div>
                            <span class="leading-relaxed">Are you sure you want to decline this support request? The applicant will be notified that the request has been rejected.</span>
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
                                <i class="fa-solid fa-lock text-rose-600"></i> Approver Step-Up Authentication
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                    Your Current Password <span class="text-rose-500">*</span>
                                </label>
                                <input type="password" name="password" required autocomplete="current-password" placeholder="Enter your current password"
                                    class="w-full text-xs rounded-xl border-slate-200 focus:border-rose-500 focus:ring-rose-500">
                            </div>

                            @if(Auth::user()->hasMfaEnrolled())
                            <div>
                                <label class="block text-[10px] font-bold text-slate-600 uppercase tracking-wider mb-1">
                                    Your Authenticator TOTP Code <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000"
                                    class="w-full text-xs rounded-xl border-slate-200 focus:border-rose-500 focus:ring-rose-500 font-mono tracking-widest text-center text-sm font-bold">
                            </div>
                            @endif
                        </div>
                        @endif

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="closeModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-bold shadow-md hover:shadow-rose-500/25 transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-ban"></i> Confirm Rejection
                            </button>
                        </div>
                    </form>
                </div>
            </template>
        </div>
    </div>
</template>

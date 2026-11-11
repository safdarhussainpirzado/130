{{-- MFA / Authenticator Support Resolution Modal --}}
<template x-teleport="body">
    <div x-show="activeModal === 'mfa'" x-cloak
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
                <div class="w-12 h-12 rounded-2xl bg-sky-600 text-white flex items-center justify-center shadow-md shadow-sky-500/20">
                    <i class="fa-solid fa-mobile-screen-button text-lg"></i>
                </div>
                <div>
                    <h3 class="text-lg font-black text-navy-900 tracking-tight">MFA / 2FA Assistance</h3>
                    <p class="text-xs text-slate-500">Resolve Authenticator Token Desync or Lost Device Request</p>
                </div>
            </div>
            <button type="button" @click="closeModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-xs"></i>
            </button>
        </div>

        {{-- Applicant Briefing --}}
        <template x-if="selectedRequest">
            <div class="space-y-4">
                <div class="p-4 rounded-2xl bg-sky-50/50 border border-sky-200 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-sky-800 font-bold uppercase tracking-wider text-[10px]">Applicant Officer</span>
                        <span class="font-mono text-[10px] text-sky-900 font-bold" x-text="selectedRequest.public_id"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-black text-navy-900 text-sm block" x-text="selectedRequest.applicant_name"></span>
                            <span class="text-xs text-slate-500 font-mono" x-text="selectedRequest.applicant_username"></span>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg bg-sky-100 text-sky-900 text-[10px] font-bold" x-text="selectedRequest.applicant_role"></span>
                    </div>

                    <div class="p-3 bg-white rounded-xl border border-sky-200 text-xs text-slate-700 leading-relaxed">
                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-wider block mb-1">Issue Description</span>
                        <span x-text="selectedRequest.description || 'No additional narrative provided.'"></span>
                    </div>
                </div>

                {{-- Form submission --}}
                <form :action="'{{ url('/mgmt/support/requests') }}/' + selectedRequest.public_id + '/resolve-general'" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="approved" value="1">

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase tracking-wider mb-1">
                            Resolution Action Remarks <span class="text-rose-500">*</span>
                        </label>
                        <textarea name="remarks" rows="2" required placeholder="Specify MFA assistance action taken (e.g. Device verification completed, MFA enrollment reset issued)"
                            class="w-full text-xs rounded-xl border-slate-200 focus:border-sky-500 focus:ring-sky-500"></textarea>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <button type="button" @click="openModal('reject', selectedRequest)" class="text-rose-600 hover:text-rose-700 text-xs font-bold flex items-center gap-1.5 transition-colors">
                            <i class="fa-solid fa-ban"></i> Decline Request
                        </button>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="closeModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 transition-colors">
                                Cancel
                            </button>
                            <button type="submit" class="px-5 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-bold shadow-md hover:shadow-sky-500/25 transition-all flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-check"></i> Complete MFA Resolution
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </template>
        </div>
    </div>
</template>

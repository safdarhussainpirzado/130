    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-center w-full gap-4">
            <div>
                <div class="flex items-center gap-2 text-slate-400 text-xs font-black tracking-widest uppercase mb-1">
                    <i class="fa-solid fa-house hover:text-blue-600 transition-colors"></i>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    <span class="text-slate-800">Security Vault</span>
                </div>
                <h2 class="font-extrabold text-3xl text-navy-900 tracking-tight">
                    Authorized User Profile
                </h2>
                <div class="text-xs font-bold text-slate-400 uppercase tracking-widest mt-1">
                    Credential Management & Identity Protection
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-[1700px] mx-auto sm:px-6 lg:px-8 space-y-10">
            <div class="grid lg:grid-cols-2 gap-10">
                <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 p-10 overflow-hidden relative">
                    <div class="absolute -right-10 -top-10 opacity-5">
                        <i class="fa-solid fa-user-shield text-9xl"></i>
                    </div>
                    <div class="relative z-10 max-w-xl">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center border border-blue-100">
                                <i class="fa-solid fa-fingerprint text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-extrabold text-navy-900 uppercase">Operational Identity</h3>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Public profile information</p>
                            </div>
                        </div>
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="bg-white rounded-[2.5rem] shadow-xl border border-slate-100 p-10 overflow-hidden relative">
                    <div class="absolute -right-10 -top-10 opacity-5 text-rose-500">
                        <i class="fa-solid fa-key text-9xl"></i>
                    </div>
                    <div class="relative z-10 max-w-xl">
                        <div class="flex items-center gap-4 mb-8">
                            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center border border-rose-100">
                                <i class="fa-solid fa-lock text-xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-extrabold text-navy-900 uppercase">Credential Vault</h3>
                                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Update authorization keys</p>
                            </div>
                        </div>
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-[2.5rem] shadow-xl border border-rose-100 p-10 overflow-hidden relative bg-rose-50/10">
                <div class="max-w-xl">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-12 h-12 rounded-2xl bg-slate-900 text-white flex items-center justify-center">
                            <i class="fa-solid fa-skull-crossbones text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-extrabold text-navy-900 uppercase">Terminate Session Access</h3>
                            <p class="text-[10px] font-black text-rose-500 uppercase tracking-widest">Irreversible account purge</p>
                        </div>
                    </div>
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>

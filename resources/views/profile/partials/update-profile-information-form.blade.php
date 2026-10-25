<section>
    <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('patch')

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Full Name</label>
            <input type="text" name="full_name" value="{{ old('full_name', $user->full_name) }}" required
                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-blue-600 focus:bg-white outline-none font-semibold text-slate-800 text-sm">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Username</label>
            <input type="text" name="username" value="{{ old('username', $user->username) }}" required
                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-blue-600 focus:bg-white outline-none font-mono font-semibold text-slate-800 text-sm">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Email Address</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-blue-600 focus:bg-white outline-none font-semibold text-slate-800 text-sm">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number</label>
            <input type="text" name="mobile_no" value="{{ old('mobile_no', $user->mobile_no) }}"
                   placeholder="e.g. 0300-1234567"
                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-blue-600 focus:bg-white outline-none font-semibold text-slate-800 text-sm">
        </div>

        <div class="flex items-center gap-4 pt-3">
            <button type="submit" class="px-6 py-2.5 bg-blue-900 text-white rounded-xl font-bold text-xs hover:bg-blue-800 transition-all shadow-sm">
                Save Changes
            </button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2500)"
                   class="text-xs font-bold text-emerald-600 flex items-center gap-1.5">
                    <i class="fa-solid fa-check"></i> Changes saved successfully
                </p>
            @endif
        </div>
    </form>
</section>

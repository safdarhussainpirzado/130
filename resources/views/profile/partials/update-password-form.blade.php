<section>
    <form method="post" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        @method('put')

        <!-- Hidden username field to comply with browser password form accessibility guidelines -->
        <input type="text" name="username" value="{{ auth()->user()->username ?? '' }}" autocomplete="username" style="display:none;" aria-hidden="true" tabindex="-1">

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Current Password</label>
            <input type="password" name="current_password" required autocomplete="current-password"
                   placeholder="Enter your current password"
                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-blue-600 focus:bg-white outline-none font-medium text-slate-800 text-sm">
            @if ($errors->updatePassword->get('current_password'))
                <span class="text-xs text-red-600 mt-1 block">{{ $errors->updatePassword->first('current_password') }}</span>
            @endif
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">New Password</label>
            <input type="password" name="password" required autocomplete="new-password"
                   placeholder="At least 8 characters"
                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-blue-600 focus:bg-white outline-none font-medium text-slate-800 text-sm">
            @if ($errors->updatePassword->get('password'))
                <span class="text-xs text-red-600 mt-1 block">{{ $errors->updatePassword->first('password') }}</span>
            @endif
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Confirm New Password</label>
            <input type="password" name="password_confirmation" required autocomplete="new-password"
                   placeholder="Re-enter new password"
                   class="w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:border-blue-600 focus:bg-white outline-none font-medium text-slate-800 text-sm">
            @if ($errors->updatePassword->get('password_confirmation'))
                <span class="text-xs text-red-600 mt-1 block">{{ $errors->updatePassword->first('password_confirmation') }}</span>
            @endif
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
            <button type="submit" class="w-full py-2.5 bg-blue-900 hover:bg-blue-800 text-white rounded-xl font-bold text-xs transition-all shadow-sm">
                Update Password
            </button>
        </div>
    </form>
</section>

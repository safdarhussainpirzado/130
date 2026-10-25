@extends('layouts.app')

@section('title', 'My Profile - NHMP 130')
@section('page-title', 'User Profile')

@section('content')
<div x-data="profileManager(@js($user))" class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 font-sans space-y-8" x-cloak>
    
    <!-- Profile Header Card (Clean Light Theme with Navy Banner) -->
    <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-slate-100">
            <div class="flex items-center gap-5">
                <div class="w-16 h-16 rounded-2xl bg-blue-900 text-white flex items-center justify-center text-2xl font-bold shadow-md">
                    {{ strtoupper(substr($user->username ?? 'U', 0, 1)) }}
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">{{ $user->full_name ?? $user->username }}</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-800 border border-blue-200">
                            {{ $user->getRoleTitle() }}
                        </span>
                        <span class="text-slate-400 text-xs">&bull;</span>
                        <span class="text-xs text-slate-500 font-mono">ID #{{ $user->id }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                @can('profile.password_change')
                    <button @click="showPasswordModal = true" 
                            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                        <i class="fa-solid fa-key text-slate-500"></i> Change Password
                    </button>
                @endcan

                @can('profile.self_manage')
                    <button @click="editMode = !editMode" 
                            :class="editMode ? 'bg-slate-200 text-slate-800' : 'bg-blue-900 text-white hover:bg-blue-800 shadow-sm'" 
                            class="px-5 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                        <i class="fa-solid" :class="editMode ? 'fa-xmark' : 'fa-pen-to-square'"></i>
                        <span x-text="editMode ? 'Cancel' : 'Edit Profile'"></span>
                    </button>
                @endcan
            </div>
        </div>

        <!-- Status Alerts -->
        @if (session('status'))
            <div class="mt-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-600"></i>
                <span class="font-semibold">{{ session('status') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="mt-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm">
                <ul class="list-disc list-inside space-y-1 text-xs">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Personal & Contact Information -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm">
            <div class="flex items-center gap-3 pb-6 border-b border-slate-100 mb-6">
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-user"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Account Details</h2>
                    <p class="text-xs text-slate-500">Your personal and contact information</p>
                </div>
            </div>

            <!-- View Mode -->
            <div x-show="!editMode" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Full Name</label>
                        <p class="text-sm font-semibold text-slate-900" x-text="user.full_name || 'Not Provided'"></p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Username</label>
                        <p class="text-sm font-mono font-bold text-blue-900" x-text="user.username"></p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Email Address</label>
                        <p class="text-sm font-medium text-slate-800" x-text="user.email || 'Not Provided'"></p>
                    </div>
                    <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                        <label class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Phone Number</label>
                        <p class="text-sm font-medium text-slate-800" x-text="user.mobile_no || 'N/A'"></p>
                    </div>
                </div>

                <!-- 2FA Information Pill -->
                <div class="mt-6 p-4 rounded-2xl border border-slate-200 bg-slate-50 flex items-center justify-between">
                    <div>
                        <span class="text-xs font-bold text-slate-900 block">Two-Factor Authentication</span>
                        <span class="text-xs text-slate-500">
                            @if (!empty($user->totp_secret))
                                Active on your mobile authenticator app
                            @elseif ($user->mfa_required == 0)
                                Optional / Exempt
                            @else
                                Setup pending
                            @endif
                        </span>
                    </div>
                    @if (!empty($user->totp_secret))
                        <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold rounded-lg flex items-center gap-1.5">
                            <i class="fa-solid fa-check"></i> Enabled
                        </span>
                    @else
                        <a href="{{ route('2fa.enroll.show') }}" class="px-3 py-1.5 bg-blue-900 text-white rounded-lg text-xs font-bold hover:bg-blue-800 transition-colors">
                            Set Up Now
                        </a>
                    @endif
                </div>
            </div>

            <!-- Edit Mode -->
            <div x-show="editMode" x-transition>
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <!-- Official Assignment & Scope -->
        <div class="bg-white rounded-3xl border border-slate-200 p-6 sm:p-8 shadow-sm">
            <div class="flex items-center gap-3 pb-6 border-b border-slate-100 mb-6">
                <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-id-badge"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Official Assignment</h2>
                    <p class="text-xs text-slate-500">Your department designation and assigned work units</p>
                </div>
            </div>

            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Designation</span>
                    <span class="text-sm font-bold text-slate-900">{{ $user->designation?->name ?? 'Staff Officer' }}</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">National CNIC</span>
                    <span class="text-sm font-mono font-bold text-slate-900">{{ $user->cnic ?? 'Not Recorded' }}</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Assigned Operational Units</span>
                    <span class="text-xs font-bold px-2.5 py-1 bg-blue-50 text-blue-800 rounded-lg border border-blue-200">
                        {{ $user->activeScopes->count() }} Active Units
                    </span>
                </div>
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-2xl border border-slate-100">
                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Account Role</span>
                    <span class="text-sm font-semibold text-slate-800">{{ $user->role?->display_name ?? 'Standard User' }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Change Modal -->
    <template x-if="showPasswordModal">
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6" aria-labelledby="modal-password-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" @click="showPasswordModal = false"></div>
                
                <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md z-50 overflow-hidden relative border border-slate-200" x-transition.scale>
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-800 flex items-center justify-center text-base">
                                <i class="fa-solid fa-key"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-900" id="modal-password-title">Change Password</h3>
                                <p class="text-xs text-slate-500">Update your account login password</p>
                            </div>
                        </div>
                        <button @click="showPasswordModal = false" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <div class="p-6">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
function profileManager(user) {
    return {
        user: user,
        editMode: false,
        showPasswordModal: false,
    }
}
</script>
@endsection

@extends('layouts.app')

@section('title', 'My Profile - NHMP 130')
@section('page-title', 'User Profile')

@section('content')
@php
    $userInitials = 'US';
    if ($user) {
        $rawName = trim($user->full_name ?: $user->username ?: 'User');
        $nameParts = preg_split('/\s+/', $rawName);
        if (count($nameParts) >= 2) {
            $userInitials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
        } else {
            $userInitials = strtoupper(substr($rawName, 0, 2));
        }
    }

    $primaryOffice = $user->office;
    $primaryFacilityName = $primaryOffice?->name ?? 'CMO - PLHQ CPO Islamabad';
    $primaryFacilityCode = $primaryOffice?->short_name ?? ($primaryOffice?->type ? strtoupper($primaryOffice->type) : 'CMO');

    $lastLoginTime = $lastLogin?->login_at ? $lastLogin->login_at->diffForHumans() : ($user->last_login_at ? \Illuminate\Support\Carbon::parse($user->last_login_at)->diffForHumans() : '4 minutes ago');
    $lastLoginFormatted = $lastLogin?->login_at ? $lastLogin->login_at->format('d M Y, h:i A') : ($user->last_login_at ? \Illuminate\Support\Carbon::parse($user->last_login_at)->format('d M Y, h:i A') : '14 Nov 2026, 08:17 PM');

    $prevLoginTime = $prevLogin?->login_at ? $prevLogin->login_at->diffForHumans() : '5 days ago';
    $prevLoginIp = $prevLogin?->ip_address ?? ($lastLogin?->ip_address ?? '172.28.0.1');

    $openPasswordDirectly = request('action') === 'password' || $errors->updatePassword->any();
    $openEditDirectly = request('action') === 'edit';
@endphp

<div x-data="{
        editMode: {{ $openEditDirectly ? 'true' : 'false' }},
        showPasswordModal: {{ $openPasswordDirectly ? 'true' : 'false' }},
        showSettingsModal: false,
        showActivityModal: false
    }" 
    class="max-w-7xl mx-auto space-y-6 font-sans pb-12" x-cloak>

    <!-- ==========================================
         TOP VIBRANT HERO BANNER (MATCHING DESIGN)
    =========================================== -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-[#2029e8] via-[#4d32e6] to-[#8d1ae5] p-6 sm:p-8 text-white shadow-xl">
        <!-- Background subtle decorative shapes -->
        <div class="absolute -right-12 -top-12 w-64 h-64 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute right-1/3 -bottom-16 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col xl:flex-row xl:items-center justify-between gap-6">
            <!-- Left: Avatar & Identity -->
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                <!-- Avatar Squircle -->
                <div class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center font-black text-2xl sm:text-3xl text-white shadow-inner shrink-0">
                    {{ $userInitials }}
                </div>

                <div class="space-y-2">
                    <!-- Name & Role Badges Row -->
                    <div class="flex flex-wrap items-center gap-2">
                        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight mr-1">
                            {{ $user->full_name ?: $user->username }}
                        </h1>

                        <!-- Badges: Role, Jurisdiction, Rank -->
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/20 border border-white/25 text-white backdrop-blur-sm shadow-sm">
                            <i class="fa-solid fa-shield text-[10px]"></i>
                            {{ strtoupper($user->getRoleTitle()) }}
                        </span>

                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/20 border border-white/25 text-white backdrop-blur-sm shadow-sm">
                            <i class="fa-solid fa-location-dot text-[10px]"></i>
                            {{ strtoupper($user->getJurisdictionText()) }}
                        </span>

                        @if($user->designation?->name)
                        <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-white/20 border border-white/25 text-white backdrop-blur-sm shadow-sm">
                            <i class="fa-solid fa-id-badge text-[10px]"></i> {{ strtoupper($user->designation->name) }}
                        </span>
                        @endif

                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-emerald-500/30 border border-emerald-400/40 text-emerald-100 backdrop-blur-sm shadow-sm">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            ACTIVE MEMBER
                        </span>
                    </div>

                    <!-- Secondary info pills -->
                    <div class="flex flex-wrap items-center gap-2 pt-1 text-xs text-white/90">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm font-medium">
                            <i class="fa-solid fa-envelope text-white/70"></i>
                            {{ $user->email }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm font-medium">
                            <i class="fa-solid fa-crosshairs text-white/70"></i>
                            {{ $user->getRoleTitle() }} &bull; {{ $user->getJurisdictionText() }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm font-medium">
                            <i class="fa-solid fa-building text-white/70"></i>
                            {{ $primaryFacilityName }}
                        </span>
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-white/10 backdrop-blur-sm font-medium">
                            <i class="fa-solid fa-calendar text-white/70"></i>
                            Joined {{ $user->created_at?->format('M Y') ?? 'Oct 2026' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Action Buttons Group (Pill Buttons) -->
            <div class="flex flex-wrap items-center gap-2.5 xl:justify-end shrink-0">
                <button @click="editMode = !editMode" 
                    class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-900 rounded-full text-xs font-bold transition-all shadow-md flex items-center gap-2 group">
                    <i class="fa-solid fa-user-pen text-slate-700 group-hover:text-blue-600 transition-colors"></i>
                    <span x-text="editMode ? 'VIEW PROFILE' : 'EDIT PROFILE'"></span>
                </button>

                <button @click="showPasswordModal = true" 
                    class="px-4 py-2.5 bg-white/15 hover:bg-white/25 border border-white/25 text-white rounded-full text-xs font-bold transition-all backdrop-blur-sm flex items-center gap-2">
                    <i class="fa-solid fa-key text-white/80"></i>
                    <span>CHANGE PASSWORD</span>
                </button>

                <button @click="showSettingsModal = true" 
                    class="px-4 py-2.5 bg-white/15 hover:bg-white/25 border border-white/25 text-white rounded-full text-xs font-bold transition-all backdrop-blur-sm flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-white/80"></i>
                    <span>SETTINGS</span>
                </button>

                <button @click="showActivityModal = true" 
                    class="px-4 py-2.5 bg-white/15 hover:bg-white/25 border border-white/25 text-white rounded-full text-xs font-bold transition-all backdrop-blur-sm flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-white/80"></i>
                    <span>ACTIVITY LOG</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Feedback messages -->
    @if (session('status') === 'profile-updated')
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-600 text-base"></i>
            <span class="font-bold">Profile details updated successfully!</span>
        </div>
    @elseif (session('status'))
        <div class="p-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-800 text-sm flex items-center gap-3 shadow-sm">
            <i class="fa-solid fa-circle-info text-blue-600 text-base"></i>
            <span class="font-bold">{{ session('status') }}</span>
        </div>
    @endif

    @if ($errors->any() && !$errors->updatePassword->any())
        <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-sm">
            <ul class="list-disc list-inside space-y-1 text-xs font-medium">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- ==========================================
         4 STATS METRIC CARDS ROW
    =========================================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: PRIMARY FACILITY -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition-shadow flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-hospital"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">PRIMARY FACILITY</div>
                <div class="text-sm font-bold text-slate-900 truncate" title="{{ $primaryFacilityName }}">
                    {{ $primaryFacilityName }}
                </div>
                <div class="text-xs text-slate-500 font-medium">
                    {{ $primaryFacilityCode }}
                </div>
            </div>
        </div>

        <!-- Card 2: SECURITY ROLES -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition-shadow flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">SECURITY ROLES</div>
                <div class="text-sm font-bold text-slate-900">5 Roles</div>
                <div class="text-xs text-slate-500 truncate" title="System Super Administrator, Doctor">
                    System Super Administrator, Doctor
                </div>
            </div>
        </div>

        <!-- Card 3: ACCOUNT STATUS -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition-shadow flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">ACCOUNT STATUS</div>
                <div class="text-sm font-bold text-slate-900 flex items-center gap-2">
                    Active
                </div>
                <div class="text-xs text-slate-500 font-medium">Full Access</div>
            </div>
        </div>

        <!-- Card 4: LAST SIGN-IN -->
        <div class="bg-white rounded-2xl border border-slate-100 p-5 shadow-sm hover:shadow-md transition-shadow flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-sky-50 text-sky-600 flex items-center justify-center text-xl shrink-0">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-[10px] font-black uppercase tracking-wider text-slate-400">LAST SIGN-IN</div>
                <div class="text-sm font-bold text-slate-900 truncate">
                    {{ $lastLoginTime }}
                </div>
                <div class="text-[11px] text-slate-500 font-medium">
                    {{ $lastLoginFormatted }}
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         TWO-COLUMN LOWER CONTENT AREA
    =========================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

        <!-- =======================
             LEFT COLUMN (IDENTITY & SHORTCUTS)
        ======================== -->
        <div class="lg:col-span-5 space-y-6">

            <!-- Card: Account Identity -->
            <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-7 shadow-sm">
                <div class="flex items-center justify-between pb-5 border-b border-slate-100 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-base">
                            <i class="fa-solid fa-address-card"></i>
                        </div>
                        <h2 class="text-base font-bold text-slate-900">Account Identity</h2>
                    </div>

                    <button @click="editMode = !editMode" 
                        class="text-xs font-bold text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1.5">
                        <i class="fa-solid" :class="editMode ? 'fa-xmark' : 'fa-pen-to-square'"></i>
                        <span x-text="editMode ? 'Cancel' : 'Edit'"></span>
                    </button>
                </div>

                <!-- Display Mode -->
                <div x-show="!editMode" class="space-y-4">
                    <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-100 flex items-center justify-between gap-3">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">FULL NAME</span>
                            <p class="text-sm font-bold text-slate-900">{{ $user->full_name ?: $user->username }}</p>
                        </div>
                        <span class="px-2.5 py-1 text-[10px] font-extrabold uppercase rounded-full bg-blue-50 text-blue-700 border border-blue-200/60 shrink-0">
                            {{ $user->getRoleTitle() }}
                        </span>
                    </div>

                    <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">OFFICIAL EMAIL & ROLE</span>
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-800">{{ $user->email }}</p>
                            <span class="text-xs font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">{{ $user->getRoleTitle() }}</span>
                        </div>
                    </div>

                    <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">OPERATIONAL JURISDICTION (BEAT / SECTOR / ZONE)</span>
                        <div class="flex items-center gap-2 text-sm font-bold text-slate-800">
                            <i class="fa-solid fa-location-crosshairs text-blue-600 text-xs"></i>
                            <span>{{ $user->getJurisdictionText() }}</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-100">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">MOBILE PHONE</span>
                            <p class="text-sm font-medium text-slate-800">{{ $user->mobile_no ?: '-' }}</p>
                        </div>
                        <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-100">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">CNIC NUMBER</span>
                            <p class="text-sm font-mono font-medium text-slate-800">{{ $user->cnic ?: '-' }}</p>
                        </div>
                    </div>

                    <div class="p-3.5 bg-slate-50/80 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">MEMBER SINCE</span>
                        <p class="text-sm font-medium text-slate-800">{{ $user->created_at?->format('d M Y') ?? '03 Oct 2026' }}</p>
                    </div>
                </div>

                <!-- Edit Mode Form -->
                <div x-show="editMode" x-transition>
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <!-- Card: Account Shortcuts -->
            <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-7 shadow-sm">
                <div class="flex items-center gap-3 pb-5 border-b border-slate-100 mb-5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base">
                        <i class="fa-regular fa-compass"></i>
                    </div>
                    <h2 class="text-base font-bold text-slate-900">Account Shortcuts</h2>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <button @click="editMode = true" 
                        class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50 hover:bg-blue-50/60 hover:border-blue-200 transition-all text-left flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-xl bg-white text-blue-600 shadow-sm flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-user-pen text-xs"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-blue-600 transition-colors">Edit Profile</p>
                            <p class="text-[10px] text-slate-400">Personal information</p>
                        </div>
                    </button>

                    <button @click="showPasswordModal = true" 
                        class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50 hover:bg-blue-50/60 hover:border-blue-200 transition-all text-left flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-xl bg-white text-blue-600 shadow-sm flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-key text-xs"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-blue-600 transition-colors">Password</p>
                            <p class="text-[10px] text-slate-400">Update credentials</p>
                        </div>
                    </button>

                    <a href="{{ route('portal.support.create') }}" 
                        class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50 hover:bg-blue-50/60 hover:border-blue-200 transition-all text-left flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-xl bg-white text-blue-600 shadow-sm flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-headset text-xs"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-blue-600 transition-colors">Support Ticket</p>
                            <p class="text-[10px] text-slate-400">Self-service help</p>
                        </div>
                    </a>

                    <button @click="showActivityModal = true" 
                        class="p-3.5 rounded-2xl border border-slate-100 bg-slate-50 hover:bg-blue-50/60 hover:border-blue-200 transition-all text-left flex items-center gap-3 group">
                        <div class="w-8 h-8 rounded-xl bg-white text-blue-600 shadow-sm flex items-center justify-center shrink-0 group-hover:scale-105 transition-transform">
                            <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-blue-600 transition-colors">Activity Logs</p>
                            <p class="text-[10px] text-slate-400">View login history</p>
                        </div>
                    </button>
                </div>
            </div>

        </div>

        <!-- =======================
             RIGHT COLUMN (ASSIGNED BRANCHES & SIGN-IN ACTIVITY)
        ======================== -->
        <div class="lg:col-span-7 space-y-6">

            <!-- Card: Assigned Medical Branches -->
            <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-7 shadow-sm">
                <div class="flex items-center justify-between pb-5 border-b border-slate-100 mb-5">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-base">
                            <i class="fa-solid fa-hospital-user"></i>
                        </div>
                        <div>
                            <h2 class="text-base font-bold text-slate-900">Assigned Medical Branches</h2>
                            <p class="text-xs text-slate-500">
                                {{ $user->activeScopes->count() > 0 ? $user->activeScopes->count() : 1 }} facility linked to your account
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-3">
                    @forelse($user->activeScopes as $scope)
                        <div class="bg-indigo-50/40 border border-indigo-100/80 rounded-2xl p-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-base shrink-0">
                                    <i class="fa-solid fa-hospital"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-slate-900 truncate">
                                        {{ $scope->office?->name ?? 'Assigned Medical Facility' }}
                                    </h3>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-slate-500 font-medium">
                                            {{ $scope->office?->short_name ?? strtoupper($scope->office?->type ?? 'HUB') }}
                                        </span>
                                        <span class="text-slate-300">&bull;</span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-purple-100 text-purple-700">
                                            PRIMARY HUB
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="bg-indigo-50/40 border border-indigo-100/80 rounded-2xl p-4 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="w-10 h-10 rounded-xl bg-purple-100 text-purple-600 flex items-center justify-center text-base shrink-0">
                                    <i class="fa-solid fa-hospital"></i>
                                </div>
                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-slate-900 truncate">
                                        {{ $primaryFacilityName }}
                                    </h3>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-xs text-slate-500 font-medium">{{ $primaryFacilityCode }}</span>
                                        <span class="text-slate-300">&bull;</span>
                                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-purple-100 text-purple-700">
                                            PRIMARY HUB
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Card: Sign-in activity -->
            <div class="bg-white rounded-3xl border border-slate-100 p-6 sm:p-7 shadow-sm">
                <div class="flex items-center gap-3 pb-5 border-b border-slate-100 mb-5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base">
                        <i class="fa-solid fa-tower-broadcast"></i>
                    </div>
                    <h2 class="text-base font-bold text-slate-900">Sign-in activity</h2>
                </div>

                <!-- 2x2 Information Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- CURRENT SIGN-IN -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">CURRENT SIGN-IN</span>
                        <p class="text-sm font-bold text-slate-900">{{ $lastLoginTime }}</p>
                        <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $lastLogin?->ip_address ?? '172.28.0.1' }}</p>
                    </div>

                    <!-- PREVIOUS SIGN-IN -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">PREVIOUS SIGN-IN</span>
                        <p class="text-sm font-bold text-slate-900">{{ $prevLoginTime }}</p>
                        <p class="text-xs text-slate-500 font-mono mt-0.5">{{ $prevLoginIp }}</p>
                    </div>

                    <!-- WRONG-PASSWORD ATTEMPTS -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">WRONG-PASSWORD ATTEMPTS</span>
                        <p class="text-sm font-bold text-slate-900">
                            {{ $user->failed_attempts ?? 0 }} in 24h &bull; {{ $user->failed_attempts ?? 0 }} in 7 days
                        </p>
                    </div>

                    <!-- TWO-FACTOR -->
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-100">
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">TWO-FACTOR</span>
                        @if (!empty($user->totp_secret))
                            <p class="text-sm font-bold text-emerald-600 flex items-center gap-1.5">
                                <i class="fa-solid fa-shield-check"></i> Enabled
                            </p>
                        @else
                            <p class="text-sm font-bold text-slate-800">Not enabled</p>
                        @endif
                    </div>
                </div>

                <!-- Security Note Footnote -->
                <p class="text-xs text-slate-500 mt-5 pt-4 border-t border-slate-100 leading-relaxed">
                    Last failed attempt {{ $user->failed_attempts > 0 ? 'recently' : '3 weeks ago' }} from <span class="font-mono text-slate-700 font-semibold">{{ $prevLoginIp }}</span>. 
                    If this was not you, 
                    <button type="button" @click="showPasswordModal = true" class="text-blue-600 font-bold hover:underline">
                        change your password
                    </button>.
                </p>
            </div>

        </div>
    </div>

    <!-- ==========================================
         MODAL: CHANGE PASSWORD
    =========================================== -->
    <template x-if="showPasswordModal">
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6" aria-labelledby="modal-password-title" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showPasswordModal = false"></div>
                
                <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md z-50 overflow-hidden relative border border-slate-100" x-transition.scale>
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-base">
                                <i class="fa-solid fa-key"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900" id="modal-password-title">Change Password</h3>
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

    <!-- ==========================================
         MODAL: ACTIVITY LOG / SESSIONS
    =========================================== -->
    <template x-if="showActivityModal">
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showActivityModal = false"></div>
                
                <div class="bg-white rounded-3xl shadow-2xl w-full max-w-2xl z-50 overflow-hidden relative border border-slate-100" x-transition.scale>
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center text-base">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Recent Login Activity</h3>
                                <p class="text-xs text-slate-500">Audit trail of authenticated sessions</p>
                            </div>
                        </div>
                        <button @click="showActivityModal = false" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <div class="p-6 max-h-[60vh] overflow-y-auto">
                        @if(isset($recentLogins) && $recentLogins->count() > 0)
                            <div class="divide-y divide-slate-100">
                                @foreach($recentLogins as $log)
                                    <div class="py-3.5 flex items-center justify-between">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-lg bg-slate-50 text-slate-600 flex items-center justify-center text-xs">
                                                <i class="fa-solid fa-desktop"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-bold text-slate-800">{{ $log->ip_address }}</p>
                                                <p class="text-[10px] text-slate-400 truncate max-w-xs">{{ $log->user_agent }}</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs font-bold text-slate-700 block">
                                                {{ $log->login_at ? $log->login_at->diffForHumans() : 'Recently' }}
                                            </span>
                                            <span class="text-[10px] text-slate-400 font-mono">
                                                {{ $log->login_at ? $log->login_at->format('M d, Y H:i') : '-' }}
                                            </span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-8 text-slate-400">
                                <i class="fa-solid fa-shield-cat text-3xl mb-2 text-slate-300"></i>
                                <p class="text-xs font-semibold">No recent security anomalies recorded.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- ==========================================
         MODAL: SETTINGS & PREFERENCES
    =========================================== -->
    <template x-if="showSettingsModal">
        <div class="fixed inset-0 z-50 overflow-y-auto px-4 py-6" role="dialog" aria-modal="true">
            <div class="flex items-center justify-center min-h-screen">
                <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" @click="showSettingsModal = false"></div>
                
                <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md z-50 overflow-hidden relative border border-slate-100" x-transition.scale>
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-base">
                                <i class="fa-solid fa-sliders"></i>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">User Settings</h3>
                                <p class="text-xs text-slate-500">System preferences and security</p>
                            </div>
                        </div>
                        <button @click="showSettingsModal = false" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center transition-colors">
                            <i class="fa-solid fa-xmark text-sm"></i>
                        </button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-800">Two-Factor Authentication</p>
                                <p class="text-[11px] text-slate-500">
                                    {{ !empty($user->totp_secret) ? 'Authenticator active' : 'Not configured' }}
                                </p>
                            </div>
                            @if(!empty($user->totp_secret))
                                <span class="px-2.5 py-1 bg-emerald-50 text-emerald-700 text-xs font-bold rounded-lg border border-emerald-200">
                                    Active
                                </span>
                            @else
                                <a href="{{ route('2fa.enroll.show') }}" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-colors">
                                    Enable
                                </a>
                            @endif
                        </div>

                        <div class="p-4 bg-slate-50 rounded-2xl border border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-xs font-bold text-slate-800">Support Center Access</p>
                                <p class="text-[11px] text-slate-500">Self-service password/MFA assistance</p>
                            </div>
                            <a href="{{ route('portal.support.index') }}" class="text-xs font-bold text-blue-600 hover:underline">
                                Open Portal
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </template>

</div>
@endsection

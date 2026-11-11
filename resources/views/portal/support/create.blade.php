@extends('layouts.app')

@section('title', 'Submit Support Request - 130 NHMP Helpline')
@section('page-title', 'Submit Support Request')

@php
    $userName = $user->full_name ?: $user->name ?: $user->username;
    $words = explode(' ', trim($userName));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $w) {
        $initials .= strtoupper(substr($w, 0, 1));
    }
    if (empty($initials)) $initials = 'US';
@endphp

@section('content')
<div x-data="{
    activeTab: '{{ old('request_type', $defaultType === 'other_account' ? 'org_transfer' : $defaultType) }}',
    zones: {{ Js::from($zones) }},
    fallbackSectors: {{ Js::from($sectors) }},
    selectedZoneId: '',
    selectedSectorId: '',
    destinationOfficeId: '{{ old('destination_office_id', '') }}',
    sectors: [],
    beats: [],

    // Transfer quick chips
    transferQuickChip: 'Routine Rotation',
    reasonText: `{{ old('reason', '') }}`,

    // MFA chips
    mfaCategory: '{{ old('category', 'lost_device') }}',
    mfaQuickReason: 'Lost / Damaged Phone',
    mfaDescription: `{{ old('description', '') }}`,

    // Profile Rectification fields (Tab 4)
    generalCategory: '{{ old('category', 'full_name') }}',
    profileFieldLabel: 'Full Name',
    generalSubject: `{{ old('subject', 'Request for Correction of Full Name') }}`,
    generalDescription: `{{ old('description', 'Requesting official update to my registered full name in personnel records.') }}`,
    profileFullName: `{{ old('new_full_name', '') }}`,
    profileEmail: `{{ old('new_email', '') }}`,
    profilePhone: `{{ old('new_phone', '') }}`,
    profileDesignationId: `{{ old('new_designation_id', '') }}`,
    profileCnic: `{{ old('new_cnic', '') }}`,

    // Account Block chips
    blockCategory: 'suspected_compromise',
    blockReasonText: 'Suspicious login or unauthorized access detected on official personnel account',

    showPassword: false,

    init() {
        if (this.zones && this.zones.length > 0) {
            const prevOfficeId = this.destinationOfficeId;
            if (prevOfficeId) {
                for (const z of this.zones) {
                    if (z.children) {
                        for (const s of z.children) {
                            if (s.children && s.children.some(b => b.id == prevOfficeId)) {
                                this.selectedZoneId = z.id;
                                this.sectors = z.children;
                                this.selectedSectorId = s.id;
                                this.beats = s.children;
                                break;
                            }
                        }
                    }
                }
            }
        }
        if (!this.reasonText) {
            this.setTransferReason('Regular Routine Rotation Order according to Annual Administrative Cycle', 'Routine Rotation');
        }
        if (!this.mfaDescription) {
            this.setMfaReason('lost_device', 'Lost / Damaged Phone', 'Lost or replaced smartphone running authenticator app. Requesting authorized reset & recovery token.');
        }
        if (!this.generalSubject) {
            this.setProfileRectification('full_name', 'Full Name', 'Request for Correction of Full Name', 'Requesting official update to my registered full name in personnel records.');
        }
    },

    onZoneChange() {
        this.selectedSectorId = '';
        this.destinationOfficeId = '';
        this.beats = [];
        const zone = this.zones.find(z => z.id == this.selectedZoneId);
        this.sectors = (zone && zone.children) ? zone.children : [];
    },

    onSectorChange() {
        this.destinationOfficeId = '';
        const sector = this.sectors.find(s => s.id == this.selectedSectorId);
        this.beats = (sector && sector.children) ? sector.children : [];
    },

    setTransferReason(preset, chipLabel) {
        this.transferQuickChip = chipLabel;
        this.reasonText = preset;
    },

    setBlockReason(preset, chipLabel) {
        this.blockCategory = chipLabel;
        this.blockReasonText = preset;
    },

    setMfaReason(category, label, prefill) {
        this.mfaCategory = category;
        this.mfaQuickReason = label;
        if (prefill) {
            this.mfaDescription = prefill;
        }
    },

    setProfileRectification(category, label, subject, prefill) {
        this.generalCategory = category;
        this.profileFieldLabel = label;
        this.generalSubject = subject;
        if (prefill) {
            this.generalDescription = prefill;
        }
    },

    getDestinationBeatName() {
        if (!this.destinationOfficeId) return 'Pending Beat Selection';
        for (const b of this.beats) {
            if (b.id == this.destinationOfficeId) return b.name;
        }
        return 'Beat #' + this.destinationOfficeId;
    }
}" class="max-w-[1440px] mx-auto pb-2">

    @if(isset($errors) && is_object($errors) && method_exists($errors, 'any') && $errors->any())
    <div class="p-3 mb-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
        <div class="font-bold flex items-center gap-2 mb-0.5">
            <i class="fa-solid fa-circle-exclamation"></i> Submission Incomplete
        </div>
        <ul class="list-disc list-inside space-y-0.5 text-[11px]">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('portal.support.store') }}" 
          @submit="if(activeTab === 'org_transfer' && !destinationOfficeId) { alert('Please select a destination Beat / Unit'); $event.preventDefault(); }">
        @csrf
        <input type="hidden" name="request_type" :value="activeTab">

        {{-- Main Two-Column Luxury Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">

            {{-- ========================================================== --}}
            {{-- LEFT COLUMN: High-Density Form Inputs & Quick Chips       --}}
            {{-- ========================================================== --}}
            <div class="lg:col-span-7 space-y-3">

                {{-- TAB 1: ORGANIZATIONAL TRANSFER --}}
                <div x-show="activeTab === 'org_transfer'" class="space-y-3" x-cloak>
                    
                    {{-- Card 1A: Assignment & Cascading Destination CHIPS --}}
                    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-4 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-widest text-blue-600 block">Unit Deployment</span>
                                <h3 class="text-sm font-black text-navy-900">Destination Office Selection (Zone &rarr; Sector &rarr; Beat)</h3>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200">
                                MD3 Cascade Chips
                            </span>
                        </div>

                        {{-- Current Scope Strip --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 p-2 rounded-xl bg-slate-50 border border-slate-100 text-[11px]">
                            <div class="flex items-center gap-2 text-slate-600">
                                <span class="font-bold text-slate-400 uppercase text-[9px]">Current Station:</span>
                                <span class="font-black text-navy-900">{{ $currentScope?->office?->name ?? 'Unassigned Scope' }}</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-200 text-slate-700">
                                    {{ $currentScope?->office?->type ?? 'National' }}
                                </span>
                            </div>
                            <div class="text-slate-500 font-semibold text-[10px]">
                                Applicant: <span class="text-navy-900 font-bold">{{ $user->full_name }}</span>
                            </div>
                        </div>

                        {{-- 3-Tier Cascading CHIPS Selectors (Matching Calls Page Style!) --}}
                        <div class="space-y-2.5">
                            {{-- Step 1: Zone Chips --}}
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-wider text-blue-600 mb-1.5">
                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-blue-100 text-blue-700 text-[9px] font-black mr-1">1</span>
                                    Destination Zone <span class="text-rose-500">*</span>
                                </label>
                                <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto pr-1">
                                    <template x-for="z in zones" :key="z.id">
                                        <button type="button"
                                                @click="selectedZoneId = z.id; onZoneChange()"
                                                :class="selectedZoneId == z.id
                                                    ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-500/30 -translate-y-0.5'
                                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700'"
                                                class="px-2.5 py-1.5 rounded-xl border text-[10px] font-bold transition-all flex items-center gap-1 cursor-pointer select-none active:scale-95">
                                            <span x-text="z.name"></span>
                                            <i x-show="selectedZoneId == z.id" class="fa-solid fa-check text-[9px] ml-1"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Step 2: Sector Chips (Revealed upon Zone Selection) --}}
                            <div x-show="selectedZoneId" x-transition class="pt-2 border-t border-slate-100">
                                <label class="block text-[10px] font-black uppercase tracking-wider text-indigo-600 mb-1.5">
                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-indigo-100 text-indigo-700 text-[9px] font-black mr-1">2</span>
                                    Destination Sector <span class="text-rose-500">*</span>
                                </label>
                                <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto pr-1">
                                    <template x-for="s in sectors" :key="s.id">
                                        <button type="button"
                                                @click="selectedSectorId = s.id; onSectorChange()"
                                                :class="selectedSectorId == s.id
                                                    ? 'bg-indigo-600 text-white border-indigo-600 shadow-sm shadow-indigo-500/30 -translate-y-0.5'
                                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-indigo-50 hover:border-indigo-300 hover:text-indigo-700'"
                                                class="px-2.5 py-1.5 rounded-xl border text-[10px] font-bold transition-all flex items-center gap-1 cursor-pointer select-none active:scale-95">
                                            <span x-text="s.name"></span>
                                            <i x-show="selectedSectorId == s.id" class="fa-solid fa-check text-[9px] ml-1"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Step 3: Beat Chips (Revealed upon Sector Selection) --}}
                            <div x-show="selectedSectorId" x-transition class="pt-2 border-t border-slate-100">
                                <label class="block text-[10px] font-black uppercase tracking-wider text-emerald-600 mb-1.5">
                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-emerald-100 text-emerald-700 text-[9px] font-black mr-1">3</span>
                                    Destination Beat / Unit <span class="text-rose-500">*</span>
                                </label>
                                <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto pr-1">
                                    <template x-for="b in beats" :key="b.id">
                                        <button type="button"
                                                @click="destinationOfficeId = b.id"
                                                :class="destinationOfficeId == b.id
                                                    ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-500/30 -translate-y-0.5'
                                                    : 'bg-white text-slate-700 border-slate-200 hover:bg-emerald-50 hover:border-emerald-300 hover:text-emerald-700'"
                                                class="px-2.5 py-1.5 rounded-xl border text-[10px] font-bold transition-all flex items-center gap-1 cursor-pointer select-none active:scale-95">
                                            <span x-text="b.name"></span>
                                            <i x-show="destinationOfficeId == b.id" class="fa-solid fa-check text-[9px] ml-1"></i>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Hidden destination input --}}
                        <input type="hidden" name="destination_office_id" :value="destinationOfficeId">

                        {{-- Transfer Justification: 1-Click Buttons Only (Input box removed as requested!) --}}
                        <div class="pt-2 border-t border-slate-100">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider">
                                    Transfer Justification <span class="text-rose-500">*</span>
                                </label>
                                <span class="text-[9px] font-bold text-slate-400">1-Click Quick Preset Selection:</span>
                            </div>

                            {{-- 3rd Colored Chips Selection --}}
                            <div class="flex flex-wrap gap-1.5">
                                <button type="button" 
                                    @click="setTransferReason('Regular Routine Rotation Order according to Annual Administrative Cycle', 'Routine Rotation')"
                                    :class="transferQuickChip === 'Routine Rotation' 
                                        ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-blue-50 hover:text-blue-700'"
                                    class="px-2.5 py-1.5 rounded-lg border text-[10px] font-bold transition-all flex items-center gap-1.5 active:scale-95">
                                    <i class="fa-solid fa-arrows-rotate text-[10px]" :class="transferQuickChip === 'Routine Rotation' ? 'text-white' : 'text-blue-500'"></i>
                                    <span>Routine Rotation</span>
                                </button>

                                <button type="button" 
                                    @click="setTransferReason('Promotional Posting / Rank Elevation Assignment per HQ Notification', 'Promotional Posting')"
                                    :class="transferQuickChip === 'Promotional Posting' 
                                        ? 'bg-amber-600 text-white border-amber-600 shadow-sm shadow-amber-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-amber-50 hover:text-amber-700'"
                                    class="px-2.5 py-1.5 rounded-lg border text-[10px] font-bold transition-all flex items-center gap-1.5 active:scale-95">
                                    <i class="fa-solid fa-award text-[10px]" :class="transferQuickChip === 'Promotional Posting' ? 'text-white' : 'text-amber-500'"></i>
                                    <span>Promotional Posting</span>
                                </button>

                                <button type="button" 
                                    @click="setTransferReason('Administrative Reassignment ordered by Zonal Commander / DIG Office', 'Administrative Order')"
                                    :class="transferQuickChip === 'Administrative Order' 
                                        ? 'bg-purple-600 text-white border-purple-600 shadow-sm shadow-purple-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-purple-50 hover:text-purple-700'"
                                    class="px-2.5 py-1.5 rounded-lg border text-[10px] font-bold transition-all flex items-center gap-1.5 active:scale-95">
                                    <i class="fa-solid fa-building-flag text-[10px]" :class="transferQuickChip === 'Administrative Order' ? 'text-white' : 'text-purple-500'"></i>
                                    <span>Administrative Order</span>
                                </button>

                                <button type="button" 
                                    @click="setTransferReason('Operational Reinforcement for High-Traffic Highway Beat Jurisdiction', 'Reinforcement')"
                                    :class="transferQuickChip === 'Reinforcement' 
                                        ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-emerald-50 hover:text-emerald-700'"
                                    class="px-2.5 py-1.5 rounded-lg border text-[10px] font-bold transition-all flex items-center gap-1.5 active:scale-95">
                                    <i class="fa-solid fa-shield text-[10px]" :class="transferQuickChip === 'Reinforcement' ? 'text-white' : 'text-emerald-500'"></i>
                                    <span>Reinforcement</span>
                                </button>

                                <button type="button" 
                                    @click="setTransferReason('Compassionate Ground / Medical Station Adjustment Request', 'Compassionate')"
                                    :class="transferQuickChip === 'Compassionate' 
                                        ? 'bg-rose-600 text-white border-rose-600 shadow-sm shadow-rose-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-rose-50 hover:text-rose-700'"
                                    class="px-2.5 py-1.5 rounded-lg border text-[10px] font-bold transition-all flex items-center gap-1.5 active:scale-95">
                                    <i class="fa-solid fa-heart-pulse text-[10px]" :class="transferQuickChip === 'Compassionate' ? 'text-white' : 'text-rose-500'"></i>
                                    <span>Compassionate</span>
                                </button>
                            </div>

                            {{-- Selected Justification Live Badge (Replaces Textarea) --}}
                            <div class="mt-2 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200/80 text-[11px] text-slate-700 font-medium flex items-center gap-2">
                                <i class="fa-solid fa-circle-check text-blue-600 text-xs shrink-0"></i>
                                <span class="font-bold text-slate-400 text-[10px] uppercase">Selected Justification:</span>
                                <span x-text="reasonText" class="truncate font-semibold text-navy-900"></span>
                            </div>

                            {{-- Hidden reason input --}}
                            <input type="hidden" name="reason" :value="reasonText">
                        </div>
                    </div>
                </div>

                {{-- TAB 2: MFA ASSISTANCE --}}
                <div x-show="activeTab === 'mfa_support'" class="space-y-3" x-cloak>
                    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-4 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-widest text-cyan-600 block">Identity Security</span>
                                <h3 class="text-sm font-black text-navy-900">MFA Issue Diagnostic</h3>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-cyan-50 text-cyan-700 border border-cyan-200">
                                Single-Click Preset
                            </span>
                        </div>

                        <input type="hidden" name="category" :value="mfaCategory">

                        {{-- 3rd Colored Chips Selection for MFA --}}
                        <div>
                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1.5">
                                Select Issue Type (Quick Chips):
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <button type="button" 
                                    @click="setMfaReason('lost_device', 'Lost / Damaged Phone', 'Lost or replaced smartphone running authenticator app. Requesting authorized reset & recovery token.')"
                                    :class="mfaCategory === 'lost_device' 
                                        ? 'bg-blue-600 text-white border-blue-600 shadow-md shadow-blue-500/25' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-blue-50 hover:text-blue-700'"
                                    class="p-2.5 rounded-xl border text-left transition-all flex flex-col justify-between">
                                    <div class="flex items-center justify-between mb-1">
                                        <i class="fa-solid fa-mobile-screen text-xs" :class="mfaCategory === 'lost_device' ? 'text-white' : 'text-blue-500'"></i>
                                        <i class="fa-solid fa-circle-check text-[10px]" :class="mfaCategory === 'lost_device' ? 'text-white' : 'text-slate-300'"></i>
                                    </div>
                                    <span class="text-[11px] font-black block">Lost Phone</span>
                                    <span class="text-[9px] opacity-80">Recovery Token</span>
                                </button>

                                <button type="button" 
                                    @click="setMfaReason('enrollment_assistance', 'New Device Setup', 'Upgrading or switching to new handset. Need guidance to transfer authenticator credentials safely.')"
                                    :class="mfaCategory === 'enrollment_assistance' 
                                        ? 'bg-cyan-600 text-white border-cyan-600 shadow-md shadow-cyan-500/25' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-cyan-50 hover:text-cyan-700'"
                                    class="p-2.5 rounded-xl border text-left transition-all flex flex-col justify-between">
                                    <div class="flex items-center justify-between mb-1">
                                        <i class="fa-solid fa-arrows-spin text-xs" :class="mfaCategory === 'enrollment_assistance' ? 'text-white' : 'text-cyan-500'"></i>
                                        <i class="fa-solid fa-circle-check text-[10px]" :class="mfaCategory === 'enrollment_assistance' ? 'text-white' : 'text-slate-300'"></i>
                                    </div>
                                    <span class="text-[11px] font-black block">New Device</span>
                                    <span class="text-[9px] opacity-80">Re-Enrollment</span>
                                </button>

                                <button type="button" 
                                    @click="setMfaReason('other', 'Clock Drift / Desync', 'Authenticator 6-digit TOTP codes are rejected due to handset system clock drift or desync.')"
                                    :class="mfaCategory === 'other' 
                                        ? 'bg-amber-600 text-white border-amber-600 shadow-md shadow-amber-500/25' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-amber-50 hover:text-amber-700'"
                                    class="p-2.5 rounded-xl border text-left transition-all flex flex-col justify-between">
                                    <div class="flex items-center justify-between mb-1">
                                        <i class="fa-solid fa-clock-rotate-left text-xs" :class="mfaCategory === 'other' ? 'text-white' : 'text-amber-500'"></i>
                                        <i class="fa-solid fa-circle-check text-[10px]" :class="mfaCategory === 'other' ? 'text-white' : 'text-slate-300'"></i>
                                    </div>
                                    <span class="text-[11px] font-black block">Code Desync</span>
                                    <span class="text-[9px] opacity-80">Time Resync</span>
                                </button>

                                <button type="button" 
                                    @click="setMfaReason('reset_request', 'Reset MFA Secret', 'Full MFA secret invalidation and token re-issuance approved by Operations Command.')"
                                    :class="mfaCategory === 'reset_request' 
                                        ? 'bg-purple-600 text-white border-purple-600 shadow-md shadow-purple-500/25' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-purple-50 hover:text-purple-700'"
                                    class="p-2.5 rounded-xl border text-left transition-all flex flex-col justify-between">
                                    <div class="flex items-center justify-between mb-1">
                                        <i class="fa-solid fa-key text-xs" :class="mfaCategory === 'reset_request' ? 'text-white' : 'text-purple-500'"></i>
                                        <i class="fa-solid fa-circle-check text-[10px]" :class="mfaCategory === 'reset_request' ? 'text-white' : 'text-slate-300'"></i>
                                    </div>
                                    <span class="text-[11px] font-black block">Secret Reset</span>
                                    <span class="text-[9px] opacity-80">Purge Key</span>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1">
                                Operational Description &amp; Details <span class="text-rose-500">*</span>
                            </label>
                            <textarea name="description" x-model="mfaDescription" rows="2" required 
                                placeholder="Describe the authenticator issue you are facing..."
                                class="w-full text-xs font-medium rounded-xl border-slate-200 focus:border-cyan-500 focus:ring-cyan-500 p-2.5 shadow-sm"></textarea>
                        </div>
                    </div>
                </div>

                {{-- TAB 3: EMERGENCY ACCOUNT BLOCKING (NEW "BLOCK" WORKFLOW!) --}}
                <div x-show="activeTab === 'account_block'" class="space-y-3" x-cloak>
                    <div class="bg-white rounded-2xl border border-rose-200/90 shadow-sm p-4 space-y-3">
                        <div class="flex items-center justify-between border-b border-rose-100 pb-2">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-widest text-rose-600 block">Emergency Security</span>
                                <h3 class="text-sm font-black text-rose-950">Immediate Account Lockout</h3>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 border border-rose-200">
                                Session Invalidation
                            </span>
                        </div>

                        {{-- Emergency Warning Banner --}}
                        <div class="p-2.5 rounded-xl bg-rose-50/80 border border-rose-200/80 text-[11px] text-rose-900 flex items-start gap-2.5">
                            <i class="fa-solid fa-shield-virus text-rose-600 text-sm mt-0.5 shrink-0"></i>
                            <div class="leading-relaxed">
                                <strong>Immediate Security Action:</strong> This action instantly terminates all active sessions across all devices, invalidates credentials, and prevents subsequent logins until authorized unlock by an Operation Manager.
                            </div>
                        </div>

                        {{-- Quick Reason Chips for Block --}}
                        <div>
                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1.5">
                                Reason for Emergency Lockout (Quick Chips):
                            </label>
                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" 
                                    @click="setBlockReason('Suspicious login or unauthorized access detected on official personnel account', 'suspected_compromise')"
                                    :class="blockCategory === 'suspected_compromise' 
                                        ? 'bg-rose-600 text-white border-rose-600 shadow-md shadow-rose-500/25' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-rose-50 hover:text-rose-700'"
                                    class="p-2.5 rounded-xl border text-left transition-all flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-user-shield text-xs" :class="blockCategory === 'suspected_compromise' ? 'text-white' : 'text-rose-500'"></i>
                                        <span class="text-[11px] font-black">Suspicious Login</span>
                                    </div>
                                    <i class="fa-solid fa-circle-check text-[10px]" :class="blockCategory === 'suspected_compromise' ? 'text-white' : 'text-slate-300'"></i>
                                </button>

                                <button type="button" 
                                    @click="setBlockReason('Lost or stolen official mobile device / SIM card compromised', 'stolen_device')"
                                    :class="blockCategory === 'stolen_device' 
                                        ? 'bg-rose-600 text-white border-rose-600 shadow-md shadow-rose-500/25' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-rose-50 hover:text-rose-700'"
                                    class="p-2.5 rounded-xl border text-left transition-all flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-mobile-screen-button text-xs" :class="blockCategory === 'stolen_device' ? 'text-white' : 'text-rose-500'"></i>
                                        <span class="text-[11px] font-black">Stolen Handset</span>
                                    </div>
                                    <i class="fa-solid fa-circle-check text-[10px]" :class="blockCategory === 'stolen_device' ? 'text-white' : 'text-slate-300'"></i>
                                </button>

                                <button type="button" 
                                    @click="setBlockReason('Received unauthorized 2FA verification prompts or password reset alerts', 'unauthorized_alerts')"
                                    :class="blockCategory === 'unauthorized_alerts' 
                                        ? 'bg-rose-600 text-white border-rose-600 shadow-md shadow-rose-500/25' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-rose-50 hover:text-rose-700'"
                                    class="p-2.5 rounded-xl border text-left transition-all flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-bell-slash text-xs" :class="blockCategory === 'unauthorized_alerts' ? 'text-white' : 'text-rose-500'"></i>
                                        <span class="text-[11px] font-black">Unknown 2FA Prompts</span>
                                    </div>
                                    <i class="fa-solid fa-circle-check text-[10px]" :class="blockCategory === 'unauthorized_alerts' ? 'text-white' : 'text-slate-300'"></i>
                                </button>

                                <button type="button" 
                                    @click="setBlockReason('Precautionary security freeze requested by officer', 'precautionary')"
                                    :class="blockCategory === 'precautionary' 
                                        ? 'bg-rose-600 text-white border-rose-600 shadow-md shadow-rose-500/25' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-rose-50 hover:text-rose-700'"
                                    class="p-2.5 rounded-xl border text-left transition-all flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <i class="fa-solid fa-snowflake text-xs" :class="blockCategory === 'precautionary' ? 'text-white' : 'text-rose-500'"></i>
                                        <span class="text-[11px] font-black">Precautionary Freeze</span>
                                    </div>
                                    <i class="fa-solid fa-circle-check text-[10px]" :class="blockCategory === 'precautionary' ? 'text-white' : 'text-slate-300'"></i>
                                </button>
                            </div>

                            {{-- Selected Block Reason Live Badge --}}
                            <div class="mt-2 px-3 py-1.5 rounded-xl bg-rose-50/60 border border-rose-200/80 text-[11px] text-rose-900 font-medium flex items-center gap-2">
                                <i class="fa-solid fa-shield text-rose-600 text-xs shrink-0"></i>
                                <span class="font-bold text-rose-500 text-[10px] uppercase">Reason:</span>
                                <span x-text="blockReasonText" class="truncate font-semibold"></span>
                            </div>

                            <input type="hidden" name="reason" :value="blockReasonText" :disabled="activeTab !== 'account_block'">
                        </div>
                    </div>
                </div>

                {{-- TAB 4: PROFILE RECTIFICATION SUPPORT --}}
                <div x-show="activeTab === 'other_account'" class="space-y-3" x-cloak>
                    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-4 space-y-3">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                            <div>
                                <span class="text-[10px] font-black uppercase tracking-widest text-violet-600 block">Personnel Portal</span>
                                <h3 class="text-sm font-black text-navy-900">Official Profile &amp; Data Rectification</h3>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-violet-50 text-violet-700 border border-violet-200">
                                Profile Correction
                            </span>
                        </div>

                        <input type="hidden" name="category" :value="generalCategory">

                        {{-- 5 Profile Rectification Quick Chips --}}
                        <div>
                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1.5">
                                Select Field to Rectify (Quick Chips):
                            </label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-1.5">
                                <button type="button" 
                                    @click="setProfileRectification('full_name', 'Full Name', 'Request for Correction of Full Name', 'Requesting official update to my registered full name in personnel records.')"
                                    :class="generalCategory === 'full_name' 
                                        ? 'bg-violet-600 text-white border-violet-600 shadow-sm shadow-violet-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-violet-50 hover:text-violet-700'"
                                    class="p-2 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 active:scale-95">
                                    <i class="fa-solid fa-user-pen text-xs" :class="generalCategory === 'full_name' ? 'text-white' : 'text-violet-600'"></i>
                                    <span class="text-[10px] font-bold">Full Name</span>
                                </button>

                                <button type="button" 
                                    @click="setProfileRectification('email', 'Email Address', 'Request for Update of Official Email Address', 'Requesting correction / migration of registered official email address.')"
                                    :class="generalCategory === 'email' 
                                        ? 'bg-blue-600 text-white border-blue-600 shadow-sm shadow-blue-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-blue-50 hover:text-blue-700'"
                                    class="p-2 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 active:scale-95">
                                    <i class="fa-solid fa-envelope text-xs" :class="generalCategory === 'email' ? 'text-white' : 'text-blue-600'"></i>
                                    <span class="text-[10px] font-bold">Email Address</span>
                                </button>

                                <button type="button" 
                                    @click="setProfileRectification('phone', 'Phone Number', 'Request for Update of Mobile Contact Number', 'Official contact phone number updated for helpline dispatch notifications.')"
                                    :class="generalCategory === 'phone' 
                                        ? 'bg-emerald-600 text-white border-emerald-600 shadow-sm shadow-emerald-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-emerald-50 hover:text-emerald-700'"
                                    class="p-2 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 active:scale-95">
                                    <i class="fa-solid fa-phone text-xs" :class="generalCategory === 'phone' ? 'text-white' : 'text-emerald-600'"></i>
                                    <span class="text-[10px] font-bold">Phone Number</span>
                                </button>

                                <button type="button" 
                                    @click="setProfileRectification('designation', 'Designation', 'Request for Update of Rank / Designation', 'Official rank / designation update following promotional order.')"
                                    :class="generalCategory === 'designation' 
                                        ? 'bg-amber-600 text-white border-amber-600 shadow-sm shadow-amber-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-amber-50 hover:text-amber-700'"
                                    class="p-2 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 active:scale-95">
                                    <i class="fa-solid fa-id-badge text-xs" :class="generalCategory === 'designation' ? 'text-white' : 'text-amber-600'"></i>
                                    <span class="text-[10px] font-bold">Designation</span>
                                </button>

                                <button type="button" 
                                    @click="setProfileRectification('cnic', 'CNIC Number', 'Request for Rectification of CNIC Number', 'Typographical or NADRA verification correction in official CNIC record.')"
                                    :class="generalCategory === 'cnic' 
                                        ? 'bg-rose-600 text-white border-rose-600 shadow-sm shadow-rose-500/30' 
                                        : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-rose-50 hover:text-rose-700'"
                                    class="p-2 rounded-xl border text-center transition-all flex flex-col items-center justify-center gap-1 active:scale-95">
                                    <i class="fa-solid fa-address-card text-xs" :class="generalCategory === 'cnic' ? 'text-white' : 'text-rose-600'"></i>
                                    <span class="text-[10px] font-bold">CNIC Number</span>
                                </button>
                            </div>
                        </div>

                        {{-- Current Value vs Proposed Value Dedicated Bento Card --}}
                        <div class="rounded-xl border border-violet-100 bg-violet-50/50 p-3 space-y-2.5">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="font-bold text-violet-900 flex items-center gap-1.5">
                                    <i class="fa-solid fa-pen-ruler text-violet-600 text-xs"></i>
                                    <span>Target Field: <strong x-text="profileFieldLabel"></strong></span>
                                </span>
                                <span class="text-[10px] font-semibold text-slate-500 flex items-center gap-1">
                                    <i class="fa-solid fa-lock text-[9px]"></i>
                                    Current Registered Value:
                                    <span class="font-mono font-bold text-slate-800 bg-white px-1.5 py-0.5 rounded border border-slate-200"
                                        x-text="generalCategory === 'full_name' ? '{{ $user->full_name ?? 'Not set' }}' : (generalCategory === 'email' ? '{{ $user->email ?? 'Not set' }}' : (generalCategory === 'phone' ? '{{ $user->mobile_no ?? 'Not set' }}' : (generalCategory === 'designation' ? '{{ $user->designation?->name ?? 'Not set' }}' : '{{ $user->cnic ?? 'Not set' }}')))">
                                    </span>
                                </span>
                            </div>

                            {{-- Input 1: Full Name --}}
                            <div x-show="generalCategory === 'full_name'">
                                <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1">
                                    Proposed Correct Full Name <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="new_full_name" x-model="profileFullName"
                                    placeholder="Enter correct full official name"
                                    :required="activeTab === 'other_account' && generalCategory === 'full_name'"
                                    class="w-full text-xs font-semibold rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500 py-2 px-3 shadow-sm bg-white">
                            </div>

                            {{-- Input 2: Email --}}
                            <div x-show="generalCategory === 'email'">
                                <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1">
                                    Proposed New Email Address <span class="text-rose-500">*</span>
                                </label>
                                <input type="email" name="new_email" x-model="profileEmail"
                                    placeholder="official.officer@nhmp.gov.pk"
                                    :required="activeTab === 'other_account' && generalCategory === 'email'"
                                    class="w-full text-xs font-semibold rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500 py-2 px-3 shadow-sm bg-white">
                            </div>

                            {{-- Input 3: Phone --}}
                            <div x-show="generalCategory === 'phone'">
                                <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1">
                                    Proposed Correct Phone Number <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="new_phone" x-model="profilePhone"
                                    placeholder="03XXXXXXXXX"
                                    :required="activeTab === 'other_account' && generalCategory === 'phone'"
                                    class="w-full text-xs font-semibold rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500 py-2 px-3 shadow-sm bg-white">
                            </div>

                            {{-- Input 4: Designation --}}
                            <div x-show="generalCategory === 'designation'">
                                <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1">
                                    Select Correct Rank / Designation <span class="text-rose-500">*</span>
                                </label>
                                <select name="new_designation_id" x-model="profileDesignationId"
                                    :required="activeTab === 'other_account' && generalCategory === 'designation'"
                                    class="w-full text-xs font-semibold rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500 py-2 px-3 shadow-sm bg-white">
                                    <option value="">-- Choose Designation --</option>
                                    @if(isset($designations))
                                        @foreach($designations as $d)
                                            <option value="{{ $d->id }}" {{ $user->designation_id == $d->id ? 'selected' : '' }}>
                                                {{ $d->name }} ({{ $d->short_code ?? 'BPS-' . $d->bps }})
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>

                            {{-- Input 5: CNIC --}}
                            <div x-show="generalCategory === 'cnic'">
                                <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1">
                                    Proposed Correct CNIC Number <span class="text-rose-500">*</span>
                                </label>
                                <input type="text" name="new_cnic" x-model="profileCnic"
                                    placeholder="XXXXX-XXXXXXX-X"
                                    :required="activeTab === 'other_account' && generalCategory === 'cnic'"
                                    class="w-full text-xs font-semibold rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500 py-2 px-3 shadow-sm bg-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1">
                                Subject / Summary <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="subject" x-model="generalSubject" :required="activeTab === 'other_account'"
                                placeholder="Enter request title"
                                class="w-full text-xs font-semibold rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500 py-2 px-3 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-[10px] font-black text-slate-700 uppercase tracking-wider mb-1">
                                Detailed Operational Justification <span class="text-rose-500">*</span>
                            </label>
                            <textarea name="description" x-model="generalDescription" rows="2" :required="activeTab === 'other_account'"
                                placeholder="Provide complete context..."
                                class="w-full text-xs font-medium rounded-xl border-slate-200 focus:border-violet-500 focus:ring-violet-500 p-2.5 shadow-sm"></textarea>
                        </div>
                    </div>
                </div>

                {{-- UNIVERSAL STEP-UP RE-AUTHENTICATION CARD (REQUIRED IN ALL WORKFLOWS!) --}}
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-lg bg-blue-600 text-white flex items-center justify-center text-[11px] shadow-sm">
                                <i class="fa-solid fa-lock"></i>
                            </span>
                            <h4 class="text-xs font-black text-navy-900">Step-Up Re-Authentication Required</h4>
                        </div>
                        <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Cryptographic Validation Across All Workflows</span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">
                                Current Password <span class="text-rose-500">*</span>
                            </label>
                            <div class="relative">
                                <input :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" 
                                    placeholder="Enter current password"
                                    class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500 py-2 pr-9 pl-3 shadow-sm">
                                <button type="button" @click="showPassword = !showPassword" 
                                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                                    <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'"></i>
                                </button>
                            </div>
                        </div>

                        @if($user->hasMfaEnrolled())
                        <div>
                            <label class="block text-[10px] font-black text-slate-600 uppercase tracking-wider mb-1">
                                Authenticator Code (TOTP 6-Digits) <span class="text-rose-500">*</span>
                            </label>
                            <input type="text" name="totp_code" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="000000"
                                class="w-full text-xs rounded-xl border-slate-200 focus:border-blue-500 focus:ring-blue-500 font-mono tracking-widest text-center text-sm font-black py-2 shadow-sm">
                        </div>
                        @else
                        <div class="flex items-center text-[11px] text-slate-500 bg-slate-50 rounded-xl p-2.5 border border-slate-100">
                            <i class="fa-solid fa-circle-check text-emerald-500 mr-2 text-sm"></i>
                            Password authentication verifies your identity for this operation.
                        </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- ========================================================== --}}
            {{-- RIGHT COLUMN: Workflow Selection & Dark Luxury Console    --}}
            {{-- ========================================================== --}}
            <div class="lg:col-span-5 space-y-3">

                {{-- Card 1: 4-Choice Luxury Workflow Selection (Transfer, MFA, Block, General) --}}
                <div class="bg-white rounded-2xl border border-slate-200/90 shadow-sm p-3.5 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase tracking-wider text-slate-400">
                            Select Operational Workflow
                        </span>
                        <span class="text-[9px] font-black text-blue-600 uppercase tracking-wider">
                            4 Modes Available
                        </span>
                    </div>

                    {{-- 4 Luxury Classification Cards in 4 Columns --}}
                    <div class="grid grid-cols-4 gap-1.5 sm:gap-2">
                        
                        <!-- 1. Transfer Card -->
                        <button type="button" @click="activeTab = 'org_transfer'"
                            :class="activeTab === 'org_transfer' 
                                ? 'bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-lg shadow-blue-500/30 ring-2 ring-blue-500/40 translate-y-[-1px]' 
                                : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200/80'"
                            class="p-2 rounded-xl text-center transition-all duration-200 cursor-pointer flex flex-col items-center justify-center gap-1 group">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs transition-transform group-hover:scale-105"
                                :class="activeTab === 'org_transfer' ? 'bg-white/20 text-white' : 'bg-white text-blue-600 shadow-sm border border-slate-100'">
                                <i class="fa-solid fa-right-left"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-black block leading-tight">TRANSFER</span>
                                <span class="text-[8px] opacity-75 font-semibold block mt-0.5">Unit Shift</span>
                            </div>
                        </button>

                        <!-- 2. MFA Assistance Card -->
                        <button type="button" @click="activeTab = 'mfa_support'"
                            :class="activeTab === 'mfa_support' 
                                ? 'bg-gradient-to-br from-cyan-600 to-blue-700 text-white shadow-lg shadow-cyan-500/30 ring-2 ring-cyan-500/40 translate-y-[-1px]' 
                                : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200/80'"
                            class="p-2 rounded-xl text-center transition-all duration-200 cursor-pointer flex flex-col items-center justify-center gap-1 group">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs transition-transform group-hover:scale-105"
                                :class="activeTab === 'mfa_support' ? 'bg-white/20 text-white' : 'bg-white text-cyan-600 shadow-sm border border-slate-100'">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-black block leading-tight">MFA ACCESS</span>
                                <span class="text-[8px] opacity-75 font-semibold block mt-0.5">Token Reset</span>
                            </div>
                        </button>

                        <!-- 3. Emergency Block Card (Requested by user!) -->
                        <button type="button" @click="activeTab = 'account_block'"
                            :class="activeTab === 'account_block' 
                                ? 'bg-gradient-to-br from-rose-600 to-red-700 text-white shadow-lg shadow-rose-500/30 ring-2 ring-rose-500/40 translate-y-[-1px]' 
                                : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200/80'"
                            class="p-2 rounded-xl text-center transition-all duration-200 cursor-pointer flex flex-col items-center justify-center gap-1 group">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs transition-transform group-hover:scale-105"
                                :class="activeTab === 'account_block' ? 'bg-white/20 text-white' : 'bg-white text-rose-600 shadow-sm border border-slate-100'">
                                <i class="fa-solid fa-user-slash"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-black block leading-tight">BLOCK</span>
                                <span class="text-[8px] opacity-75 font-semibold block mt-0.5">Emergency</span>
                            </div>
                        </button>

                        <!-- 4. General Support Card -->
                        <button type="button" @click="activeTab = 'other_account'"
                            :class="activeTab === 'other_account' 
                                ? 'bg-gradient-to-br from-violet-600 to-purple-700 text-white shadow-lg shadow-violet-500/30 ring-2 ring-violet-500/40 translate-y-[-1px]' 
                                : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border border-slate-200/80'"
                            class="p-2 rounded-xl text-center transition-all duration-200 cursor-pointer flex flex-col items-center justify-center gap-1 group">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs transition-transform group-hover:scale-105"
                                :class="activeTab === 'other_account' ? 'bg-white/20 text-white' : 'bg-white text-violet-600 shadow-sm border border-slate-100'">
                                <i class="fa-solid fa-id-card-clip"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-black block leading-tight">PROFILE</span>
                                <span class="text-[8px] opacity-75 font-semibold block mt-0.5">Profile / Data</span>
                            </div>
                        </button>
                    </div>

                    {{-- Dynamic Informational Note (matches Image 1 note) --}}
                    <div class="text-[10px] text-slate-500 flex items-center gap-1.5 pt-0.5">
                        <i class="fa-solid fa-circle-info text-blue-500 shrink-0"></i>
                        <span x-show="activeTab === 'org_transfer'" class="leading-tight">
                            Movement between Zone, Sector, and Beat units with 3-stage cryptographic chain verification.
                        </span>
                        <span x-show="activeTab === 'mfa_support'" class="leading-tight">
                            Cryptographic single-use token issuance for lost handset or TOTP desynchronization.
                        </span>
                        <span x-show="activeTab === 'account_block'" class="leading-tight text-rose-600 font-semibold">
                            Immediate emergency suspension of active sessions and credentials revocation.
                        </span>
                        <span x-show="activeTab === 'other_account'" class="leading-tight text-violet-700 font-medium">
                            Administrative profile rectifications: Name, Email, Phone, Rank/Designation, or CNIC.
                        </span>
                    </div>
                </div>


                {{-- Card 3: DARK LUXURY DISPATCH CONSOLE (Exact match to ADMISSION CONSOLE in Image 1!) --}}
                <div class="bg-[#0A101D] text-white rounded-2xl p-4 border border-slate-800 shadow-2xl relative overflow-hidden space-y-3">
                    
                    {{-- Ambient Background Glow --}}
                    <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -left-10 -top-10 w-40 h-40 bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

                    {{-- Console Header --}}
                    <div class="relative z-10 flex items-center justify-between border-b border-slate-800 pb-2">
                        <span class="text-[10px] font-black uppercase tracking-[0.2em] text-blue-400">
                            Support Dispatch Console
                        </span>
                        <div class="flex items-center gap-1.5 text-[9px] font-bold" 
                            :class="activeTab === 'account_block' ? 'text-rose-400' : 'text-emerald-400'">
                            <span class="w-1.5 h-1.5 rounded-full animate-pulse"
                                :class="activeTab === 'account_block' ? 'bg-rose-400' : 'bg-emerald-400'"></span>
                            <span x-text="activeTab === 'account_block' ? 'EMERGENCY LOCKOUT' : 'DISPATCH READY'"></span>
                        </div>
                    </div>

                    {{-- Applicant Profile Banner (Matching PT in Image 1) --}}
                    <div class="relative z-10 flex items-center gap-2.5 p-2 rounded-xl bg-slate-900/90 border border-slate-800">
                        <div class="w-9 h-9 rounded-xl text-white font-black text-xs flex items-center justify-center shadow-md shrink-0"
                            :class="activeTab === 'account_block' ? 'bg-gradient-to-br from-rose-600 to-red-700 shadow-rose-500/30' : 'bg-gradient-to-br from-blue-600 to-indigo-600 shadow-blue-500/30'">
                            {{ $initials }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-black text-white truncate">{{ $user->full_name }}</span>
                                <span class="text-[9px] font-mono text-slate-400">({{ $user->username }})</span>
                            </div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider border"
                                    :class="activeTab === 'account_block' 
                                        ? 'bg-rose-500/20 text-rose-300 border-rose-500/30' 
                                        : 'bg-blue-500/20 text-blue-300 border-blue-500/30'"
                                    x-text="activeTab === 'org_transfer' ? 'TRANSFER' : (activeTab === 'mfa_support' ? 'MFA HELP' : (activeTab === 'account_block' ? 'BLOCK LOCK' : 'PROFILE RECTIFY'))"></span>
                                <span class="px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-wider bg-slate-800 text-slate-300">
                                    {{ $user->getRoleTitle() }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Real-Time Telemetry Data --}}
                    <div class="relative z-10 space-y-1.5 text-[11px] font-medium">
                        <div class="flex items-center justify-between text-slate-400">
                            <span>Request ID:</span>
                            <span class="text-slate-200 font-mono text-[10px]">Auto-Assigned UUIDv7</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400">
                            <span>Current Base:</span>
                            <span class="text-slate-200 font-semibold">{{ $currentScope?->office?->name ?? 'National Scope' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400" x-show="activeTab === 'org_transfer'">
                            <span>Target Beat:</span>
                            <span x-text="getDestinationBeatName()" class="font-bold text-amber-400 truncate max-w-[180px]"></span>
                        </div>
                        <div class="flex items-center justify-between text-slate-400">
                            <span>Security Action:</span>
                            <span :class="activeTab === 'account_block' ? 'text-rose-400' : 'text-emerald-400'" class="font-bold flex items-center gap-1 text-[10px]">
                                <i class="fa-solid fa-lock text-[8px]"></i>
                                <span x-text="activeTab === 'account_block' ? 'Session Invalidation' : 'Cryptographic Audit'"></span>
                            </span>
                        </div>
                    </div>

                    {{-- Checkbox Toggle (Direct to Clinical Intake style from Image 1) --}}
                    <div class="relative z-10 pt-0.5">
                        <label class="flex items-start gap-2 cursor-pointer text-[10px] text-slate-300">
                            <input type="checkbox" required checked 
                                class="mt-0.5 rounded border-slate-700 bg-slate-900 text-blue-600 focus:ring-0 focus:ring-offset-0 cursor-pointer">
                            <span class="leading-tight">
                                <strong>Certified Submission:</strong> I verify operational accuracy and acknowledge official dispatch under NHMP Standing Orders.
                            </span>
                        </label>
                    </div>

                    {{-- Vivid Glowing Submit Button (Matching REGISTER & GENERATE VISIT in Image 1) --}}
                    <div class="relative z-10 pt-1">
                        <button type="submit" 
                            :class="activeTab === 'account_block' 
                                ? 'bg-gradient-to-r from-rose-600 via-rose-700 to-red-800 hover:from-rose-500 hover:to-red-700 shadow-rose-600/30' 
                                : 'bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600 hover:from-blue-500 hover:to-purple-500 shadow-indigo-600/30'"
                            class="w-full py-2.5 px-4 rounded-xl font-black text-xs uppercase tracking-wider text-white shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer hover:scale-[1.01] active:scale-[0.99]">
                            <i :class="activeTab === 'account_block' ? 'fa-solid fa-user-slash' : 'fa-solid fa-paper-plane'" class="text-xs"></i>
                            <span x-text="activeTab === 'org_transfer' ? 'Dispatch Transfer Request' : (activeTab === 'mfa_support' ? 'Submit MFA Request' : (activeTab === 'account_block' ? 'Confirm & Block Account Immediately' : 'Submit Profile Rectification'))"></span>
                        </button>

                        <a href="{{ route('portal.support.index') }}" 
                            class="text-[10px] font-bold text-slate-400 hover:text-white transition-colors block text-center mt-2">
                            Cancel &amp; Return to Support Center
                        </a>
                    </div>

                </div>

            </div>

        </div>
    </form>
</div>
@endsection

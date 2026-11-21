@extends('layouts.app')
@section('title', 'Survey Locations - 130 NHMP Helpline')
@section('page-title', 'Survey Locations')
@section('content')
<div class="hs-wrap" x-data="{ filterOpen:false, reviewing:null, decision:'approve' }">
    @include('humsafar._style')
    <div class="hs-head">
        <div><h1>Survey Locations</h1><p>Business / facility details collected by patrol officers. Only approved and active locations reach commuters.</p></div>
        <form method="GET" class="hs-row">
            <input class="hs-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search name / address">
            <button class="hs-btn" type="submit">Search</button>
        </form>
    </div>
    <div class="hs-cards">
        @foreach(['draft'=>'Draft','submitted'=>'Awaiting review','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$label)
            <div class="hs-card"><b>{{ $counts[$k] ?? 0 }}</b><span>{{ $label }}</span></div>
        @endforeach
    </div>
    <div class="hs-panel hs-scroll">
        <table class="hs-table">
            <thead><tr><th>Location</th><th>Category</th><th>Beat / KM</th><th>Hours</th><th>Geo</th><th>Status</th><th>By</th><th></th></tr></thead>
            <tbody>
            @forelse($locations as $l)
                <tr>
                    <td><b>{{ $l->name }}</b><div style="font-size:11px;color:#94a3b8">{{ $l->address }}</div></td>
                    <td>{{ $l->category?->name }}</td>
                    <td>{{ $l->office?->name ?? '-' }}@if($l->km) <span class="hs-pill">KM {{ $l->km }} {{ $l->direction }}</span>@endif</td>
                    <td>{{ $l->is_24h ? '24 hours' : (substr((string)$l->opens_at,0,5).' - '.substr((string)$l->closes_at,0,5)) }}</td>
                    <td><span class="hs-pill {{ $l->geo_status==='matched'?'ok':($l->geo_status==='needs_verification'?'warn':'') }}">{{ str_replace('_',' ',$l->geo_status) }}</span></td>
                    <td><span class="hs-pill {{ $l->status==='approved'?'ok':($l->status==='rejected'?'bad':($l->status==='submitted'?'info':'')) }}">{{ $l->status }}</span></td>
                    <td>{{ $l->surveyor?->full_name ?? '-' }}</td>
                    <td>@if(in_array($l->status,['submitted','draft'])) <button type="button" class="hs-btn ghost" @click="reviewing={{ $l->id }}; decision='approve'">Review</button>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="hs-empty">No survey locations match these filters.</div></td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="padding:12px 16px">{{ $locations->links() }}</div>
    </div>

    <template x-teleport="body"><div x-show="reviewing" x-cloak style="display:none" class="hs-modalbg" @click.self="reviewing=null" @keydown.escape.window="reviewing=null">
        <form method="POST" :action="'{{ url('/humsafar/surveys') }}/'+reviewing+'/review'" class="hs-modal">
            @csrf
            <div style="font-weight:900;font-size:15px;margin-bottom:10px">Review survey</div>
            <div class="hs-row" style="margin-bottom:10px">
                <button type="button" class="hs-btn green" :style="decision==='approve'?'':'opacity:.45'" @click="decision='approve'">Approve</button>
                <button type="button" class="hs-btn red" :style="decision==='reject'?'':'opacity:.45'" @click="decision='reject'">Reject</button>
            </div>
            <input type="hidden" name="decision" :value="decision">
            <textarea name="note" class="hs-input" style="width:100%;height:80px;padding:10px" placeholder="Reason (required when rejecting)"></textarea>
            <div class="hs-row" style="margin-top:12px;justify-content:flex-end"><button type="button" class="hs-btn ghost" @click="reviewing=null">Cancel</button><button class="hs-btn" type="submit">Save</button></div>
        </form>
    </div></template>

    <x-filter-drawer open="filterOpen" title="Survey Filters" subtitle="Refine locations" count="{{ count(array_filter($filters)) }}" reset="window.location='{{ route('humsafar.surveys') }}'">
        <form method="GET" id="hs-filter-form" style="display:grid;gap:14px">
            <div class="fd-card"><div class="fd-label">Category</div><select class="fd-input" name="category_id"><option value="">All categories</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category_id']??'')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>
            <div class="fd-card"><div class="fd-label">Status</div><select class="fd-input" name="status"><option value="">Any</option>@foreach(['draft','submitted','approved','rejected'] as $s)<option value="{{ $s }}" @selected(($filters['status']??'')==$s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
            <div class="fd-card"><div class="fd-label">Geo match</div><select class="fd-input" name="geo_status"><option value="">Any</option>@foreach(['matched','needs_verification','unmatched'] as $s)<option value="{{ $s }}" @selected(($filters['geo_status']??'')==$s)>{{ str_replace('_',' ',$s) }}</option>@endforeach</select></div>
            <div class="fd-card"><div class="fd-label">Direction</div><select class="fd-input" name="direction"><option value="">Any</option><option value="N" @selected(($filters['direction']??'')==='N')>N</option><option value="S" @selected(($filters['direction']??'')==='S')>S</option></select></div>
            <div class="fd-card"><div class="fd-label">Created between</div><div class="fd-grid2"><input class="fd-input" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"><input class="fd-input" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div></div>
            <button class="fd-btn apply" type="submit" style="height:44px">Apply filters</button>
        </form>
    </x-filter-drawer>
</div>
@endsection

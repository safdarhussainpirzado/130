@extends('layouts.app')
@section('title', 'Help Feedback - 130 NHMP Helpline')
@section('page-title', 'Help Feedback')
@section('content')
<div class="hs-wrap">
    @include('humsafar._style')
    <div class="hs-head"><div><h1>Help Feedback</h1><p>Commuter ratings (1 to 5) after completed assistance.</p></div></div>
    <div class="hs-panel hs-scroll"><table class="hs-table"><thead><tr><th>Officer</th><th>Responses</th><th>Overall</th><th>Timeliness</th><th>Behaviour</th><th>Helpfulness</th></tr></thead><tbody>
    @forelse($perOfficer as $o)<tr><td><b>{{ $o->officer?->full_name ?? 'Unassigned' }}</b></td><td>{{ $o->n }}</td><td><span class="hs-pill {{ $o->avg_overall>=4?'ok':($o->avg_overall>=3?'warn':'bad') }}">{{ $o->avg_overall }}</span></td><td>{{ $o->avg_t }}</td><td>{{ $o->avg_b }}</td><td>{{ $o->avg_h }}</td></tr>
    @empty<tr><td colspan="6"><div class="hs-empty">No feedback yet.</div></td></tr>@endforelse</tbody></table></div>
    <div class="hs-panel hs-scroll"><table class="hs-table"><thead><tr><th>Case</th><th>Officer</th><th>Overall</th><th>Comment</th><th>When</th></tr></thead><tbody>
    @forelse($recent as $f)<tr><td>{{ $f->call?->call_number }}</td><td>{{ $f->officer?->full_name ?? '-' }}</td><td>{{ $f->overall }}/5</td><td>{{ $f->comment ?: '-' }}</td><td>{{ $f->submitted_at?->diffForHumans() }}</td></tr>
    @empty<tr><td colspan="5"><div class="hs-empty">Nothing submitted.</div></td></tr>@endforelse</tbody></table></div>
</div>
@endsection

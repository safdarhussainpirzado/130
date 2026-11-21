@extends('layouts.app')
@section('title', 'Commuter Directory - 130 NHMP Helpline')
@section('page-title', 'Commuter Directory')
@section('content')
<div class="hs-wrap" x-data="{ shown:{} , async reveal(id){ const r=await fetch('{{ url('/humsafar/commuters') }}/'+id+'/reveal',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]')?.content,'X-Requested-With':'XMLHttpRequest',Accept:'application/json'}}); if(r.ok){ const d=await r.json(); this.shown[id]=d.mobile; } else if(window.showError){ window.showError('Not allowed to reveal this number.'); } } }">
    @include('humsafar._style')
    <div class="hs-head"><div><h1>Commuter Directory</h1><p>Mobile numbers are masked. Revealing a number is audited.</p></div>
        <form method="GET" class="hs-row"><input class="hs-input" name="search" value="{{ $search }}" placeholder="Name, mobile or email"><button class="hs-btn" type="submit">Search</button></form></div>
    <div class="hs-panel hs-scroll"><table class="hs-table"><thead><tr><th>Commuter</th><th>Mobile</th><th>Email</th><th>CNIC verified</th><th>Joined</th></tr></thead><tbody>
    @forelse($commuters as $u)<tr><td><b>{{ $u->full_name }}</b></td>
        <td><span x-text="shown[{{ $u->id }}] || '{{ $u->masked_mobile }}'"></span>@if($canReveal) <button type="button" class="hs-pill" style="border:0;cursor:pointer" x-show="!shown[{{ $u->id }}]" @click="reveal({{ $u->id }})">Reveal</button>@endif</td>
        <td>{{ $u->email ?: '-' }}</td><td><span class="hs-pill {{ $u->commuterProfile?->is_cnic_verified?'ok':'' }}">{{ $u->commuterProfile?->is_cnic_verified?'Verified':'No' }}</span></td><td>{{ $u->created_at?->format('d M Y') }}</td></tr>
    @empty<tr><td colspan="5"><div class="hs-empty">No commuters found.</div></td></tr>@endforelse</tbody></table><div style="padding:12px 16px">{{ $commuters->links() }}</div></div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Online Officers - 130 NHMP Helpline')
@section('page-title', 'Online Officers')
@section('content')
<div class="hs-wrap" x-data="officersBoard()" x-init="init()">
    @include('humsafar._style')
    <div class="hs-head"><div><h1>Online Officers</h1><p>Heartbeat every 60 seconds. Online means seen within 3 minutes. Online is not the same as available.</p></div>
        <div class="hs-row">
            <select class="hs-input" x-model="online" @change="load()"><option value="">All</option><option value="1">Online</option><option value="0">Offline</option></select>
            <select class="hs-input" x-model="availability" @change="load()"><option value="">Any availability</option>@foreach($availabilities as $a)<option value="{{ $a }}">{{ str_replace('_',' ',$a) }}</option>@endforeach</select>
        </div></div>
    <div class="hs-cards"><div class="hs-card"><b x-text="summary.online"></b><span>Online</span></div><div class="hs-card"><b x-text="summary.available"></b><span>Online and available</span></div><div class="hs-card"><b x-text="summary.total"></b><span>Known officers</span></div></div>
    <div class="hs-panel hs-scroll"><table class="hs-table"><thead><tr><th>Officer</th><th>Presence</th><th>Availability</th><th>Last seen</th><th>Last location</th></tr></thead><tbody>
        <template x-for="r in rows" :key="r.username"><tr>
            <td><b x-text="r.name"></b><div style="font-size:11px;color:#94a3b8" x-text="r.username"></div></td>
            <td><span class="hs-pill" :class="r.online?'ok':''" x-text="r.online?'Online':'Offline'"></span></td>
            <td><span class="hs-pill info" x-text="(r.availability||'').replace('_',' ')"></span></td>
            <td x-text="r.last_seen||'-'"></td><td x-text="r.lat? (r.lat+', '+r.lng) : '-'"></td></tr></template>
        <tr x-show="!rows.length"><td colspan="5"><div class="hs-empty">No officers match.</div></td></tr></tbody></table></div>
</div>
<script>
function officersBoard(){return{rows:@json($rows),summary:@json($summary),online:'',availability:'',
 async load(){const p=new URLSearchParams();if(this.online!=='')p.set('online',this.online);if(this.availability)p.set('availability',this.availability);
  try{const r=await fetch('{{ route('humsafar.officers.data') }}?'+p,{headers:{'X-Requested-With':'XMLHttpRequest',Accept:'application/json'}});const d=await r.json();this.rows=d.rows;this.summary=d.summary;return r;}catch(e){return false}},
 init(){ if(window.nhmpPoll){window.nhmpPoll(()=>this.load(),{interval:30000});} } }}
</script>
@endsection

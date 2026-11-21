@extends('layouts.app')
@section('title', 'Live Commuter Monitoring - 130 NHMP Helpline')
@section('page-title', 'Live Commuters')
@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<div class="hs-wrap" x-data="monitorBoard()" x-init="init()">
    @include('humsafar._style')
    <div class="hs-head"><div><h1>Live Commuter Monitoring</h1><p>Only commuters who share their location and were seen in the last 5 minutes. No history is stored. Every view is audited.</p></div></div>
    <div class="hs-cards"><div class="hs-card"><b x-text="d.registered"></b><span>Registered</span></div><div class="hs-card"><b x-text="d.online"></b><span>Online</span></div><div class="hs-card"><b x-text="d.sharing"></b><span>Sharing location</span></div></div>
    <div class="hs-panel"><div id="hs-map" style="height:420px"></div></div>
    <div class="hs-panel hs-scroll"><table class="hs-table"><thead><tr><th>Commuter</th><th>Seen</th><th>Speed</th><th>Position</th></tr></thead><tbody>
        <template x-for="c in d.commuters" :key="c.id"><tr><td><b x-text="c.name"></b></td><td x-text="c.seen"></td><td x-text="c.speed!=null? Math.round(c.speed)+' km/h':'-'"></td><td x-text="c.lat+', '+c.lng"></td></tr></template>
        <tr x-show="!d.commuters.length"><td colspan="4"><div class="hs-empty">No commuters are sharing their location right now.</div></td></tr></tbody></table></div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function monitorBoard(){return{d:{registered:0,online:0,sharing:0,commuters:[]},map:null,layer:null,
 async load(){try{const r=await fetch('{{ route('humsafar.monitor.data') }}',{headers:{'X-Requested-With':'XMLHttpRequest',Accept:'application/json'}});this.d=await r.json();this.draw();return r;}catch(e){return false}},
 draw(){ if(!this.map) return; this.layer.clearLayers(); this.d.commuters.forEach(c=>L.circleMarker([c.lat,c.lng],{radius:7,color:'#4f46e5',fillOpacity:.8}).bindTooltip(c.name||'Commuter').addTo(this.layer)); },
 init(){ this.map=L.map('hs-map').setView([31.5,73.5],6); L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18}).addTo(this.map); this.layer=L.layerGroup().addTo(this.map); this.load();
  if(window.nhmpPoll){window.nhmpPoll(()=>this.load(),{interval:30000});} } }}
</script>
@endsection

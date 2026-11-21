@extends('layouts.app')
@section('title', 'Survey Categories & Services - 130 NHMP Helpline')
@section('page-title', 'Categories & Services')
@section('content')
<div class="hs-wrap">
    @include('humsafar._style')
    <div class="hs-head"><div><h1>Survey Categories &amp; Services</h1><p>Admin-editable catalogue used by the patrol and commuter apps.</p></div></div>
    <form method="POST" action="{{ route('humsafar.catalog.category.save') }}" class="hs-row hs-panel" style="padding:14px">
        @csrf
        <input class="hs-input" name="name" placeholder="New category name" required>
        <input class="hs-input" name="icon" placeholder="Icon (fa-solid fa-...)">
        <button class="hs-btn" type="submit">Add category</button>
    </form>
    @foreach($categories as $c)
    <div class="hs-panel" style="padding:16px">
        <div class="hs-row" style="justify-content:space-between">
            <div class="hs-row"><i class="{{ $c->icon ?: 'fa-solid fa-location-dot' }}" style="color:#6366f1"></i><b style="font-size:14px">{{ $c->name }}</b>
                <span class="hs-pill {{ $c->is_active ? 'ok' : 'bad' }}">{{ $c->is_active ? 'Active' : 'Inactive' }}</span></div>
            <form method="POST" action="{{ route('humsafar.catalog.toggle', ['type'=>'category','id'=>$c->id]) }}">@csrf<button class="hs-btn ghost" type="submit">{{ $c->is_active ? 'Deactivate' : 'Activate' }}</button></form>
        </div>
        <div class="hs-row" style="margin-top:12px">
            @foreach($c->services as $s)
                <form method="POST" action="{{ route('humsafar.catalog.toggle', ['type'=>'service','id'=>$s->id]) }}">@csrf
                    <button class="hs-pill {{ $s->is_active ? 'info' : 'bad' }}" style="border:0;cursor:pointer" title="Click to {{ $s->is_active ? 'deactivate' : 'activate' }}">{{ $s->name }}</button></form>
            @endforeach
            <form method="POST" action="{{ route('humsafar.catalog.service.save') }}" class="hs-row">@csrf
                <input type="hidden" name="category_id" value="{{ $c->id }}"><input class="hs-input" name="name" placeholder="Add service" required style="height:32px"><button class="hs-btn ghost" type="submit">Add</button></form>
        </div>
    </div>
    @endforeach
</div>
@endsection

@extends('layouts.app')
@section('title', 'Recovery codes')
@section('page-title', 'Recovery codes')
@section('content')
<div style="max-width:560px;margin:32px auto;padding:0 16px;">
    <h2>Emergency recovery codes</h2>
    <p>Recovery codes are optional. Generating new codes immediately invalidates every earlier code. They are shown only once, so store them offline.</p>
    <p>Unused codes currently on your account: <strong>{{ $remaining }}</strong></p>
    <form method="POST" action="{{ route('2fa.recovery.generate') }}">
        @csrf
        <button type="submit" onclick="return confirm('Generate new recovery codes? Old codes stop working.')">Generate new recovery codes</button>
    </form>
</div>
@endsection

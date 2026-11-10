<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ request()->routeIs('login') ? 'Sign in · 130 NHMP Helpline' : (request()->is('2fa*') ? 'Security Verification · 130 NHMP Helpline' : (request()->routeIs('password.*') ? 'Reset Password · 130 NHMP Helpline' : config('app.name', '130 NHMP Helpline'))) }}</title>

        <!-- Favicon & App Icons -->
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="icon" type="image/png" href="{{ asset('images/login/shield-logo.png') }}">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">

        <!-- Compiled Assets (All auth & security guest pages share the login design system) -->
        @vite(['resources/css/login.css', 'resources/js/login.js', 'resources/css/two-factor.css'])
    </head>
    <body class="">
        {{ $slot }}
    </body>
</html>

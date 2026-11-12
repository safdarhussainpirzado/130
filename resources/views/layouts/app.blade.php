@if(request()->header('X-PJAX'))
    <title>@yield('title', config('app.name', '130 NHMP Helpline'))</title>
    <div id="pjax-meta" 
         data-title="@yield('title', config('app.name', '130 NHMP Helpline'))"
         data-page-title="@yield('page-title', 'Dashboard')"
         data-route="{{ request()->route()?->getName() }}"
         data-url="{{ request()->fullUrl() }}"
         style="display:none;"></div>
    @include('partials.notifications')
    <main class="flex-1 overflow-y-auto bg-white">
        <div class="p-4">
            @yield('content')
        </div>
    </main>
    <div id="pjax-scripts" style="display:none;">
        @stack('scripts')
    </div>
@else
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="appLayout">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('app.name', '130 NHMP Helpline'))</title>

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/login/shield-logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100;200;300;400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    <!-- Compiled Assets (Tailwind, Alpine, etc.) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Leaflet Maps CSS & Vendor Assets (Persistent shell: always loaded for instant map transitions) -->
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/leaflet.css') }}" />
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/MarkerCluster.css') }}" />
    <link rel="stylesheet" href="{{ asset('vendor/leaflet/MarkerCluster.Default.css') }}" />
    <script src="{{ asset('vendor/leaflet/leaflet.js') }}"></script>
    <script src="{{ asset('vendor/leaflet/leaflet.markercluster.js') }}"></script>
    <script>
        // Global Defensive Guards for Leaflet Animation & Position Updates (Anti-Crash Layer)
        (function() {
            if (!window.L) return;

            // 1. L.Tooltip Guard (Fixes TypeError: Cannot read properties of null reading 'latLngToLayerPoint' or '_latLngToNewLayerPoint')
            if (L.Tooltip && !L.Tooltip._patchedZoomGuard) {
                L.Tooltip._patchedZoomGuard = true;
                const origTooltipAnimate = L.Tooltip.prototype._animateZoom;
                L.Tooltip.prototype._animateZoom = function(opt) {
                    if (!this._map || !this._latlng) return;
                    return origTooltipAnimate ? origTooltipAnimate.call(this, opt) : undefined;
                };
                const origTooltipUpdatePos = L.Tooltip.prototype._updatePosition;
                L.Tooltip.prototype._updatePosition = function() {
                    if (!this._map || !this._latlng) return;
                    return origTooltipUpdatePos ? origTooltipUpdatePos.call(this) : undefined;
                };
            }

            // 2. L.Marker Guard (Fixes TypeError: Cannot read properties of null reading '_latLngToNewLayerPoint')
            if (L.Marker && !L.Marker._patchedZoomGuard) {
                L.Marker._patchedZoomGuard = true;
                const origMarkerAnimate = L.Marker.prototype._animateZoom;
                L.Marker.prototype._animateZoom = function(opt) {
                    if (!this._map || !this._latlng) return;
                    return origMarkerAnimate ? origMarkerAnimate.call(this, opt) : undefined;
                };
                const origMarkerUpdatePos = L.Marker.prototype._updatePosition;
                L.Marker.prototype._updatePosition = function() {
                    if (!this._map || !this._latlng) return;
                    return origMarkerUpdatePos ? origMarkerUpdatePos.call(this) : undefined;
                };
            }

            // 3. L.Popup Guard
            if (L.Popup && !L.Popup._patchedZoomGuard) {
                L.Popup._patchedZoomGuard = true;
                const origPopupAnimate = L.Popup.prototype._animateZoom;
                L.Popup.prototype._animateZoom = function(opt) {
                    if (!this._map || !this._latlng) return;
                    return origPopupAnimate ? origPopupAnimate.call(this, opt) : undefined;
                };
                const origPopupUpdatePos = L.Popup.prototype._updatePosition;
                L.Popup.prototype._updatePosition = function() {
                    if (!this._map || !this._latlng) return;
                    return origPopupUpdatePos ? origPopupUpdatePos.call(this) : undefined;
                };
            }

            // 4. L.CircleMarker Guard
            if (L.CircleMarker && !L.CircleMarker._patchedZoomGuard) {
                L.CircleMarker._patchedZoomGuard = true;
                const origCircleAnimate = L.CircleMarker.prototype._animateZoom;
                if (origCircleAnimate) {
                    L.CircleMarker.prototype._animateZoom = function(opt) {
                        if (!this._map || !this._latlng) return;
                        return origCircleAnimate.call(this, opt);
                    };
                }
            }

            // 5. L.Path Guard
            if (L.Path && !L.Path._patchedZoomGuard) {
                L.Path._patchedZoomGuard = true;
                if (L.Path.prototype._animateZoom) {
                    const origPathAnimate = L.Path.prototype._animateZoom;
                    L.Path.prototype._animateZoom = function(opt) {
                        if (!this._map) return;
                        return origPathAnimate.call(this, opt);
                    };
                }
            }
        })();
    </script>
    @stack('leaflet')

    <!-- Notification & Routing JS -->
    <script defer src="{{ asset('js/notification.js') }}"></script>
    <script defer src="{{ asset('js/bento-bridge.js') }}?v=1.0.2"></script>
    
    {{-- Hardened Session Security with Cross-Tab Activity Synchronization --}}
    <script>
        (function() {
            "use strict";
            const LOGOUT_KEY = 'nhmp_session_logout';
            const ACTIVITY_KEY = 'nhmp_last_user_activity';
            const IDLE_LIMIT = 30 * 60 * 1000; // 30 mins

            // 1. Cross-Tab Sync: If another tab logs out, reload. If another tab is active, reset timer.
            window.addEventListener('storage', (e) => {
                if (e.key === LOGOUT_KEY && e.newValue) {
                    localStorage.removeItem(LOGOUT_KEY);
                    setTimeout(() => { window.location.reload(); }, 500);
                } else if (e.key === ACTIVITY_KEY && e.newValue) {
                    // Another tab recorded user activity! Reset local idle timer
                    scheduleIdleCheck();
                }
            });

            // 2. bfcache Protection
            window.addEventListener('pageshow', (e) => {
                if (e.persisted) window.location.reload();
            });

            // 3. Heartbeat Polling with Active Presence Indicator
            let isSyncing = false;
            function heartbeat() {
                if (isSyncing) return;
                let lastActive = 0;
                try {
                    lastActive = parseInt(localStorage.getItem(ACTIVITY_KEY) || '0', 10);
                } catch (e) {}

                // If user was active within last 2 minutes, signal active presence
                const isActive = (Date.now() - lastActive) < 120000;
                const url = isActive ? '/auth/heartbeat?active=1' : '/auth/heartbeat';

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(r => { 
                    if (r.status === 401 || r.status === 419) {
                        isSyncing = true;
                        window.location.href = '/login';
                    }
                })
                .catch(() => {});
            }
            setInterval(heartbeat, 60000); // 60s check
            heartbeat(); // Initial check

            // 4. Inactivity Auto-Logout with Multi-Tab Coordination
            let idleTimer = null;
            let lastThrottledRecord = 0;

            function recordActivity() {
                const now = Date.now();
                // Throttle writing to localStorage to once every 3 seconds
                if (now - lastThrottledRecord > 3000) {
                    lastThrottledRecord = now;
                    try {
                        localStorage.setItem(ACTIVITY_KEY, now.toString());
                    } catch (e) {}
                }
                scheduleIdleCheck();
            }

            function scheduleIdleCheck() {
                if (idleTimer) clearTimeout(idleTimer);
                idleTimer = setTimeout(checkIdleTimeout, IDLE_LIMIT);
            }

            function checkIdleTimeout() {
                // Verify if ANY open tab was active recently
                let lastActive = 0;
                try {
                    lastActive = parseInt(localStorage.getItem(ACTIVITY_KEY) || '0', 10);
                } catch (e) {}

                const elapsed = Date.now() - lastActive;
                if (lastActive > 0 && elapsed < IDLE_LIMIT) {
                    // Another tab was active! Reschedule timer for the remaining duration
                    const remaining = IDLE_LIMIT - elapsed;
                    idleTimer = setTimeout(checkIdleTimeout, Math.max(remaining, 5000));
                    return;
                }

                // If document is visible and has focus, do NOT abruptly terminate session
                if (document.visibilityState === 'visible' && document.hasFocus()) {
                    recordActivity();
                    return;
                }

                console.warn('SessionGuard: Multi-tab inactivity limit reached (30m). Logging out.');
                window.SessionGuard.triggerGlobalLogout();
                window.location.href = '/logout';
            }

            // Listen to all interaction events including keydown, input, and focus
            const activityEvents = ['mousedown', 'mousemove', 'keydown', 'input', 'scroll', 'touchstart', 'wheel', 'focus'];
            activityEvents.forEach(evt => {
                document.addEventListener(evt, recordActivity, { passive: true, capture: true });
            });

            // Initial activity record
            recordActivity();

            // Global trigger
            window.SessionGuard = {
                triggerGlobalLogout: function() {
                    try {
                        localStorage.setItem(LOGOUT_KEY, Date.now().toString());
                    } catch (e) {}
                },
                recordActivity: recordActivity
            };
        })();
    </script>

    @stack('styles')

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Outfit', sans-serif;
        }

        [x-cloak] {
            display: none !important;
        }

        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Navy colors */
        .text-navy-900 {
            color: #0a1c2f !important;
        }

        .bg-navy-900 {
            background-color: #0a1c2f !important;
        }

        .border-navy-900 {
            border-color: #0a1c2f !important;
        }

        .from-navy-900 {
            --tw-gradient-from: #0a1c2f;
        }

        .shadow-navy-900\/30 {
            --tw-shadow-color: rgba(10, 28, 47, 0.3);
        }

        /* Prevent Leaflet maps from breaking stacking contexts and bleeding over modals */
        .leaflet-container {
            isolation: isolate !important;
            z-index: 1 !important;
        }

        /* ============================================
           SIDEBAR SYSTEM
        ============================================ */
        .sidebar-wrap {
            width: 260px;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: visible;
            /* Allow toggle tab to overflow */
            flex-shrink: 0;
            position: relative;
        }

        .sidebar-wrap.collapsed {
            width: 68px;
        }

        /* Clip nav to sidebar width while keeping toggle visible */
        .sidebar-inner {
            width: 100%;
            overflow: hidden;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* Premium Floating Toggle Tab */
        .sidebar-toggle-tab {
            position: absolute;
            right: -20px;
            top: 50%;
            margin-top: -20px;
            z-index: 60;
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #ffffff, #f1f5f9);
            border: 3px solid #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            color: #64748b;
        }

        .sidebar-toggle-tab:hover {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            border-color: #3b82f6;
            color: white;
            transform: translate(2px, -2px) !important;
            box-shadow: -4px 4px 0px #1e40af, 0 10px 15px -3px rgba(37, 99, 235, 0.4);
        }

        .sidebar-toggle-tab:active {
            transform: translate(0px, 0px) !important;
            box-shadow: 0 2px 5px rgba(37, 99, 235, 0.3);
        }

        .sidebar-toggle-tab i {
            font-size: 0.85rem;
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .sidebar-wrap.collapsed .sidebar-toggle-tab i {
            transform: rotate(180deg) scale(1.1);
        }

        /* Mobile: slide in/out */
        @media (max-width: 1023px) {
            .sidebar-wrap {
                position: fixed;
                top: 0;
                bottom: 0;
                left: 0;
                z-index: 50;
                width: 260px !important;
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .sidebar-wrap.mobile-open {
                transform: translateX(0);
            }
        }

        /* Nav item base */
        .nav-item {
            display: flex;
            align-items: center;
            padding: 0.625rem 0.75rem;
            border-radius: 0.75rem;
            font-weight: 700;
            font-size: 0.8rem;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            position: relative;
            text-decoration: none;
            gap: 0.75rem;
            -webkit-tap-highlight-color: transparent;
            -webkit-user-select: none;
            user-select: none;
            outline: none;
        }

        .nav-item:focus, .nav-item:active {
            outline: none;
        }

        .nav-item:hover {
            background-color: #f1f5f9;
            color: #3b82f6;
        }

        .nav-item.active {
            background-color: #2563eb !important;
            color: white !important;
            box-shadow: -4px 4px 0px #1e40af, 0 10px 15px -3px rgba(37, 99, 235, 0.4) !important;
            transform: translate(3px, -3px) !important;
            font-weight: 950 !important;
            position: relative;
            z-index: 10;
        }

        .nav-item.active * {
            color: white !important;
        }

        /* Active item hover */
        .nav-item.active:hover {
            background-color: #1d4ed8 !important;
            transform: translate(1px, -1px) !important;
            box-shadow: -2px 2px 0px #1e40af, 0 10px 15px -3px rgba(37, 99, 235, 0.4) !important;
        }

        .nav-item:hover .nav-icon {
            color: #2563eb;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            flex-shrink: 0;
            color: #94a3b8;
            transition: color 0.2s;
        }

        .nav-label {
            transition: opacity 0.2s ease, transform 0.2s ease;
            opacity: 1;
            transform: translateX(0);
            overflow: hidden;
        }

        .collapsed .nav-label {
            opacity: 0;
            transform: translateX(-8px);
            pointer-events: none;
            width: 0;
        }

        /* Section headings */
        .nav-section {
            font-size: 0.6rem;
            font-weight: 900;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #cbd5e1;
            padding: 0.875rem 0.875rem 0.375rem;
            white-space: nowrap;
            transition: opacity 0.2s ease;
        }

        .collapsed .nav-section {
            opacity: 0;
            height: 0;
            padding: 0;
            overflow: hidden;
        }

        .collapsed .nav-section-divider {
            display: block !important;
        }

        .nav-section-divider {
            display: none;
            height: 1px;
            background: #e2e8f0;
            margin: 0.5rem 0.75rem;
        }

        /* Tooltip */
        .nav-tooltip {
            position: absolute;
            left: calc(100% + 12px);
            top: 50%;
            transform: translateY(-50%);
            background: #0f172a;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.375rem 0.75rem;
            border-radius: 0.5rem;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.15s ease, transform 0.15s ease;
            transform: translateY(-50%) translateX(-4px);
            z-index: 100;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .nav-tooltip::before {
            content: '';
            position: absolute;
            right: 100%;
            top: 50%;
            transform: translateY(-50%);
            border: 5px solid transparent;
            border-right-color: #0f172a;
        }

        .collapsed .nav-item:hover .nav-tooltip {
            opacity: 1;
            transform: translateY(-50%) translateX(0);
        }

        /* Search box */
        .sidebar-search {
            transition: opacity 0.2s ease;
        }

        .collapsed .sidebar-search {
            opacity: 0;
            height: 0;
            overflow: hidden;
            padding: 0;
            margin: 0;
        }

        /* User footer */
        .sidebar-user-label {
            transition: opacity 0.2s ease, width 0.2s ease;
            overflow: hidden;
        }

        .collapsed .sidebar-user-label {
            opacity: 0;
            width: 0;
        }

        /* Toggle button */
        .sidebar-toggle-btn {
            width: 28px;
            height: 28px;
            border-radius: 0.5rem;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease, transform 0.3s ease;
            flex-shrink: 0;
        }

        .sidebar-toggle-btn:hover {
            background: #e2e8f0;
            color: #2563eb;
        }

        .sidebar-toggle-btn.rotated {
            transform: rotate(180deg);
        }

        .collapsed .sidebar-toggle-btn {
            margin: 0 auto;
        }

        /* Brand area */
        .sidebar-brand-text {
            transition: opacity 0.2s ease, width 0.2s ease;
            overflow: hidden;
            white-space: nowrap;
        }

        .collapsed .sidebar-brand-text {
            opacity: 0;
            width: 0;
        }

        /* Primary badge pill */
        .nav-badge {
            margin-left: auto;
            font-size: 0.55rem;
            font-weight: 900;
            padding: 0.1rem 0.4rem;
            border-radius: 9999px;
            transition: opacity 0.2s ease;
        }

        .collapsed .nav-badge {
            opacity: 0;
        }

        /* Density / table utilities */
        .condensed-table td,
        .condensed-table th {
            padding-top: 0.5rem !important;
            padding-bottom: 0.5rem !important;
            font-size: 0.7rem !important;
        }

        .spacious-table td,
        .spacious-table th {
            padding-top: 1rem !important;
            padding-bottom: 1rem !important;
            font-size: 0.75rem !important;
        }

        .rose-reset-btn {
            background-color: #e11d48 !important;
            color: white !important;
            border: 1px solid #e11d48 !important;
        }

        .rose-reset-btn:hover {
            background-color: #be123c !important;
        }
        .card-3d-active {
            transform: translate(2px, -2px) !important;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1) !important;
        }
        .card-3d-active.amber { box-shadow: -4px 4px 0px #b45309, 0 10px 15px -3px rgba(245, 158, 11, 0.4) !important; }
        .card-3d-active.blue  { box-shadow: -4px 4px 0px #1e40af, 0 10px 15px -3px rgba(37, 99, 235, 0.4) !important; }
        .card-3d-active.indigo { box-shadow: -4px 4px 0px #4338ca, 0 10px 15px -3px rgba(79, 70, 229, 0.4) !important; }
        .card-3d-active.purple { box-shadow: -4px 4px 0px #7e22ce, 0 10px 15px -3px rgba(147, 51, 234, 0.4) !important; }
        .card-3d-active.emerald { box-shadow: -4px 4px 0px #047857, 0 10px 15px -3px rgba(16, 185, 129, 0.4) !important; }
        .card-3d-active.rose { box-shadow: -4px 4px 0px #be123c, 0 10px 15px -3px rgba(225, 29, 72, 0.4) !important; }
        .card-3d-active.sky { box-shadow: -4px 4px 0px #0369a1, 0 10px 15px -3px rgba(14, 165, 233, 0.4) !important; }
        .card-3d-active.slate { box-shadow: -4px 4px 0px #334155, 0 10px 15px -3px rgba(71, 85, 105, 0.4) !important; }
    </style>
</head>

<body x-data="heartbeatManager" class="font-sans antialiased text-slate-800 bg-[#f4f7fb] selection:bg-blue-500 selection:text-white">

    <div class="flex h-screen overflow-hidden">
        {{-- Global SPA Loader --}}
        <div x-show="navigating" 
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-[10000] bg-white/60 backdrop-blur-md flex flex-col items-center justify-center gap-4"
             x-cloak>
            <div class="relative">
                <div class="w-14 h-14 border-[3px] border-blue-100 border-t-blue-600 rounded-full animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <i class="fa-solid fa-shield-halved text-blue-600 text-xs animate-pulse"></i>
                </div>
            </div>
            <div class="flex flex-col items-center">
                <span class="text-[10px] font-black text-blue-700 uppercase tracking-[0.3em] animate-pulse">NHMP 130 Helpline</span>
                <span class="text-[8px] font-bold text-slate-400 uppercase tracking-widest mt-1">Syncing Emergency Operations Center...</span>
            </div>
        </div>

        <!-- Mobile overlay -->
        <div x-show="isMobile && mobileOpen" x-transition:enter="transition-opacity duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity duration-300" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="mobileOpen = false"
            class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 lg:hidden" x-cloak>
        </div>

        <!-- ============================
             SIDEBAR
        ============================ -->
        <aside class="sidebar-wrap h-screen bg-white border-r border-slate-100 shadow-[2px_0_20px_rgba(0,0,0,0.04)]"
            :class="{
                'collapsed': !isMobile && !expanded,
                'mobile-open': isMobile && mobileOpen
            }">

            <!-- Floating toggle tab (always visible) -->
            <button class="sidebar-toggle-tab hidden lg:flex group" @click="expanded = !expanded"
                :title="expanded ? 'Collapse Sidebar' : 'Expand Sidebar'">
                <i class="fa-solid fa-chevron-left"></i>
            </button>

            <div class="sidebar-inner">

                <!-- Brand Header -->
                <div class="flex items-center px-4 h-[64px] border-b border-slate-100 shrink-0 gap-3">
                    <div
                        class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-[0_4px_10px_rgba(37,99,235,0.3)] shrink-0 mx-auto">
                        <i class="fa-solid fa-shield-halved text-sm"></i>
                    </div>
                    <div class="flex flex-col sidebar-brand-text flex-1 min-w-0">
                        <span class="font-black text-base tracking-tight text-navy-900 leading-none">NHMP<span
                                class="text-blue-600">130</span></span>
                        <span class="text-[8px] font-black text-slate-400 tracking-[0.12em] uppercase mt-0.5">Control
                            Grid</span>
                    </div>
                    <!-- Mobile close -->
                    <button @click="mobileOpen = false"
                        class="lg:hidden w-7 h-7 flex items-center justify-center text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100">
                        <i class="fa-solid fa-times text-sm"></i>
                    </button>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto py-3 px-3 space-y-0.5 no-scrollbar">

                    @php 
                        $cur = Route::currentRouteName();
                        $st  = request('status');
                    @endphp

                    <!-- Dashboard -->
                    @can('dashboard.view')
                        <a href="{{ route('dashboard') }}"
                            class="nav-item {{ $cur === 'dashboard' ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span class="nav-label">Dashboard</span>
                            <span class="nav-tooltip">Dashboard</span>
                        </a>
                    @endcan

                    <!-- CALL CENTER -->
                    <div class="nav-section">Call Center</div>
                    <div class="nav-section-divider"></div>

                    @can('calls.create')
                        <a href="{{ route('calls.create') }}"
                            class="nav-item {{ $cur === 'calls.create' ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fa-solid fa-plus-circle"></i></span>
                            <span class="nav-label">Add Call</span>
                            <span class="nav-tooltip">Add Call</span>
                        </a>
                    @endcan

                    @if (Auth::user()->hasPermission('calls.view'))
                        <a href="{{ route('calls.pending') }}"
                            class="nav-item {{ $cur === 'calls.pending' || ($cur === 'calls.index' && $st === 'pending') ? 'active' : '' }}">
                            <span class="nav-icon text-rose-500"><i class="fa-solid fa-bell-concierge"></i></span>
                            <span class="nav-label">Pending Helps</span>
                            <span class="nav-badge bg-rose-100 text-rose-600 animate-pulse" 
                                  x-show="pendingCount > 0" 
                                  x-text="pendingCount" 
                                  x-transition>!</span>
                            <span class="nav-tooltip">Pending Helps</span>
                        </a>

                        <a href="{{ route('calls.inprogress') }}"
                            class="nav-item {{ $cur === 'calls.inprogress' || ($cur === 'calls.index' && $st === 'in_progress') ? 'active' : '' }}">
                            <span class="nav-icon text-amber-500"><i class="fa-solid fa-bolt-lightning"></i></span>
                            <span class="nav-label">In-Process Helps</span>
                            <span class="nav-tooltip">In-Process Helps</span>
                        </a>

                        <a href="{{ route('calls.tiger_reached') }}"
                            class="nav-item {{ $cur === 'calls.tiger_reached' || ($cur === 'calls.index' && $st === 'tiger_reached') ? 'active' : '' }}">
                            <span class="nav-icon text-sky-500"><i class="fa-solid fa-location-dot"></i></span>
                            <span class="nav-label">Tiger Reached</span>
                            <span class="nav-tooltip">Tiger Reached</span>
                        </a>

                        <a href="{{ route('calls.index') }}"
                            class="nav-item {{ $cur === 'calls.index' && !in_array($st, ['pending', 'in_progress', 'tiger_reached']) ? 'active' : '' }}">
                            <span class="nav-icon"><i class="fa-solid fa-list-ul"></i></span>
                            <span class="nav-label">All Helps Details</span>
                            <span class="nav-tooltip">All Helps Details</span>
                        </a>
                    @endif

                    <!-- TRAVEL ADVISORY -->
                    @php
                        $isSectorOperatorOrAdmin = in_array(Auth::user()?->role?->name, ['sector_admin', \App\Models\Role::SUPER_ADMIN], true) 
                            || (Auth::user() && Auth::user()->hasPermission('travel_advisory.view'));
                    @endphp

                    @if ($isSectorOperatorOrAdmin)
                        <div class="nav-section">Travel Advisory</div>
                        <div class="nav-section-divider"></div>

                        <a href="{{ route('ops.travel-advisory.create') }}"
                            class="nav-item {{ request()->routeIs('ops.travel-advisory.create') ? 'active' : '' }}">
                            <span class="nav-icon text-amber-500"><i class="fa-solid fa-tower-broadcast"></i></span>
                            <span class="nav-label">Log Advisory / Fog</span>
                            <span class="nav-tooltip">Wireless Operator Entry</span>
                        </a>

                        <a href="{{ route('ops.travel-advisory.index') }}"
                            class="nav-item {{ request()->routeIs('ops.travel-advisory.index') ? 'active' : '' }}">
                            <span class="nav-icon text-sky-500"><i class="fa-solid fa-triangle-exclamation"></i></span>
                            <span class="nav-label">Active Advisories Grid</span>
                            <span class="nav-tooltip">Corridor Management</span>
                        </a>

                        <a href="{{ route('public.travel-advisory') }}" target="_blank"
                            class="nav-item">
                            <span class="nav-icon text-emerald-500"><i class="fa-solid fa-earth-asia"></i></span>
                            <span class="nav-label">Commuter Portal</span>
                            <span class="nav-tooltip">Humsafar Public View</span>
                        </a>
                    @endif

                    <!-- SUPPORT MANAGEMENT -->
                    @canany(['support.view', 'support.manage', 'support.analytics', 'support.transfer_verify_source', 'support.transfer_confirm_destination', 'support.transfer_approve'])
                        <div class="nav-section">Support Management</div>
                        <div class="nav-section-divider"></div>

                        @php
                            $pendingSupportCount = (int) \App\Models\SupportRequest::pending()->count();
                        @endphp
                        <a href="{{ route('admin.support.index') }}"
                            class="nav-item {{ request()->routeIs('admin.support.*') ? 'active' : '' }} flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="nav-icon text-blue-600"><i class="fa-solid fa-headset"></i></span>
                                <span class="nav-label">Support Center</span>
                            </div>
                            <span id="support-pending-badge" 
                                  class="px-2 py-0.5 rounded-full text-[10px] font-black bg-rose-500 text-white shadow-sm ml-auto {{ $pendingSupportCount > 0 ? '' : 'hidden' }}">
                                {{ $pendingSupportCount }}
                            </span>
                            <span class="nav-tooltip">Support Center ({{ $pendingSupportCount }} Pending)</span>
                        </a>
                    @endcanany

                    @can('reports.view')
                        <!-- REPORTS -->
                        <div class="nav-section">Analytical Reports</div>
                        <div class="nav-section-divider"></div>

                        <a href="{{ route('reports.call-type-summary') }}"
                            class="nav-item {{ request()->routeIs('reports.call-type-summary') ? 'active' : '' }}">
                            <span class="nav-icon text-blue-500"><i class="fa-solid fa-list-check"></i></span>
                            <span class="nav-label">Call Type Summary</span>
                            <span class="nav-tooltip">Categorical</span>
                        </a>

                        <a href="{{ route('reports.agent-wise') }}"
                            class="nav-item {{ request()->routeIs('reports.agent-wise') ? 'active' : '' }}">
                            <span class="nav-icon text-emerald-500"><i class="fa-solid fa-user-check"></i></span>
                            <span class="nav-label">Agent Performance</span>
                            <span class="nav-tooltip">Personnel</span>
                        </a>

                        <a href="{{ route('reports.junk-calls-frequency') }}"
                            class="nav-item {{ request()->routeIs('reports.junk-calls-frequency') ? 'active' : '' }}">
                            <span class="nav-icon text-slate-500"><i class="fa-solid fa-ban"></i></span>
                            <span class="nav-label">Junk Calls Frequency</span>
                            <span class="nav-tooltip">Junk/Silent</span>
                        </a>

                        <a href="{{ route('reports.help-response-time') }}"
                            class="nav-item {{ request()->routeIs('reports.help-response-time') ? 'active' : '' }}">
                            <span class="nav-icon text-amber-500"><i class="fa-solid fa-stopwatch-20"></i></span>
                            <span class="nav-label">Help Response-Time</span>
                            <span class="nav-tooltip">Response & Assistance</span>
                        </a>
                    @endcan

                    @if (Auth::user()->hasPermission('users.view') ||
                         Auth::user()->hasPermission('security.mfa_roles.view') ||
                         Auth::user()->hasPermission('roles.view') ||
                         Auth::user()->hasPermission('permissions.view') ||
                         Auth::user()->hasPermission('system.audit_view') ||
                         Auth::user()->hasPermission('system.settings.view'))
                        <!-- ADMINISTRATIVE -->
                        <div class="nav-section">Administrative</div>
                        <div class="nav-section-divider"></div>

                        @can('users.view')
                            <a href="{{ route('admin.users.index') }}"
                                class="nav-item {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                <span class="nav-icon text-blue-600"><i class="fa-solid fa-user-gear"></i></span>
                                <span class="nav-label">User Management</span>
                                <span class="nav-tooltip">Users</span>
                            </a>
                        @endcan

                        @can('security.mfa_roles.view')
                            <a href="{{ route('admin.mfa-settings.index') }}"
                                class="nav-item {{ request()->routeIs('admin.mfa-settings.*') || request()->routeIs('admin.settings.*') ? 'active' : '' }}">
                                <span class="nav-icon text-cyan-600"><i class="fa-solid fa-shield-halved"></i></span>
                                <span class="nav-label">MFA Settings</span>
                                <span class="nav-tooltip">MFA Settings</span>
                            </a>
                        @endcan

                        @can('roles.view')
                            <a href="{{ route('admin.roles.index') }}"
                                class="nav-item {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}">
                                <span class="nav-icon text-amber-600"><i class="fa-solid fa-user-shield"></i></span>
                                <span class="nav-label">Roles Management</span>
                                <span class="nav-tooltip">Roles</span>
                            </a>

                            <a href="{{ route('admin.permissions.index') }}"
                                class="nav-item {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}">
                                <span class="nav-icon text-emerald-600"><i class="fa-solid fa-key"></i></span>
                                <span class="nav-label">Permission Management</span>
                                <span class="nav-tooltip">Permissions</span>
                            </a>

                            <a href="{{ route('admin.audit.index') }}"
                                class="nav-item {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}">
                                <span class="nav-icon text-indigo-600"><i class="fa-solid fa-fingerprint"></i></span>
                                <span class="nav-label">Security Audit Logs</span>
                                <span class="nav-tooltip">Security Audit Logs</span>
                            </a>
                        @endcan
                    @endif

                    @canany(['geography.offices.view', 'geography.carriageways.view', 'geography.geospatial.view'])
                        <!-- OPERATIONS -->
                        <div class="nav-section">Operations</div>
                        <div class="nav-section-divider"></div>

                        <a href="{{ route('admin.offices.index') }}"
                            class="nav-item {{ request()->routeIs('admin.offices.*') ? 'active' : '' }}">
                            <span class="nav-icon text-blue-600"><i class="fa-solid fa-earth-asia"></i></span>
                            <span class="nav-label">Operational Units</span>
                            <span class="nav-tooltip">Field offices</span>
                        </a>

                        <a href="{{ route('admin.carriageways.index') }}"
                            class="nav-item {{ request()->routeIs('admin.carriageways.*') ? 'active' : '' }}">
                            <span class="nav-icon text-emerald-600"><i class="fa-solid fa-road"></i></span>
                            <span class="nav-label">Highway Registry</span>
                            <span class="nav-tooltip">Highways</span>
                        </a>

                        <a href="{{ route('admin.geospatial-landmarks.index') }}"
                            class="nav-item {{ request()->routeIs('admin.geospatial-landmarks.*') ? 'active' : '' }}">
                            <span class="nav-icon text-indigo-500"><i class="fa-solid fa-satellite-dish"></i></span>
                            <span class="nav-label">Geospatial Landmarks</span>
                            <span class="nav-tooltip">Caller Helper (1,042)</span>
                        </a>

                        <a href="{{ route('admin.geospatial-markers.index') }}"
                            class="nav-item {{ request()->routeIs('admin.geospatial-markers.*') ? 'active' : '' }}">
                            <span class="nav-icon text-sky-500"><i class="fa-solid fa-map-location-dot"></i></span>
                            <span class="nav-label">Geospatial Markers</span>
                            <span class="nav-tooltip">Coordinate Grid (97,479)</span>
                        </a>
                    @endcanany

                </nav>

                <!-- User Profile Footer -->
                <div class="border-t border-slate-100 p-3 shrink-0">
                    <div class="flex items-center gap-3 px-1">
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 flex-1 min-w-0 hover:bg-slate-50 p-1 rounded-xl transition-colors group">
                            <div
                                class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-black text-sm shrink-0 shadow-sm group-hover:scale-110 transition-transform">
                                {{ strtoupper(substr(Auth::user()->username ?? 'U', 0, 1)) }}
                            </div>
                            <div class="flex flex-col min-w-0 sidebar-user-label flex-1">
                                <span
                                    class="text-xs font-black text-navy-900 tracking-tight truncate">{{ strtoupper(str_replace('_', ' ', Auth::user()->username ?? 'nhmp_admin')) }}</span>
                                <span class="text-[8px] font-black text-blue-500 uppercase tracking-widest mt-0.5">{{ Auth::user()?->getRoleTitle() ?? 'System Commander' }}</span>
                            </div>
                        </a>
                        <form method="POST" action="{{ route('logout') }}" data-no-pjax
                            class="sidebar-user-label shrink-0">
                            @csrf
                            <button type="submit"
                                onclick="if(window.SessionGuard) window.SessionGuard.triggerGlobalLogout();"
                                class="w-7 h-7 rounded-lg text-slate-400 hover:text-rose-500 hover:bg-rose-50 transition-all flex items-center justify-center"
                                title="Logout">
                                <i class="fa-solid fa-arrow-right-from-bracket text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>

            </div><!-- /sidebar-inner -->
        </aside>

        <!-- ============================
             MAIN CONTENT AREA
        ============================ -->
        <div class="flex-1 flex flex-col overflow-hidden min-w-0">

            <header
                class="h-[64px] bg-white px-5 flex items-center justify-between shrink-0 sticky top-0 z-30 border-b border-slate-100 shadow-[0_1px_0_rgba(0,0,0,0.04)]">
                <div class="flex items-center gap-4">
                    <!-- Mobile hamburger -->
                    <button @click="mobileOpen = true"
                        class="lg:hidden w-9 h-9 rounded-xl text-slate-400 hover:bg-slate-100 hover:text-blue-600 transition-colors flex items-center justify-center">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <h2 class="text-base font-black text-navy-900 tracking-tight">@yield('page-title', 'Dashboard')</h2>
                </div>

                <div class="flex items-center gap-4">
                    <div class="hidden lg:block text-right">
                        <div
                            class="text-[8px] font-black text-slate-400 uppercase tracking-widest leading-none mb-0.5">
                            System Time</div>
                        <div id="live-time" class="text-[10px] font-mono font-bold text-navy-900 leading-none"></div>
                    </div>
                    <div class="h-6 w-px bg-slate-200"></div>

                    {{-- Self-Service Support Center Access --}}
                    <a href="{{ route('portal.support.index') }}" 
                        class="h-10 px-3 rounded-2xl border-2 transition-all flex items-center gap-2 group text-xs font-bold {{ request()->routeIs('portal.support.*') ? 'bg-blue-50 border-blue-200 text-blue-700 shadow-sm' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-blue-50 hover:border-blue-200 hover:text-blue-700' }}"
                        title="Self-Service Support Center">
                        <i class="fa-solid fa-headset text-blue-600"></i>
                        <span class="hidden sm:inline">Support Center</span>
                    </a>

                    {{-- Voice Activation Protocol --}}
                    <button @click="toggleVoice()" 
                        class="w-10 h-10 rounded-2xl border-2 transition-all flex items-center justify-center relative group"
                        :class="voiceEnabled ? 'bg-emerald-50 border-emerald-100 text-emerald-600 shadow-lg shadow-emerald-500/10' : 'bg-rose-50 border-rose-100 text-rose-500'"
                        :title="voiceEnabled ? 'Mute Voice Alerts' : 'Enable Voice Alerts'">
                        <i class="fa-solid" :class="voiceEnabled ? 'fa-volume-high' : 'fa-volume-xmark'"></i>
                        <template x-if="voiceEnabled">
                            <span class="absolute -top-1 -right-1 w-3 h-3 bg-emerald-500 border-2 border-white rounded-full animate-pulse"></span>
                        </template>
                    </button>
                    <div class="hidden md:flex flex-col items-end leading-none">
                        <span
                            class="text-[11px] font-black text-navy-900 uppercase tracking-tight">{{ strtoupper(str_replace('_', ' ', Auth::user()->username ?? 'nhmp_admin')) }}</span>
                        <span class="text-[7px] font-black text-blue-600 uppercase tracking-widest mt-0.5">{{ Auth::user()?->getRoleTitle() ?? 'System Commander' }}</span>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto bg-white">
                <div class="p-4">
                    @yield('content')
                </div>
            </main>
        </div>

    </div>

    <script>
        function updateLiveTime() {
            const now = new Date();
            const el = document.getElementById('live-time');
            if (el) el.innerHTML = now.toLocaleDateString('en-US', {
                weekday: 'short',
                month: 'short',
                day: 'numeric'
            }) + ' | ' + now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        setInterval(updateLiveTime, 1000);
        updateLiveTime();
    </script>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('appLayout', () => ({
                expanded: window.innerWidth >= 1024,
                isMobile: window.innerWidth < 1024,
                mobileOpen: false,
                navigating: false,
                init() {
                    window.addEventListener('resize', () => {
                        const wasMobile = this.isMobile;
                        this.isMobile = window.innerWidth < 1024;
                        if (!this.isMobile) {
                            this.mobileOpen = false;
                            if (wasMobile) this.expanded = true;
                        }
                    });

                    window.addEventListener('bento:page-loading', () => {
                        this.navigating = true;
                    });
                    
                    window.addEventListener('bento:page-loaded', () => {
                        this.navigating = false;
                    });

                    // Optimistic UI for instant 3D active state on click
                    const navItems = document.querySelectorAll('.nav-item');
                    navItems.forEach(item => {
                        item.addEventListener('click', function() {
                            navItems.forEach(n => n.classList.remove('active'));
                            this.classList.add('active');
                        });
                    });
                }
            }));

            // Operational Intelligence: Heartbeat for Real-Time Trace Notifications
            Alpine.data('heartbeatManager', () => ({
                lastCheck: new Date().toISOString(),
                interval: null,
                activeUtterance: null, // Essential: Prevent garbage collection during speech
                pollFrequency: 20000, // 20 Seconds
                allowedRoles: ['beat_operator', 'sector_admin', 'zone_admin', 'agent_supervisor', 'super_admin'],
                userRole: '{{ auth()->user()->role?->name }}',
                pendingCount: 0,
                voiceEnabled: localStorage.getItem('nhmp_voice_enabled') === 'true',

                init() {
                    // Efficiency Protocol: Only specific regional roles initiate the heartbeat
                    if (!this.allowedRoles.includes(this.userRole)) {
                        return;
                    }

                    // Slight delay to allow system stabilization before first poll
                    setTimeout(() => {
                        this.poll();
                        this.interval = setInterval(() => this.poll(), this.pollFrequency);
                    }, 5000);
                },

                toggleVoice() {
                    this.voiceEnabled = !this.voiceEnabled;
                    localStorage.setItem('nhmp_voice_enabled', this.voiceEnabled);
                    
                    if (this.voiceEnabled) {
                        // Protocol Activation: Browser requires user activation to 'unlock' audio
                        const unlock = new SpeechSynthesisUtterance('Voice alerts activated.');
                        unlock.volume = 0.5;
                        unlock.rate = 1.0;
                        window.speechSynthesis.cancel(); // Clear any stalled speech
                        window.speechSynthesis.speak(unlock);
                        
                        if (window.showSuccess) window.showSuccess('Voice alerts activated.', 'Voice Protocol Enabled');
                    } else {
                        window.speechSynthesis.cancel();
                        if (window.showInfo) window.showInfo('Voice alerts disabled.', 'Voice Protocol Disabled');
                    }
                },

                async poll() {
                    try {
                        const response = await fetch(`/api/notifications/poll?since=${encodeURIComponent(this.lastCheck)}`, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        if (response.status === 403) {
                            console.warn('Heartbeat: Access denied (403). Polling suspended.');
                            if (this.interval) clearInterval(this.interval);
                            return;
                        }

                        const data = await response.json();

                        if (data.new_calls && data.new_calls.length > 0) {
                            data.new_calls.forEach(call => {
                                this.dispatchAlert(call);
                            });
                        }
                        
                        if (typeof data.pending_count !== 'undefined') {
                            this.pendingCount = data.pending_count;
                        }
                        
                        if (data.timestamp) {
                            this.lastCheck = data.timestamp;
                        }
                    } catch (e) {
                        console.error('Heartbeat sync failed:', e);
                    }
                },

                dispatchAlert(call) {
                    const category = call.call_type ? call.call_type.name : 'Unknown';
                    const isReminder = call.last_reminder_at && (!call.created_at || new Date(call.last_reminder_at) > new Date(call.created_at));
                    
                    const title = isReminder 
                        ? `🚨 REPEAT REMINDER: ${call.call_number}`
                        : `New Help Acquisition: ${call.call_number}`;
                    
                    const message = isReminder
                        ? `${category}: URGENT REMINDER for ${call.caller_name || 'Anonymous'}. Trace is still pending.`
                        : `${category}: ${call.caller_name || 'Anonymous Entity'} reported.`;

                    // UI Alert Notification (Premium System)
                    if (window.showWarning) {
                        window.showWarning(message, title);
                    } else if (window.Notification && window.Notification.warning) {
                        window.Notification.warning(message, title);
                    }

                    // Voice Alarm (Advanced Protocol)
                    if ('speechSynthesis' in window && this.voiceEnabled) {
                        try {
                            const voiceText = isReminder
                                ? `Attention. Urgent reminder. ${category} help acquisition is still pending in the command grid.`
                                : `Attention. New ${category} help acquisition recorded in the command grid.`;
                            
                            const utterance = new SpeechSynthesisUtterance(voiceText);
                            this.activeUtterance = utterance; // Store reference
                            utterance.rate = 1.0; // Standard rate for less delay
                            utterance.pitch = 1.0;
                            
                            // Essential: Clear queue and speak immediately
                            window.speechSynthesis.cancel(); 
                            setTimeout(() => {
                                window.speechSynthesis.speak(utterance);
                            }, 50); // Microsurgical delay for engine reset
                        } catch (err) {
                            console.warn('Voice alarm suppressed by browser security policy.');
                        }
                    }
                }
            }));
        });
    </script>

    <script>
        // Enforce Zero-Query-String Architecture: sanitize address bar across all application routes
        (function() {
            if (window.location.search) {
                try {
                    window.history.replaceState({}, document.title, window.location.pathname);
                } catch (e) {}
            }
        })();
    </script>

    @canany(['support.manage', 'support.view', 'support.transfer_approve'])
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let lastNotifiedId = parseInt(sessionStorage.getItem('nhmp_last_notified_support_id_u{{ (int) auth()->id() }}') || '0', 10);
            let isInitialCheck = true;

            async function checkPendingSupportRequests() {
                try {
                    const res = await fetch('{{ route("admin.support.pendingCount") }}', {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (!res.ok) return;
                    const data = await res.json();
                    
                    // Live update sidebar badge
                    const badge = document.getElementById('support-pending-badge');
                    if (badge) {
                        badge.textContent = data.count;
                        if (data.count > 0) {
                            badge.classList.remove('hidden');
                        } else {
                            badge.classList.add('hidden');
                        }
                    }

                    // Trigger JS toast via notification.js if new request or first load with pending
                    if (data.count > 0 && data.latest_id) {
                        if (isInitialCheck) {
                            if (lastNotifiedId !== data.latest_id) {
                                lastNotifiedId = data.latest_id;
                                sessionStorage.setItem('nhmp_last_notified_support_id_u{{ (int) auth()->id() }}', lastNotifiedId.toString());
                                if (window.showInfo) {
                                    window.showInfo(`There are ${data.count} pending request(s) awaiting review in Support Center.`, 'Pending Support Alert', 6000);
                                }
                            }
                        } else if (data.latest_id > lastNotifiedId) {
                            lastNotifiedId = data.latest_id;
                            sessionStorage.setItem('nhmp_last_notified_support_id_u{{ (int) auth()->id() }}', lastNotifiedId.toString());
                            const subject = data.subject || 'New Support Request';
                            if (window.showWarning) {
                                window.showWarning(`New Support Request: "${subject}". Awaiting operational decision.`, 'Support Center Dispatch', 7000);
                            }
                        }
                    }
                    isInitialCheck = false;
                } catch (e) {
                    // Fail silently on polling connection drop
                }
            }

            // Initial check + 30-second interval poll
            setTimeout(checkPendingSupportRequests, 1200);
            setInterval(checkPendingSupportRequests, 30000);
        });
    </script>
    @endcanany

    @include('partials.notifications')
    @stack('scripts')
</body>

</html>
@endif

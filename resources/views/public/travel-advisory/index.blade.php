<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Humsafar Travel Advisory | National Highways & Motorway Police</title>
    <meta name="description" content="Live motorway closures, dense fog visibility updates, roadworks, and travel advisories for National Highways & Motorways of Pakistan (NHMP).">

    <!-- Favicon & App Icons -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('images/login/shield-logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">

    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>

    <style>
        :root {
            --nhmp-navy: #002855;
            --nhmp-dark: #0a192f;
            --nhmp-blue: #0284c7;
            --nhmp-royal: #1d4ed8;
            --nhmp-gold: #f59e0b;
            --nhmp-accent: #38bdf8;
            --bg-canvas: #f1f5f9;
            --card-bg: #ffffff;
            --border-soft: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --color-danger: #ef4444;
            --color-warning: #f59e0b;
            --color-caution: #f97316;
            --color-success: #10b981;
            --color-info: #3b82f6;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-canvas);
            color: var(--text-dark);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Top Bar */
        .top-national-bar {
            background-color: #051937;
            color: #cbd5e1;
            font-size: 0.78rem;
            padding: 6px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .top-national-bar a {
            color: #93c5fd;
            text-decoration: none;
            transition: color 0.15s;
        }
        .top-national-bar a:hover {
            color: #ffffff;
        }

        /* Main NHMP Header */
        .nhmp-header {
            background: linear-gradient(180deg, var(--nhmp-navy) 0%, #001f42 100%);
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0, 40, 85, 0.25);
            position: relative;
            z-index: 30;
        }
        .nhmp-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: inherit;
        }
        .nhmp-logo-badge {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: radial-gradient(circle, #f59e0b 0%, #b45309 100%);
            border: 2px solid #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.85rem;
            color: #002855;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            flex-shrink: 0;
        }
        .nhmp-title-text h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            line-height: 1.2;
            letter-spacing: -0.01em;
        }
        .nhmp-title-text p {
            font-size: 0.75rem;
            color: #94a3b8;
            font-weight: 400;
        }

        /* Navigation Menu */
        .nhmp-nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .nhmp-nav a {
            color: #e2e8f0;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            padding: 8px 4px;
            position: relative;
            transition: color 0.15s;
        }
        .nhmp-nav a:hover {
            color: #ffffff;
        }
        .nhmp-nav a.active {
            color: #ffffff;
            font-weight: 600;
        }
        .nhmp-nav a.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 3px;
            background-color: var(--nhmp-gold);
            border-radius: 2px;
        }

        /* Humsafar Shield Badge */
        .humsafar-shield {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 6px 14px;
            border-radius: 8px;
            backdrop-filter: blur(4px);
        }
        .humsafar-shield-icon {
            color: #38bdf8;
            font-size: 1.5rem;
        }
        .humsafar-shield-text h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 800;
            line-height: 1;
            letter-spacing: 0.02em;
            color: #ffffff;
        }
        .humsafar-shield-text p {
            font-size: 0.68rem;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        /* Roadworks Radar UK Style Live Marquee Ticker */
        .live-ticker-wrap {
            background-color: #0b1329;
            border-bottom: 1px solid #1e293b;
            padding: 8px 16px;
            display: flex;
            align-items: center;
            overflow: hidden;
            position: relative;
            z-index: 25;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);
        }
        .ticker-pill-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
            margin-right: 14px;
            flex-shrink: 0;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .ticker-live-dot {
            width: 7px;
            height: 7px;
            background-color: #ffffff;
            border-radius: 50%;
            display: inline-block;
            animation: pulse-dot 1.4s infinite ease-in-out;
        }
        @keyframes pulse-dot {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: 0.6; }
        }
        .ticker-viewport {
            overflow: hidden;
            white-space: nowrap;
            width: 100%;
        }
        .ticker-content {
            display: inline-block;
            white-space: nowrap;
            animation: ticker-slide 42s linear infinite;
        }
        .ticker-viewport:hover .ticker-content {
            animation-play-state: paused;
        }
        @keyframes ticker-slide {
            0% { transform: translate3d(0, 0, 0); }
            100% { transform: translate3d(-50%, 0, 0); }
        }
        .ticker-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #cbd5e1;
            font-size: 0.8rem;
            margin-right: 32px;
            text-decoration: none;
            transition: color 0.15s;
        }
        .ticker-item:hover {
            color: #38bdf8;
        }
        .ticker-item strong {
            color: #38bdf8;
            font-weight: 700;
        }
        .ticker-item .tag-crit {
            background-color: rgba(239, 68, 68, 0.2);
            color: #f87171;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        .ticker-item .tag-warn {
            background-color: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        /* Container */
        .main-container {
            max-width: 1440px;
            margin: 0 auto;
            padding: 20px 24px 60px 24px;
        }

        /* Breadcrumbs & Status Bar */
        .portal-subbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .breadcrumbs {
            font-size: 0.8rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .breadcrumbs a {
            color: #0284c7;
            text-decoration: none;
        }
        .breadcrumbs span.current {
            color: var(--text-dark);
            font-weight: 500;
        }
        .status-pills-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .pill-sample {
            font-size: 0.72rem;
            color: #475569;
            background: #e2e8f0;
            padding: 3px 10px;
            border-radius: 9999px;
            font-weight: 500;
        }
        .pill-timestamp {
            font-size: 0.75rem;
            color: var(--text-muted);
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* Page Hero Title */
        .portal-hero {
            margin-bottom: 20px;
        }
        .portal-hero h2 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.85rem;
            font-weight: 800;
            color: var(--nhmp-navy);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .portal-hero p {
            font-size: 0.92rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* 3D Tactile Route Planner Box */
        .route-planner-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 18px 22px;
            box-shadow: 0 4px 20px -2px rgba(0, 40, 85, 0.08), 0 2px 6px -1px rgba(0, 0, 0, 0.04);
            border: 1px solid #e2e8f0;
            margin-bottom: 18px;
        }
        .planner-grid {
            display: grid;
            grid-template-columns: 2.2fr auto 2.2fr 2.4fr auto;
            gap: 12px;
            align-items: flex-end;
        }
        .planner-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .planner-label {
            font-size: 0.72rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .planner-input-box {
            position: relative;
            display: flex;
            align-items: center;
        }
        .planner-input-box select,
        .planner-input-box input {
            width: 100%;
            height: 44px;
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 9px;
            padding: 0 14px 0 38px;
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--text-dark);
            appearance: none;
            transition: all 0.2s;
            cursor: pointer;
        }
        .planner-input-box select:focus,
        .planner-input-box input:focus {
            outline: none;
            border-color: #0284c7;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }
        .input-icon-left {
            position: absolute;
            left: 14px;
            font-size: 0.95rem;
            pointer-events: none;
        }
        .icon-from { color: #0284c7; }
        .icon-to { color: #ef4444; }
        .icon-road { color: #002855; }
        .input-arrow-right {
            position: absolute;
            right: 14px;
            color: #94a3b8;
            font-size: 0.75rem;
            pointer-events: none;
        }
        .planner-helper {
            font-size: 0.7rem;
            color: #94a3b8;
            margin-top: 3px;
        }

        /* Swap Button */
        .btn-swap {
            width: 44px;
            height: 44px;
            background: #f1f5f9;
            border: 1.5px solid #cbd5e1;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.04);
            margin-bottom: 2px;
        }
        .btn-swap:hover {
            background: #e2e8f0;
            color: var(--nhmp-navy);
            transform: rotate(180deg);
        }

        /* 3D Action Button */
        .btn-view-advisories {
            height: 44px;
            padding: 0 24px;
            background: linear-gradient(180deg, #0284c7 0%, #0369a1 100%);
            color: #ffffff;
            border: 1px solid #0284c7;
            border-bottom: 3px solid #075985;
            border-radius: 9px;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 4px 10px rgba(2, 132, 199, 0.25);
            margin-bottom: 2px;
        }
        .btn-view-advisories:hover {
            background: linear-gradient(180deg, #0369a1 0%, #075985 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(2, 132, 199, 0.35);
        }
        .btn-view-advisories:active {
            transform: translateY(2px);
            border-bottom-width: 1px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }

        /* Popular Journey Chips */
        .popular-journeys-tray {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #e2e8f0;
            overflow-x: auto;
        }
        .popular-label {
            font-size: 0.72rem;
            color: #64748b;
            font-weight: 600;
            white-space: nowrap;
        }
        .chip-journey {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
            color: #334155;
            text-decoration: none;
            white-space: nowrap;
            transition: all 0.15s;
        }
        .chip-journey:hover {
            background: #e0f2fe;
            border-color: #7dd3fc;
            color: #0369a1;
        }

        /* Quick Filter Bar */
        .filter-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .filter-road-types {
            display: inline-flex;
            background: #e2e8f0;
            padding: 3px;
            border-radius: 8px;
            gap: 2px;
        }
        .pill-road-type {
            padding: 5px 14px;
            border-radius: 6px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            transition: all 0.15s;
        }
        .pill-road-type.active {
            background: #002855;
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filter-dropdowns {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .filter-select {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 6px 26px 6px 10px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #334155;
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2364748b'%3e%3cpath fill-rule='evenodd' d='M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z' clip-rule='evenodd'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 6px center;
            background-size: 16px;
            cursor: pointer;
        }
        .btn-filter-action {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            padding: 6px 12px;
            font-size: 0.8rem;
            font-weight: 500;
            color: #475569;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.15s;
            text-decoration: none;
        }
        .btn-filter-action:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: var(--text-dark);
        }

        /* 3D Category Pills Bar */
        .category-pills-bar {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 18px;
        }
        .cat-pill {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-bottom: 2px solid #cbd5e1;
            padding: 7px 14px;
            border-radius: 9999px;
            font-size: 0.82rem;
            font-weight: 600;
            color: #334155;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            white-space: nowrap;
            transition: all 0.15s;
        }
        .cat-pill:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            transform: translateY(-1px);
        }
        .cat-pill.active {
            background: #0284c7;
            color: #ffffff;
            border-color: #0284c7;
            border-bottom-color: #0369a1;
            box-shadow: 0 3px 8px rgba(2, 132, 199, 0.25);
        }
        .cat-pill.active .cat-count-badge {
            background: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .cat-count-badge {
            background: #e2e8f0;
            color: #475569;
            font-size: 0.72rem;
            font-weight: 700;
            padding: 1px 7px;
            border-radius: 9999px;
        }
        .icon-closure { color: #ef4444; }
        .icon-fog { color: #f59e0b; }
        .icon-weather { color: #0284c7; }
        .icon-traffic { color: #dc2626; }
        .icon-works { color: #f97316; }
        .icon-diversion { color: #8b5cf6; }

        /* Split Screen Workspace */
        .split-workspace {
            display: grid;
            grid-template-columns: 1.45fr 1fr;
            gap: 20px;
            align-items: stretch;
            min-height: 560px;
        }

        /* Map Panel (Left) */
        .map-card-panel {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .map-card-header {
            padding: 12px 18px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
        }
        .map-title-wrap h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--nhmp-navy);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .route-badge-m2 {
            background: #0284c7;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 5px;
            letter-spacing: 0.04em;
        }
        .map-title-wrap p {
            font-size: 0.76rem;
            color: var(--text-muted);
        }
        .map-tools-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-toggle-view {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.76rem;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-toggle-view.active {
            background: #002855;
            color: #ffffff;
            border-color: #002855;
        }
        .map-viewport {
            flex: 1;
            min-height: 480px;
            position: relative;
            background: #e2e8f0;
        }
        #humsafar-map {
            width: 100%;
            height: 100%;
            min-height: 480px;
        }

        /* 3D Map Pin Styling (Roadworks Radar UK style) */
        .pin-3d-wrap {
            position: relative;
            cursor: pointer;
            transform: translate(-50%, -100%);
            transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .pin-3d-wrap:hover {
            transform: translate(-50%, -115%) scale(1.1);
            z-index: 9999 !important;
        }
        .pin-3d-body {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1rem;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.35), inset 0 1px 1px rgba(255,255,255,0.4);
            border: 2px solid #ffffff;
            position: relative;
        }
        .pin-3d-body::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 8px solid inherit;
        }
        .pin-3d-fog {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            border-top-color: #d97706;
        }
        .pin-3d-works {
            background: repeating-linear-gradient(45deg, #ea580c, #ea580c 6px, #c2410c 6px, #c2410c 12px);
            border-top-color: #c2410c;
        }
        .pin-3d-traffic {
            background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%);
            border-top-color: #b91c1c;
        }
        .pin-3d-closure {
            background: linear-gradient(135deg, #b91c1c 0%, #7f1d1d 100%);
            border-top-color: #7f1d1d;
        }

        /* Map Bottom Legend */
        .map-legend-bar {
            padding: 8px 18px;
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.74rem;
            color: var(--text-muted);
        }
        .legend-items {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
            color: #334155;
        }
        .leg-line-route {
            width: 18px;
            height: 4px;
            background: #0284c7;
            border-radius: 2px;
        }
        .leg-dot-closure {
            color: #ef4444;
            font-size: 0.85rem;
        }
        .leg-dot-caution {
            color: #f97316;
            font-size: 0.85rem;
        }
        .leg-dot-info {
            color: #0284c7;
            font-size: 0.85rem;
        }

        /* Advisories Panel (Right) */
        .advisories-panel {
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 16px -2px rgba(0, 0, 0, 0.06);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .advisories-header {
            padding: 14px 18px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .advisories-header-title h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--nhmp-navy);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .advisories-header-title p {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        .badge-counter-circle {
            background: #0284c7;
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 800;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .advisories-list {
            padding: 12px;
            overflow-y: auto;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        /* Advisory Card */
        .advisory-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            position: relative;
        }
        .advisory-card:hover {
            border-color: #94a3b8;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
            transform: translateX(2px);
        }
        .advisory-card.selected {
            border-color: #0284c7;
            background: #f0f9ff;
            box-shadow: 0 0 0 1.5px #0284c7;
        }
        .adv-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.15rem;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }
        .icon-wrap-fog { background: #fef3c7; color: #d97706; }
        .icon-wrap-works { background: #ffedd5; color: #ea580c; }
        .icon-wrap-traffic { background: #fee2e2; color: #dc2626; }
        .icon-wrap-closure { background: #fee2e2; color: #991b1b; }
        .icon-wrap-weather { background: #e0f2fe; color: #0284c7; }

        .adv-body {
            flex: 1;
        }
        .adv-meta-category {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 2px;
        }
        .meta-fog { color: #d97706; }
        .meta-works { color: #ea580c; }
        .meta-traffic { color: #dc2626; }
        .meta-closure { color: #991b1b; }

        .adv-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
        }
        .adv-location {
            font-size: 0.78rem;
            font-weight: 600;
            color: #334155;
            margin-top: 2px;
        }
        .adv-desc {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 4px;
            line-height: 1.4;
        }
        .adv-tags-row {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            flex-wrap: wrap;
        }
        .pill-bound {
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 9999px;
        }
        .pill-tag-sample {
            background: #f1f5f9;
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 500;
            padding: 2px 8px;
            border-radius: 9999px;
        }
        .pill-vis {
            background: #fef3c7;
            color: #b45309;
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
        }
        .adv-timestamp {
            font-size: 0.72rem;
            color: #94a3b8;
            margin-top: 8px;
            text-align: right;
        }
        .adv-chevron {
            color: #cbd5e1;
            font-size: 0.85rem;
            margin-top: 10px;
            transition: color 0.15s;
        }
        .advisory-card:hover .adv-chevron {
            color: #0284c7;
        }

        .advisories-footer-tip {
            padding: 10px 16px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Travel News & Safety Notices Row */
        .safety-notices-section {
            margin-top: 26px;
        }
        .safety-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }
        .safety-section-header h3 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--nhmp-navy);
        }
        .safety-section-header a {
            font-size: 0.8rem;
            font-weight: 600;
            color: #0284c7;
            text-decoration: none;
        }
        .safety-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
        }
        .notice-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            gap: 14px;
            align-items: flex-start;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.2s;
        }
        .notice-card:hover {
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        .notice-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #f0f9ff;
            color: #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        .notice-body h4 {
            font-family: 'Outfit', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
        }
        .notice-body p {
            font-size: 0.78rem;
            color: #64748b;
            margin-top: 2px;
            line-height: 1.4;
        }
        .notice-pill {
            display: inline-block;
            margin-top: 8px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 9999px;
        }

        /* Footer */
        .portal-footer {
            background: #001a38;
            color: #94a3b8;
            padding: 14px 24px;
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.78rem;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .footer-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .footer-left strong {
            color: #e2e8f0;
        }
        .footer-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .footer-right a {
            color: #94a3b8;
            text-decoration: none;
            transition: color 0.15s;
        }
        .footer-right a:hover {
            color: #ffffff;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .planner-grid {
                grid-template-columns: 1fr;
            }
            .split-workspace {
                grid-template-columns: 1fr;
            }
            .safety-cards-grid {
                grid-template-columns: 1fr;
            }
            .nhmp-nav {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- 1. Top Government of Pakistan Bar -->
    <div class="top-national-bar">
        <div>
            <i class="fa-solid fa-star-and-crescent" style="color: #10b981; margin-right: 6px;"></i>
            Government of Pakistan
        </div>
        <div style="display: flex; align-items: center; gap: 16px;">
            <span><i class="fa-solid fa-phone" style="margin-right: 4px; color: #38bdf8;"></i> NHMP Helpline <strong>130</strong></span>
            <span>|</span>
            <a href="#">English</a>
            <span>|</span>
            <a href="#" style="font-family: system-ui;">اردو</a>
        </div>
    </div>

    <!-- 2. Main NHMP Header -->
    <header class="nhmp-header">
        <a href="{{ route('public.travel-advisory') }}" class="nhmp-brand">
            <div class="nhmp-logo-badge">NHMP</div>
            <div class="nhmp-title-text">
                <h1>National Highways & Motorway Police</h1>
                <p>Government of Pakistan</p>
            </div>
        </a>

        <nav class="nhmp-nav">
            <a href="/">Home</a>
            <a href="#">About NHMP <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem; margin-left: 2px;"></i></a>
            <a href="#">Public Services <i class="fa-solid fa-chevron-down" style="font-size: 0.7rem; margin-left: 2px;"></i></a>
            <a href="{{ route('public.travel-advisory') }}" class="active">Travel Advisory</a>
            <a href="#">News & Updates</a>
            <a href="#">Contact</a>
        </nav>

        <div class="humsafar-shield">
            <i class="fa-solid fa-shield-halved humsafar-shield-icon"></i>
            <div class="humsafar-shield-text">
                <h2>Humsafar</h2>
                <p>Travel information</p>
            </div>
        </div>
    </header>

    <!-- 3. Roadworks Radar UK Live Marquee Ticker -->
    <div class="live-ticker-wrap">
        <div class="ticker-pill-badge">
            <span class="ticker-live-dot"></span>
            Live Updates
        </div>
        <div class="ticker-viewport">
            <div class="ticker-content">
                @foreach ($tickerItems as $item)
                    <a href="{{ route('public.travel-advisory', ['road' => $item->route_name]) }}" class="ticker-item">
                        <strong>Live on the {{ $item->route_name }}</strong>
                        <span>{{ $item->title }}</span>
                        <span>•</span>
                        <span>{{ $item->start_landmark ?: "KM {$item->start_km}" }}</span>
                        @if ($item->visibility_meters && $item->visibility_meters < 100)
                            <span class="tag-warn">Vis < {{ $item->visibility_meters }}m</span>
                        @elseif ($item->closure_type)
                            <span class="tag-crit">{{ ucfirst(str_replace('_', ' ', $item->closure_type)) }}</span>
                        @endif
                    </a>
                @endforeach
                <!-- Duplicate for seamless loop -->
                @foreach ($tickerItems as $item)
                    <a href="{{ route('public.travel-advisory', ['road' => $item->route_name]) }}" class="ticker-item">
                        <strong>Live on the {{ $item->route_name }}</strong>
                        <span>{{ $item->title }}</span>
                        <span>•</span>
                        <span>{{ $item->start_landmark ?: "KM {$item->start_km}" }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- 4. Main Body Container -->
    <main class="main-container">

        <!-- Sub-bar: Breadcrumbs & Status -->
        <div class="portal-subbar">
            <div class="breadcrumbs">
                <a href="/">Home</a>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                <a href="#">Public Services</a>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.65rem;"></i>
                <span class="current">Travel Advisory</span>
            </div>
            <div class="status-pills-right">
                <span class="pill-sample">Design preview • Sample data</span>
                <span class="pill-timestamp"><i class="fa-solid fa-rotate" style="color: #0284c7;"></i> Updated {{ $updatedTime }} • Sample</span>
            </div>
        </div>

        <!-- Hero Header -->
        <div class="portal-hero">
            <h2>Humsafar Travel Advisory</h2>
            <p>Motorway and highway updates for your journey.</p>
        </div>

        <!-- 5. 3D Route Planner Bar -->
        <form method="GET" action="{{ route('public.travel-advisory') }}" class="route-planner-card" id="route-planner-form">
            <div class="planner-grid">
                <!-- FROM -->
                <div class="planner-field">
                    <label class="planner-label" for="select-from">From</label>
                    <div class="planner-input-box">
                        <i class="fa-regular fa-circle-dot input-icon-left icon-from"></i>
                        <select name="from" id="select-from">
                            @php
                                $cities = ['Karachi', 'Sukkur', 'Hyderabad', 'Multan', 'Faisalabad', 'Lahore', 'Rawalpindi', 'Islamabad', 'Peshawar', 'Quetta', 'Abdul Hakim', 'Pindi Bhattian', 'Sialkot', 'D.I. Khan', 'Bahawalpur', 'Rahim Yar Khan', 'Rohri', 'Gujranwala'];
                            @endphp
                            @if (!in_array($from, $cities))
                                <option value="{{ $from }}" selected>{{ $from }}</option>
                            @endif
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" {{ strcasecmp($from, $city) === 0 ? 'selected' : '' }}>{{ $city }}</option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down input-arrow-right"></i>
                    </div>
                    <span class="planner-helper">Choose cities, interchanges or public stops.</span>
                </div>

                <!-- Swap Button -->
                <button type="button" class="btn-swap" id="btn-swap-cities" title="Swap From and To">
                    <i class="fa-solid fa-arrow-right-arrow-left"></i>
                </button>

                <!-- TO -->
                <div class="planner-field">
                    <label class="planner-label" for="select-to">To</label>
                    <div class="planner-input-box">
                        <i class="fa-solid fa-location-dot input-icon-left icon-to"></i>
                        <select name="to" id="select-to">
                            @if (!in_array($to, $cities))
                                <option value="{{ $to }}" selected>{{ $to }}</option>
                            @endif
                            @foreach ($cities as $city)
                                <option value="{{ $city }}" {{ strcasecmp($to, $city) === 0 ? 'selected' : '' }}>{{ $city }}</option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down input-arrow-right"></i>
                    </div>
                    <span class="planner-helper">&nbsp;</span>
                </div>

                <!-- Road -->
                <div class="planner-field">
                    <label class="planner-label" for="select-road">Road</label>
                    <div class="planner-input-box">
                        <i class="fa-solid fa-road input-icon-left icon-road"></i>
                        <select name="road" id="select-road">
                            @foreach ($carriageways as $cw)
                                @php
                                    $cwCode = $cw->road_short ?: $cw->road;
                                    $isSelected = (strtoupper($currentRoad) === strtoupper($cwCode) || str_replace('-', '', strtoupper($currentRoad)) === str_replace('-', '', strtoupper($cwCode)));
                                @endphp
                                <option value="{{ $cwCode }}" {{ $isSelected ? 'selected' : '' }}>
                                    {{ $cw->road_name ?: ($cw->type . ' ' . $cwCode) }}
                                </option>
                            @endforeach
                        </select>
                        <i class="fa-solid fa-chevron-down input-arrow-right"></i>
                    </div>
                    <span class="planner-helper">&nbsp;</span>
                </div>

                <!-- Action Button -->
                <div>
                    <button type="submit" class="btn-view-advisories" id="btn-view-advisories">
                        <i class="fa-solid fa-compass"></i>
                        View route advisories
                    </button>
                    <div style="height: 16px;"></div>
                </div>
            </div>

            <!-- Popular Journeys -->
            <div class="popular-journeys-tray">
                <span class="popular-label">Popular routes:</span>
                @foreach ($popularJourneys as $pj)
                    <a href="{{ route('public.travel-advisory', ['from' => $pj['from'], 'to' => $pj['to'], 'road' => $pj['road']]) }}" class="chip-journey">
                        {{ $pj['from'] }} ⇄ {{ $pj['to'] }} ({{ $pj['road'] }})
                    </a>
                @endforeach
            </div>
        </form>

        <!-- 6. Quick Filter Toolbar -->
        <div class="filter-toolbar">
            <div class="filter-road-types">
                <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['road_type' => 'all'])) }}" class="pill-road-type">All roads</a>
                <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['road_type' => 'motorways'])) }}" class="pill-road-type active">Motorways</a>
                <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['road_type' => 'highways'])) }}" class="pill-road-type">National highways</a>
            </div>

            <div class="filter-dropdowns">
                <select class="filter-select" id="filter-direction">
                    <option value="both" {{ $direction === 'both' ? 'selected' : '' }}>Direction: Both</option>
                    <option value="northbound" {{ $direction === 'northbound' ? 'selected' : '' }}>Direction: Northbound</option>
                    <option value="southbound" {{ $direction === 'southbound' ? 'selected' : '' }}>Direction: Southbound</option>
                </select>

                <select class="filter-select" id="filter-type">
                    <option value="all">Advisory type: All</option>
                    <option value="fog">Fog & visibility</option>
                    <option value="road_closure">Road closure</option>
                    <option value="road_works">Road works</option>
                    <option value="traffic">Traffic</option>
                </select>

                <select class="filter-select">
                    <option>Updated: Latest</option>
                    <option>Updated: Last 1 hour</option>
                    <option>Updated: Last 6 hours</option>
                </select>

                <button class="btn-filter-action" type="button">
                    <i class="fa-solid fa-sliders"></i> More filters
                </button>

                <a href="{{ route('public.travel-advisory', ['road' => 'M-2', 'from' => 'Islamabad', 'to' => 'Lahore']) }}" class="btn-filter-action">
                    <i class="fa-solid fa-arrow-rotate-left"></i> Reset
                </a>
            </div>
        </div>

        <!-- 7. Category Pills Bar -->
        <div class="category-pills-bar">
            <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['type' => 'all'])) }}" class="cat-pill {{ $selectedType === 'all' ? 'active' : '' }}">
                <i class="fa-solid fa-layer-group"></i> All updates
                <span class="cat-count-badge">{{ $counters['all'] }}</span>
            </a>
            <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['type' => 'road_closure'])) }}" class="cat-pill {{ $selectedType === 'road_closure' ? 'active' : '' }}">
                <i class="fa-solid fa-ban icon-closure"></i> Road closure
                <span class="cat-count-badge">{{ $counters['road_closure'] }}</span>
            </a>
            <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['type' => 'fog'])) }}" class="cat-pill {{ $selectedType === 'fog' ? 'active' : '' }}">
                <i class="fa-solid fa-smog icon-fog"></i> Fog & visibility
                <span class="cat-count-badge">{{ $counters['fog'] }}</span>
            </a>
            <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['type' => 'weather'])) }}" class="cat-pill {{ $selectedType === 'weather' ? 'active' : '' }}">
                <i class="fa-solid fa-cloud-showers-heavy icon-weather"></i> Weather
                <span class="cat-count-badge">{{ $counters['weather'] }}</span>
            </a>
            <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['type' => 'traffic'])) }}" class="cat-pill {{ $selectedType === 'traffic' ? 'active' : '' }}">
                <i class="fa-solid fa-car icon-traffic"></i> Traffic
                <span class="cat-count-badge">{{ $counters['traffic'] }}</span>
            </a>
            <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['type' => 'road_works'])) }}" class="cat-pill {{ $selectedType === 'road_works' ? 'active' : '' }}">
                <i class="fa-solid fa-triangle-exclamation icon-works"></i> Road works
                <span class="cat-count-badge">{{ $counters['road_works'] }}</span>
            </a>
            <a href="{{ route('public.travel-advisory', array_merge(request()->query(), ['type' => 'diversion'])) }}" class="cat-pill {{ $selectedType === 'diversion' ? 'active' : '' }}">
                <i class="fa-solid fa-arrows-split-up-and-left icon-diversion"></i> Diversions
                <span class="cat-count-badge">{{ $counters['diversion'] }}</span>
            </a>
        </div>

        <!-- 8. Split Screen Main Area -->
        <div class="split-workspace">

            <!-- LEFT: Route Corridor Map Card -->
            <div class="map-card-panel">
                <div class="map-card-header">
                    <div class="map-title-wrap">
                        <h3>
                            <span>{{ $from }} → {{ $to }}</span>
                            <span class="route-badge-m2">{{ $currentRoad }}</span>
                        </h3>
                        <p>{{ $advisories->count() }} advisories on your selected route</p>
                    </div>
                    <div class="map-tools-right">
                        <button type="button" class="btn-toggle-view active" id="btn-view-map">
                            <i class="fa-solid fa-map"></i> Map
                        </button>
                        <button type="button" class="btn-toggle-view" id="btn-view-list">
                            <i class="fa-solid fa-list"></i> List
                        </button>
                        <button type="button" class="btn-toggle-view" id="btn-map-fullscreen" title="Fullscreen">
                            <i class="fa-solid fa-expand"></i>
                        </button>
                    </div>
                </div>

                <div class="map-viewport">
                    <div id="humsafar-map"></div>
                </div>

                <!-- Legend Bar -->
                <div class="map-legend-bar">
                    <div class="legend-items">
                        <div class="legend-item">
                            <div class="leg-line-route"></div>
                            <span>Selected route</span>
                        </div>
                        <div class="legend-item">
                            <i class="fa-solid fa-circle-xmark leg-dot-closure"></i>
                            <span>Closure</span>
                        </div>
                        <div class="legend-item">
                            <i class="fa-solid fa-triangle-exclamation leg-dot-caution"></i>
                            <span>Caution</span>
                        </div>
                        <div class="legend-item">
                            <i class="fa-solid fa-circle-info leg-dot-info"></i>
                            <span>Information</span>
                        </div>
                    </div>
                    <div>Map preview • Illustrative geography</div>
                </div>
            </div>

            <!-- RIGHT: Route Advisories Accordion List -->
            <div class="advisories-panel">
                <div class="advisories-header">
                    <div class="advisories-header-title">
                        <h3>
                            <span>Route advisories</span>
                            <span class="badge-counter-circle">{{ $advisories->count() }}</span>
                        </h3>
                        <p>Matched to {{ $currentRoad }} • Both directions</p>
                    </div>
                    <div>
                        <select class="filter-select" style="font-size: 0.75rem; padding: 4px 22px 4px 8px;">
                            <option>Latest first</option>
                            <option>Severity</option>
                            <option>Distance from origin</option>
                        </select>
                    </div>
                </div>

                <div class="advisories-list" id="advisories-container">
                    @forelse ($advisories as $adv)
                        @php
                            $iconClass = match($adv->advisory_type) {
                                'fog'          => 'fa-solid fa-smog',
                                'road_works'   => 'fa-solid fa-triangle-exclamation',
                                'traffic'      => 'fa-solid fa-car',
                                'road_closure' => 'fa-solid fa-ban',
                                default        => 'fa-solid fa-bell',
                            };
                            $iconWrapClass = match($adv->advisory_type) {
                                'fog'          => 'icon-wrap-fog',
                                'road_works'   => 'icon-wrap-works',
                                'traffic'      => 'icon-wrap-traffic',
                                'road_closure' => 'icon-wrap-closure',
                                default        => 'icon-wrap-weather',
                            };
                            $metaClass = match($adv->advisory_type) {
                                'fog'          => 'meta-fog',
                                'road_works'   => 'meta-works',
                                'traffic'      => 'meta-traffic',
                                'road_closure' => 'meta-closure',
                                default        => 'meta-fog',
                            };
                            $catLabel = match($adv->advisory_type) {
                                'fog'          => 'Fog & visibility',
                                'road_works'   => 'Road works',
                                'traffic'      => 'Traffic',
                                'road_closure' => 'Road closure',
                                default        => ucfirst(str_replace('_', ' ', $adv->advisory_type)),
                            };
                        @endphp
                        <div class="advisory-card" data-adv-id="{{ $adv->id }}" data-lat="{{ $adv->start_lat }}" data-lng="{{ $adv->start_lng }}" onclick="selectAdvisoryCard(this.dataset.advId, this.dataset.lat, this.dataset.lng)">
                            <div class="adv-icon-wrap {{ $iconWrapClass }}">
                                <i class="{{ $iconClass }}"></i>
                            </div>
                            <div class="adv-body">
                                <div class="adv-meta-category {{ $metaClass }}">{{ $catLabel }}</div>
                                <div class="adv-title">{{ $adv->title }}</div>
                                <div class="adv-location">
                                    {{ $adv->route_name }} • 
                                    @if ($adv->start_landmark && $adv->end_landmark)
                                        {{ $adv->start_landmark }} to {{ $adv->end_landmark }}
                                    @elseif ($adv->start_landmark)
                                        Near {{ $adv->start_landmark }}
                                    @else
                                        KM {{ $adv->start_km }} to KM {{ $adv->end_km }}
                                    @endif
                                </div>
                                <div class="adv-desc">{{ $adv->message }}</div>
                                <div class="adv-tags-row">
                                    <span class="pill-bound">
                                        {{ $adv->carriageway === 'northbound' ? 'Islamabad-bound' : ($adv->carriageway === 'southbound' ? 'Lahore-bound' : 'Both directions') }}
                                    </span>
                                    @if ($adv->visibility_meters && $adv->visibility_meters < 200)
                                        <span class="pill-vis"><i class="fa-solid fa-eye-slash" style="margin-right: 3px;"></i> Vis: {{ $adv->visibility_meters }}m</span>
                                    @endif
                                    <span class="pill-tag-sample">Sample</span>
                                </div>
                                <div class="adv-timestamp">
                                    Updated {{ $adv->published_at ? $adv->published_at->timezone('Asia/Karachi')->format('H:i \P\K\T') : '10:20 PKT' }}
                                </div>
                            </div>
                            <i class="fa-solid fa-chevron-right adv-chevron"></i>
                        </div>
                    @empty
                        <div style="text-align: center; padding: 40px 20px; color: #64748b;">
                            <i class="fa-regular fa-circle-check" style="font-size: 2.5rem; color: #10b981; margin-bottom: 12px; display: block;"></i>
                            <h4 style="font-size: 1.05rem; font-weight: 700; color: #0f172a;">All Clear</h4>
                            <p style="font-size: 0.8rem; margin-top: 4px;">No active advisories reported on this route section.</p>
                        </div>
                    @endforelse
                </div>

                <div class="advisories-footer-tip">
                    <i class="fa-solid fa-circle-info" style="color: #0284c7;"></i>
                    Select an update to read the full advisory and focus on map.
                </div>
            </div>

        </div>

        <!-- 9. Bottom Travel News & Safety Notices Row -->
        <section class="safety-notices-section">
            <div class="safety-section-header">
                <h3>Travel news & safety notices</h3>
                <a href="#">View all <i class="fa-solid fa-chevron-right" style="font-size: 0.7rem;"></i></a>
            </div>

            <div class="safety-cards-grid">
                <!-- Card 1 -->
                <div class="notice-card">
                    <div class="notice-icon-box">
                        <i class="fa-solid fa-umbrella"></i>
                    </div>
                    <div class="notice-body">
                        <h4>Wet-weather travel guidance</h4>
                        <p>Key safety tips for travelling in wet weather. Reduce cruising speed, check tyre tread depths, and maintain safe headway.</p>
                        <span class="notice-pill">Safety guidance</span>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="notice-card">
                    <div class="notice-icon-box" style="background: #f0fdf4; color: #16a34a;">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </div>
                    <div class="notice-body">
                        <h4>Check conditions before departure</h4>
                        <p>Review route advisories and plan your journey timing. Avoid night travel during dense fog alerts and heavy smog windows.</p>
                        <span class="notice-pill" style="background: #dcfce7; color: #15803d;">Travel notice</span>
                    </div>
                </div>

                <!-- Card 3 -->
                <div class="notice-card">
                    <div class="notice-icon-box" style="background: #fffbeb; color: #d97706;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div class="notice-body">
                        <h4>Stay alert on long journeys</h4>
                        <p>Maintain safe speed, keep a safe distance and take regular rest breaks at designated NHMP service areas every 2 hours.</p>
                        <span class="notice-pill" style="background: #fef3c7; color: #b45309;">Road safety</span>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <!-- 10. Footer -->
    <footer class="portal-footer">
        <div class="footer-left">
            <span>National Highways & Motorway Police</span>
            <span>|</span>
            <a href="https://nhmp.gov.pk" target="_blank" style="color: #93c5fd; text-decoration: none;">nhmp.gov.pk</a>
            <span>|</span>
            <span>Helpline 130</span>
        </div>
        <div class="footer-right">
            <a href="#">Accessibility</a>
            <span>|</span>
            <a href="#">Privacy</a>
            <span>|</span>
            <span>Read-only travel information</span>
        </div>
    </footer>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Server Page State Injected as JSON inside hidden DOM element (Zero IDE JS/TS parsing) -->
    <div id="travel-advisory-data" style="display:none;" aria-hidden="true">{!! json_encode([
        'currentRoad'    => $currentRoad,
        'currentFrom'    => $from,
        'currentTo'      => $to,
        'advisoriesData' => $allAdvisories,
    ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</div>

    <script>
        // Advisories data and route parameters parsed safely
        const _travelPageData = JSON.parse(document.getElementById('travel-advisory-data')?.textContent || '{}');
        const currentRoad    = _travelPageData.currentRoad || '';
        const currentFrom    = _travelPageData.currentFrom || '';
        const currentTo      = _travelPageData.currentTo || '';
        const advisoriesData = _travelPageData.advisoriesData || [];

        let mapInstance = null;
        let routePolyline = null;
        let routeGlowPolyline = null;
        let cityLabelsGroup = L.layerGroup();
        let markersGroup = L.layerGroup();

        // City Coordinates Master Dictionary
        const CITY_COORDINATES = {
            'Islamabad': [33.6844, 73.0479],
            'Rawalpindi': [33.5651, 73.0169],
            'Lahore': [31.5204, 74.3587],
            'Peshawar': [34.0151, 71.5249],
            'Faisalabad': [31.4187, 73.0791],
            'Multan': [30.1575, 71.5249],
            'Sukkur': [27.7052, 68.8574],
            'Hyderabad': [25.3960, 68.3578],
            'Karachi': [24.8607, 67.0011],
            'Quetta': [30.1798, 66.9750],
            'Abdul Hakim': [30.6569, 72.1356],
            'Pindi Bhattian': [31.7500, 73.6500],
            'Sialkot': [32.4945, 74.5229],
            'D.I. Khan': [31.8314, 70.9019],
            'Bahawalpur': [29.3544, 71.6911],
            'Rahim Yar Khan': [28.4212, 70.2989],
            'Rohri': [27.6740, 68.8950],
            'Gujranwala': [32.1877, 74.1945],
            'Abbottabad': [34.1688, 73.2215]
        };

        // Complete Pakistan Motorways & National Highways Corridors
        const CORRIDOR_COORDINATES = {
            'M-1': [ // Peshawar -> Islamabad (155 km)
                [34.0040, 71.6500], // Peshawar Chamkani
                [34.0280, 71.7200], // Charsadda
                [34.1200, 71.9800], // Rashakai / Mardan
                [34.0700, 72.4200], // Swabi
                [33.9100, 72.2400], // Indus River Bridge
                [33.8200, 72.6800], // Burhan / Hassan Abdal
                [33.7400, 72.7800], // Brahma Bahtar
                [33.6200, 72.9200]  // Islamabad Toll Plaza
            ],
            'M-2': [ // Islamabad -> Lahore (375 km)
                [33.6000, 72.9500], // Islamabad Toll Plaza
                [33.3092, 72.7789], // Chakri
                [32.9500, 72.7500], // Balkasar
                [32.7845, 72.7089], // Salt Range Descent
                [32.5756, 72.8017], // Kallar Kahar
                [32.3500, 72.9200], // Lilla
                [32.1879, 73.0370], // Bhera
                [32.0800, 73.1200], // Salam
                [31.9800, 73.2500], // Kot Momin
                [31.7500, 73.6500], // Pindi Bhattian
                [31.7000, 73.8300], // Sukheke
                [31.6200, 73.9500], // Sheikhupura
                [31.5540, 74.1950], // Faizpur
                [31.4663, 74.2291]  // Ravi Toll Plaza Lahore
            ],
            'M-3': [ // Lahore (Faizpur) -> Abdul Hakim (M-4 Junction) (230 km) - Official NHMP Motorway Centerline
                [31.5747, 74.2292], // KM 1148
                [31.5716, 74.2250], // KM 1147 Faizpur Interchange
                [31.5491, 74.1926], // KM 1143
                [31.5280, 74.1469], // KM 1138
                [31.5153, 74.0967], // KM 1133
                [31.5105, 74.0878], // KM 1132 Sharaqpur / Sheikhupura Interchange
                [31.4905, 74.0528], // KM 1128
                [31.4742, 74.0041], // KM 1123
                [31.4604, 73.9541], // KM 1118
                [31.4394, 73.9076], // KM 1113
                [31.4252, 73.8580], // KM 1108
                [31.4183, 73.8061], // KM 1103
                [31.4016, 73.7574], // KM 1098 Nankana Sahib Interchange
                [31.3826, 73.7097], // KM 1093
                [31.3636, 73.6622], // KM 1088
                [31.3377, 73.6196], // KM 1083
                [31.3092, 73.5791], // KM 1078
                [31.2832, 73.5363], // KM 1073
                [31.2614, 73.4911], // KM 1068
                [31.2397, 73.4577], // KM 1064 Jaranwala / Syedwala Interchange
                [31.2353, 73.4486], // KM 1063
                [31.2122, 73.4036], // KM 1058
                [31.1846, 73.3625], // KM 1053
                [31.1681, 73.3137], // KM 1048
                [31.1524, 73.2820], // KM 1045 Jaranwala Service Area
                [31.1440, 73.2635], // KM 1043
                [31.1330, 73.2127], // KM 1038
                [31.1134, 73.1657], // KM 1033
                [31.0984, 73.1165], // KM 1028
                [31.0727, 73.0736], // KM 1023
                [31.0426, 73.0348], // KM 1018
                [31.0308, 73.0190], // KM 1016 Samundri Interchange (Exit 5)
                [31.0156, 72.9931], // KM 1013
                [31.0031, 72.9429], // KM 1008
                [30.9976, 72.9122], // KM 1005 Samundri Rest Area
                [30.9936, 72.8918], // KM 1003
                [30.9767, 72.8437], // KM 998
                [30.9547, 72.7985], // KM 993
                [30.9418, 72.7484], // KM 988
                [30.9180, 72.7042], // KM 983
                [30.8994, 72.6566], // KM 978
                [30.8775, 72.6113], // KM 973
                [30.8477, 72.5721], // KM 968
                [30.8388, 72.5540], // KM 966 Rajana Chichawatni Interchange
                [30.8299, 72.5245], // KM 963
                [30.8138, 72.4757], // KM 958
                [30.7926, 72.4297], // KM 953
                [30.7831, 72.4120], // KM 951 Peer Mahal / Shorkot Interchange
                [30.7678, 72.3863], // KM 948
                [30.7378, 72.3478], // KM 943
                [30.7174, 72.3014], // KM 938
                [30.6935, 72.2573], // KM 933
                [30.6792, 72.2087], // KM 928
                [30.6678, 72.1594], // KM 923
                [30.6569, 72.1356]  // KM 920 Darkhana / Abdul Hakim (M-4 Junction)
            ],
            'M-4': [ // Pindi Bhattian -> Faisalabad -> Multan (309 km)
                [31.7500, 73.6500], // Pindi Bhattian
                [31.7100, 73.3800], // Sangla Hill
                [31.6400, 73.2200], // Sahianwala
                [31.4187, 73.0791], // Faisalabad
                [31.1500, 72.6800], // Gojra
                [30.9700, 72.4800], // Toba Tek Singh
                [30.8200, 72.0700], // Shorkot
                [30.5500, 72.1300], // Abdul Hakim
                [30.3000, 71.9300], // Khanewal
                [30.1575, 71.4250]  // Multan Sher Shah
            ],
            'M-5': [ // Multan -> Sukkur (392 km)
                [30.1575, 71.4250], // Multan
                [29.8800, 71.3000], // Shujabad
                [29.5000, 71.2200], // Jalalpur Pirwala
                [29.2300, 71.0700], // Uch Sharif
                [29.1400, 71.2600], // Ahmedpur East
                [28.9500, 70.8000], // Tarinda Muhammad Panah
                [28.8100, 70.5200], // Zahir Pir
                [28.4212, 70.2989], // Rahim Yar Khan
                [28.3000, 70.1300], // Sadiqabad
                [28.0000, 69.3100], // Ghotki
                [27.8500, 69.1100], // Pano Aqil
                [27.7052, 68.8574]  // Rohri / Sukkur
            ],
            'M-9': [ // Karachi -> Hyderabad (136 km)
                [24.9575, 67.0980], // Karachi Sohrab Goth
                [25.0120, 67.2010], // Gadap
                [25.0600, 67.3400], // Bahria Town Karachi
                [25.0900, 67.4500], // Kathore
                [25.1700, 68.0100], // Nooriabad
                [25.2600, 68.1200], // Thana Bula Khan
                [25.3700, 68.2700], // Kotri
                [25.4300, 68.2800]  // Jamshoro / Hyderabad
            ],
            'M-9-SUKKUR': [ // Karachi -> Hyderabad -> Sukkur Corridor (M-9 + N-5 artery)
                [24.8607, 67.0011], // Karachi City
                [24.9575, 67.0980], // Sohrab Goth Toll Plaza
                [25.0600, 67.3400], // Bahria Town Karachi
                [25.1700, 68.0100], // Nooriabad
                [25.4300, 68.2800], // Jamshoro / Hyderabad
                [25.5971, 68.4467], // Matiari
                [25.8118, 68.4219], // Hala
                [26.2483, 68.4096], // Nawabshah Junction
                [26.6629, 68.0006], // Moro
                [26.8400, 68.1200], // Naushahro Feroze
                [27.2885, 68.5085], // Ranipur
                [27.5295, 68.7592], // Khairpur
                [27.6740, 68.8950], // Rohri
                [27.7052, 68.8574]  // Sukkur
            ],
            'M-11': [ // Lahore -> Sialkot (103 km)
                [31.7200, 74.2700], // Kala Shah Kaku Lahore
                [31.8000, 74.2600], // Muridke
                [31.9000, 74.5200], // Narang Mandi
                [32.1500, 74.4500], // Gujranwala / Pasrur
                [32.3200, 74.3500], // Daska
                [32.4900, 74.3500]  // Sambrial / Sialkot
            ],
            'M-14': [ // Hakla -> D.I. Khan (292 km)
                [33.6200, 72.8200], // Hakla M-2 Junction
                [33.5600, 72.6400], // Fateh Jang
                [33.2400, 72.2600], // Pindi Gheb
                [33.0000, 71.9500], // Tarap
                [32.7000, 71.6000], // Kot Belian
                [32.6800, 71.2800], // Isa Khel
                [32.4000, 71.1800], // Kundal
                [32.0500, 70.8500]  // Yarik / D.I. Khan
            ],
            'E-35': [ // Hassan Abdal -> Thakot (180 km)
                [33.8200, 72.6800], // Hassan Abdal / Burhan
                [33.9900, 72.9300], // Haripur
                [34.0500, 73.1500], // Havelian
                [34.1600, 73.2200], // Abbottabad
                [34.3300, 73.2000], // Mansehra
                [34.4700, 73.2700], // Shinkiari
                [34.8000, 72.9300]  // Thakot
            ],
            'N-5': [ // Grand Trunk Road (Peshawar -> Karachi)
                [34.0151, 71.5249], // Peshawar
                [33.7700, 72.3600], // Attock
                [33.5651, 73.0169], // Rawalpindi
                [32.9400, 73.7300], // Jhelum
                [32.5700, 74.0700], // Gujrat
                [32.1877, 74.1945], // Gujranwala
                [31.5204, 74.3587], // Lahore
                [30.8100, 73.4500], // Okara
                [30.6700, 73.1100], // Sahiwal
                [30.1575, 71.5249], // Multan
                [29.3544, 71.6911], // Bahawalpur
                [28.4212, 70.2989], // Rahim Yar Khan
                [27.7052, 68.8574], // Sukkur
                [26.6629, 68.0006], // Moro
                [25.3960, 68.3578], // Hyderabad
                [24.8607, 67.0011]  // Karachi
            ]
        };

        document.addEventListener('DOMContentLoaded', function() {
            initLeafletMap();
            setupInteractiveControls();
        });

        function resolveRouteCorridor(road, fromCity, toCity) {
            const cleanRoad = (road || 'M-2').toUpperCase().replace(/\s+/g, '');
            const f = (fromCity || '').toLowerCase().trim();
            const t = (toCity || '').toLowerCase().trim();

            // Special check: Karachi <-> Sukkur (on M-9 or N-5)
            if ((f.includes('karachi') && t.includes('sukkur')) || (f.includes('sukkur') && t.includes('karachi'))) {
                return CORRIDOR_COORDINATES['M-9-SUKKUR'];
            }

            // Normal matching by clean road key
            if (cleanRoad.includes('M9') || cleanRoad === 'M-9') {
                if (t.includes('sukkur') || f.includes('sukkur')) {
                    return CORRIDOR_COORDINATES['M-9-SUKKUR'];
                }
                return CORRIDOR_COORDINATES['M-9'];
            }
            if (cleanRoad.includes('M11') || cleanRoad === 'M-11') return CORRIDOR_COORDINATES['M-11'];
            if (cleanRoad.includes('M14') || cleanRoad === 'M-14') return CORRIDOR_COORDINATES['M-14'];
            if (cleanRoad.includes('M1') || cleanRoad === 'M-1') return CORRIDOR_COORDINATES['M-1'];
            if (cleanRoad.includes('M3') || cleanRoad === 'M-3') return CORRIDOR_COORDINATES['M-3'];
            if (cleanRoad.includes('M4') || cleanRoad === 'M-4') return CORRIDOR_COORDINATES['M-4'];
            if (cleanRoad.includes('M5') || cleanRoad === 'M-5') return CORRIDOR_COORDINATES['M-5'];
            if (cleanRoad.includes('E35') || cleanRoad === 'E-35') return CORRIDOR_COORDINATES['E-35'];
            if (cleanRoad.includes('N5') || cleanRoad === 'N-5') return CORRIDOR_COORDINATES['N-5'];
            if (cleanRoad.includes('M2') || cleanRoad === 'M-2') return CORRIDOR_COORDINATES['M-2'];

            // Fallback: Check if we have both city coordinates
            if (CITY_COORDINATES[fromCity] && CITY_COORDINATES[toCity]) {
                const c1 = CITY_COORDINATES[fromCity];
                const c2 = CITY_COORDINATES[toCity];
                return [
                    c1,
                    [(c1[0]*0.75 + c2[0]*0.25), (c1[1]*0.75 + c2[1]*0.25)],
                    [(c1[0]*0.50 + c2[0]*0.50), (c1[1]*0.50 + c2[1]*0.50)],
                    [(c1[0]*0.25 + c2[0]*0.75), (c1[1]*0.25 + c2[1]*0.75)],
                    c2
                ];
            }

            return CORRIDOR_COORDINATES['M-2'];
        }

        function initLeafletMap() {
            const corridorCoords = resolveRouteCorridor(currentRoad, currentFrom, currentTo);

            // Compute center of the corridor
            const lats = corridorCoords.map(c => c[0]);
            const lngs = corridorCoords.map(c => c[1]);
            const centerLat = (Math.min(...lats) + Math.max(...lats)) / 2;
            const centerLng = (Math.min(...lngs) + Math.max(...lngs)) / 2;

            mapInstance = L.map('humsafar-map', {
                center: [centerLat, centerLng],
                zoom: 8,
                zoomControl: true,
                scrollWheelZoom: true
            });

            // Standard high-reliability OpenStreetMap tile layer without watermark
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors | NHMP 130',
                subdomains: 'abc',
                maxZoom: 19
            }).addTo(mapInstance);

            markersGroup.addTo(mapInstance);
            cityLabelsGroup.addTo(mapInstance);

            // Draw corridor polylines and fit bounds
            drawRouteCorridor(corridorCoords, currentFrom, currentTo);

            // Render 3D extruded hazard pins on corridor
            renderAdvisoryPins();
        }

        function drawRouteCorridor(coords, fromName, toName) {
            if (routeGlowPolyline) mapInstance.removeLayer(routeGlowPolyline);
            if (routePolyline) mapInstance.removeLayer(routePolyline);
            cityLabelsGroup.clearLayers();

            // Outer glow line for corridor
            routeGlowPolyline = L.polyline(coords, {
                color: '#38bdf8',
                weight: 8,
                opacity: 0.35,
                lineCap: 'round'
            }).addTo(mapInstance);

            // Core high-visibility route line
            routePolyline = L.polyline(coords, {
                color: '#0284c7',
                weight: 4.5,
                opacity: 0.95,
                lineCap: 'round',
                lineJoin: 'round'
            }).addTo(mapInstance);

            // City labels
            const fromCoord = CITY_COORDINATES[fromName] || coords[0];
            const toCoord = CITY_COORDINATES[toName] || coords[coords.length - 1];
            addCityLabel(fromCoord, fromName, 'from');
            addCityLabel(toCoord, toName, 'to');

            // Fit map bounds to the route polyline with comfortable padding
            try {
                mapInstance.fitBounds(routePolyline.getBounds(), {
                    padding: [50, 50],
                    maxZoom: 12
                });
            } catch (e) {
                console.warn('Could not fit bounds:', e);
            }
        }

        function addCityLabel(latlng, name, type) {
            const iconHtml = type === 'from' 
                ? '<div style="background: #0284c7; color: #fff; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; box-shadow: 0 2px 6px rgba(0,0,0,0.3); white-space: nowrap; border: 1.5px solid #fff;"><i class=\"fa-regular fa-circle-dot\" style=\"margin-right: 4px;\"></i>' + name + '</div>'
                : '<div style="background: #dc2626; color: #fff; padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 11px; box-shadow: 0 2px 6px rgba(0,0,0,0.3); white-space: nowrap; border: 1.5px solid #fff;"><i class=\"fa-solid fa-location-dot\" style=\"margin-right: 4px;\"></i>' + name + '</div>';

            const icon = L.divIcon({
                className: 'city-label-icon',
                html: iconHtml,
                iconSize: [80, 24],
                iconAnchor: [40, 12]
            });

            L.marker(latlng, { icon: icon }).addTo(cityLabelsGroup);
        }

        function renderAdvisoryPins() {
            markersGroup.clearLayers();

            advisoriesData.forEach(adv => {
                if (!adv.start_lat || !adv.start_lng) return;

                let iconTypeClass = 'pin-3d-fog';
                let iconGlyph = 'fa-smog';

                if (adv.advisory_type === 'road_works') {
                    iconTypeClass = 'pin-3d-works';
                    iconGlyph = 'fa-triangle-exclamation';
                } else if (adv.advisory_type === 'traffic') {
                    iconTypeClass = 'pin-3d-traffic';
                    iconGlyph = 'fa-car';
                } else if (adv.advisory_type === 'road_closure' || adv.advisory_type === 'diversion') {
                    iconTypeClass = 'pin-3d-closure';
                    iconGlyph = 'fa-ban';
                }

                const markerHtml = `
                    <div class="pin-3d-wrap" id="pin-${adv.id}">
                        <div class="pin-3d-body ${iconTypeClass}">
                            <i class="fa-solid ${iconGlyph}"></i>
                        </div>
                    </div>
                `;

                const customIcon = L.divIcon({
                    className: 'custom-advisory-pin',
                    html: markerHtml,
                    iconSize: [38, 46],
                    iconAnchor: [19, 46]
                });

                const marker = L.marker([adv.start_lat, adv.start_lng], { icon: customIcon });

                // Popup matching card style
                const popupContent = `
                    <div style="font-family: 'Inter', sans-serif; min-width: 210px; padding: 4px 2px;">
                        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #f59e0b; letter-spacing: 0.04em;">${(adv.advisory_type || 'advisory').toUpperCase()}</div>
                        <div style="font-size: 14px; font-weight: 700; color: #0f172a; margin-top: 2px;">${adv.title}</div>
                        <div style="font-size: 12px; color: #475569; margin-top: 2px;">${adv.route_name} • ${adv.start_landmark || 'KM ' + adv.start_km}</div>
                        <div style="font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.35;">${adv.message || ''}</div>
                        <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 11px; font-weight: 600; color: #0284c7;">Live Advisory</span>
                            <a href="#adv-${adv.id}" style="font-size: 11px; color: #0284c7; text-decoration: none; font-weight: 600;">View details &gt;</a>
                        </div>
                    </div>
                `;

                marker.bindPopup(popupContent);
                markersGroup.addLayer(marker);
            });
        }

        function selectAdvisoryCard(id, lat, lng) {
            document.querySelectorAll('.advisory-card').forEach(el => el.classList.remove('selected'));
            const targetCard = document.querySelector(`.advisory-card[data-adv-id="${id}"]`);
            if (targetCard) {
                targetCard.classList.add('selected');
                targetCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }

            if (lat && lng && mapInstance) {
                mapInstance.flyTo([lat, lng], 11, {
                    duration: 1.2
                });
            }
        }

        function setupInteractiveControls() {
            // Swap button
            const swapBtn = document.getElementById('btn-swap-cities');
            if (swapBtn) {
                swapBtn.addEventListener('click', function() {
                    const fromSelect = document.getElementById('select-from');
                    const toSelect = document.getElementById('select-to');
                    const temp = fromSelect.value;
                    fromSelect.value = toSelect.value;
                    toSelect.value = temp;

                    const roadVal = document.getElementById('select-road')?.value || currentRoad;
                    const coords = resolveRouteCorridor(roadVal, fromSelect.value, toSelect.value);
                    drawRouteCorridor(coords, fromSelect.value, toSelect.value);
                });
            }

            // Road dropdown change listener
            const roadSelect = document.getElementById('select-road');
            if (roadSelect) {
                roadSelect.addEventListener('change', function() {
                    const selectedRoad = this.value;
                    const fromSelect = document.getElementById('select-from');
                    const toSelect = document.getElementById('select-to');

                    // Smart auto-suggestion of terminal cities based on selected road
                    const roadKey = selectedRoad.toUpperCase().replace(/\s+/g, '');
                    if (roadKey.includes('M9') || roadKey === 'M-9') {
                        fromSelect.value = 'Karachi';
                        toSelect.value = 'Sukkur';
                    } else if (roadKey.includes('M5') || roadKey === 'M-5') {
                        fromSelect.value = 'Multan';
                        toSelect.value = 'Sukkur';
                    } else if (roadKey.includes('M4') || roadKey === 'M-4') {
                        fromSelect.value = 'Pindi Bhattian';
                        toSelect.value = 'Multan';
                    } else if (roadKey.includes('M3') || roadKey === 'M-3') {
                        fromSelect.value = 'Lahore';
                        toSelect.value = 'Abdul Hakim';
                    } else if (roadKey.includes('M2') || roadKey === 'M-2') {
                        fromSelect.value = 'Islamabad';
                        toSelect.value = 'Lahore';
                    } else if (roadKey.includes('M1') || roadKey === 'M-1') {
                        fromSelect.value = 'Peshawar';
                        toSelect.value = 'Islamabad';
                    } else if (roadKey.includes('M11') || roadKey === 'M-11') {
                        fromSelect.value = 'Lahore';
                        toSelect.value = 'Sialkot';
                    } else if (roadKey.includes('N5') || roadKey === 'N-5') {
                        fromSelect.value = 'Lahore';
                        toSelect.value = 'Rawalpindi';
                    }

                    // Live-update map corridor immediately
                    const coords = resolveRouteCorridor(selectedRoad, fromSelect.value, toSelect.value);
                    drawRouteCorridor(coords, fromSelect.value, toSelect.value);
                });
            }

            // City dropdown changes
            ['select-from', 'select-to'].forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.addEventListener('change', function() {
                        const roadVal = document.getElementById('select-road')?.value || currentRoad;
                        const fVal = document.getElementById('select-from')?.value || currentFrom;
                        const tVal = document.getElementById('select-to')?.value || currentTo;
                        const coords = resolveRouteCorridor(roadVal, fVal, tVal);
                        drawRouteCorridor(coords, fVal, tVal);
                    });
                }
            });

            // Map Fullscreen
            const fsBtn = document.getElementById('btn-map-fullscreen');
            if (fsBtn) {
                fsBtn.addEventListener('click', function() {
                    const mapEl = document.querySelector('.map-card-panel');
                    if (!document.fullscreenElement) {
                        mapEl.requestFullscreen().catch(err => console.log(err));
                    } else {
                        document.exitFullscreen();
                    }
                });
            }
        }
    </script>
</body>
</html>

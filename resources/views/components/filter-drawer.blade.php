@props([
    'open' => 'filterOpen',
    'title' => 'Filters',
    'subtitle' => 'Refine results',
    'count' => '0',
    'reset' => 'resetFilters()',
])
{{--
    Reusable sticky "FILTERS" tab + slide-over drawer (same pattern as the HMS patient registry).
    Usage (inside an Alpine x-data scope that owns `filterOpen`, `activeFilterCount()` and `resetFilters()`):
        <x-filter-drawer open="filterOpen" title="Advisory Filters" subtitle="Refine corridor alerts" count="activeFilterCount()" reset="resetFilters()">
            ... filter groups (use .fd-card, .fd-label, .fd-chips, .fd-chip, .fd-input) ...
        </x-filter-drawer>
    All styles are self-contained (no dependency on the compiled Tailwind build).
--}}
@once
<style>
    .fd-tab{position:fixed;top:50%;right:0;transform:translateY(-50%);z-index:8000;display:flex;flex-direction:column;align-items:center;gap:10px;padding:20px 11px;border:0;cursor:pointer;color:#fff;border-radius:18px 0 0 18px;background:linear-gradient(160deg,#0f172a,#1e293b);box-shadow:-6px 10px 30px rgba(15,23,42,.35),inset 0 1px 0 rgba(255,255,255,.12),inset 0 -3px 0 rgba(0,0,0,.35);border-left:1px solid #334155;transition:padding .2s ease,box-shadow .2s ease}
    .fd-tab:hover{padding-right:16px;box-shadow:-10px 14px 36px rgba(37,99,235,.35),inset 0 1px 0 rgba(255,255,255,.18),inset 0 -3px 0 rgba(0,0,0,.35)}
    .fd-tab i{color:#818cf8;font-size:14px;transition:transform .3s ease}
    .fd-tab:hover i{transform:rotate(90deg)}
    .fd-tab span.fd-tab-text{writing-mode:vertical-rl;transform:rotate(180deg);font-size:9px;font-weight:800;letter-spacing:.28em;text-transform:uppercase;color:#cbd5e1}
    .fd-badge{min-width:20px;height:20px;border-radius:999px;background:linear-gradient(135deg,#f43f5e,#e11d48);color:#fff;font-size:10px;font-weight:800;display:inline-flex;align-items:center;justify-content:center;padding:0 5px;box-shadow:0 4px 10px rgba(225,29,72,.5)}
    .fd-backdrop{position:fixed;inset:0;z-index:9000;background:transparent;pointer-events:none}
    .fd-panel{position:fixed;top:0;bottom:0;right:0;z-index:9001;width:min(450px,100vw);background:#fff;display:flex;flex-direction:column;box-shadow:-20px 0 60px rgba(15,23,42,.3)}
    .fd-head{flex:none;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;color:#fff;background:linear-gradient(90deg,#2563eb,#4f46e5 55%,#7c3aed)}
    .fd-head-l{display:flex;align-items:center;gap:12px;min-width:0}
    .fd-head-icon{width:40px;height:40px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);box-shadow:inset 0 1px 0 rgba(255,255,255,.2)}
    .fd-head h2{margin:0;font-size:15px;font-weight:800;letter-spacing:-.01em;line-height:1.2}
    .fd-head p{margin:2px 0 0;font-size:10px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#c7d2fe}
    .fd-close{appearance:none;border:1px solid rgba(255,255,255,.2);cursor:pointer;width:36px;height:36px;border-radius:12px;background:rgba(255,255,255,.12);color:#fff;font-size:14px}
    .fd-close:hover{background:rgba(255,255,255,.22)}
    .fd-body{flex:1;overflow-y:auto;padding:18px 20px;display:grid;gap:14px;align-content:start;background:#fff}
    .fd-card{background:linear-gradient(180deg,#fafbfd,#f4f6fa);border:1px solid #e5e9f0;border-radius:20px;padding:14px;box-shadow:0 1px 0 #fff inset,0 8px 20px -14px rgba(15,23,42,.25)}
    .fd-label{display:flex;align-items:center;gap:7px;margin:0 0 9px;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8}
    .fd-label i{color:#6366f1}
    .fd-chips{display:flex;flex-wrap:wrap;gap:7px}
    .fd-chip{appearance:none;cursor:pointer;display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 13px;border-radius:12px;border:1px solid #e2e8f0;border-bottom-width:3px;background:#fff;font-size:12px;font-weight:700;color:#334155;font-family:inherit;white-space:nowrap;transition:transform .12s ease,box-shadow .12s ease,background .12s ease}
    .fd-chip:hover{transform:translateY(-1px);background:#f8fafc}
    .fd-chip:active{transform:translateY(1px);border-bottom-width:1px}
    .fd-chip.on{color:#fff;border-color:rgba(0,0,0,.12);border-bottom-color:rgba(0,0,0,.28);background:linear-gradient(135deg,#2563eb,#4f46e5);box-shadow:0 10px 18px -10px rgba(37,99,235,.7)}
    .fd-chip.zone.on{background:linear-gradient(135deg,#0f766e,#14b8a6);box-shadow:0 10px 18px -10px rgba(13,148,136,.7)}
    .fd-chip.sector.on{background:linear-gradient(135deg,#7c3aed,#a855f7);box-shadow:0 10px 18px -10px rgba(124,58,237,.7)}
    .fd-chip.beat.on{background:linear-gradient(135deg,#ea580c,#f59e0b);box-shadow:0 10px 18px -10px rgba(234,88,12,.7)}
    .fd-chip.locked{cursor:not-allowed;transform:none}
    .fd-chip.locked i{color:rgba(255,255,255,.92)}
    .fd-chip.mono{font-family:ui-monospace,SFMono-Regular,Menlo,monospace}
    .fd-input{width:100%;height:42px;border-radius:14px;border:1px solid #e2e8f0;border-bottom-width:2px;background:#fff;padding:0 12px;font-size:12.5px;font-weight:700;color:#1e293b;outline:none;font-family:inherit;transition:border-color .15s ease,box-shadow .15s ease}
    .fd-input:focus{border-color:#818cf8;box-shadow:0 0 0 3px rgba(99,102,241,.16)}
    .fd-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .fd-hint{font-size:11px;font-weight:600;color:#94a3b8}
    .fd-foot{flex:none;display:flex;gap:10px;padding:16px 20px;border-top:1px solid #eef1f6;background:#f8fafc}
    .fd-btn{appearance:none;cursor:pointer;flex:1;height:44px;border-radius:14px;border:0;font-size:11.5px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;font-family:inherit;display:inline-flex;align-items:center;justify-content:center;gap:8px;transition:transform .12s ease}
    .fd-btn:active{transform:scale(.97)}
    .fd-btn.reset{color:#e11d48;background:#fff1f2;border:1px solid #fecdd3}
    .fd-btn.apply{color:#fff;background:linear-gradient(135deg,#2563eb,#4f46e5);box-shadow:0 12px 22px -12px rgba(37,99,235,.8)}
    @media (prefers-reduced-motion:reduce){.fd-tab,.fd-tab i,.fd-chip,.fd-btn{transition:none}}
    @media (max-width:640px){.fd-tab{padding:14px 8px}.fd-panel{width:100vw}}
</style>
@endonce

<template x-teleport="body">
<div class="fd-root">
<button type="button" class="fd-tab" @click="{{ $open }} = !{{ $open }}" title="Open filters" aria-label="Open filters" :aria-expanded="{{ $open }}">
    <i class="fa-solid fa-sliders"></i>
    <span class="fd-badge" x-show="({{ $count }}) > 0" x-text="{{ $count }}" x-cloak></span>
    <span class="fd-tab-text">Filters</span>
</button>

<div x-show="{{ $open }}" x-cloak style="display:none;"
     x-transition:enter="transition-opacity ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="fd-backdrop"></div>

<aside x-show="{{ $open }}" x-cloak style="display:none;" role="dialog" aria-label="{{ $title }}"
       x-effect="window.dispatchEvent(new CustomEvent({{ $open }} ? 'fd:open' : 'fd:close'))"
       @keydown.escape.window="{{ $open }} = false"
       x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
       x-transition:leave="transition ease-in duration-200 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
       class="fd-panel">
    <div class="fd-head">
        <div class="fd-head-l">
            <div class="fd-head-icon"><i class="fa-solid fa-sliders"></i></div>
            <div>
                <h2>{{ $title }}</h2>
                <p>{{ $subtitle }}</p>
            </div>
        </div>
        <button type="button" class="fd-close" @click="{{ $open }} = false" title="Close filters" aria-label="Close filters"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="fd-body">
        {{ $slot }}
    </div>
    <div class="fd-foot">
        <button type="button" class="fd-btn reset" @click="{{ $reset }}"><i class="fa-solid fa-rotate-left"></i> Reset filters</button>
        <button type="button" class="fd-btn apply" @click="{{ $open }} = false"><i class="fa-solid fa-check"></i> Apply &amp; close</button>
    </div>
</aside>
</div>
</template>

@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'p-4 rounded-2xl bg-emerald-50 border border-emerald-200/80 flex items-start gap-3 shadow-sm']) }}>
        <div class="w-8 h-8 rounded-xl bg-emerald-500 text-white flex items-center justify-center shrink-0 mt-0.5 shadow-md shadow-emerald-500/20">
            <i class="fa-solid fa-circle-check text-sm"></i>
        </div>
        <div class="flex-1">
            <p class="text-xs text-emerald-800 font-semibold leading-relaxed mt-1">
                {{ $status }}
            </p>
        </div>
    </div>
@endif

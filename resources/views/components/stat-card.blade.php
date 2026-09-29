@props(['label', 'value', 'unit' => null, 'hint' => null])

{{-- Kartu angka pendukung. Dipisahkan dari dasbor supaya halaman lain bisa
     memakai gaya yang sama tanpa menyalin ulang kelasnya. --}}
<div {{ $attributes->merge(['class' => 'rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5']) }}>
    <div class="flex items-center gap-2.5">
        @isset($icon)
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">{{ $icon }}</span>
        @endisset
        <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">{{ $label }}</p>
    </div>
    <p class="mt-3 text-2xl font-black tracking-tight tabular-nums text-slate-900">
        {{ $value }}@if($unit)<span class="ml-1 text-base font-bold text-slate-400">{{ $unit }}</span>@endif
    </p>
    @if($hint)
        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $hint }}</p>
    @endif
</div>

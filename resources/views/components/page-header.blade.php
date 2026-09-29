@props(['eyebrow' => null, 'title'])

{{-- Kepala halaman admin. Dipusatkan di satu komponen agar gaya judul
     tidak lagi ditulis ulang di tiap halaman dan tetap seragam. --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        @if($eyebrow)
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-1 text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">{{ $title }}</h1>
        @if(trim($slot))
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{{ $slot }}</p>
        @endif
    </div>
    @isset($action)
        <div class="shrink-0">{{ $action }}</div>
    @endisset
</div>

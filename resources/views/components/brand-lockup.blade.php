@props(['tagline' => true])

{{-- Logo CUANTAH digambar sebagai markup, bukan berkas gambar: ia tetap tajam
     di layar kerapatan tinggi, ikut mewarisi warna tema, dan tidak membawa
     latar putih bawaan yang memaksa header selalu berlatar putih. --}}
<span {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <svg viewBox="0 0 40 40" class="h-9 w-9 shrink-0" role="img" aria-hidden="true" focusable="false">
        <rect width="40" height="40" rx="11" class="fill-emerald-700" />
        <circle cx="20" cy="20.5" r="10.5" fill="none" stroke="#ffffff" stroke-width="1.7" />
        <path d="M20 12.4c3.5 2.7 5.4 5.3 5.4 7.9a5.4 5.4 0 0 1-10.8 0c0-2.6 1.9-5.2 5.4-7.9Z" fill="#ffffff" />
        <path d="M20 16.6v7.2" class="stroke-emerald-700" stroke-width="1.4" stroke-linecap="round" />
    </svg>

    <span class="min-w-0">
        <span class="block text-xl font-black leading-none tracking-tight text-emerald-900">CUANTAH</span>
        @if($tagline)
            <span class="mt-1 hidden text-[8px] font-bold uppercase leading-none tracking-[0.16em] text-emerald-700/75 sm:block">
                Cuan dari minyak jelantah
            </span>
        @endif
    </span>
</span>

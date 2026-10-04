@props(['variant' => 'aplikasi', 'tagline' => true])

{{-- Logo CUANTAH digambar sebagai markup, bukan berkas gambar: ia tetap tajam
     di layar kerapatan tinggi, ikut mewarisi warna tema, dan tidak membawa
     latar putih bawaan yang memaksa header selalu berlatar putih.

     Lambangnya pernah ditulis ulang di lima tempat, dan yang terjadi persis
     seperti yang bisa diduga: satu di antaranya menyimpang menjadi gambar yang
     sama sekali berbeda, dan yang menyimpang justru yang dilihat orang paling
     lama. Sekarang gambarnya hanya ada di berkas ini.

     Yang berbeda antar tempat hanyalah ukuran dan warna latarnya, jadi itulah
     yang dinamai sebagai varian. Pemakainya menyebut perannya, bukan merakit
     ukurannya sendiri. --}}
@php
    $bentuk = [
        // Bilah atas di dalam aplikasi: menampung menu dan notifikasi pada
        // baris yang sama, jadi paling ringkas.
        'aplikasi' => [
            'jarak' => 'gap-2.5',
            'keping' => 'h-10 w-10 rounded-2xl bg-emerald-700 text-white shadow-lg shadow-emerald-900/20',
            'ikon' => 'h-6 w-6',
            'kata' => 'text-xl text-emerald-800',
            'tagline' => 'hidden text-[10px] tracking-[0.24em] text-emerald-700/70 sm:block',
            'teks' => 'Cuan dari minyak jelantah',
        ],
        // Panel admin memakai lambang yang sama dengan sisi aplikasi; yang
        // membedakannya hanya tagline, sebab yang perlu disebut adalah sisi
        // mana yang sedang terbuka, bukan merek yang berbeda. Taglinenya
        // tidak disembunyikan di layar sempit karena di sanalah pil "Admin
        // CUANTAH" ikut hilang.
        'admin' => [
            'jarak' => 'gap-2.5',
            'keping' => 'h-10 w-10 rounded-2xl bg-emerald-700 text-white shadow-lg shadow-emerald-900/20',
            'ikon' => 'h-6 w-6',
            'kata' => 'text-xl text-emerald-800',
            'tagline' => 'block text-[10px] tracking-[0.16em] text-emerald-700/70',
            'teks' => 'Admin panel',
        ],
        'header' => [
            'jarak' => 'gap-3',
            'keping' => 'h-11 w-11 rounded-2xl bg-emerald-700 text-white shadow-lg shadow-emerald-900/20 sm:h-12 sm:w-12',
            'ikon' => 'h-7 w-7 sm:h-8 sm:w-8',
            'kata' => 'text-xl text-emerald-800 sm:text-2xl',
            'tagline' => 'hidden text-[10px] tracking-[0.24em] text-emerald-700/70 sm:block',
            'teks' => 'Cuan dari minyak jelantah',
        ],
        // Kaki halaman berlatar gelap, jadi warnanya dibalik: keping putih
        // dengan lambang hijau.
        'footer' => [
            'jarak' => 'gap-3',
            'keping' => 'h-10 w-10 rounded-xl bg-white text-emerald-700 shadow-lg shadow-black/15 sm:h-11 sm:w-11 sm:rounded-2xl',
            'ikon' => 'h-6 w-6 sm:h-7 sm:w-7',
            'kata' => 'text-xl text-white sm:text-2xl',
            'tagline' => 'block text-[10px] tracking-[0.2em] text-emerald-200',
            'teks' => 'Cuan dari minyak jelantah',
        ],
        // Halaman masuk dan daftar: lambangnya yang menyambut, jadi paling besar.
        'auth' => [
            'jarak' => 'gap-4',
            'keping' => 'h-[72px] w-[72px] rounded-3xl bg-emerald-700 text-white shadow-lg shadow-emerald-900/20',
            'ikon' => 'h-11 w-11',
            'kata' => 'text-4xl text-emerald-800',
            'tagline' => 'block text-sm tracking-[0.28em] text-emerald-700/70',
            'teks' => 'Cuan dari minyak jelantah',
        ],
    ][$variant] ?? null;

    abort_if($bentuk === null, 500, "Varian lambang \"{$variant}\" tidak dikenal.");
@endphp

<span {{ $attributes->merge(['class' => 'flex items-center '.$bentuk['jarak']]) }}>
    <span class="flex shrink-0 items-center justify-center {{ $bentuk['keping'] }}">
        {{-- viewBox ditulis lebih dulu: BrandLockupTest mencocokkan logo
             penyetor dan karyawan lewat potongan yang diawali atribut ini. --}}
        <svg viewBox="0 0 40 40" class="{{ $bentuk['ikon'] }}" fill="none" role="img" aria-hidden="true" focusable="false">
            <path d="M20 4C13.5 10.8 8 17.6 8 25.2C8 32.1 13.4 36 20 36C26.6 36 32 32.1 32 25.2C32 17.6 26.5 10.8 20 4Z" stroke="currentColor" stroke-width="4" stroke-linejoin="round" />
            <path d="M20 13V31" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
            <path d="M20 24C16.6 23.6 14.3 21.8 13 18.5" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
        </svg>
    </span>

    <span class="min-w-0 text-left">
        <span class="block font-black leading-none tracking-tight {{ $bentuk['kata'] }}">CUANTAH</span>
        @if($tagline)
            <span class="mt-1 font-black uppercase leading-none {{ $bentuk['tagline'] }}">{{ $bentuk['teks'] }}</span>
        @endif
    </span>
</span>

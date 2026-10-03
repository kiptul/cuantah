@props(['tagline' => true])

{{-- Logo CUANTAH digambar sebagai markup, bukan berkas gambar: ia tetap tajam
     di layar kerapatan tinggi, ikut mewarisi warna tema, dan tidak membawa
     latar putih bawaan yang memaksa header selalu berlatar putih.

     Lambangnya sengaja sama persis dengan yang dipakai halaman depan, halaman
     masuk, dan halaman daftar. Sebelumnya bagian dalam aplikasi memakai gambar
     yang berbeda sendiri, sehingga orang yang baru masuk disambut lambang yang
     bukan lambang yang membawanya ke sana.

     Ukurannya lebih ringkas daripada di halaman depan, sebab bilah atas di
     dalam aplikasi menampung menu dan notifikasi di baris yang sama. --}}
<span {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-emerald-700 text-white shadow-lg shadow-emerald-900/20">
        {{-- viewBox ditulis lebih dulu: BrandLockupTest mencocokkan logo
             penyetor dan karyawan lewat potongan yang diawali atribut ini. --}}
        <svg viewBox="0 0 40 40" class="h-6 w-6" fill="none" role="img" aria-hidden="true" focusable="false">
            <path d="M20 4C13.5 10.8 8 17.6 8 25.2C8 32.1 13.4 36 20 36C26.6 36 32 32.1 32 25.2C32 17.6 26.5 10.8 20 4Z" stroke="currentColor" stroke-width="4" stroke-linejoin="round" />
            <path d="M20 13V31" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
            <path d="M20 24C16.6 23.6 14.3 21.8 13 18.5" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
        </svg>
    </span>

    <span class="min-w-0">
        <span class="block text-xl font-black leading-none tracking-tight text-emerald-800">CUANTAH</span>
        @if($tagline)
            <span class="mt-1 hidden text-[10px] font-black uppercase leading-none tracking-[0.24em] text-emerald-700/70 sm:block">
                Cuan dari minyak jelantah
            </span>
        @endif
    </span>
</span>

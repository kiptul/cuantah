<x-layouts.public title="Edukasi Jelantah">
    <section class="relative overflow-hidden bg-white">
        <div class="absolute inset-x-0 bottom-0 h-32 bg-[#f7faf5]"></div>

        <div class="relative mx-auto grid max-w-[1500px] items-center gap-10 px-4 py-12 lg:grid-cols-[.9fr_1.1fr] lg:gap-16 lg:px-8 lg:py-20">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-700 text-white">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M10 2.5C7.2 5.6 4.8 8.7 4.8 12.1C4.8 15.1 7.1 16.9 10 16.9C12.9 16.9 15.2 15.1 15.2 12.1C15.2 8.7 12.8 5.6 10 2.5Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                        </svg>
                    </span>
                    Edukasi Jelantah
                </div>

                <h1 class="mt-7 max-w-4xl text-4xl font-black leading-[1.06] tracking-tight text-emerald-950 sm:text-6xl">
                    Simpan jelantah dengan benar sebelum disetor.
                </h1>

                <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-600">
                    Kualitas jelantah lebih mudah dijaga kalau minyak didinginkan, disaring, dan disimpan dalam wadah tertutup. Proses verifikasi juga jadi lebih cepat dan jelas.
                </p>

                {{-- Penghubung ke alasan yang paling dirasakan penyetor: volume
                     hasil takaran menentukan bayaran, dan kualitas menentukan volume. --}}
                <div class="mt-8 flex max-w-xl items-start gap-4 rounded-2xl border border-emerald-100 bg-emerald-50/70 p-5">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-white">
                        {{-- "Rp", bukan lambang dolar. Seluruh nilai di aplikasi ini
                             dalam rupiah, dan ikon mata uang asing di sebelah kalimat
                             tentang bayaran membuat pembaca ragu pada mata uang yang
                             dimaksud. Ditulis sebagai teks, bukan jalur SVG, supaya
                             ikut berubah bila ukuran atau warnanya disetel. --}}
                        <span class="text-sm font-black leading-none tracking-tight">Rp</span>
                    </span>
                    <p class="text-[15px] leading-7 text-emerald-950">
                        <strong class="font-black">Ini berpengaruh ke bayaranmu.</strong>
                        Yang dibayar adalah volume hasil takaran karyawan, bukan perkiraan awal. Air dan sisa makanan yang ikut tercampur membuat jelantah ditolak atau ditakar lebih rendah.
                    </p>
                </div>
            </div>

            <div class="rounded-[2rem] border border-emerald-100 bg-white p-8 shadow-xl shadow-emerald-950/10 lg:p-10">
                <p class="text-sm font-black uppercase tracking-[0.18em] text-emerald-700">Checklist sebelum setor</p>

                <div class="mt-8 grid gap-7">
                    <div class="flex items-start gap-5">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-700 text-lg font-black text-white">1</span>
                        <div>
                            <h2 class="text-xl font-black text-emerald-950">Dinginkan</h2>
                            <p class="mt-2 leading-7 text-slate-600">Biarkan minyak bekas turun suhu agar aman dipindahkan.</p>
                        </div>
                    </div>

                    <div class="h-px bg-emerald-100"></div>

                    <div class="flex items-start gap-5">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-lg font-black text-emerald-700 ring-1 ring-emerald-100">2</span>
                        <div>
                            <h2 class="text-xl font-black text-emerald-950">Saring</h2>
                            <p class="mt-2 leading-7 text-slate-600">Pisahkan sisa makanan supaya kualitas jelantah tetap baik.</p>
                        </div>
                    </div>

                    <div class="h-px bg-emerald-100"></div>

                    <div class="flex items-start gap-5">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-lime-100 text-lg font-black text-emerald-800 ring-1 ring-lime-200">3</span>
                        <div>
                            <h2 class="text-xl font-black text-emerald-950">Tutup rapat</h2>
                            <p class="mt-2 leading-7 text-slate-600">Simpan di botol atau jeriken bersih sebelum disetor.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f7faf5]">
        <div class="mx-auto max-w-[1500px] px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-sm font-black uppercase tracking-[0.18em] text-emerald-700">Perlu dihindari</p>
                <h2 class="mt-4 text-4xl font-black tracking-tight text-emerald-950">Hal kecil yang bisa menurunkan kualitas jelantah.</h2>
                <p class="mt-4 leading-7 text-slate-600">
                    Setelah jelantah didinginkan, disaring, dan disimpan tertutup, pastikan tiga hal ini tidak ikut tercampur.
                </p>
            </div>

            <div class="mt-12 grid gap-6 md:grid-cols-3">
                <article class="rounded-[2rem] border border-amber-100 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-950/10">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3.5C9.3 7.2 7 9.9 7 12.4a5 5 0 0 0 10 0c0-2.5-2.3-5.2-5-8.9Z" stroke="currentColor" stroke-width="2.1" stroke-linejoin="round" />
                            <path d="M4.5 19.5 19.5 4.5" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-xl font-black text-emerald-950">Jangan campur air</h3>
                    <p class="mt-3 leading-7 text-slate-600">Air menurunkan kualitas dan menyulitkan proses timbang. Ia juga menambah volume yang bukan minyak, sehingga hasil takaran ikut dikoreksi.</p>
                </article>

                <article class="rounded-[2rem] border border-amber-100 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-950/10">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M3.5 8.5h17" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" />
                            <path d="M7 8.5v3.5a5 5 0 0 0 10 0V8.5" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M12 17v3.5" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-xl font-black text-emerald-950">Jangan buang ke wastafel</h3>
                    <p class="mt-3 leading-7 text-slate-600">Minyak menempel di dinding pipa, mengeras, lalu menyumbat saluran. Yang terbuang bukan cuma minyaknya, tapi juga nilainya.</p>
                </article>

                <article class="rounded-[2rem] border border-amber-100 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-950/10">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M9.5 3v6.2L4.8 18a2 2 0 0 0 1.7 3h11a2 2 0 0 0 1.7-3l-4.7-8.8V3" stroke="currentColor" stroke-width="2.1" stroke-linejoin="round" />
                            <path d="M8 3h8" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" />
                        </svg>
                    </div>
                    <h3 class="mt-6 text-xl font-black text-emerald-950">Jangan campur bahan lain</h3>
                    <p class="mt-3 leading-7 text-slate-600">Sabun, cairan pembersih, atau limbah dapur membuat jelantah tidak bisa diolah dan berisiko ditolak saat verifikasi.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- Halaman ini sebelumnya berhenti di daftar larangan. Dua bagian penutup
         berikut menjawab "lalu jelantahnya jadi apa" dan mengarahkan pembaca
         yang sudah siap untuk menyetor. --}}
    <section class="bg-white">
        <div class="mx-auto max-w-[1500px] px-4 py-16 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                <p class="text-sm font-black uppercase tracking-[0.18em] text-emerald-700">Setelah disetor</p>
                <h2 class="mt-4 text-4xl font-black tracking-tight text-emerald-950">Jelantahmu tidak berhenti di mitra.</h2>
                <p class="mt-4 leading-7 text-slate-600">
                    Minyak yang terkumpul diteruskan mitra ke tujuan pengolahan untuk diproses kembali menjadi bahan bakar nabati. Itulah sebabnya kualitas jelantah ikut menentukan apakah ia bisa dipakai.
                </p>
            </div>

            <div class="mx-auto mt-12 grid max-w-4xl gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-emerald-100 bg-[#f7faf5] p-6 text-center">
                    <p class="text-sm font-black uppercase tracking-[0.14em] text-emerald-700">Dapurmu</p>
                    <p class="mt-2 leading-7 text-slate-600">Minyak bekas dikumpulkan dan disimpan tertutup.</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-[#f7faf5] p-6 text-center">
                    <p class="text-sm font-black uppercase tracking-[0.14em] text-emerald-700">Mitra</p>
                    <p class="mt-2 leading-7 text-slate-600">Volume ditakar, dicatat, lalu dikumpulkan bersama setoran lain.</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-[#f7faf5] p-6 text-center">
                    <p class="text-sm font-black uppercase tracking-[0.14em] text-emerald-700">Pengolahan</p>
                    <p class="mt-2 leading-7 text-slate-600">Diteruskan untuk diproses kembali jadi bahan bakar nabati.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-[#f7faf5]">
        <div class="mx-auto max-w-[1500px] px-4 pb-20 lg:px-8">
            <div class="mx-auto max-w-3xl rounded-[2rem] border border-emerald-100 bg-white p-8 text-center shadow-xl shadow-emerald-950/10 sm:p-12">
                <h2 class="text-3xl font-black tracking-tight text-emerald-950 sm:text-4xl">Jelantahmu sudah siap?</h2>
                <p class="mt-4 leading-7 text-slate-600">
                    Harga aktif ditampilkan sebelum kamu mengirim setoran, lengkap dengan estimasi rupiah yang akan diterima.
                </p>
                <div class="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
                    <a href="{{ route('deposits.create') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-emerald-700 px-7 py-4 font-black text-white shadow-lg shadow-emerald-950/15 transition hover:bg-emerald-800">
                        Setor Sekarang
                        <span aria-hidden="true">&rarr;</span>
                    </a>
                    <a href="{{ route('public.page', 'harga') }}" class="inline-flex items-center justify-center rounded-2xl border border-emerald-200 bg-white px-7 py-4 font-black text-emerald-800 transition hover:bg-emerald-50">
                        Lihat Harga
                    </a>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>

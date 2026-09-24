<x-layouts.public title="FAQ CUANTAH">
    <section class="bg-[#f7faf5]">
        <div class="mx-auto max-w-[1500px] px-4 py-12 lg:px-8 lg:py-16">
            <div class="relative overflow-hidden rounded-[2rem] border border-emerald-100 bg-white p-5 shadow-sm sm:p-7 lg:p-9">
                <div class="absolute -right-10 -top-16 h-48 w-48 rounded-full bg-emerald-100"></div>
                <div class="absolute bottom-0 right-24 h-24 w-24 rounded-full bg-lime-100"></div>

                <div class="relative max-w-3xl">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 shadow-sm">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-700 text-white">?</span>
                        Pertanyaan Umum
                    </div>

                    <h1 class="mt-6 text-4xl font-black leading-tight tracking-tight text-emerald-950 sm:text-5xl">
                        Ada yang ingin dipastikan dulu?
                    </h1>

                    <p class="mt-4 max-w-2xl text-lg leading-8 text-slate-600">
                        Cek jawaban singkat seputar pembayaran, verifikasi volume, dan harga transaksi sebelum mulai setor.
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 ring-1 ring-emerald-100">Pembayaran</span>
                        <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 ring-1 ring-emerald-100">Verifikasi</span>
                        <span class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 ring-1 ring-emerald-100">Harga</span>
                    </div>
                </div>
            </div>

            <div class="mt-10 grid gap-8 lg:grid-cols-[.62fr_1.38fr] lg:items-start">
                <div class="rounded-[1.75rem] border border-emerald-100 bg-white p-5 shadow-sm">
                    <p class="text-sm font-black uppercase tracking-[0.16em] text-emerald-700">Yang perlu diketahui</p>

                    <div class="mt-5 grid gap-3">
                        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white font-black text-emerald-700 shadow-sm">Rp</span>
                            <div class="min-w-0">
                                <p class="font-black text-emerald-950">Pembayaran langsung</p>
                                <p class="text-sm leading-6 text-slate-600">Cash atau transfer oleh mitra.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white font-black text-emerald-700 shadow-sm">L</span>
                            <div class="min-w-0">
                                <p class="font-black text-emerald-950">Volume diverifikasi</p>
                                <p class="text-sm leading-6 text-slate-600">Nilai akhir mengikuti hasil timbang aktual.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 px-4 py-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white font-black text-emerald-700 shadow-sm">✓</span>
                            <div class="min-w-0">
                                <p class="font-black text-emerald-950">Harga tersimpan</p>
                                <p class="text-sm leading-6 text-slate-600">Transaksi memakai snapshot harga saat dibuat.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="join join-vertical w-full gap-3">
                    @foreach([
                        ['Apakah ada wallet CUAN?', 'Tidak untuk MVP. Pembayaran dilakukan langsung oleh mitra secara cash atau transfer.'],
                        ['Kapan nilai akhir dihitung?', 'Setelah minyak ditimbang dan volume aktual diverifikasi oleh admin.'],
                        ['Apakah harga lama berubah jika harga baru dibuat?', 'Tidak. Transaksi menyimpan snapshot harga per liter.'],
                    ] as $index => [$q, $a])
                        <div class="collapse join-item rounded-[1.5rem] border border-emerald-100 bg-white shadow-sm">
                            <input type="radio" name="public-faq" @checked($index === 0) />
                            <div class="collapse-title flex items-center gap-4 py-5 pr-12 text-lg font-black text-emerald-950">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-700 text-sm font-black text-white">{{ $index + 1 }}</span>
                                <span>{{ $q }}</span>
                            </div>
                            <div class="collapse-content">
                                <p class="pl-[3.25rem] leading-7 text-slate-600">{{ $a }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>

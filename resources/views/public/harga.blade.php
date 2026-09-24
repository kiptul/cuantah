<x-layouts.public title="Harga Jelantah">
    <section class="relative overflow-hidden bg-white">
        <div class="absolute inset-y-0 right-0 hidden w-[42%] bg-gradient-to-br from-emerald-50 via-emerald-100 to-lime-100 lg:block"></div>
        <div class="absolute -right-24 top-24 hidden h-96 w-96 rounded-full bg-emerald-200/60 blur-3xl lg:block"></div>

        <div class="relative mx-auto grid max-w-[1500px] items-center gap-10 px-4 py-12 lg:grid-cols-[.9fr_1.1fr] lg:gap-14 lg:px-8 lg:py-20">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-700 text-white">Rp</span>
                    Harga Jelantah
                </div>

                <h1 class="mt-7 max-w-4xl text-4xl font-black leading-[1.06] tracking-tight text-emerald-950 sm:text-6xl">
                    Harga aktif yang transparan sebelum kamu setor.
                </h1>

                <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-600">
                    Harga minyak jelantah ditampilkan sejak awal agar user bisa memahami nilai setoran. Harga pada transaksi lama tetap aman karena disimpan sebagai snapshot.
                </p>
            </div>

            <div class="rounded-3xl border border-emerald-100 bg-white p-8 shadow-xl shadow-emerald-950/10">
                <p class="text-sm font-black uppercase tracking-[0.18em] text-emerald-700">Harga aktif saat ini</p>
                <p class="mt-5 text-6xl font-black tracking-tight text-emerald-700">
                    Rp{{ number_format($price?->price_per_liter ?? 0, 0, ',', '.') }}
                    <span class="text-2xl text-emerald-950">/L</span>
                </p>

                <div class="mt-7 grid gap-3 text-sm font-semibold text-slate-600">
                    <div class="flex items-center justify-between gap-4 rounded-2xl bg-emerald-50 px-4 py-3">
                        <span>Status harga</span>
                        <span class="font-black text-emerald-800">{{ $price ? 'Aktif' : 'Belum tersedia' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-4 rounded-2xl bg-emerald-50 px-4 py-3">
                        <span>Berlaku sejak</span>
                        <span class="font-black text-emerald-800">{{ $price?->effective_date?->format('d M Y') ?? '-' }}</span>
                    </div>
                </div>

                <p class="mt-6 leading-7 text-slate-600">
                    Nominal akhir tetap mengikuti volume hasil verifikasi mitra saat transaksi diproses.
                </p>
            </div>
        </div>
    </section>

    <section class="bg-[#f7faf5]">
        <div class="mx-auto max-w-[1500px] px-4 py-16 lg:px-8">
            <div class="max-w-3xl">
                <p class="text-sm font-black uppercase tracking-[0.18em] text-emerald-700">Transparansi Harga</p>
                <h2 class="mt-4 text-4xl font-black tracking-tight text-emerald-950">Bagaimana harga digunakan?</h2>
            </div>

            <div class="mt-10 rounded-3xl border border-emerald-100 bg-white p-6 shadow-sm [--color-primary:#047857] [--color-primary-content:#ffffff]">
                <ul class="steps steps-vertical w-full lg:steps-horizontal">
                    <li class="step step-primary">
                        <span class="mt-3 block max-w-xs text-center">
                            <span class="block font-black text-emerald-950">Ditampilkan sebelum setor</span>
                            <span class="mt-1 block text-sm font-medium leading-6 text-slate-500">Harga aktif terlihat sebelum transaksi dibuat.</span>
                        </span>
                    </li>
                    <li class="step step-primary">
                        <span class="mt-3 block max-w-xs text-center">
                            <span class="block font-black text-emerald-950">Disimpan sebagai snapshot</span>
                            <span class="mt-1 block text-sm font-medium leading-6 text-slate-500">Transaksi lama tetap memakai harga saat dibuat.</span>
                        </span>
                    </li>
                    <li class="step step-primary">
                        <span class="mt-3 block max-w-xs text-center">
                            <span class="block font-black text-emerald-950">Dibayar setelah verifikasi</span>
                            <span class="mt-1 block text-sm font-medium leading-6 text-slate-500">Pembayaran mengikuti volume aktual dari mitra.</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</x-layouts.public>

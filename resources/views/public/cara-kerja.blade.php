<x-layouts.public title="Cara Kerja CUANTAH">
    <section class="bg-white">
        <div class="mx-auto max-w-[1500px] px-4 py-12 text-center lg:px-8 lg:py-20">
            <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 shadow-sm">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-700 text-white">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                        <path d="M10 2.5C7.2 5.6 4.8 8.7 4.8 12.1C4.8 15.1 7.1 16.9 10 16.9C12.9 16.9 15.2 15.1 15.2 12.1C15.2 8.7 12.8 5.6 10 2.5Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                    </svg>
                </span>
                Cara Kerja CUANTAH
            </div>

            <h1 class="mx-auto mt-7 max-w-4xl text-4xl font-black leading-[1.06] tracking-tight text-emerald-950 sm:text-6xl">
                Setor jelantah cukup lewat empat langkah.
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-slate-600">
                Pilih cara setor yang paling nyaman, kirim pengajuan, lalu mitra akan memverifikasi volume sebelum pembayaran dilakukan.
            </p>
        </div>
    </section>

    <section class="bg-[#f7faf5]">
        <div class="mx-auto max-w-[1320px] px-4 py-12 lg:px-8 lg:py-16">
            <ul class="timeline timeline-snap-icon max-md:timeline-compact timeline-vertical">
                @foreach ([
                    [
                        'title' => 'Pilih metode setor',
                        'body' => 'Pilih antar mandiri ke titik pengumpulan atau ajukan pickup jika ingin dijemput mitra.',
                    ],
                    [
                        'title' => 'Tentukan lokasi',
                        'body' => 'Isi alamat atau titik lokasi agar proses pickup dan verifikasi lebih jelas.',
                    ],
                    [
                        'title' => 'Mitra memproses',
                        'body' => 'Pengajuan akan diproses, lalu mitra atau petugas mengikuti status transaksi di sistem.',
                    ],
                    [
                        'title' => 'Timbang, bayar, selesai',
                        'body' => 'Volume diverifikasi, pembayaran dilakukan langsung, dan riwayat transaksi tersimpan.',
                    ],
                ] as $index => $step)
                    <li data-timeline-item>
                        @if (! $loop->first)
                            <hr data-timeline-line class="bg-emerald-200 transition-colors duration-300" />
                        @endif

                        <div class="timeline-middle">
                            <div data-timeline-marker class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-full bg-emerald-700 text-sm font-black text-white shadow-lg shadow-emerald-900/20 transition duration-300 hover:scale-110">
                                {{ $index + 1 }}
                            </div>
                        </div>

                        <button type="button" data-timeline-card @class([
                            'mb-10 w-full rounded-3xl bg-white p-6 text-left shadow-sm shadow-emerald-950/5 ring-1 ring-emerald-100 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-emerald-950/10 hover:ring-emerald-100 focus:outline-none focus:ring-4 focus:ring-emerald-100',
                            'timeline-start md:text-end' => $index % 2 === 0,
                            'timeline-end' => $index % 2 === 1,
                        ])>
                            <p data-timeline-label class="text-sm font-black uppercase tracking-[0.16em] text-emerald-700 transition-colors duration-300">Langkah {{ $index + 1 }}</p>
                            <h2 data-timeline-title class="mt-2 text-2xl font-black text-emerald-950 transition-colors duration-300">{{ $step['title'] }}</h2>
                            <p data-timeline-body class="mt-3 leading-7 text-slate-600 transition-colors duration-300">{{ $step['body'] }}</p>
                        </button>

                        @if (! $loop->last)
                            <hr data-timeline-line class="bg-emerald-200 transition-colors duration-300" />
                        @endif
                    </li>
                @endforeach
            </ul>

            <p class="mx-auto mt-4 max-w-2xl text-center text-sm font-semibold leading-6 text-slate-500">
                Nominal akhir mengikuti volume hasil verifikasi mitra. CUANTAH mencatat status dan riwayat transaksi agar prosesnya mudah dipantau.
            </p>
        </div>
    </section>

    <section class="bg-white px-4 py-8 lg:px-8 lg:pb-14">
        <div class="mx-auto flex max-w-[1500px] flex-col gap-6 rounded-3xl bg-emerald-700 p-6 text-white shadow-xl shadow-emerald-950/10 md:flex-row md:items-center md:justify-between lg:p-8">
            <div>
                <p class="text-sm font-black uppercase tracking-[0.18em] text-emerald-100">Siap mencoba?</p>
                <h2 class="mt-2 text-3xl font-black">Setor jelantah pertamamu lewat CUANTAH.</h2>
                <p class="mt-2 max-w-2xl text-emerald-50">Mulai dari volume kecil, lalu pantau status dan riwayatnya dari dashboard.</p>
            </div>
            <a href="{{ route('register') }}" class="inline-flex items-center justify-center rounded-2xl bg-white px-6 py-4 font-black text-emerald-800 transition hover:bg-emerald-50">Setor Sekarang</a>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const items = [...document.querySelectorAll('[data-timeline-item]')];

            if (items.length === 0) {
                return;
            }

            const setActiveItem = (activeItem) => {
                items.forEach((item) => {
                    const isActive = item === activeItem;
                    const marker = item.querySelector('[data-timeline-marker]');
                    const card = item.querySelector('[data-timeline-card]');
                    marker.classList.toggle('scale-110', isActive);
                    marker.classList.toggle('ring-4', isActive);
                    marker.classList.toggle('ring-emerald-100', isActive);

                    card.classList.toggle('shadow-xl', isActive);
                    card.classList.toggle('shadow-emerald-950/10', isActive);
                });
            };

            items.forEach((item) => {
                item.querySelector('[data-timeline-card]').addEventListener('click', () => setActiveItem(item));
                item.querySelector('[data-timeline-marker]').addEventListener('click', () => setActiveItem(item));
            });

            setActiveItem(items[0]);
        });
    </script>
</x-layouts.public>

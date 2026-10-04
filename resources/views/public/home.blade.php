<x-layouts.public title="CUANTAH — Cuan dari Minyak Jelantah">
    <section class="relative overflow-hidden bg-white">
        <div class="absolute inset-y-0 right-0 hidden w-[47%] bg-gradient-to-br from-emerald-50 via-emerald-100 to-lime-100 lg:block"></div>
        <div class="absolute right-[-9rem] top-24 hidden h-[34rem] w-[34rem] rounded-full bg-emerald-200/55 blur-3xl lg:block"></div>

        <div class="relative mx-auto grid max-w-[1500px] items-center gap-8 px-4 py-8 sm:gap-10 sm:py-10 lg:min-h-[calc(100vh-88px)] lg:grid-cols-[.92fr_1.08fr] lg:gap-14 lg:px-8 lg:py-14">
            <div class="max-w-3xl">
                <div class="inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-800 shadow-sm">
                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-700 text-white">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                            <path d="M10 2.5C7.2 5.6 4.8 8.7 4.8 12.1C4.8 15.1 7.1 16.9 10 16.9C12.9 16.9 15.2 15.1 15.2 12.1C15.2 8.7 12.8 5.6 10 2.5Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                        </svg>
                    </span>
                    Cuan dari Minyak Jelantah
                </div>

                <h1 class="mt-7 max-w-4xl text-4xl font-black leading-[1.04] tracking-tight text-emerald-950 sm:text-6xl lg:text-7xl">
                    Minyak Jelantahmu Bisa Jadi <span class="text-emerald-600">Cuan.</span>
                </h1>

                <p class="mt-7 max-w-2xl text-lg leading-8 text-slate-600">
                    CUANTAH membantu rumah tangga dan UMKM menyetor minyak jelantah dengan harga transparan,
                    pickup terjadwal, dan dampak lingkungan yang nyata.
                </p>

                <div class="mt-7 flex flex-col gap-3 sm:mt-9 sm:gap-4 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex w-full items-center justify-center gap-3 rounded-2xl bg-emerald-700 px-8 py-3.5 text-base font-black text-white shadow-xl shadow-emerald-900/20 transition hover:bg-emerald-800 sm:w-auto sm:py-4">
                        Setor Sekarang
                        <span aria-hidden="true">→</span>
                    </a>
                    <a href="{{ route('public.page', 'cara-kerja') }}" class="inline-flex w-full items-center justify-center gap-3 rounded-2xl border border-emerald-200 bg-white px-7 py-3.5 text-base font-black text-emerald-800 shadow-sm shadow-emerald-900/5 transition hover:border-emerald-300 hover:bg-emerald-50 sm:w-auto sm:py-4">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-700 text-white" aria-hidden="true">
                            <svg class="ml-0.5 h-3.5 w-3.5" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M4.5 3.2C4.5 2.4 5.4 1.9 6.1 2.4L12.6 6.9C13.2 7.3 13.2 8.3 12.6 8.7L6.1 13.2C5.4 13.7 4.5 13.2 4.5 12.4V3.2Z" />
                            </svg>
                        </span>
                        Lihat Cara Kerja
                    </a>
                </div>

                <dl class="mt-8 grid max-w-3xl gap-4 sm:mt-10 sm:grid-cols-3 sm:gap-5 lg:mt-12">
                    <div class="flex items-center gap-3 sm:gap-4">
                        <dt class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 sm:h-14 sm:w-14">
                            <span class="text-lg font-black sm:text-xl">Rp</span>
                        </dt>
                        <dd>
                            <p class="text-sm font-semibold text-slate-500">Harga</p>
                            <p class="text-lg font-black text-emerald-800 sm:text-xl">Rp{{ number_format($price?->price_per_liter ?? 0, 0, ',', '.') }}/L</p>
                        </dd>
                    </div>
                    <div class="flex items-center gap-3 sm:gap-4">
                        <dt class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 sm:h-14 sm:w-14">
                            <svg class="h-6 w-6 sm:h-7 sm:w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M3 7H15V16H3V7Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                                <path d="M15 10H19L21 13V16H15V10Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                                <path d="M7 19.5C8.10457 19.5 9 18.6046 9 17.5C9 16.3954 8.10457 15.5 7 15.5C5.89543 15.5 5 16.3954 5 17.5C5 18.6046 5.89543 19.5 7 19.5Z" fill="currentColor" />
                                <path d="M17.5 19.5C18.6046 19.5 19.5 18.6046 19.5 17.5C19.5 16.3954 18.6046 15.5 17.5 15.5C16.3954 15.5 15.5 16.3954 15.5 17.5C15.5 18.6046 16.3954 19.5 17.5 19.5Z" fill="currentColor" />
                            </svg>
                        </dt>
                        <dd>
                            <p class="text-sm font-semibold text-slate-500">Pengambilan</p>
                            <p class="text-lg font-black text-emerald-950 sm:text-xl">Antar / Jemput</p>
                        </dd>
                    </div>
                    <div class="flex items-center gap-3 sm:gap-4">
                        <dt class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-700 sm:h-14 sm:w-14">
                            <svg class="h-6 w-6 sm:h-7 sm:w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 7H20V18H4V7Z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" />
                                <path d="M4 10H20" stroke="currentColor" stroke-width="2.2" />
                                <path d="M8 15H12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                            </svg>
                        </dt>
                        <dd>
                            <p class="text-sm font-semibold text-slate-500">Pembayaran</p>
                            <p class="text-lg font-black text-emerald-950 sm:text-xl">Cash / Transfer</p>
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="mx-auto grid w-full max-w-[860px] items-center gap-6 md:grid-cols-[minmax(240px,1fr)_minmax(280px,330px)] md:gap-6">
                <div class="order-2 relative mx-auto h-[270px] w-full max-w-[360px] overflow-hidden sm:h-[340px] sm:max-w-[420px] md:order-1 md:h-[430px] md:max-w-[560px] lg:h-[560px]" aria-hidden="true">
                    <div class="absolute inset-x-0 bottom-4 top-8 rounded-[45%_55%_50%_50%/42%_44%_56%_58%] bg-emerald-200/70 sm:bottom-6 sm:top-12 md:bottom-8 md:top-16"></div>
                    <div class="absolute left-0 top-[22%] h-32 w-32 rounded-full bg-emerald-600/10 sm:h-44 sm:w-44 md:h-56 md:w-56"></div>
                    <div class="absolute right-[8%] top-6 h-16 w-16 rounded-full bg-white/70 blur-2xl sm:h-24 sm:w-24 md:top-8 md:h-36 md:w-36"></div>

                    <svg class="absolute left-[2%] top-[14%] h-48 w-40 text-emerald-700/75 sm:h-56 sm:w-48 md:h-72 md:w-64 lg:h-80 lg:w-72" viewBox="0 0 260 310" fill="none">
                        <path d="M104 282C119 202 135 129 218 53" stroke="currentColor" stroke-width="9" stroke-linecap="round" />
                        <path d="M111 186C73 128 33 123 11 139C40 186 75 203 111 186Z" fill="currentColor" opacity=".36" />
                        <path d="M142 139C138 84 169 47 218 29C221 87 196 126 142 139Z" fill="currentColor" opacity=".48" />
                        <path d="M97 239C55 211 22 220 4 245C44 275 75 273 97 239Z" fill="currentColor" opacity=".30" />
                    </svg>

                    <div class="absolute bottom-4 left-1/2 h-48 w-40 -translate-x-1/2 rounded-[2rem] border-[7px] border-emerald-800/75 bg-[linear-gradient(180deg,rgba(255,255,255,.72)_0%,rgba(255,255,255,.28)_29%,rgba(250,204,21,.78)_30%,rgba(217,119,6,.86)_100%)] shadow-2xl shadow-emerald-950/20 sm:bottom-5 sm:h-56 sm:w-48 md:bottom-10 md:left-[12%] md:h-72 md:w-60 md:-translate-x-0 md:rounded-[2.5rem] md:border-[9px] lg:h-80 lg:w-64 lg:border-[10px]">
                        <div class="absolute left-1/2 top-[-15%] h-[20%] w-[44%] -translate-x-1/2 rounded-t-3xl bg-emerald-800"></div>
                        <div class="absolute left-1/2 top-[-22%] h-[11%] w-[58%] -translate-x-1/2 rounded-xl bg-emerald-700"></div>
                        <div class="absolute left-[15%] right-[15%] top-[32%] h-px bg-white/80"></div>
                        <div class="absolute bottom-[30%] left-[15%] right-[15%] h-px bg-amber-100/70"></div>
                        <div class="absolute bottom-[10%] left-1/2 h-[20%] w-[62%] -translate-x-1/2 rounded-full bg-amber-900/10"></div>
                    </div>
                </div>

                <div id="income-calculator" data-price="{{ $price?->price_per_liter ?? 4000 }}" class="order-1 relative z-10 w-full rounded-[1.5rem] border border-emerald-100 bg-white/95 p-5 shadow-2xl shadow-emerald-950/15 backdrop-blur md:order-2">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-lg font-black text-emerald-950">Estimasi Pendapatan</p>
                            <p class="mt-1 text-sm font-semibold text-slate-500">Hitung potensi cuan dari jelantahmu.</p>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-black text-emerald-800">Aktif</span>
                    </div>

                    <label for="calculator-liters" class="mt-4 block text-sm font-bold text-slate-600">Jumlah jelantah</label>
                    <div class="mt-2 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 focus-within:border-emerald-500 focus-within:ring-4 focus-within:ring-emerald-100">
                        <input id="calculator-liters" type="number" min="0" step="0.1" value="4.8" inputmode="decimal" class="w-full bg-transparent text-3xl font-black tracking-tight text-emerald-950 outline-none" aria-label="Jumlah liter minyak jelantah">
                        <span class="rounded-xl bg-white px-3 py-2 text-base font-black text-emerald-800 shadow-sm">L</span>
                    </div>

                    <div class="mt-3 grid grid-cols-[1fr_auto_1fr] items-center gap-2 rounded-2xl bg-slate-50 p-3">
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Volume</p>
                            <p id="calculator-liters-display" class="mt-1 text-base font-black text-emerald-950">4,8 L</p>
                        </div>
                        <span class="text-xl font-black text-slate-300">×</span>
                        <div>
                            <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-slate-400">Harga aktif</p>
                            <p id="calculator-price-display" class="mt-1 text-base font-black text-emerald-950">Rp{{ number_format($price?->price_per_liter ?? 4000, 0, ',', '.') }}/L</p>
                        </div>
                    </div>

                    <div class="mt-3 rounded-3xl bg-emerald-700 p-4 text-white shadow-lg shadow-emerald-900/20">
                        <p class="text-sm font-bold text-emerald-100">Estimasi diterima</p>
                        <p id="calculator-total" class="mt-2 text-4xl font-black tracking-tight">Rp{{ number_format(4.8 * ($price?->price_per_liter ?? 4000), 0, ',', '.') }}</p>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ([1, 5, 10, 20] as $liter)
                            <button type="button" data-liters="{{ $liter }}" class="inline-flex min-h-11 items-center rounded-full border border-emerald-100 bg-white px-4 text-sm font-black text-emerald-800 shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50">{{ $liter }} L</button>
                        @endforeach
                    </div>

                    <p class="mt-3 text-xs font-semibold leading-5 text-slate-500">Nominal akhir mengikuti volume hasil verifikasi mitra.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-white">
        <div class="mx-auto max-w-[1500px] px-4 py-14 lg:px-8">
            <div class="grid gap-8 md:grid-cols-3">
                <div>
                    <h2 class="text-xl font-black text-emerald-950">Harga transparan</h2>
                    <p class="mt-2 text-slate-600">Harga berasal dari database dan tersimpan sebagai snapshot pada setiap transaksi.</p>
                </div>
                <div>
                    <h2 class="text-xl font-black text-emerald-950">Map-first pickup</h2>
                    <p class="mt-2 text-slate-600">Pilih titik jemput atau titik pengumpulan dengan Leaflet dan OpenStreetMap.</p>
                </div>
                <div>
                    <h2 class="text-xl font-black text-emerald-950">Tanpa wallet MVP</h2>
                    <p class="mt-2 text-slate-600">Mitra membayar langsung. Sistem hanya mencatat history transaksi dan pembayaran.</p>
                </div>
            </div>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const calculator = document.getElementById('income-calculator');

            if (! calculator) {
                return;
            }

            const price = Number(calculator.dataset.price || 0);
            const input = document.getElementById('calculator-liters');
            const litersDisplay = document.getElementById('calculator-liters-display');
            const priceDisplay = document.getElementById('calculator-price-display');
            const total = document.getElementById('calculator-total');
            const formatter = new Intl.NumberFormat('id-ID');

            const formatLiters = (value) => {
                return formatter.format(value).replace('.', ',');
            };

            const updateEstimate = () => {
                const liters = Math.max(0, Number(input.value || 0));
                const income = Math.round(liters * price);

                litersDisplay.textContent = `${formatLiters(liters)} L`;
                priceDisplay.textContent = `Rp${formatter.format(price)}/L`;
                total.textContent = `Rp${formatter.format(income)}`;
            };

            input.addEventListener('input', updateEstimate);

            calculator.querySelectorAll('[data-liters]').forEach((button) => {
                button.addEventListener('click', () => {
                    input.value = button.dataset.liters;
                    updateEstimate();
                });
            });

            updateEstimate();
        });
    </script>
</x-layouts.public>

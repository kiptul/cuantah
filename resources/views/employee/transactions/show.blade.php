<x-layouts.app title="Proses Transaksi">
    <x-slot:head>
        @vite('resources/js/map.js')
    </x-slot:head>

    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div><p class="text-sm font-bold uppercase text-emerald-700">Transaksi</p><h1 class="text-3xl font-black">{{ $transaction->code }}</h1></div>
        <x-status-badge :status="$transaction->status" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            @if($transaction->pickup)
                <div id="employee-map" class="h-[360px] rounded-lg border border-slate-200"></div>
            @endif
            <p class="mt-4 font-bold">Lokasi</p>
            <p class="mt-1 text-sm text-slate-600">{{ $transaction->pickup?->address ?? 'Lokasi belum tercatat.' }}</p>
            @if($transaction->pickup)
                <a target="_blank" class="mt-3 inline-flex rounded-md border border-slate-300 px-3 py-2 text-sm font-bold" href="https://www.google.com/maps?q={{ $transaction->pickup->latitude }},{{ $transaction->pickup->longitude }}">Buka Navigasi</a>
            @endif
        </section>

        <aside class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
            {{-- Nama dan nomor penyetor. Karyawan yang tiba di lokasi dan tidak
                 menemukan alamatnya sebelumnya tidak punya cara menghubungi
                 siapa pun dari dalam aplikasi. --}}
            <div class="mb-5 rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Penyetor</p>
                <p class="mt-1 font-bold text-slate-900">{{ $transaction->user->name }}</p>
                @if($transaction->user->phone)
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $transaction->user->phone) }}"
                       class="mt-2 inline-flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-bold text-emerald-800 shadow-sm ring-1 ring-slate-900/10 transition hover:ring-emerald-300">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 5a1 1 0 0 1 1-1h2.6a1 1 0 0 1 1 .76l.7 2.9a1 1 0 0 1-.3 1L7.6 10.1a12 12 0 0 0 5.4 5.4l1.4-1.4a1 1 0 0 1 1-.26l2.9.7a1 1 0 0 1 .76 1V19a1 1 0 0 1-1 1A15 15 0 0 1 4 5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        </svg>
                        {{ $transaction->user->phone }}
                    </a>
                @else
                    <p class="mt-1 text-sm text-slate-500">Nomor telepon belum diisi.</p>
                @endif
            </div>

            <dl class="mb-5 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt>Mitra</dt><dd class="text-right font-bold">{{ $transaction->partner?->name ?? '-' }}</dd></div>
                {{-- Jadwal yang dipilih penyetor. Sebelumnya hanya admin yang
                     melihatnya, padahal karyawanlah yang harus menepatinya. --}}
                <div class="flex justify-between gap-3">
                    <dt>Jadwal jemput</dt>
                    <dd class="text-right font-bold">
                        @if($transaction->pickup?->pickup_date)
                            {{ $transaction->pickup->pickup_date->translatedFormat('d M Y') }}@if($transaction->pickup->pickup_time)<span class="block text-xs font-semibold text-slate-500">pukul {{ \Illuminate\Support\Str::of($transaction->pickup->pickup_time)->substr(0, 5) }}</span>@endif
                        @else
                            <span class="text-slate-500">Tanpa jadwal</span>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between"><dt>Estimasi liter</dt><dd class="font-bold">{{ number_format($transaction->estimated_liter, 2, ',', '.') }} L</dd></div>
                <div class="flex justify-between"><dt>Ongkir jemput</dt><dd class="font-bold">Rp{{ number_format($transaction->pickup_fee, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt>Estimasi total</dt><dd class="font-black text-emerald-800">Rp{{ number_format($transaction->estimated_total, 0, ',', '.') }}</dd></div>
            </dl>

            {{-- Satu form langsung menutup transaksi. Tombol "Dijemput" dan
                 "Verifikasi" dihapus karena ketiganya terjadi di tempat dan
                 waktu yang sama, dan penyetor tidak lagi perlu mengonfirmasi
                 ulang pembayaran yang diserahkan karyawan di depannya. --}}
            @if($transaction->isFinal())
                <div @class([
                    'rounded-xl px-4 py-4 text-sm',
                    'bg-emerald-50 text-emerald-900 ring-1 ring-emerald-900/10' => $transaction->status === \App\Models\Transaction::STATUS_COMPLETED,
                    'bg-slate-50 text-slate-600' => $transaction->status !== \App\Models\Transaction::STATUS_COMPLETED,
                ])>
                    @if($transaction->status === \App\Models\Transaction::STATUS_COMPLETED)
                        <p class="font-black">Transaksi selesai</p>
                        <p class="mt-1 tabular-nums">{{ number_format($transaction->actual_liter, 2, ',', '.') }} L &middot; Rp{{ number_format($transaction->total_value, 0, ',', '.') }} &middot; {{ $transaction->payment_status === 'paid' ? 'sudah dibayar' : 'belum dibayar' }}</p>
                    @else
                        Transaksi ini sudah {{ $transaction->statusLabel() }}.
                    @endif
                </div>
            @else
                <form method="post" action="{{ route('employee.transactions.verify', $transaction) }}" class="space-y-4 border-t border-slate-100 pt-5"
                      data-complete-form data-price="{{ $transaction->price_per_liter }}" data-fee="{{ $transaction->pickup_fee }}">
                    @csrf
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-emerald-700">Form penjemputan</p>
                        <p class="mt-1 text-sm leading-6 text-slate-600">Takar jelantahnya, serahkan pembayaran, lalu kirim. Transaksi langsung selesai.</p>
                    </div>
                    <div>
                        <label for="actual_liter" class="text-sm font-bold">Volume aktual (L)</label>
                        <input id="actual_liter" name="actual_liter" type="number" inputmode="decimal" step="0.01" min="0.1" max="500" value="{{ old('actual_liter', $transaction->actual_liter) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-lg font-bold tabular-nums outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required data-liter>
                        <p class="mt-1.5 text-xs text-slate-500">Estimasi penyetor {{ number_format($transaction->estimated_liter, 2, ',', '.') }} L.</p>
                    </div>

                    <fieldset>
                        <legend class="text-sm font-bold">Pembayaran</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach(['cash' => 'Tunai', 'transfer' => 'Transfer'] as $value => $label)
                                <label class="cursor-pointer">
                                    <input type="radio" name="payment_method" value="{{ $value }}" class="peer sr-only" @checked(old('payment_method', $transaction->payment_method ?? 'cash') === $value)>
                                    <span class="block rounded-xl border border-slate-300 px-3 py-2.5 text-center text-sm font-bold text-slate-600 transition peer-checked:border-emerald-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-800 peer-focus-visible:ring-4 peer-focus-visible:ring-emerald-100">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        <label class="mt-3 flex items-start gap-2.5 text-sm text-slate-700">
                            <input type="hidden" name="payment_status" value="unpaid">
                            <input type="checkbox" name="payment_status" value="paid" class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-700 focus:ring-emerald-500" @checked(old('payment_status', $transaction->payment_status ?? 'paid') === 'paid')>
                            <span>Uang sudah diserahkan ke penyetor. <span class="text-slate-500">Kosongkan bila dibayar menyusul; admin bisa melunasinya nanti.</span></span>
                        </label>
                    </fieldset>

                    <textarea name="notes" rows="2" maxlength="700" placeholder="Catatan (opsional)" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('notes', $transaction->notes) }}</textarea>

                    <div class="flex items-baseline justify-between rounded-xl bg-emerald-50 px-4 py-3">
                        <span class="text-sm font-bold text-emerald-900">Diterima penyetor</span>
                        {{-- Dibiarkan kosong sampai volume diisi. Memulai dengan
                             estimasi membuat angka perkiraan berdiri di bawah
                             label "Diterima penyetor", dan karyawan dapat
                             menyebutkannya sebelum apa pun ditimbang. --}}
                        <span class="text-lg font-black tabular-nums text-emerald-800" data-total>&mdash;</span>
                    </div>

                    <button class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">Kirim &amp; Selesaikan</button>
                </form>
                <script>
                    document.addEventListener('DOMContentLoaded', () => {
                        const form = document.querySelector('[data-complete-form]');
                        const liter = form.querySelector('[data-liter]');
                        const total = form.querySelector('[data-total]');
                        const price = Number(form.dataset.price);
                        const fee = Number(form.dataset.fee);
                        const render = () => {
                            const value = parseFloat(liter.value);
                            // Isian kosong atau tidak sah mengosongkan angkanya.
                            // Sebelumnya nilai lama dibiarkan bertahan, sehingga
                            // angka yang tampil tidak lagi berasal dari isi kolom.
                            if (! Number.isFinite(value) || value <= 0) {
                                total.textContent = '—';
                                return;
                            }
                            total.textContent = 'Rp' + Math.max(Math.round(value * price) - fee, 0).toLocaleString('id-ID');
                        };
                        liter.addEventListener('input', render);
                        render();
                    });
                </script>
            @endif
        </aside>
    </div>

    @if($transaction->pickup)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const lat = {{ $transaction->pickup->latitude }};
                const lng = {{ $transaction->pickup->longitude }};
                const map = L.map('employee-map').setView([lat, lng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
                L.marker([lat, lng]).addTo(map);
            });
        </script>
    @endif
</x-layouts.app>

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
            <div id="employee-map" class="h-[360px] rounded-lg border border-slate-200"></div>
            <p class="mt-4 font-bold">Lokasi</p>
            <p class="mt-1 text-sm text-slate-600">{{ $transaction->pickup?->address }}</p>
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

            @php
                $isDropOff = $transaction->method === \App\Models\Transaction::METHOD_DROP_OFF;
                $isPickup = $transaction->method === \App\Models\Transaction::METHOD_PICKUP;
                $canMarkPickedUp = $isPickup && $transaction->status === \App\Models\Transaction::STATUS_SCHEDULED;
                $canMarkVerification = ($isPickup && $transaction->status === \App\Models\Transaction::STATUS_PICKED_UP)
                    || ($isDropOff && $transaction->status === \App\Models\Transaction::STATUS_SCHEDULED);
                $canComplete = $transaction->status === \App\Models\Transaction::STATUS_VERIFICATION;
            @endphp

            @if($canMarkPickedUp)
                <form method="post" action="{{ route('employee.transactions.picked-up', $transaction) }}">
                    @csrf
                    <button class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-800">Dijemput</button>
                </form>
            @endif

            @if($canMarkVerification)
                <form method="post" action="{{ route('employee.transactions.verification', $transaction) }}" class="{{ $canMarkPickedUp ? 'mt-2' : '' }}">
                    @csrf
                    <button class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-800">Verifikasi</button>
                </form>
            @endif

            @if($canComplete)
                <form method="post" action="{{ route('employee.transactions.verify', $transaction) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-sm font-bold">Volume aktual (L)</label>
                        <input name="actual_liter" type="number" step="0.01" min="0.1" max="500" value="{{ old('actual_liter', $transaction->actual_liter) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                        <p class="mt-1.5 text-xs text-slate-500">Estimasi penyetor {{ number_format($transaction->estimated_liter, 2, ',', '.') }} L.</p>
                    </div>
                    <div><label class="text-sm font-bold">Metode pembayaran</label><select name="payment_method" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"><option value="cash" @selected(old('payment_method', $transaction->payment_method) === 'cash')>Tunai</option><option value="transfer" @selected(old('payment_method', $transaction->payment_method) === 'transfer')>Transfer</option></select></div>
                    <div><label class="text-sm font-bold">Status pembayaran</label><select name="payment_status" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"><option value="paid" @selected(old('payment_status', $transaction->payment_status) === 'paid')>Sudah dibayar</option><option value="unpaid" @selected(old('payment_status', $transaction->payment_status) === 'unpaid')>Belum dibayar</option></select></div>
                    <textarea name="notes" rows="3" maxlength="700" placeholder="Catatan" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('notes', $transaction->notes) }}</textarea>
                    <button class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">Selesaikan Transaksi</button>
                </form>
            @endif

            @if(! $canMarkPickedUp && ! $canMarkVerification && ! $canComplete)
                <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
                    @if($transaction->status === \App\Models\Transaction::STATUS_COMPLETED)
                        Transaksi ini sudah selesai.
                    @else
                        Belum ada aksi lanjutan untuk status saat ini.
                    @endif
                </div>
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

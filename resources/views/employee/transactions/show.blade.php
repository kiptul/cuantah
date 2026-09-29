<x-layouts.app title="Proses Transaksi">
    <x-slot:head>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    </x-slot:head>

    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div><p class="text-sm font-bold uppercase text-emerald-700">Transaksi</p><h1 class="text-3xl font-black">{{ $transaction->code }}</h1></div>
        <x-status-badge :status="$transaction->status" />
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div id="employee-map" class="h-[360px] rounded-lg border border-slate-200"></div>
            <p class="mt-4 font-bold">Lokasi</p>
            <p class="mt-1 text-sm text-slate-600">{{ $transaction->pickup?->address }}</p>
            @if($transaction->pickup)
                <a target="_blank" class="mt-3 inline-flex rounded-md border border-slate-300 px-3 py-2 text-sm font-bold" href="https://www.google.com/maps?q={{ $transaction->pickup->latitude }},{{ $transaction->pickup->longitude }}">Buka Navigasi</a>
            @endif
        </section>

        <aside class="rounded-lg border border-slate-200 bg-white p-5">
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
                    <button class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-bold hover:bg-slate-50">Dijemput</button>
                </form>
            @endif

            @if($canMarkVerification)
                <form method="post" action="{{ route('employee.transactions.verification', $transaction) }}" class="{{ $canMarkPickedUp ? 'mt-2' : '' }}">
                    @csrf
                    <button class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-bold hover:bg-slate-50">Verifikasi</button>
                </form>
            @endif

            @if($canComplete)
                <form method="post" action="{{ route('employee.transactions.verify', $transaction) }}" class="space-y-4">
                    @csrf
                    <div><label class="text-sm font-bold">Actual liter</label><input name="actual_liter" type="number" step="0.01" value="{{ old('actual_liter', $transaction->actual_liter) }}" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" required></div>
                    <div><label class="text-sm font-bold">Metode pembayaran</label><select name="payment_method" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><option value="cash">Cash</option><option value="transfer">Transfer</option></select></div>
                    <div><label class="text-sm font-bold">Status pembayaran</label><select name="payment_status" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><option value="paid">Dibayar</option><option value="unpaid">Belum dibayar</option></select></div>
                    <textarea name="notes" rows="3" placeholder="Catatan" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('notes', $transaction->notes) }}</textarea>
                    <button class="w-full rounded-md bg-emerald-700 px-4 py-3 font-bold text-white">Selesaikan Transaksi</button>
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

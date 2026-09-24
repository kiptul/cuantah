<x-layouts.app title="Detail Transaksi">
    <x-slot:head>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    </x-slot:head>
    <div class="mb-5"><a href="{{ route('transactions.index') }}" class="text-sm font-bold text-emerald-700">Kembali</a><h1 class="mt-2 text-3xl font-black">{{ $transaction->code }}</h1></div>
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div id="map" class="h-[360px] rounded-lg border border-slate-200"></div>
            <div class="mt-5">
                <p class="font-bold">Lokasi {{ $transaction->method === 'pickup' ? 'Rumah Tangga/UMKM' : 'Pengumpulan CUANTAH' }}</p>
                <p class="mt-1 text-slate-600">{{ $transaction->pickup?->address }}</p>
                @if($transaction->pickup)
                    <a target="_blank" class="mt-4 inline-flex rounded-md border border-slate-300 px-4 py-2 text-sm font-bold" href="https://www.google.com/maps?q={{ $transaction->pickup->latitude }},{{ $transaction->pickup->longitude }}">Buka Navigasi</a>
                @endif
            </div>
        </section>
        <aside class="h-fit rounded-lg border border-slate-200 bg-white p-5">
            <x-status-badge :status="$transaction->status" />
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between"><dt>Estimasi liter</dt><dd class="font-bold">{{ number_format($transaction->estimated_liter, 2, ',', '.') }} L</dd></div>
                <div class="flex justify-between"><dt>Mitra</dt><dd class="font-bold">{{ $transaction->partner?->name ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt>Actual liter</dt><dd class="font-bold">{{ $transaction->actual_liter ? number_format($transaction->actual_liter, 2, ',', '.').' L' : '-' }}</dd></div>
                <div class="flex justify-between"><dt>Harga/L</dt><dd class="font-bold">Rp{{ number_format($transaction->price_per_liter, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt>Ongkir jemput</dt><dd class="font-bold">Rp{{ number_format($transaction->pickup_fee, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt>Total</dt><dd class="font-black">Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt>Pembayaran</dt><dd class="font-bold">{{ $transaction->payment_method ? str($transaction->payment_method)->title() : '-' }}</dd></div>
                <div class="flex justify-between"><dt>Status bayar</dt><dd>{{ $transaction->payment_status ? '' : '-' }}@if($transaction->payment_status)<x-status-badge :status="$transaction->payment_status" />@endif</dd></div>
                <div class="flex justify-between"><dt>Karyawan</dt><dd class="font-bold">{{ $transaction->pickup?->assignedUser?->name ?? '-' }}</dd></div>
            </dl>
            @if($transaction->method === 'drop_off')
                <a href="{{ route('transactions.barcode', $transaction) }}" class="mt-5 block rounded-md bg-emerald-700 px-4 py-3 text-center font-bold text-white">Download Barcode PDF</a>
            @endif
        </aside>
    </div>
    @if($transaction->pickup)
        <script>
            const lat = {{ $transaction->pickup->latitude }};
            const lng = {{ $transaction->pickup->longitude }};
            const map = L.map('map').setView([lat, lng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
            L.marker([lat, lng]).addTo(map);
        </script>
    @endif
</x-layouts.app>

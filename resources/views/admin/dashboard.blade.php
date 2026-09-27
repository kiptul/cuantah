<x-layouts.admin title="Admin Dashboard">
    <div class="mb-6">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Admin</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">Dashboard Operasional</h1>
    </div>

    {{-- Tiga angka hasil didahulukan dan dibesarkan. Sebelumnya keenam angka
         tampil seragam, sehingga nilai yang terkumpul terbaca sama penting
         dengan jumlah pengguna. --}}
    <section class="grid gap-4 sm:grid-cols-3">
        @foreach([
            ['Nilai terkumpul', 'Rp'.number_format($total_value, 0, ',', '.')],
            ['Liter terkumpul', number_format($total_liter, 2, ',', '.').' L'],
            ['Total transaksi', $total_transactions],
        ] as [$label, $value])
            <div class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
                <p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">{{ $label }}</p>
                <p class="mt-3 text-3xl font-black tracking-tight text-emerald-950">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    {{-- Pickup pending diberi warna peringatan ketika lebih dari nol, karena
         angka itu menuntut tindakan dan bukan sekadar catatan riwayat. --}}
    <section class="mt-4 grid grid-cols-3 gap-3">
        <div @class([
            'rounded-2xl border p-4',
            'border-amber-200 bg-amber-50' => $pending_pickups > 0,
            'border-slate-200 bg-white' => $pending_pickups === 0,
        ])>
            <p @class([
                'text-xs font-bold uppercase tracking-[0.12em]',
                'text-amber-700' => $pending_pickups > 0,
                'text-slate-500' => $pending_pickups === 0,
            ])>Pickup pending</p>
            <p @class([
                'mt-2 text-xl font-black',
                'text-amber-900' => $pending_pickups > 0,
                'text-slate-900' => $pending_pickups === 0,
            ])>{{ $pending_pickups }}</p>
        </div>
        @foreach([
            ['Selesai', $completed_transactions],
            ['Pengguna', $total_users],
        ] as [$label, $value])
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">{{ $label }}</p>
                <p class="mt-2 text-xl font-black text-slate-900">{{ $value }}</p>
            </div>
        @endforeach
    </section>

    <section class="mt-6 rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
        <h2 class="font-black tracking-tight text-emerald-950">Tren bulanan</h2>
        <canvas id="monthlyChart" class="mt-4 max-h-[360px]"></canvas>
    </section>
    {{-- Versi mayor dipatok. Tanpa patokan, CDN melayani rilis terbaru
         sehingga breaking change pada Chart.js mematikan grafik ini
         tanpa ada perubahan kode sama sekali. --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script>
        const monthly = @json($monthly);
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthly.map(item => item.month),
                datasets: [
                    { label: 'Volume (L)', data: monthly.map(item => item.volume), backgroundColor: '#047857' },
                    { label: 'Jumlah Transaksi', data: monthly.map(item => item.count), backgroundColor: '#0f766e' },
                    { label: 'Nilai / 1000', data: monthly.map(item => item.value / 1000), backgroundColor: '#334155' },
                ]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    </script>
</x-layouts.admin>

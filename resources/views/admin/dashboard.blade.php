<x-layouts.admin title="Admin Dashboard">
    <div class="mb-5"><p class="text-sm font-bold uppercase text-emerald-700">Admin</p><h1 class="text-2xl font-black sm:text-3xl">Dashboard Operasional</h1></div>
    <section class="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
        @foreach([
            ['Pengguna', $total_users],
            ['Liter', number_format($total_liter, 2, ',', '.')],
            ['Transaksi', $total_transactions],
            ['Nilai', 'Rp'.number_format($total_value, 0, ',', '.')],
            ['Pickup Pending', $pending_pickups],
            ['Selesai', $completed_transactions],
        ] as [$label, $value])
            <div class="rounded-lg border border-slate-200 bg-white p-4"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-black">{{ $value }}</p></div>
        @endforeach
    </section>
    <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5">
        <h2 class="font-black">Tren bulanan</h2>
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

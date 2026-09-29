<x-layouts.admin title="Admin Dashboard">
    <div class="mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Ringkasan operasional</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Dashboard</h1>
            <p class="mt-2 text-sm text-slate-500">Pantau aktivitas setoran dan pickup CUANTAH.</p>
        </div>
        <a href="{{ route('admin.pickups.index') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800">
            Kelola pickup <span aria-hidden="true">→</span>
        </a>
    </div>

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Pengguna aktif', $total_users, 'Terdaftar sebagai penyetor'],
            ['Volume terkumpul', number_format($total_liter, 2, ',', '.').' L', 'Transaksi berstatus selesai'],
            ['Total transaksi', $total_transactions, 'Seluruh setoran yang tercatat'],
            ['Nilai terkumpul', 'Rp'.number_format($total_value, 0, ',', '.'), 'Dari transaksi selesai'],
        ] as [$label, $value, $description])
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5">
                <p class="text-sm font-bold text-slate-500">{{ $label }}</p>
                <p class="mt-3 text-2xl font-black tracking-tight text-slate-950">{{ $value }}</p>
                <p class="mt-2 text-xs font-medium text-slate-400">{{ $description }}</p>
            </div>
        @endforeach
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Tren bulanan</h2>
                    <p class="mt-1 text-sm text-slate-500">Volume, jumlah transaksi, dan nilai setoran selama 12 bulan terakhir.</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800">12 bulan</span>
            </div>
            <div class="mt-5 h-[300px] sm:h-[340px]">
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 sm:p-6">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Perlu perhatian</p>
            <h2 class="mt-2 text-lg font-black text-slate-950">Status operasional</h2>
            <div class="mt-5 space-y-3">
                <a href="{{ route('admin.pickups.index') }}" class="block rounded-xl border border-amber-100 bg-amber-50 p-4 transition hover:border-amber-200">
                    <p class="text-sm font-bold text-amber-800">Pickup menunggu</p>
                    <p class="mt-2 text-3xl font-black tracking-tight text-amber-950">{{ $pending_pickups }}</p>
                    <p class="mt-1 text-xs font-medium text-amber-700">Perlu assignment atau tindak lanjut</p>
                </a>
                <a href="{{ route('admin.transactions.index') }}" class="block rounded-xl border border-emerald-100 bg-emerald-50 p-4 transition hover:border-emerald-200">
                    <p class="text-sm font-bold text-emerald-800">Transaksi selesai</p>
                    <p class="mt-2 text-3xl font-black tracking-tight text-emerald-950">{{ $completed_transactions }}</p>
                    <p class="mt-1 text-xs font-medium text-emerald-700">Terverifikasi dan tercatat</p>
                </a>
            </div>
        </aside>
    </section>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 18 } },
                },
                scales: {
                    x: { grid: { display: false }, border: { display: false } },
                    y: { beginAtZero: true, border: { display: false }, grid: { color: '#e2e8f0' } },
                },
            }
        });
    </script>
</x-layouts.admin>

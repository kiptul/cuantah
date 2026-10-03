<x-layouts.admin title="Admin Dashboard">
    <x-slot:head>
        @vite('resources/js/chart.js')
    </x-slot:head>

    @php
        $rupiah = fn ($value) => 'Rp'.number_format($value, 0, ',', '.');
        $liter = fn ($value) => number_format($value, 2, ',', '.').' L';
        $totalAttention = array_sum($attention);
    @endphp

    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Ringkasan operasional &middot; {{ now()->translatedFormat('F Y') }}</p>
            <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Dashboard</h1>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.transactions.index') }}" class="inline-flex flex-1 items-center justify-center rounded-xl bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-slate-900/10 transition hover:ring-slate-900/20 sm:flex-none">Transaksi</a>
            <a href="{{ route('admin.pickups.index') }}" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800 sm:flex-none">
                Kelola pickup <span aria-hidden="true">→</span>
            </a>
        </div>
    </div>

    {{-- Angka bulan ini dibandingkan bulan lalu. Angka sepanjang waktu saja
         tidak pernah turun, jadi tidak bisa menunjukkan apakah operasional
         sedang membaik atau melambat. --}}
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Volume bulan ini', $liter($kpis['liter']['current']), $kpis['liter'], 'Total '.$liter($lifetime['liter'])],
            ['Dibayarkan ke penyetor', $rupiah($kpis['value']['current']), $kpis['value'], 'Total '.$rupiah($lifetime['value'])],
            ['Transaksi selesai', $kpis['transactions']['current'], $kpis['transactions'], $lifetime['transactions'].' tercatat seluruhnya'],
            ['Penyetor aktif', $kpis['depositors']['current'], $kpis['depositors'], $lifetime['depositors'].' pernah menyetor'],
        ] as [$label, $value, $compare, $footnote])
            @php
                $delta = $compare['previous'] > 0
                    ? ($compare['current'] - $compare['previous']) / $compare['previous'] * 100
                    : null;
            @endphp
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-bold text-slate-500">{{ $label }}</p>
                    @if($delta !== null)
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-xs font-bold tabular-nums',
                            'bg-emerald-50 text-emerald-700' => $delta >= 0,
                            'bg-rose-50 text-rose-700' => $delta < 0,
                        ]) title="Dibanding tanggal yang sama bulan lalu">{{ $delta >= 0 ? '▲' : '▼' }} {{ number_format(abs($delta), 0, ',', '.') }}%</span>
                    @endif
                </div>
                <p class="mt-3 text-2xl font-black tracking-tight tabular-nums text-slate-950">{{ $value }}</p>
                <p class="mt-2 text-xs font-medium text-slate-400">{{ $footnote }}</p>
            </div>
        @endforeach
    </section>

    {{-- Antrean tindakan: semua hal yang dilaporkan ke admin dan menunggu
         keputusan, dikumpulkan di satu tempat. --}}
    <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 sm:p-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Perlu tindakan</p>
                <h2 class="mt-1 text-lg font-black text-slate-950">
                    {{ $totalAttention > 0 ? $totalAttention.' hal menunggu keputusanmu' : 'Semua beres' }}
                </h2>
            </div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Pickup belum ditugaskan', $attention['unassigned'], 'Tugaskan karyawan', route('admin.pickups.index'), 'amber'],
                ['Jadwal terlewat', $attention['overdue'], 'Hubungi karyawan/penyetor', route('admin.pickups.index'), 'rose'],
                ['Keberatan takaran', $attention['disputes'], 'Tanggapi di bawah', '#keberatan', 'amber'],
                ['Belum dibayar', $attention['unpaid'], 'Tandai lunas di bawah', '#belum-dibayar', 'amber'],
            ] as [$label, $count, $hint, $url, $tone])
                <a href="{{ $url }}" @class([
                    'block rounded-xl border p-4 transition',
                    'border-slate-100 bg-slate-50 hover:border-slate-200' => $count === 0,
                    'border-amber-100 bg-amber-50 hover:border-amber-200' => $count > 0 && $tone === 'amber',
                    'border-rose-100 bg-rose-50 hover:border-rose-200' => $count > 0 && $tone === 'rose',
                ])>
                    <p @class([
                        'text-sm font-bold',
                        'text-slate-500' => $count === 0,
                        'text-amber-800' => $count > 0 && $tone === 'amber',
                        'text-rose-800' => $count > 0 && $tone === 'rose',
                    ])>{{ $label }}</p>
                    <p @class([
                        'mt-2 text-3xl font-black tracking-tight tabular-nums',
                        'text-slate-300' => $count === 0,
                        'text-amber-950' => $count > 0 && $tone === 'amber',
                        'text-rose-950' => $count > 0 && $tone === 'rose',
                    ])>{{ $count }}</p>
                    <p class="mt-1 text-xs font-medium text-slate-500">{{ $count > 0 ? $hint : 'Tidak ada' }}</p>
                </a>
            @endforeach
        </div>

        @if($open_disputes->isNotEmpty() || $unpaid_transactions->isNotEmpty())
            <div class="mt-5 grid gap-5 lg:grid-cols-2">
                @if($open_disputes->isNotEmpty())
                    <div id="keberatan">
                        <h3 class="text-xs font-black uppercase tracking-[0.12em] text-amber-700">Keberatan belum ditanggapi</h3>
                        <div class="mt-2 divide-y divide-slate-100 rounded-xl ring-1 ring-slate-900/5">
                            @foreach($open_disputes as $transaction)
                                <a href="{{ route('admin.transactions.show', $transaction) }}" class="block px-4 py-3 transition hover:bg-slate-50">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ $transaction->user->name }}</p>
                                        <span class="shrink-0 text-xs text-slate-400">{{ $transaction->disputed_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="mt-1 line-clamp-1 text-sm text-slate-500">{{ $transaction->dispute_reason }}</p>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
                @if($unpaid_transactions->isNotEmpty())
                    <div id="belum-dibayar">
                        <h3 class="text-xs font-black uppercase tracking-[0.12em] text-amber-700">Belum dibayar ke penyetor</h3>
                        <div class="mt-2 divide-y divide-slate-100 rounded-xl ring-1 ring-slate-900/5">
                            @foreach($unpaid_transactions as $transaction)
                                <div class="flex items-center gap-3 px-4 py-3">
                                    <a href="{{ route('admin.transactions.show', $transaction) }}" class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold text-slate-900 hover:text-emerald-800">{{ $transaction->user->name }}</p>
                                        <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $transaction->code }}</p>
                                    </a>
                                    <span class="shrink-0 text-sm font-black tabular-nums text-slate-900">{{ $rupiah($transaction->total_value) }}</span>
                                    <form method="post" action="{{ route('admin.transactions.mark-paid', $transaction) }}"
                                          onsubmit="return confirm('Tandai {{ $transaction->code }} sudah dibayar?')">
                                        @csrf
                                        <button class="rounded-lg bg-amber-700 px-3 py-1.5 text-xs font-black text-white transition hover:bg-amber-800">Lunas</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-slate-950">Tren bulanan</h2>
                    <p class="mt-1 text-sm text-slate-500">Volume dan nilai transaksi selesai, 12 bulan terakhir.</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800">12 bulan</span>
            </div>
            <div class="mt-5 h-[300px] sm:h-[320px]">
                <canvas id="monthlyChart" aria-label="Grafik volume dan nilai bulanan" role="img"></canvas>
            </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 sm:p-6">
            <h2 class="text-lg font-black text-slate-950">Stok mitra</h2>
            <p class="mt-1 text-sm text-slate-500">Terkumpul dikurangi yang sudah disalurkan.</p>
            <div class="mt-5 space-y-4">
                @forelse($partners as $partner)
                    @php
                        $stock = max($partner->availableLiter(), 0);
                        $percent = $partner->capacity_liter > 0 ? min($stock / $partner->capacity_liter * 100, 100) : 0;
                    @endphp
                    <div>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <p class="truncate font-bold text-slate-900">{{ $partner->name }}</p>
                            <p class="shrink-0 tabular-nums text-slate-500"><span class="font-bold text-slate-900">{{ number_format($stock, 0, ',', '.') }}</span>/{{ number_format($partner->capacity_liter, 0, ',', '.') }} L</p>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100">
                            <div @class([
                                'h-full rounded-full',
                                'bg-emerald-600' => $percent < 75,
                                'bg-amber-500' => $percent >= 75 && $percent < 100,
                                'bg-rose-600' => $percent >= 100,
                            ]) style="width: {{ $percent }}%"></div>
                        </div>
                        @if($percent >= 75)
                            <p class="mt-1 text-xs font-semibold {{ $percent >= 100 ? 'text-rose-700' : 'text-amber-700' }}">
                                {{ $percent >= 100 ? 'Penuh, setoran baru ditolak.' : 'Hampir penuh, jadwalkan penyaluran.' }}
                                <a href="{{ route('admin.distributions.index') }}" class="underline">Salurkan</a>
                            </p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Belum terhubung ke mitra mana pun.</p>
                @endforelse
            </div>
        </aside>
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm shadow-slate-950/5">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <h2 class="text-lg font-black text-slate-950">Laporan masuk terbaru</h2>
                <a href="{{ route('admin.reports.index') }}" class="text-sm font-bold text-emerald-700 transition hover:text-emerald-900">Laporan lengkap</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($recent_completions as $transaction)
                    <a href="{{ route('admin.transactions.show', $transaction) }}" class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-slate-50">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-bold text-slate-900">{{ $transaction->user->name }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                {{ $transaction->pickup?->assignedUser?->name ?? 'Admin' }} &middot; {{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar' }} &middot; {{ $transaction->updated_at->diffForHumans() }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm font-black tabular-nums text-slate-900">{{ $liter($transaction->actual_liter) }}</p>
                            <p class="mt-0.5 text-xs tabular-nums {{ $transaction->payment_status === 'unpaid' ? 'font-bold text-amber-700' : 'text-slate-500' }}">
                                {{ $rupiah($transaction->total_value) }}{{ $transaction->payment_status === 'unpaid' ? ' · belum dibayar' : '' }}
                            </p>
                        </div>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-slate-500">Belum ada transaksi yang diselesaikan.</p>
                @endforelse
            </div>
        </div>

        <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-950/5 sm:p-6">
            <h2 class="text-lg font-black text-slate-950">Karyawan bulan ini</h2>
            <p class="mt-1 text-sm text-slate-500">Berdasarkan liter yang diselesaikan.</p>
            <ol class="mt-4 space-y-3">
                @forelse($top_employees as $index => $employee)
                    <li>
                        <a href="{{ route('admin.reports.employee', $employee->id) }}" class="flex items-center gap-3 rounded-xl px-2 py-1.5 transition hover:bg-emerald-50">
                            <span @class([
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-black',
                                'bg-emerald-700 text-white' => $index === 0,
                                'bg-emerald-50 text-emerald-800' => $index > 0,
                            ])>{{ $index + 1 }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-bold text-slate-900">{{ $employee->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $employee->completed_count }} transaksi</span>
                            </span>
                            <span class="shrink-0 text-sm font-black tabular-nums text-slate-900">{{ $liter($employee->total_liter) }}</span>
                        </a>
                    </li>
                @empty
                    <li class="py-6 text-center text-sm text-slate-500">Belum ada transaksi selesai bulan ini.</li>
                @endforelse
            </ol>
        </aside>
    </section>

    {{-- Dibungkus DOMContentLoaded karena Chart.js ikut dibundel lewat modul
         yang dieksekusi tertunda. Volume dan nilai memakai sumbu masing-masing,
         menggantikan "Nilai / 1000" yang memaksa pembaca menghitung ulang. --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const monthly = @json($monthly);
            const rupiah = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 });
            new Chart(document.getElementById('monthlyChart'), {
                data: {
                    labels: monthly.map(item => item.label),
                    datasets: [
                        { type: 'bar', label: 'Volume (L)', data: monthly.map(item => item.volume), backgroundColor: '#047857', borderRadius: 6, yAxisID: 'y', order: 2 },
                        { type: 'line', label: 'Nilai dibayarkan', data: monthly.map(item => item.value), borderColor: '#d97706', backgroundColor: '#d97706', tension: 0.35, pointRadius: 3, yAxisID: 'value', order: 1 },
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 18 } },
                        tooltip: {
                            callbacks: {
                                label: (context) => context.dataset.yAxisID === 'value'
                                    ? ` ${context.dataset.label}: ${rupiah.format(context.parsed.y)}`
                                    : ` ${context.dataset.label}: ${context.parsed.y.toLocaleString('id-ID')} L (${monthly[context.dataIndex].count} transaksi)`,
                            },
                        },
                    },
                    scales: {
                        x: { grid: { display: false }, border: { display: false } },
                        y: { beginAtZero: true, border: { display: false }, grid: { color: '#e2e8f0' }, title: { display: true, text: 'Liter' } },
                        value: {
                            position: 'right',
                            beginAtZero: true,
                            border: { display: false },
                            grid: { display: false },
                            ticks: { callback: (value) => 'Rp' + Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value) },
                        },
                    },
                }
            });
        });
    </script>
</x-layouts.admin>

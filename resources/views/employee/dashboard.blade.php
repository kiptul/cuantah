<x-layouts.app title="Dashboard Karyawan">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Karyawan CUANTAH</p>
            <h1 class="mt-1 truncate text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">Halo, {{ auth()->user()->name }}</h1>
        </div>
        <div class="flex shrink-0 gap-2">
            <a href="{{ route('employee.scan') }}"
               class="inline-flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800 sm:flex-none">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M4 12h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                Scan Barcode
            </a>
            <a href="{{ route('employee.pickups.available') }}"
               class="inline-flex flex-1 items-center justify-center whitespace-nowrap rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-slate-900/10 transition hover:text-slate-950 hover:ring-slate-900/20 sm:flex-none">
                Cari Pickup
            </a>
        </div>
    </div>

    {{-- Pekerjaan yang masih terbuka dijadikan angka utama. Sebelumnya
         ketiga angka berukuran sama, sehingga hal yang paling perlu
         ditindaklanjuti tidak lebih menonjol daripada catatan riwayat. --}}
    <section class="mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <div class="relative overflow-hidden rounded-2xl bg-emerald-800 p-6 text-white shadow-lg shadow-emerald-900/20 sm:p-7">
            <div class="pointer-events-none absolute -right-16 -top-16 h-52 w-52 rounded-full bg-emerald-600/40 blur-2xl" aria-hidden="true"></div>
            <div class="relative flex h-full flex-col">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-200">Pickup aktif</p>
                <p class="mt-3 text-5xl font-black tracking-tight tabular-nums">{{ $active_pickups }}</p>
                <p class="mt-3 max-w-sm text-sm leading-6 text-emerald-100/90">
                    @if($active_pickups > 0)
                        Masih menunggu dijemput atau diverifikasi.
                    @else
                        Tidak ada yang tertunda. Ambil pickup baru lewat tombol Cari Pickup.
                    @endif
                </p>
                <a href="{{ route('employee.transactions.index') }}"
                   class="mt-auto inline-flex w-fit items-center gap-1.5 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-bold text-white backdrop-blur transition hover:bg-white/25 sm:mt-6">
                    Lihat transaksi saya
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            <x-stat-card label="Pickup selesai" :value="$completed_pickups" hint="Sepanjang waktu." />
            <x-stat-card label="Liter terkumpul" :value="number_format($completed_liter, 2, ',', '.')" unit="L" hint="Dari transaksi yang sudah selesai." />
        </div>
    </section>

    <section class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach([
            ['label' => 'Jemput ke lokasi', 'jumlah' => $pickup_count, 'liter' => $pickup_liter],
            ['label' => 'Antar ke mitra', 'jumlah' => $drop_off_count, 'liter' => $drop_off_liter],
        ] as $rincian)
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">{{ $rincian['label'] }}</p>
                <div class="mt-3 flex items-baseline gap-5">
                    <span class="text-2xl font-black tabular-nums text-slate-900">{{ $rincian['jumlah'] }}<span class="ml-1 text-sm font-bold text-slate-400">transaksi</span></span>
                    <span class="text-2xl font-black tabular-nums text-slate-900">{{ number_format($rincian['liter'], 2, ',', '.') }}<span class="ml-1 text-sm font-bold text-slate-400">L</span></span>
                </div>
            </div>
        @endforeach
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
            <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Pickup terbaru</h2>
            <a href="{{ route('employee.transactions.index') }}" class="text-sm font-bold text-emerald-700 transition hover:text-emerald-900">Lihat semua</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($latest_pickups as $pickup)
                <a href="{{ route('employee.transactions.show', $pickup->transaction) }}" class="flex items-start gap-4 px-5 py-4 transition hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                            <p class="font-mono text-sm font-bold text-slate-900">{{ $pickup->transaction->code }}</p>
                            <x-status-badge :status="$pickup->status" />
                        </div>
                        <p class="mt-1.5 line-clamp-2 text-sm leading-5 text-slate-500">{{ $pickup->address }}</p>
                    </div>
                    <svg class="mt-1 h-5 w-5 shrink-0 text-slate-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </a>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="font-bold text-slate-900">Belum ada pickup</p>
                    <p class="mx-auto mt-1.5 max-w-xs text-sm leading-6 text-slate-500">Ambil pekerjaan pertamamu dari daftar pickup yang tersedia.</p>
                    <a href="{{ route('employee.pickups.available') }}" class="mt-5 inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800">Cari Pickup</a>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.app>

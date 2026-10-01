<x-layouts.app title="Dashboard Karyawan">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Karyawan CUANTAH &middot; {{ now()->translatedFormat('l, d F') }}</p>
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
               class="relative inline-flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-slate-900/10 transition hover:text-slate-950 hover:ring-slate-900/20 sm:flex-none">
                Cari Pickup
                @if($available_count > 0)
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-black text-amber-800">{{ $available_count }}</span>
                @endif
            </a>
        </div>
    </div>

    <section class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="relative overflow-hidden rounded-2xl bg-emerald-800 p-5 text-white shadow-lg shadow-emerald-900/20">
            <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-emerald-600/40 blur-2xl" aria-hidden="true"></div>
            <p class="relative text-xs font-bold uppercase tracking-[0.16em] text-emerald-200">Tugas terbuka</p>
            <p class="relative mt-3 text-4xl font-black tabular-nums">{{ $tasks_count }}</p>
            <p class="relative mt-1 text-xs text-emerald-100/90">{{ $available_count }} pickup lain menunggu diambil.</p>
        </div>
        <x-stat-card label="Selesai hari ini" :value="$today_count"
                     :hint="number_format($today_liter, 2, ',', '.').' L terkumpul.'" />
        <x-stat-card label="Bulan ini" :value="number_format($month_liter, 2, ',', '.')" unit="L"
                     :hint="$month_count.' transaksi. Sepanjang waktu '.number_format($total_liter, 2, ',', '.').' L.'" />
    </section>

    {{-- Daftar kerja utama. Tiap kartu langsung membawa ke form penjemputan;
         mengirimnya menutup transaksi tanpa langkah "Dijemput"/"Verifikasi". --}}
    <section class="mt-6">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Tugas saya</h2>
            <a href="{{ route('employee.transactions.index') }}" class="text-sm font-bold text-emerald-700 transition hover:text-emerald-900">Semua transaksi</a>
        </div>

        @if($tasks_count === 0)
            @php
                $adaPickup = $available_count > 0;
            @endphp
            {{-- Keadaan kosong membaca jumlah pickup yang tersedia. Sebelumnya
                 ia selalu menyuruh mengambil pickup, bahkan ketika hero tepat
                 di atasnya sudah menyatakan tidak ada satu pun, dan tombolnya
                 mengantar ke halaman yang mengulangi hal yang sama. --}}
            <div class="mt-3 rounded-2xl bg-white px-5 py-12 text-center shadow-sm ring-1 ring-slate-900/5">
                <p class="font-bold text-slate-900">Tidak ada tugas terbuka</p>
                <p class="mx-auto mt-1.5 max-w-xs text-sm leading-6 text-slate-500">
                    @if($adaPickup)
                        {{ $available_count }} pickup menunggu diambil, atau scan barcode penyetor yang datang ke mitra.
                    @else
                        Belum ada pickup yang bisa diambil. Penyetor masih bisa datang sendiri ke mitra, scan barcode-nya saat itu terjadi.
                    @endif
                </p>
                <a href="{{ $adaPickup ? route('employee.pickups.available') : route('employee.scan') }}"
                   class="mt-5 inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800">
                    {{ $adaPickup ? 'Cari Pickup' : 'Scan Barcode' }}
                </a>
            </div>
        @else
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                @foreach($tasks as $task)
                    @php
                        $pickup = $task->pickup;
                        $date = $pickup?->pickup_date;
                        $isOverdue = $pickup?->isOverdue() ?? false;
                        $isToday = $date && $date->isToday();
                        // Hanya untuk tugas tanpa tanggal: drop-off dinilai dari
                        // berapa lama ia menunggu, bukan dari janji tanggal.
                        $hariMenunggu = $date ? null : $pickup?->daysWaiting();
                    @endphp
                    <div @class([
                        'flex flex-col rounded-2xl bg-white p-5 shadow-sm ring-1',
                        'ring-rose-300' => $isOverdue,
                        'ring-emerald-300' => $isToday,
                        'ring-slate-900/5' => ! $isOverdue && ! $isToday,
                    ])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-bold text-slate-900">{{ $task->user->name }}</p>
                                <p class="mt-0.5 font-mono text-xs text-slate-500">{{ $task->code }}</p>
                            </div>
                            <span @class([
                                'shrink-0 rounded-full px-2.5 py-1 text-xs font-bold',
                                'bg-rose-100 text-rose-800' => $isOverdue,
                                'bg-emerald-100 text-emerald-800' => $isToday,
                                'bg-slate-100 text-slate-700' => ! $isOverdue && ! $isToday,
                            ])>
                                @if(! $date)
                                    {{-- Direktif ditulis di baris sendiri. Blade hanya
                                         mengenali @ bila didahului karakter non-huruf,
                                         sehingga menempelkannya di belakang kata akan
                                         tercetak apa adanya sebagai teks. --}}
                                    Antar sendiri
                                    @if($isOverdue && $hariMenunggu)
                                        &middot; menunggu {{ $hariMenunggu }} hari
                                    @endif
                                @else
                                    {{ $isToday ? 'Hari ini' : ($isOverdue ? 'Terlewat · ' : '').$date->translatedFormat('d M') }}@if($task->pickup->pickup_time), {{ \Illuminate\Support\Str::of($task->pickup->pickup_time)->substr(0, 5) }}@endif
                                @endif
                            </span>
                        </div>
                        <p class="mt-3 line-clamp-2 text-sm leading-6 text-slate-600">{{ $task->pickup?->address }}</p>
                        <p class="mt-1 text-sm font-bold tabular-nums text-slate-900">± {{ number_format($task->estimated_liter, 2, ',', '.') }} L</p>
                        {{-- Mitra tujuan. Seorang karyawan dapat terhubung ke
                             lebih dari satu mitra, dan tanpa baris ini kartunya
                             menyebut dari mana jelantah diambil tetapi tidak ke
                             mana ia harus disetorkan. --}}
                        <p class="mt-0.5 text-xs text-slate-500">Setor ke {{ $task->partner?->name ?? 'mitra belum ditentukan' }}</p>
                        <div class="mt-4 flex gap-2">
                            <a href="{{ route('employee.transactions.show', $task) }}"
                               class="inline-flex flex-1 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800">
                                Proses
                            </a>
                            @if($task->user->phone)
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $task->user->phone) }}" aria-label="Telepon {{ $task->user->name }}"
                                   class="inline-flex items-center justify-center rounded-xl px-3 py-2.5 text-emerald-800 ring-1 ring-slate-900/10 transition hover:ring-emerald-300">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M4 5a1 1 0 0 1 1-1h2.6a1 1 0 0 1 1 .76l.7 2.9a1 1 0 0 1-.3 1L7.6 10.1a12 12 0 0 0 5.4 5.4l1.4-1.4a1 1 0 0 1 1-.26l2.9.7a1 1 0 0 1 .76 1V19a1 1 0 0 1-1 1A15 15 0 0 1 4 5Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                    </svg>
                                </a>
                            @endif
                            @if($task->pickup && $date)
                                <a target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $task->pickup->latitude }},{{ $task->pickup->longitude }}" aria-label="Navigasi ke lokasi"
                                   class="inline-flex items-center justify-center rounded-xl px-3 py-2.5 text-emerald-800 ring-1 ring-slate-900/10 transition hover:ring-emerald-300">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 21s7-6.2 7-11.5A7 7 0 0 0 5 9.5C5 14.8 12 21 12 21Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                                        <circle cx="12" cy="9.5" r="2.5" stroke="currentColor" stroke-width="1.8" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            @if($tasks_count > $tasks->count())
                {{-- Sisanya disebut jumlahnya. Tugas yang tidak tertampil tetap
                     menjadi tanggung jawab karyawan, jadi memotongnya tanpa
                     keterangan menyembunyikan pekerjaan yang harus dikerjakan. --}}
                <a href="{{ route('employee.transactions.index') }}"
                   class="mt-3 flex items-center justify-between gap-3 rounded-2xl bg-white px-5 py-3 text-sm font-bold text-slate-600 shadow-sm ring-1 ring-slate-900/5 transition hover:text-slate-900 hover:ring-slate-900/15">
                    {{ $tasks_count - $tasks->count() }} tugas lain belum tertampil
                    <span aria-hidden="true">&rarr;</span>
                </a>
            @endif
        @endif
    </section>

    <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-slate-100 px-5 py-3.5">
            <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Baru saya selesaikan</h2>
            {{-- Angkanya menyebut satuan dan rentangnya. "1 total" tidak
                 mengatakan total apa, dan daftar di bawahnya hanya memuat lima
                 terbaru sehingga rentangnya pun tidak terbaca dari isinya.
                 Disembunyikan saat nol, sebab keadaan kosong di bawah sudah
                 mengatakan hal yang sama. --}}
            @if($total_count > 0)
                <span class="text-xs font-bold text-slate-500">{{ $total_count }} setoran sepanjang waktu</span>
            @endif
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($recent_completions as $transaction)
                <a href="{{ route('employee.transactions.show', $transaction) }}" class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-bold text-slate-900">{{ $transaction->user->name }}</p>
                        {{-- Waktu selesai, bukan waktu sentuh terakhir. Kartu
                             statistik di atas sudah memakai completed_at, jadi
                             updated_at di sini membuat dua angka pada satu
                             layar saling membantah. --}}
                        <p class="mt-0.5 text-xs text-slate-500">
                            @if($transaction->completed_at){{ $transaction->completed_at->diffForHumans() }} &middot; @endif{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar' }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="text-sm font-black tabular-nums text-slate-900">{{ number_format($transaction->actual_liter, 2, ',', '.') }} L</p>
                        @if($transaction->payment_status === 'unpaid')
                            <x-status-badge status="unpaid" class="mt-1" />
                        @endif
                    </div>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">Belum ada transaksi yang kamu selesaikan.</p>
            @endforelse
        </div>
    </section>
</x-layouts.app>

<x-layouts.app title="Dashboard CUANTAH">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Dasbor penyetor</p>
            <h1 class="mt-1 truncate text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">Halo, {{ auth()->user()->name }}</h1>
        </div>
        <div class="flex shrink-0 gap-2">
            <a href="{{ route('deposits.create') }}"
               class="inline-flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800 sm:flex-none">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                </svg>
                Setor Jelantah
            </a>
            <a href="{{ route('transactions.index') }}"
               class="inline-flex flex-1 items-center justify-center whitespace-nowrap rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-slate-900/10 transition hover:text-slate-950 hover:ring-slate-900/20 sm:flex-none">
                Riwayat
            </a>
        </div>
    </div>

    {{-- Satu angka dijadikan pusat perhatian, dua sisanya sebagai penopang.
         Sebelumnya ketiganya sejajar sehingga tidak ada yang menonjol. --}}
    <section class="mt-6 grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <div class="relative overflow-hidden rounded-2xl bg-emerald-800 p-6 text-white shadow-lg shadow-emerald-900/20 sm:p-7">
            <div class="pointer-events-none absolute -right-16 -top-16 h-52 w-52 rounded-full bg-emerald-600/40 blur-2xl" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-10 h-48 w-48 rounded-full bg-emerald-950/30 blur-2xl" aria-hidden="true"></div>
            <div class="relative flex h-full flex-col">
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-200">CUAN diterima</p>
                <p class="mt-3 text-4xl font-black tracking-tight tabular-nums sm:text-5xl">Rp{{ number_format($total_value, 0, ',', '.') }}</p>
                <p class="mt-3 max-w-sm text-sm leading-6 text-emerald-100/90">
                    Dari {{ number_format($total_liter, 2, ',', '.') }} liter jelantah yang sudah ditimbang dan diverifikasi mitra.
                </p>
                @if($latest_transactions->isNotEmpty())
                    <p class="mt-auto flex items-center gap-2 border-t border-white/15 pt-4 text-xs font-semibold text-emerald-100/80 sm:mt-6">
                        <svg class="h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="8.5" stroke="currentColor" stroke-width="1.8" />
                            <path d="M12 7.5V12l3 1.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                        </svg>
                        Setoran terakhir {{ $latest_transactions->first()->created_at->translatedFormat('d F Y') }}
                    </p>
                @endif
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            <x-stat-card label="Liter disetor" :value="number_format($total_liter, 2, ',', '.')" unit="L"
                         hint="Hanya setoran yang sudah selesai.">
                <x-slot:icon>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 3s6 6.4 6 10.2A6 6 0 0 1 6 13.2C6 9.4 12 3 12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>

            <x-stat-card label="Transaksi" :value="$total_transactions"
                         hint="Termasuk yang masih berjalan.">
                <x-slot:icon>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M5 4h14v16l-3.5-2-3.5 2-3.5-2L5 20V4Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                        <path d="M9 9h6M9 13h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                    </svg>
                </x-slot:icon>
            </x-stat-card>
        </div>
    </section>

    {{-- Hanya muncul bila memang ada yang menunggu, dan tiap barisnya diberi
         tombol. Sebelumnya ketiganya berbunyi sama persis tanpa ajakan apa pun. --}}
    @if($needs_action->isNotEmpty())
        <section class="mt-6 overflow-hidden rounded-2xl bg-amber-50 shadow-sm ring-1 ring-amber-900/10">
            <div class="flex items-center gap-2 border-b border-amber-900/10 px-5 py-3.5">
                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-amber-500/20 text-amber-700" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                        <path d="M12 8v5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
                        <circle cx="12" cy="16.6" r="1.2" fill="currentColor" />
                    </svg>
                </span>
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-amber-900">Perlu tindakanmu</h2>
                <span class="ml-auto rounded-full bg-amber-500/20 px-2 py-0.5 text-xs font-bold text-amber-900">{{ $needs_action->count() }}</span>
            </div>
            <div class="divide-y divide-amber-900/10">
                @foreach($needs_action as $item)
                    <a href="{{ route('transactions.show', $item['transaction']) }}"
                       class="group flex items-center gap-4 px-5 py-4 transition hover:bg-amber-100/60">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                                <p class="font-bold text-amber-950">{{ $item['label'] }}</p>
                                <span class="font-mono text-xs text-amber-700">{{ $item['transaction']->code }}</span>
                            </div>
                            <p class="mt-1 text-sm leading-6 text-amber-900/80">{{ $item['hint'] }}</p>
                        </div>
                        <span class="hidden shrink-0 items-center gap-1 rounded-lg bg-white px-3 py-2 text-sm font-bold text-amber-900 shadow-sm ring-1 ring-amber-900/10 transition group-hover:ring-amber-900/25 sm:inline-flex">
                            Tinjau
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <svg class="h-5 w-5 shrink-0 text-amber-700 sm:hidden" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m9 6 6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3.5">
            <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Transaksi terbaru</h2>
            <a href="{{ route('transactions.index') }}" class="text-sm font-bold text-emerald-700 transition hover:text-emerald-900">Lihat semua</a>
        </div>

        <div class="divide-y divide-slate-100">
            @forelse($latest_transactions as $transaction)
                <a href="{{ route('transactions.show', $transaction) }}" class="flex items-start gap-4 px-5 py-4 transition hover:bg-slate-50">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                            <p class="font-mono text-sm font-bold text-slate-900">{{ $transaction->code }}</p>
                            <x-status-badge :status="$transaction->status" />
                        </div>
                        <p class="mt-1.5 text-sm text-slate-500">
                            {{ $transaction->created_at->translatedFormat('d M Y') }} &middot; {{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar Sendiri' }}
                        </p>
                    </div>
                    <div class="shrink-0 text-right">
                        <p class="font-black tabular-nums text-slate-900">Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}</p>
                        <p class="mt-1.5 text-sm tabular-nums text-slate-500">{{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L</p>
                    </div>
                </a>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="font-bold text-slate-900">Belum ada transaksi</p>
                    <p class="mx-auto mt-1.5 max-w-xs text-sm leading-6 text-slate-500">Setoran pertamamu akan muncul di sini beserta nilai CUAN yang kamu terima.</p>
                    <a href="{{ route('deposits.create') }}" class="mt-5 inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">
                        Buat setoran pertama
                    </a>
                </div>
            @endforelse
        </div>
    </section>
</x-layouts.app>

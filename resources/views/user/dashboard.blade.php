<x-layouts.app title="Dashboard CUANTAH">
    <div class="mb-8 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
        <h1 class="text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">Halo, {{ auth()->user()->name }}</h1>
        <div class="grid gap-2 sm:flex">
            <a href="{{ route('deposits.create') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-emerald-800">
                Setor Jelantah
            </a>
            <a href="{{ route('transactions.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 px-5 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-400">
                Riwayat
            </a>
        </div>
    </div>

    {{-- Satu angka yang dibesarkan, sisanya dibiarkan tenang. Sebelumnya
         ketiganya berukuran sama sehingga tidak ada yang menonjol. --}}
    <section class="border-y border-slate-200 py-7">
        <div class="grid gap-7 sm:grid-cols-[1.6fr_1fr_1fr] sm:gap-4 sm:divide-x sm:divide-slate-200">
            <div class="sm:pr-4">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">CUAN diterima</p>
                <p class="mt-2 text-4xl font-black tracking-tight tabular-nums text-emerald-800 sm:text-5xl">Rp{{ number_format($total_value, 0, ',', '.') }}</p>
            </div>
            <div class="sm:px-4">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Liter disetor</p>
                <p class="mt-2 text-2xl font-black tracking-tight tabular-nums text-slate-900">{{ number_format($total_liter, 2, ',', '.') }}</p>
            </div>
            <div class="sm:px-4">
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">Transaksi</p>
                <p class="mt-2 text-2xl font-black tracking-tight tabular-nums text-slate-900">{{ $total_transactions }}</p>
            </div>
        </div>
    </section>

    {{-- Hanya muncul bila memang ada yang menunggu. Penanda merah kecil
         dipakai, bukan latar berwarna, supaya tidak bersaing dengan angka utama. --}}
    @if($needs_action->isNotEmpty())
        <section class="mt-8">
            <h2 class="flex items-center gap-2 text-sm font-black uppercase tracking-[0.14em] text-slate-500">
                <span class="h-1.5 w-1.5 rounded-full bg-red-600" aria-hidden="true"></span>
                Perlu tindakanmu
            </h2>
            <div class="mt-3 divide-y divide-slate-200 border-y border-slate-200">
                @foreach($needs_action as $item)
                    <a href="{{ route('transactions.show', $item['transaction']) }}"
                       class="flex items-center justify-between gap-4 py-4 transition hover:bg-slate-50">
                        <div class="min-w-0">
                            <p class="font-bold text-slate-900">{{ $item['label'] }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">{{ $item['hint'] }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-sm text-slate-400">{{ $item['transaction']->code }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-8">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-black uppercase tracking-[0.14em] text-slate-500">Transaksi terbaru</h2>
            <a href="{{ route('transactions.index') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-800">Lihat semua</a>
        </div>

        <div class="mt-3 divide-y divide-slate-200 border-y border-slate-200">
            @forelse($latest_transactions as $transaction)
                <a href="{{ route('transactions.show', $transaction) }}" class="block py-4 transition hover:bg-slate-50">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <p class="font-mono text-sm font-bold text-slate-900">{{ $transaction->code }}</p>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ $transaction->created_at->translatedFormat('d M Y') }} · {{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar Sendiri' }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-bold tabular-nums text-slate-900">Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}</p>
                            <p class="mt-1 text-sm tabular-nums text-slate-500">{{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L</p>
                        </div>
                    </div>
                    <div class="mt-2"><x-status-badge :status="$transaction->status" /></div>
                </a>
            @empty
                <div class="py-6"><x-empty-state title="Belum ada transaksi" body="Buat pengajuan setor pertama kamu." /></div>
            @endforelse
        </div>
    </section>
</x-layouts.app>

<x-layouts.app title="Dashboard CUANTAH">
    <div class="mb-7 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Dashboard</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">Halo, {{ auth()->user()->name }}</h1>
        </div>
        <div class="grid gap-2 sm:flex">
            <a href="{{ route('deposits.create') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-5 py-3 text-sm font-black text-white shadow-lg shadow-emerald-900/15 transition hover:bg-emerald-800">
                Setor Jelantah <span aria-hidden="true">&rarr;</span>
            </a>
            <a href="{{ route('transactions.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-5 py-3 text-sm font-black text-slate-700 transition hover:border-emerald-400 hover:text-emerald-800">
                Lihat Transaksi
            </a>
        </div>
    </div>

    {{-- CUAN diterima adalah angka yang paling dicari penyetor, jadi ia
         mendapat kartu sendiri. Sebelumnya ketiganya tampil sama rata. --}}
    <section class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
        <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-6 shadow-sm shadow-emerald-950/5">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Total CUAN diterima</p>
            <p class="mt-3 text-4xl font-black tracking-tight text-emerald-950 sm:text-5xl">Rp{{ number_format($total_value, 0, ',', '.') }}</p>
            <p class="mt-2 text-sm text-emerald-800/80">Dari transaksi yang sudah selesai diverifikasi.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Total liter disetor</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-slate-900">{{ number_format($total_liter, 2, ',', '.') }} L</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Total transaksi</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-slate-900">{{ $total_transactions }}</p>
            </div>
        </div>
    </section>

    {{-- Hanya muncul bila memang ada yang menunggu. Tanpa bagian ini,
         konfirmasi pembayaran dan tenggat sanggahan terlewat tanpa terlihat. --}}
    @if($needs_action->isNotEmpty())
        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-amber-700">Perlu tindakanmu</p>
            <div class="mt-4 grid gap-2">
                @foreach($needs_action as $item)
                    <a href="{{ route('transactions.show', $item['transaction']) }}"
                       class="flex items-center justify-between gap-3 rounded-xl border border-amber-200 bg-white px-4 py-3 transition hover:border-amber-400">
                        <div class="min-w-0">
                            <p class="font-black text-emerald-950">{{ $item['label'] }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">{{ $item['hint'] }}</p>
                        </div>
                        <span class="shrink-0 text-sm font-black text-amber-800" aria-hidden="true">&rarr;</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-6 grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <h2 class="font-black tracking-tight text-emerald-950">Transaksi terbaru</h2>
                <a href="{{ route('transactions.index') }}" class="text-sm font-bold text-emerald-700 hover:text-emerald-800">Semua</a>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($latest_transactions as $transaction)
                    <a href="{{ route('transactions.show', $transaction) }}" class="block px-5 py-4 transition hover:bg-slate-50">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-black text-emerald-700">{{ $transaction->code }}</p>
                                <p class="mt-0.5 text-sm text-slate-500">
                                    {{ $transaction->created_at->translatedFormat('d M Y') }} · {{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar Sendiri' }}
                                </p>
                            </div>
                            <x-status-badge :status="$transaction->status" class="shrink-0" />
                        </div>
                        <p class="mt-2 text-sm font-bold text-slate-700">
                            {{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L
                            <span class="font-semibold text-slate-400">·</span>
                            Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}
                        </p>
                    </a>
                @empty
                    <div class="p-5"><x-empty-state title="Belum ada transaksi" body="Buat pengajuan setor pertama kamu." /></div>
                @endforelse
            </div>
        </div>

        <div class="h-fit overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 px-5 py-4">
                <h2 class="font-black tracking-tight text-emerald-950">Notifikasi</h2>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($notifications as $notification)
                    {{-- Yang belum terbaca diberi latar dan lencana supaya terlihat
                         berbeda pada kunjungan yang pertama kali memunculkannya. --}}
                    <div @class(['px-5 py-4', 'bg-emerald-50/60' => $notification->read_at === null])>
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-bold text-emerald-950">{{ $notification->title }}</p>
                            @if($notification->read_at === null)
                                <span class="shrink-0 rounded-full bg-emerald-700 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-white">Baru</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm leading-6 text-slate-600">{{ $notification->message }}</p>
                        <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                    </div>
                @empty
                    <div class="p-5"><x-empty-state title="Belum ada notifikasi" /></div>
                @endforelse
            </div>
        </div>
    </section>
</x-layouts.app>

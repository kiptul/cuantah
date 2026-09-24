<x-layouts.app title="Dashboard CUANTAH">
    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase text-emerald-700">Dashboard</p>
            <h1 class="text-2xl font-black sm:text-3xl">Halo, {{ auth()->user()->name }}</h1>
        </div>
        <div class="grid gap-2 sm:flex">
            <a href="{{ route('deposits.create') }}" class="rounded-md bg-emerald-700 px-4 py-2 text-center text-sm font-bold text-white">Setor Jelantah</a>
            <a href="{{ route('transactions.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-bold text-slate-700">Lihat Transaksi</a>
        </div>
    </div>

    <section class="mt-6 grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Total liter disetor</p><p class="mt-2 text-2xl font-black sm:text-3xl">{{ number_format($total_liter, 2, ',', '.') }} L</p></div>
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Total transaksi</p><p class="mt-2 text-2xl font-black sm:text-3xl">{{ $total_transactions }}</p></div>
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Total CUAN diterima</p><p class="mt-2 text-2xl font-black sm:text-3xl">Rp{{ number_format($total_value, 0, ',', '.') }}</p></div>
    </section>

    <section class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        <div class="rounded-lg border border-slate-200 bg-white">
            <div class="border-b border-slate-200 p-4"><h2 class="font-black">Transaksi terbaru</h2></div>
            <div class="divide-y divide-slate-100">
                @forelse($latest_transactions as $transaction)
                    <a href="{{ route('transactions.show', $transaction) }}" class="block p-4 hover:bg-slate-50">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="font-bold">{{ $transaction->code }}</p><p class="text-sm text-slate-500">{{ $transaction->created_at->format('d M Y') }} · {{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar Sendiri' }}</p></div>
                            <x-status-badge :status="$transaction->status" />
                        </div>
                    </a>
                @empty
                    <div class="p-4"><x-empty-state title="Belum ada transaksi" body="Buat pengajuan setor pertama kamu." /></div>
                @endforelse
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-black">Status pengajuan terbaru</h2>
            @if($latest_request)
                <p class="mt-4 font-bold">{{ $latest_request->code }}</p>
                <div class="mt-2"><x-status-badge :status="$latest_request->status" /></div>
                <p class="mt-4 text-sm text-slate-600">{{ $latest_request->pickup?->address }}</p>
            @else
                <div class="mt-4"><x-empty-state title="Belum ada pengajuan" /></div>
            @endif
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-4"><h2 class="font-black">Notifikasi</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse($notifications as $notification)
                <div class="p-4">
                    <p class="font-bold">{{ $notification->title }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $notification->message }}</p>
                </div>
            @empty
                <div class="p-4"><x-empty-state title="Belum ada notifikasi" /></div>
            @endforelse
        </div>
    </section>
</x-layouts.app>

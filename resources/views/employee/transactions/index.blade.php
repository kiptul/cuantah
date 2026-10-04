<x-layouts.app title="Transaksi Karyawan">
    <div class="mb-6">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Karyawan CUANTAH</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">Transaksi Saya</h1>
    </div>

    {{-- Daftar berbentuk kartu, bukan tabel selebar 720px. Karyawan bekerja
         dari lapangan dengan telepon di tangan, dan tabel selebar itu hanya
         terbaca dengan menggeser layar ke samping. --}}
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5">
        <div class="divide-y divide-slate-100">
            @forelse($transactions as $transaction)
                <a href="{{ route('employee.transactions.show', $transaction) }}" class="block px-5 py-4 transition hover:bg-slate-50">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                                <p class="font-mono text-sm font-bold text-slate-900">{{ $transaction->code }}</p>
                                <x-status-badge :status="$transaction->status" />
                            </div>
                            <p class="mt-1.5 truncate text-sm text-slate-600">{{ $transaction->user->name }}</p>
                            <p class="mt-0.5 text-sm text-slate-500">
                                {{ $transaction->partner?->name ?? 'Mitra belum ditentukan' }}
                                &middot; {{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar Sendiri' }}
                            </p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="font-black tabular-nums text-slate-900">
                                {{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L
                            </p>
                            <p class="mt-1 text-xs text-slate-500">{{ $transaction->actual_liter ? 'aktual' : 'estimasi' }}</p>
                        </div>
                    </div>
                </a>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="font-bold text-slate-900">Belum ada transaksi</p>
                    <p class="mx-auto mt-1.5 max-w-xs text-sm leading-6 text-slate-500">Transaksi yang kamu ambil atau scan akan muncul di sini.</p>
                </div>
            @endforelse
        </div>
    </div>

    <div class="mt-5">{{ $transactions->links() }}</div>
</x-layouts.app>

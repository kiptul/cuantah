<x-layouts.app title="Transaksi Saya">
    <div class="mb-5 flex items-end justify-between gap-3">
        <div><p class="text-sm font-bold uppercase text-emerald-700">Riwayat</p><h1 class="text-2xl font-black sm:text-3xl">Transaksi Saya</h1></div>
        <a href="{{ route('deposits.create') }}" class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Setor Jelantah</a>
    </div>
    {{-- Layar kecil memakai kartu. Tabel enam kolom hanya menyisakan ID dan
         Mitra pada lebar 375px; metode, estimasi, total, dan status berada di
         luar layar dan hanya terjangkau lewat gulir mendatar. --}}
    <div class="grid gap-3 md:hidden">
        @forelse($transactions as $transaction)
            <a href="{{ route('transactions.show', $transaction) }}"
               class="block rounded-lg border border-slate-200 bg-white p-4 transition hover:border-emerald-300">
                <div class="flex items-start justify-between gap-3">
                    <span class="font-black text-emerald-700">{{ $transaction->code }}</span>
                    <x-status-badge :status="$transaction->status" />
                </div>
                <p class="mt-1 text-sm text-slate-500">{{ $transaction->partner?->name ?? '-' }}</p>
                <dl class="mt-4 grid grid-cols-3 gap-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Metode</dt>
                        <dd class="mt-0.5 text-sm font-bold">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Estimasi</dt>
                        <dd class="mt-0.5 text-sm font-bold">{{ number_format($transaction->estimated_liter, 2, ',', '.') }} L</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Total</dt>
                        <dd class="mt-0.5 text-sm font-bold"><x-transaction-amount :transaction="$transaction" /></dd>
                    </div>
                </dl>
            </a>
        @empty
            <div class="rounded-lg border border-slate-200 bg-white p-4"><x-empty-state title="Belum ada transaksi" /></div>
        @endforelse
    </div>

    <div class="hidden overflow-x-auto rounded-lg border border-slate-200 bg-white md:block">
        <table class="min-w-[720px] w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase text-slate-500"><tr><th class="p-3">ID</th><th class="p-3">Mitra</th><th class="p-3">Metode</th><th class="p-3">Estimasi</th><th class="p-3">Total</th><th class="p-3">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($transactions as $transaction)
                    <tr class="hover:bg-slate-50">
                        <td class="p-3"><a class="font-bold text-emerald-700" href="{{ route('transactions.show', $transaction) }}">{{ $transaction->code }}</a></td>
                        <td class="p-3">{{ $transaction->partner?->name ?? '-' }}</td>
                        <td class="p-3">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar Sendiri' }}</td>
                        <td class="p-3">{{ number_format($transaction->estimated_liter, 2, ',', '.') }} L</td>
                        <td class="p-3"><x-transaction-amount :transaction="$transaction" /></td>
                        <td class="p-3"><x-status-badge :status="$transaction->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-4"><x-empty-state title="Belum ada transaksi" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.app>

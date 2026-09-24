<x-layouts.admin title="Kelola Transaksi">
    <h1 class="mb-5 text-2xl font-black sm:text-3xl">Kelola Transaksi</h1>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-[760px] w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase text-slate-500"><tr><th class="p-3">ID</th><th class="p-3">User</th><th class="p-3">Mitra</th><th class="p-3">Metode</th><th class="p-3">Liter</th><th class="p-3">Total</th><th class="p-3">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($transactions as $transaction)
                    <tr>
                        <td class="p-3"><a href="{{ route('admin.transactions.show', $transaction) }}" class="font-bold text-emerald-700">{{ $transaction->code }}</a></td>
                        <td class="p-3">{{ $transaction->user->name }}</td>
                        <td class="p-3">{{ $transaction->partner?->name ?? '-' }}</td>
                        <td class="p-3">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar' }}</td>
                        <td class="p-3">{{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L</td>
                        <td class="p-3">Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}</td>
                        <td class="p-3"><x-status-badge :status="$transaction->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.admin>

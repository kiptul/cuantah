<x-layouts.app title="Transaksi Karyawan">
    <h1 class="mb-5 text-2xl font-black sm:text-3xl">Transaksi Saya</h1>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="min-w-[720px] w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase text-slate-500"><tr><th class="p-3">ID</th><th class="p-3">Mitra</th><th class="p-3">User</th><th class="p-3">Metode</th><th class="p-3">Liter</th><th class="p-3">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($transactions as $transaction)
                    <tr>
                        <td class="p-3"><a class="font-bold text-emerald-700" href="{{ route('employee.transactions.show', $transaction) }}">{{ $transaction->code }}</a></td>
                        <td class="p-3">{{ $transaction->partner?->name ?? '-' }}</td>
                        <td class="p-3">{{ $transaction->user->name }}</td>
                        <td class="p-3">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Drop-off' }}</td>
                        <td class="p-3">{{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L</td>
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

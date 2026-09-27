<x-layouts.admin title="Kelola Transaksi">
    <x-page-header eyebrow="Operasional" title="Kelola Transaksi" />

    {{-- Layar kecil memakai kartu. Tabel tujuh kolom berlebar minimum 760px
         hanya menyisakan dua kolom pertama pada lebar 375px. --}}
    <div class="grid gap-3 lg:hidden">
        @forelse($transactions as $transaction)
            <a href="{{ route('admin.transactions.show', $transaction) }}"
               class="block rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-emerald-300">
                <div class="flex items-start justify-between gap-3">
                    <span class="font-black text-emerald-700">{{ $transaction->code }}</span>
                    <x-status-badge :status="$transaction->status" />
                </div>
                <p class="mt-1 text-sm text-slate-600">{{ $transaction->user->name }} · {{ $transaction->partner?->name ?? 'Tanpa mitra' }}</p>
                <dl class="mt-4 grid grid-cols-3 gap-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Metode</dt>
                        <dd class="mt-0.5 text-sm font-bold">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Liter</dt>
                        <dd class="mt-0.5 text-sm font-bold">{{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-400">Total</dt>
                        <dd class="mt-0.5 text-sm font-bold">Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}</dd>
                    </div>
                </dl>
            </a>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-4"><x-empty-state title="Belum ada transaksi" /></div>
        @endforelse
    </div>

    <div class="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white lg:block">
        <table class="min-w-[760px] w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="p-3">ID</th><th class="p-3">User</th><th class="p-3">Mitra</th><th class="p-3">Metode</th><th class="p-3">Liter</th><th class="p-3">Total</th><th class="p-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($transactions as $transaction)
                    <tr class="hover:bg-slate-50">
                        <td class="p-3"><a href="{{ route('admin.transactions.show', $transaction) }}" class="font-bold text-emerald-700">{{ $transaction->code }}</a></td>
                        <td class="p-3">{{ $transaction->user->name }}</td>
                        <td class="p-3">{{ $transaction->partner?->name ?? '-' }}</td>
                        <td class="p-3">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar' }}</td>
                        <td class="p-3 tabular-nums">{{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L</td>
                        <td class="p-3 tabular-nums">Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}</td>
                        <td class="p-3"><x-status-badge :status="$transaction->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.admin>

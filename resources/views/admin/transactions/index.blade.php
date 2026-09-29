<x-layouts.admin title="Kelola Transaksi">
    <x-page-header eyebrow="Operasional" title="Kelola Transaksi" />

    @php
        $isFiltered = $filters['q'] !== '' || $filters['status'] !== null || $filters['method'] !== null;
    @endphp

    <form method="get" action="{{ route('admin.transactions.index') }}"
          class="mb-4 rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm shadow-emerald-950/5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto]">
            <div class="lg:min-w-0">
                <label for="filter-q" class="text-sm font-bold text-slate-700">Cari</label>
                <input
                    id="filter-q"
                    type="search"
                    name="q"
                    value="{{ $filters['q'] }}"
                    placeholder="Kode transaksi atau nama penyetor"
                    class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                >
            </div>

            <div>
                <label for="filter-status" class="text-sm font-bold text-slate-700">Status</label>
                <select
                    id="filter-status"
                    name="status"
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-emerald-950 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                >
                    <option value="">Semua status</option>
                    @foreach($statusLabels as $status => $label)
                        <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ ucfirst($label) }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-method" class="text-sm font-bold text-slate-700">Metode</label>
                <select
                    id="filter-method"
                    name="method"
                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-emerald-950 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                >
                    <option value="">Semua metode</option>
                    @foreach($methodLabels as $value => $label)
                        <option value="{{ $value }}" @selected($filters['method'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end gap-2">
                <button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-950/15 transition hover:bg-emerald-800">
                    Terapkan
                </button>
                @if($isFiltered)
                    <a href="{{ route('admin.transactions.index') }}"
                       class="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 outline-none transition hover:bg-slate-50 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                        Reset
                    </a>
                @endif
            </div>
        </div>
    </form>

    <p class="mb-3 text-sm text-slate-600">
        {{ number_format($transactions->total(), 0, ',', '.') }} transaksi
        @if($isFiltered)
            cocok dengan penyaring
        @endif
    </p>

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
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <x-empty-state
                    :title="$isFiltered ? 'Tidak ada transaksi yang cocok' : 'Belum ada transaksi'"
                    :body="$isFiltered ? 'Ubah kata kunci atau longgarkan penyaringnya.' : null"
                />
            </div>
        @endforelse
    </div>

    <div class="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white lg:block">
        <table class="min-w-[760px] w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr><th class="p-3">ID</th><th class="p-3">User</th><th class="p-3">Mitra</th><th class="p-3">Metode</th><th class="p-3">Liter</th><th class="p-3">Total</th><th class="p-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($transactions as $transaction)
                    <tr class="hover:bg-slate-50">
                        <td class="p-3"><a href="{{ route('admin.transactions.show', $transaction) }}" class="font-bold text-emerald-700">{{ $transaction->code }}</a></td>
                        <td class="p-3">{{ $transaction->user->name }}</td>
                        <td class="p-3">{{ $transaction->partner?->name ?? '-' }}</td>
                        <td class="p-3">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar' }}</td>
                        <td class="p-3 tabular-nums">{{ number_format($transaction->actual_liter ?? $transaction->estimated_liter, 2, ',', '.') }} L</td>
                        <td class="p-3 tabular-nums">Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}</td>
                        <td class="p-3"><x-status-badge :status="$transaction->status" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-6 text-center text-sm text-slate-500">
                            {{ $isFiltered ? 'Tidak ada transaksi yang cocok dengan penyaring.' : 'Belum ada transaksi.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.admin>

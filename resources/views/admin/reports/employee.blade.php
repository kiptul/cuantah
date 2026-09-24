<x-layouts.admin title="History Karyawan">
    <div class="mb-5 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <a href="{{ route('admin.reports.index') }}" class="text-sm font-bold text-emerald-700">Kembali ke laporan</a>
            <h1 class="mt-2 text-3xl font-black">History {{ $employee->name }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ $employee->email }} · {{ $employee->phone ?? '-' }}</p>
        </div>
    </div>

    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Transaksi selesai</p><p class="mt-2 text-3xl font-black">{{ $summary['completed_count'] }}</p></div>
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Total liter</p><p class="mt-2 text-3xl font-black">{{ number_format($summary['total_liter'], 2, ',', '.') }} L</p></div>
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Nilai transaksi</p><p class="mt-2 text-3xl font-black">Rp{{ number_format($summary['total_value'], 0, ',', '.') }}</p></div>
    </section>

    <section class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm font-bold uppercase text-emerald-700">Jemput</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-md bg-slate-50 p-3"><p class="text-sm text-slate-500">Transaksi</p><p class="text-2xl font-black">{{ $summary['pickup_count'] }}</p></div>
                <div class="rounded-md bg-slate-50 p-3"><p class="text-sm text-slate-500">Liter</p><p class="text-2xl font-black">{{ number_format($summary['pickup_liter'], 2, ',', '.') }} L</p></div>
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm font-bold uppercase text-emerald-700">Proses di Lokasi Mitra</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-md bg-slate-50 p-3"><p class="text-sm text-slate-500">Transaksi</p><p class="text-2xl font-black">{{ $summary['drop_off_count'] }}</p></div>
                <div class="rounded-md bg-slate-50 p-3"><p class="text-sm text-slate-500">Liter</p><p class="text-2xl font-black">{{ number_format($summary['drop_off_liter'], 2, ',', '.') }} L</p></div>
            </div>
        </div>
    </section>

    <section class="mt-6 overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-4"><h2 class="font-black">Riwayat Pekerjaan</h2></div>
        <table class="min-w-[760px] w-full text-left text-sm">
            <thead class="bg-slate-100 text-xs uppercase text-slate-500">
                <tr><th class="p-3">Tanggal</th><th class="p-3">ID</th><th class="p-3">Jenis</th><th class="p-3">User</th><th class="p-3">Liter</th><th class="p-3">Nilai</th><th class="p-3">Bayar</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($transactions as $transaction)
                    <tr>
                        <td class="p-3">{{ $transaction->updated_at->format('d M Y H:i') }}</td>
                        <td class="p-3">{{ $transaction->code }}</td>
                        <td class="p-3">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Lokasi Mitra' }}</td>
                        <td class="p-3">{{ $transaction->user->name }}</td>
                        <td class="p-3">{{ number_format($transaction->actual_liter, 2, ',', '.') }} L</td>
                        <td class="p-3">Rp{{ number_format($transaction->total_value, 0, ',', '.') }}</td>
                        <td class="p-3">{{ str($transaction->payment_method)->title() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-4"><x-empty-state title="Belum ada pekerjaan selesai" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.admin>

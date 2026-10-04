<x-layouts.admin title="Laporan">
    <x-page-header eyebrow="Laporan" title="Transaksi Selesai" />
    <section class="mb-6 rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
        <h2 class="font-black tracking-tight text-emerald-950">Performa Karyawan</h2>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($employeeReports as $employee)
                <div class="rounded-lg bg-slate-50 p-4">
                    <p class="font-black">{{ $employee->name }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-md bg-white p-3"><p class="text-slate-500">Total selesai</p><p class="text-xl font-black">{{ $employee->completed_pickups_count }}</p></div>
                        <div class="rounded-md bg-white p-3"><p class="text-slate-500">Total liter</p><p class="text-xl font-black">{{ number_format($employee->completed_liter_sum ?? 0, 2, ',', '.') }} L</p></div>
                        <div class="rounded-md bg-white p-3"><p class="text-slate-500">Jemput</p><p class="text-xl font-black">{{ $employee->pickup_count }}</p><p class="text-xs text-slate-500">{{ number_format($employee->pickup_liter ?? 0, 2, ',', '.') }} L</p></div>
                        <div class="rounded-md bg-white p-3"><p class="text-slate-500">Lokasi Mitra</p><p class="text-xl font-black">{{ $employee->drop_off_count }}</p><p class="text-xs text-slate-500">{{ number_format($employee->drop_off_liter ?? 0, 2, ',', '.') }} L</p></div>
                    </div>
                    <a href="{{ route('admin.reports.employee', $employee) }}" class="mt-3 block rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-bold text-slate-700 hover:bg-white">Lihat History</a>
                </div>
            @empty
                <x-empty-state title="Belum ada karyawan" />
            @endforelse
        </div>
    </section>
    {{-- Layar kecil memakai kartu. Tabel laporan punya delapan kolom, jauh
         melampaui lebar layar ponsel walau kontainernya sudah bisa digulir. --}}
    <div class="grid gap-3 lg:hidden">
        @forelse($transactions as $transaction)
            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <span class="font-black text-emerald-700">{{ $transaction->code }}</span>
                    <span class="text-xs font-bold text-slate-500">{{ $transaction->created_at->format('d M Y') }}</span>
                </div>
                <p class="mt-1 text-sm text-slate-600">{{ $transaction->user->name }} · {{ $transaction->partner?->name ?? '-' }}</p>
                <p class="mt-0.5 text-xs text-slate-500">Karyawan: {{ $transaction->pickup?->assignedUser?->name ?? '-' }}</p>
                <dl class="mt-4 grid grid-cols-3 gap-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Volume</dt>
                        <dd class="mt-0.5 text-sm font-bold tabular-nums">{{ number_format($transaction->actual_liter, 2, ',', '.') }} L</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Nilai</dt>
                        <dd class="mt-0.5 text-sm font-bold tabular-nums">Rp{{ number_format($transaction->total_value, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Bayar</dt>
                        <dd class="mt-0.5 text-sm font-bold">{{ str($transaction->payment_method)->title() ?: '-' }}</dd>
                    </div>
                </dl>
            </div>
        @empty
            <div class="rounded-2xl border border-slate-200 bg-white p-4"><x-empty-state title="Belum ada transaksi selesai" /></div>
        @endforelse
    </div>

    <div class="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white lg:block">
        <table class="min-w-[760px] w-full text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="p-3">Tanggal</th><th class="p-3">ID</th><th class="p-3">Mitra</th><th class="p-3">User</th><th class="p-3">Karyawan</th><th class="p-3">Volume</th><th class="p-3">Nilai</th><th class="p-3">Bayar</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($transactions as $transaction)
                    <tr><td class="p-3">{{ $transaction->created_at->format('d M Y') }}</td><td class="p-3">{{ $transaction->code }}</td><td class="p-3">{{ $transaction->partner?->name ?? '-' }}</td><td class="p-3">{{ $transaction->user->name }}</td><td class="p-3">{{ $transaction->pickup?->assignedUser?->name ?? '-' }}</td><td class="p-3">{{ number_format($transaction->actual_liter, 2, ',', '.') }} L</td><td class="p-3">Rp{{ number_format($transaction->total_value, 0, ',', '.') }}</td><td class="p-3">{{ str($transaction->payment_method)->title() }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $transactions->links() }}</div>
</x-layouts.admin>

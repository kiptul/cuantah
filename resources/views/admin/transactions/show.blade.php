<x-layouts.admin title="Detail Transaksi Admin">
    <h1 class="text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">{{ $transaction->code }}</h1>
    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
            <h2 class="font-black tracking-tight text-emerald-950">Informasi</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">User</dt><dd class="font-bold">{{ $transaction->user->name }}</dd></div>
                <div><dt class="text-slate-500">Mitra</dt><dd class="font-bold">{{ $transaction->partner?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-500">Metode</dt><dd class="font-bold">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar Sendiri' }}</dd></div>
                <div><dt class="text-slate-500">Alamat</dt><dd class="font-bold">{{ $transaction->pickup?->address }}</dd></div>
                <div><dt class="text-slate-500">Karyawan</dt><dd class="font-bold">{{ $transaction->pickup?->assignedUser?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-500">Ongkir Jemput</dt><dd class="font-bold">Rp{{ number_format($transaction->pickup_fee, 0, ',', '.') }}</dd></div>
                {{-- Menandai lunas tidak sama dengan uang sudah diterima.
                     Baris ini memperlihatkan mana yang benar-benar dibenarkan penyetor. --}}
                <div>
                    <dt class="text-slate-500">Dikonfirmasi penyetor</dt>
                    <dd class="font-bold">
                        @if($transaction->payment_confirmed_at)
                            <span class="text-emerald-700">Ya, {{ $transaction->payment_confirmed_at->format('d M Y H:i') }}</span>
                        @elseif($transaction->payment_status === 'paid')
                            <span class="text-amber-700">Belum dikonfirmasi</span>
                        @else
                            -
                        @endif
                    </dd>
                </div>
            </dl>
        </section>
        <aside class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
            <div class="mb-5">
                <h2 class="font-black tracking-tight text-emerald-950">Progress status</h2>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <form method="post" action="{{ route('admin.transactions.picked-up', $transaction) }}">@csrf<button class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-800">Dijemput</button></form>
                    <form method="post" action="{{ route('admin.transactions.verification', $transaction) }}">@csrf<button class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-800">Verifikasi</button></form>
                </div>
            </div>
            <h2 class="font-black tracking-tight text-emerald-950">Verifikasi volume & pembayaran</h2>
            <form method="post" action="{{ route('admin.transactions.verify', $transaction) }}" class="mt-4 space-y-4">
                @csrf
                <div><label class="text-sm font-bold">Actual liter</label><input name="actual_liter" type="number" step="0.01" value="{{ old('actual_liter', $transaction->actual_liter) }}" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required></div>
                <div><label class="text-sm font-bold">Metode pembayaran</label><select name="payment_method" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"><option value="cash">Cash</option><option value="transfer">Transfer</option></select></div>
                <div><label class="text-sm font-bold">Status pembayaran</label><select name="payment_status" class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"><option value="paid">Dibayar</option><option value="unpaid">Belum dibayar</option></select></div>
                <textarea name="notes" rows="3" placeholder="Catatan" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('notes', $transaction->notes) }}</textarea>
                <button class="w-full rounded-md bg-emerald-700 px-4 py-3 font-bold text-white">Selesaikan Transaksi</button>
            </form>
            <form method="post" action="{{ route('admin.transactions.reject', $transaction) }}" class="mt-3">@csrf<button class="w-full rounded-md border border-rose-300 px-4 py-2 font-bold text-rose-700">Tolak</button></form>
        </aside>
    </div>
</x-layouts.admin>

<x-layouts.admin title="Detail Transaksi Admin">
    <h1 class="text-3xl font-black">{{ $transaction->code }}</h1>
    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <h2 class="font-black">Informasi</h2>
            <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div><dt class="text-slate-500">User</dt><dd class="font-bold">{{ $transaction->user->name }}</dd></div>
                <div><dt class="text-slate-500">Mitra</dt><dd class="font-bold">{{ $transaction->partner?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-500">Metode</dt><dd class="font-bold">{{ $transaction->method === 'pickup' ? 'Jemput' : 'Antar Sendiri' }}</dd></div>
                <div><dt class="text-slate-500">Alamat</dt><dd class="font-bold">{{ $transaction->pickup?->address }}</dd></div>
                <div><dt class="text-slate-500">Karyawan</dt><dd class="font-bold">{{ $transaction->pickup?->assignedUser?->name ?? '-' }}</dd></div>
                <div><dt class="text-slate-500">Ongkir Jemput</dt><dd class="font-bold">Rp{{ number_format($transaction->pickup_fee, 0, ',', '.') }}</dd></div>
            </dl>
        </section>
        <aside class="rounded-lg border border-slate-200 bg-white p-5">
            <div class="mb-5">
                <h2 class="font-black">Progress status</h2>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <form method="post" action="{{ route('admin.transactions.picked-up', $transaction) }}">@csrf<button class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">Dijemput</button></form>
                    <form method="post" action="{{ route('admin.transactions.verification', $transaction) }}">@csrf<button class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-bold">Verifikasi</button></form>
                </div>
            </div>
            <h2 class="font-black">Verifikasi volume & pembayaran</h2>
            <form method="post" action="{{ route('admin.transactions.verify', $transaction) }}" class="mt-4 space-y-4">
                @csrf
                <div><label class="text-sm font-bold">Actual liter</label><input name="actual_liter" type="number" step="0.01" value="{{ old('actual_liter', $transaction->actual_liter) }}" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" required></div>
                <div><label class="text-sm font-bold">Metode pembayaran</label><select name="payment_method" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><option value="cash">Cash</option><option value="transfer">Transfer</option></select></div>
                <div><label class="text-sm font-bold">Status pembayaran</label><select name="payment_status" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"><option value="paid">Dibayar</option><option value="unpaid">Belum dibayar</option></select></div>
                <textarea name="notes" rows="3" placeholder="Catatan" class="w-full rounded-md border border-slate-300 px-3 py-2">{{ old('notes', $transaction->notes) }}</textarea>
                <button class="w-full rounded-md bg-emerald-700 px-4 py-3 font-bold text-white">Selesaikan Transaksi</button>
            </form>
            <form method="post" action="{{ route('admin.transactions.reject', $transaction) }}" class="mt-3">@csrf<button class="w-full rounded-md border border-rose-300 px-4 py-2 font-bold text-rose-700">Tolak</button></form>
        </aside>
    </div>
</x-layouts.admin>

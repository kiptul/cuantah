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
            {{-- Keberatan penyetor atas takaran. Ditaruh paling menonjol karena
                 menuntut tindakan, bukan sekadar catatan. --}}
            @if($transaction->disputed_at)
                <div class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-4">
                    <p class="text-xs font-black uppercase tracking-[0.12em] text-amber-700">Keberatan penyetor</p>
                    <p class="mt-1.5 text-sm leading-6 text-amber-900">{{ $transaction->dispute_reason }}</p>
                    <p class="mt-2 text-xs text-amber-700">{{ $transaction->disputed_at->format('d M Y H:i') }}</p>

                    @if($transaction->dispute_resolved_at)
                        <div class="mt-3 border-t border-amber-200 pt-3">
                            <p class="text-xs font-black uppercase tracking-[0.12em] text-emerald-700">Sudah ditanggapi</p>
                            <p class="mt-1.5 text-sm leading-6 text-emerald-900">{{ $transaction->dispute_resolution }}</p>
                        </div>
                    @else
                        <form method="post" action="{{ route('admin.transactions.resolve-dispute', $transaction) }}" class="mt-3 border-t border-amber-200 pt-3">
                            @csrf
                            <textarea name="dispute_resolution" rows="2" required minlength="10" maxlength="500"
                                      placeholder="Jelaskan hasil penelusuran dan tindakan yang diambil."
                                      class="w-full rounded-xl border border-amber-300 px-3 py-2.5 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-100">{{ old('dispute_resolution') }}</textarea>
                            @error('dispute_resolution')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                            <button class="mt-3 w-full rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-amber-800">Kirim Tanggapan</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Alasan diwajibkan supaya penolakan tidak berhenti sebagai
                 pesan generik yang tidak bisa ditindaklanjuti penyetor. --}}
            <form method="post" action="{{ route('admin.transactions.reject', $transaction) }}" class="mt-5 border-t border-slate-100 pt-5">
                @csrf
                <label class="text-sm font-bold text-slate-700">Alasan penolakan</label>
                <textarea name="rejection_reason" rows="2" required minlength="5" maxlength="500"
                          placeholder="Contoh: jelantah tercampur air, tidak bisa diolah."
                          class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100">{{ old('rejection_reason') }}</textarea>
                @error('rejection_reason')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                <button class="mt-3 w-full rounded-xl border border-rose-300 px-4 py-2.5 text-sm font-black text-rose-700 transition hover:bg-rose-50">Tolak Transaksi</button>
            </form>
        </aside>
    </div>
</x-layouts.admin>

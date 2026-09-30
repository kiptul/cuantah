<x-layouts.admin title="Detail Transaksi Admin">
    @php
        $final = $transaction->isFinal();
        $estimasi = (float) $transaction->estimated_liter;
    @endphp

    <div class="mb-6">
        <a href="{{ route('admin.transactions.index') }}" class="inline-flex items-center gap-1.5 text-sm font-bold text-emerald-700 transition hover:text-emerald-900">
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="m15 18-6-6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            Semua transaksi
        </a>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <h1 class="font-mono text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">{{ $transaction->code }}</h1>
            <x-status-badge :status="$transaction->status" />
            @if($transaction->payment_status)
                <x-status-badge :status="$transaction->payment_status" />
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1fr_380px]">
        <section class="space-y-6">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Informasi</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-slate-500">Penyetor</dt><dd class="mt-0.5 font-bold text-slate-900">{{ $transaction->user->name }}</dd></div>
                    <div><dt class="text-slate-500">Mitra</dt><dd class="mt-0.5 font-bold text-slate-900">{{ $transaction->partner?->name ?? '-' }}</dd></div>
                    <div><dt class="text-slate-500">Metode</dt><dd class="mt-0.5 font-bold text-slate-900">{{ $transaction->method === 'pickup' ? 'Jemput ke lokasi' : 'Antar sendiri' }}</dd></div>
                    <div><dt class="text-slate-500">Karyawan</dt><dd class="mt-0.5 font-bold text-slate-900">{{ $transaction->pickup?->assignedUser?->name ?? 'Belum ditugaskan' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">Alamat</dt><dd class="mt-0.5 font-bold leading-6 text-slate-900">{{ $transaction->pickup?->address ?? '-' }}</dd></div>
                    @if($transaction->notes)
                        <div class="sm:col-span-2"><dt class="text-slate-500">Catatan</dt><dd class="mt-0.5 leading-6 text-slate-700">{{ $transaction->notes }}</dd></div>
                    @endif
                </dl>
            </div>

            {{-- Estimasi disandingkan dengan hasil takaran. Tanpa pembanding ini
                 angka aktual diisi tanpa acuan, padahal selisihnya justru yang
                 paling sering menjadi pokok keberatan penyetor. --}}
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Volume &amp; nilai</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.1em] text-slate-400">Estimasi penyetor</p>
                        <p class="mt-1 text-xl font-black tabular-nums text-slate-900">{{ number_format($estimasi, 2, ',', '.') }} L</p>
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.1em] text-slate-400">Hasil takaran</p>
                        <p class="mt-1 text-xl font-black tabular-nums text-slate-900">
                            {{ $transaction->actual_liter !== null ? number_format($transaction->actual_liter, 2, ',', '.').' L' : 'Belum ditakar' }}
                        </p>
                        @if($transaction->actual_liter !== null && $estimasi > 0)
                            @php $selisih = (float) $transaction->actual_liter - $estimasi; @endphp
                            <p @class([
                                'mt-1 text-xs font-bold tabular-nums',
                                'text-rose-700' => $selisih < 0,
                                'text-emerald-700' => $selisih > 0,
                                'text-slate-500' => $selisih === 0.0,
                            ])>
                                {{ $selisih > 0 ? '+' : '' }}{{ number_format($selisih, 2, ',', '.') }} L
                                ({{ $selisih > 0 ? '+' : '' }}{{ number_format($selisih / $estimasi * 100, 1, ',', '.') }}%)
                            </p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.1em] text-slate-400">Diterima penyetor</p>
                        <p class="mt-1 text-xl font-black tabular-nums text-emerald-800">Rp{{ number_format($transaction->total_value ?? $transaction->estimated_total, 0, ',', '.') }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            Rp{{ number_format($transaction->price_per_liter, 0, ',', '.') }}/L
                            @if($transaction->pickup_fee > 0)
                                &minus; ongkir Rp{{ number_format($transaction->pickup_fee, 0, ',', '.') }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 border-t border-slate-100 pt-4 text-sm sm:grid-cols-2">
                    <div>
                        <p class="text-slate-500">Cara pembayaran</p>
                        <p class="mt-0.5 font-bold text-slate-900">{{ $transaction->payment_method ? str($transaction->payment_method)->title() : '-' }}</p>
                    </div>
                    {{-- Menandai lunas tidak sama dengan uang sudah diterima.
                         Baris ini memisahkan pengakuan karyawan dari pembenaran penyetor. --}}
                    <div>
                        <p class="text-slate-500">Dikonfirmasi penyetor</p>
                        <p class="mt-0.5 font-bold">
                            @if($transaction->payment_confirmed_at)
                                <span class="text-emerald-700">Ya, {{ $transaction->payment_confirmed_at->format('d M Y H:i') }}</span>
                            @elseif($transaction->payment_status === 'paid')
                                <span class="text-amber-700">Belum dikonfirmasi</span>
                            @else
                                <span class="text-slate-500">-</span>
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            @if($transaction->status === 'rejected' && $transaction->rejection_reason)
                <div class="rounded-2xl bg-rose-50 p-5 ring-1 ring-rose-900/10">
                    <p class="text-xs font-black uppercase tracking-[0.12em] text-rose-700">Alasan penolakan</p>
                    <p class="mt-1.5 text-sm leading-6 text-rose-900">{{ $transaction->rejection_reason }}</p>
                </div>
            @endif
        </section>

        <aside class="space-y-5">
            {{-- Keberatan penyetor ditaruh paling atas karena menuntut tindakan,
                 bukan sekadar catatan yang cukup dibaca. --}}
            @if($transaction->disputed_at)
                <div class="rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-900/15">
                    <p class="text-xs font-black uppercase tracking-[0.12em] text-amber-700">Keberatan penyetor</p>
                    <p class="mt-1.5 text-sm leading-6 text-amber-900">{{ $transaction->dispute_reason }}</p>
                    <p class="mt-2 text-xs text-amber-700">{{ $transaction->disputed_at->format('d M Y H:i') }}</p>

                    @if($transaction->dispute_resolved_at)
                        <div class="mt-3 border-t border-amber-900/15 pt-3">
                            <p class="text-xs font-black uppercase tracking-[0.12em] text-emerald-700">Sudah ditanggapi</p>
                            <p class="mt-1.5 text-sm leading-6 text-emerald-900">{{ $transaction->dispute_resolution }}</p>
                        </div>
                    @else
                        <form method="post" action="{{ route('admin.transactions.resolve-dispute', $transaction) }}" class="mt-3 border-t border-amber-900/15 pt-3">
                            @csrf
                            <textarea name="dispute_resolution" rows="3" required minlength="10" maxlength="500"
                                      placeholder="Jelaskan hasil penelusuran dan tindakan yang diambil."
                                      class="w-full rounded-xl border border-amber-300 px-3 py-2.5 text-sm outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-100">{{ old('dispute_resolution') }}</textarea>
                            @error('dispute_resolution')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                            <button class="mt-3 w-full rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-amber-800">Kirim Tanggapan</button>
                        </form>
                    @endif
                </div>
            @endif

            {{-- Transaksi yang sudah berstatus akhir tidak lagi menampilkan
                 tombol aksi. Sebelumnya semuanya tetap terlihat, sehingga
                 tombol Tolak menggoda ditekan pada transaksi yang sudah
                 selesai dan baru ditahan setelah dikirim. --}}
            @if($final)
                <div class="rounded-2xl bg-white p-5 text-center shadow-sm ring-1 ring-slate-900/5">
                    <p class="text-sm font-bold text-slate-900">Transaksi sudah {{ $transaction->statusLabel() }}</p>
                    <p class="mt-1.5 text-sm leading-6 text-slate-500">Statusnya tidak bisa diubah lagi. Bila ada kekeliruan, catat penyesuaiannya lewat penyaluran atau buat transaksi baru.</p>
                </div>
            @else
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Ubah status</h2>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <form method="post" action="{{ route('admin.transactions.picked-up', $transaction) }}">@csrf<button class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-800">Dijemput</button></form>
                        <form method="post" action="{{ route('admin.transactions.verification', $transaction) }}">@csrf<button class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm font-bold text-slate-700 transition hover:border-emerald-400 hover:text-emerald-800">Verifikasi</button></form>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Selesaikan transaksi</h2>
                    <form method="post" action="{{ route('admin.transactions.verify', $transaction) }}" class="mt-4 space-y-4">
                        @csrf
                        <x-form-field label="Volume aktual (L)" name="actual_liter" type="number"
                                      :value="old('actual_liter', $transaction->actual_liter)" required
                                      :hint="'Estimasi penyetor '.number_format($estimasi, 2, ',', '.').' L.'"
                                      step="0.01" min="0.1" max="500" />
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Cara pembayaran</span>
                            <select name="payment_method" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                <option value="cash" @selected(old('payment_method', $transaction->payment_method) === 'cash')>Tunai</option>
                                <option value="transfer" @selected(old('payment_method', $transaction->payment_method) === 'transfer')>Transfer</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Status pembayaran</span>
                            <select name="payment_status" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                <option value="paid" @selected(old('payment_status', $transaction->payment_status) === 'paid')>Sudah dibayar</option>
                                <option value="unpaid" @selected(old('payment_status', $transaction->payment_status) === 'unpaid')>Belum dibayar</option>
                            </select>
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Catatan</span>
                            <textarea name="notes" rows="3" maxlength="700" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('notes', $transaction->notes) }}</textarea>
                        </label>
                        <button class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">Selesaikan Transaksi</button>
                    </form>
                </div>

                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Tolak transaksi</h2>
                    <form method="post" action="{{ route('admin.transactions.reject', $transaction) }}" class="mt-3"
                          onsubmit="return confirm('Tolak transaksi ini? Statusnya tidak bisa dikembalikan.')">
                        @csrf
                        <textarea name="rejection_reason" rows="3" required minlength="5" maxlength="500"
                                  placeholder="Contoh: jelantah tercampur air, tidak bisa diolah."
                                  class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                        <p class="mt-2 text-xs leading-5 text-slate-500">Alasannya dikirim ke penyetor, jadi tulis yang bisa ia perbaiki.</p>
                        <button class="mt-3 w-full rounded-xl border border-rose-300 px-4 py-2.5 text-sm font-black text-rose-700 transition hover:bg-rose-50">Tolak Transaksi</button>
                    </form>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.admin>

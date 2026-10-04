<x-layouts.admin title="Detail Transaksi Admin">
    @php
        $final = $transaction->isFinal();
        $estimasi = (float) $transaction->estimated_liter;

        /**
         * Kolom kanan hanya berisi keberatan penyetor dan panel aksi, dan
         * keduanya bisa sama-sama tidak ada: transaksi yang sudah selesai
         * tanpa sanggahan menyisakannya kosong sepenuhnya sementara seluruh
         * isi halaman menumpuk di kolom kiri. Halaman seperti itu tidak perlu
         * dibagi dua.
         */
        $adaPanelSamping = $transaction->disputed_at !== null || ! $final;
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

    <div @class([
        'grid items-start gap-6',
        'lg:grid-cols-[1fr_380px]' => $adaPanelSamping,
    ])>
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
                    <div>
                        <p class="text-slate-500">Dibayar</p>
                        <p class="mt-0.5 font-bold">
                            @if($transaction->payment_status === 'paid')
                                <span class="text-emerald-700">{{ $transaction->paid_at?->format('d M Y H:i') ?? 'Ya' }}</span>
                            @elseif($transaction->payment_status === 'unpaid')
                                <span class="text-amber-700">Belum dibayar</span>
                            @else
                                <span class="text-slate-500">-</span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Tanpa ini admin mengunggah tanpa pernah bisa memeriksa
                     berkas mana yang akhirnya tersimpan, dan saat menutup
                     sanggahan ia tidak punya bukti untuk dilihat.

                     Lebarnya mengikuti gambar, bukan kolomnya. Bukti bayar
                     hampir selalu tangkapan layar ponsel yang jangkung, dan
                     w-full membuatnya terbingkai kotak putih selebar kolom
                     dengan gambar setipis pita di tengahnya. --}}
                @if($transaction->hasPaymentProof())
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-slate-500">Bukti pembayaran</p>
                        <a href="{{ route('transactions.payment-proof', $transaction) }}" target="_blank" rel="noopener">
                            <img src="{{ route('transactions.payment-proof', $transaction) }}"
                                 alt="Bukti pembayaran transaksi {{ $transaction->code }}"
                                 class="mx-auto mt-2 max-h-96 w-auto max-w-full rounded-lg border border-slate-200 bg-white">
                        </a>
                    </div>
                @endif

                {{-- Koreksi volume. Karyawan menakar di lapangan dan mengetik
                     angkanya di ponsel, jadi salah ketik tidak terhindarkan.
                     Panelnya ditaruh di kartu ini, tepat di sebelah angka yang
                     keliru, bukan di panel terpisah. --}}
                @if($transaction->status === 'completed')
                    <details class="mt-4">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                            Koreksi volume
                        </summary>
                        <form method="post" action="{{ route('admin.transactions.correct', $transaction) }}" class="mt-3 space-y-3 rounded-xl bg-slate-50 px-4 py-4 ring-1 ring-slate-900/10"
                              data-correct-form data-price="{{ $transaction->price_per_liter }}" data-fee="{{ $transaction->pickup_fee }}">
                            @csrf
                            <label class="block">
                                <span class="text-sm font-bold text-slate-700">Volume aktual (L)</span>
                                <input name="actual_liter" type="number" step="0.01" min="0.1" max="500"
                                       value="{{ old('actual_liter', $transaction->actual_liter) }}" required
                                       class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm tabular-nums outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" data-correct-liter>
                                @error('actual_liter')<span class="mt-1.5 block text-sm font-semibold text-rose-700">{{ $message }}</span>@enderror
                            </label>

                            <label class="block">
                                <span class="text-sm font-bold text-slate-700">Alasan koreksi</span>
                                <textarea name="reason" rows="2" minlength="10" maxlength="500" required
                                          placeholder="Karyawan salah ketik, hasil timbangan sebenarnya 15 L."
                                          class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('reason') }}</textarea>
                                @error('reason')<span class="mt-1.5 block text-sm font-semibold text-rose-700">{{ $message }}</span>@enderror
                            </label>

                            {{-- Pratinjau dihitung di peramban supaya admin melihat
                                 akibatnya sebelum mengirim, bukan sesudahnya. --}}
                            <div class="flex items-baseline justify-between rounded-xl bg-white px-4 py-3 ring-1 ring-slate-900/10">
                                <span class="text-sm font-bold text-slate-700">Total setelah koreksi</span>
                                <span class="text-base font-black tabular-nums text-emerald-900" data-correct-total>&mdash;</span>
                            </div>

                            <button class="w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800">Simpan Koreksi</button>
                        </form>
                    </details>
                @endif

                @if($transaction->corrections->isNotEmpty())
                    <div class="mt-4 rounded-xl border border-slate-200 bg-white px-4 py-3">
                        <p class="text-xs font-black uppercase tracking-[0.12em] text-slate-500">Riwayat koreksi</p>
                        <ul class="mt-2 space-y-3">
                            @foreach($transaction->corrections as $koreksi)
                                <li class="border-t border-slate-100 pt-3 first:border-t-0 first:pt-0">
                                    <p class="text-sm font-bold text-slate-900">
                                        {{ number_format((float) $koreksi->liter_before, 2, ',', '.') }} L
                                        &rarr;
                                        {{ number_format((float) $koreksi->liter_after, 2, ',', '.') }} L
                                        <span class="font-medium text-slate-500">
                                            (Rp{{ number_format($koreksi->value_before, 0, ',', '.') }} &rarr; Rp{{ number_format($koreksi->value_after, 0, ',', '.') }})
                                        </span>
                                    </p>
                                    <p class="mt-1 text-sm leading-6 text-slate-600">{{ $koreksi->reason }}</p>
                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ $koreksi->correctedBy?->name ?? 'Admin terhapus' }} &middot; {{ $koreksi->created_at->format('d M Y H:i') }}
                                    </p>
                                    {{-- Bukti yang berlaku sebelum koreksi. Begitu
                                         kekurangannya dilunasi, kolom bukti di
                                         transaksi menunjuk foto yang baru, dan tanpa
                                         tautan ini bukti pembayaran pertama hilang
                                         dari halaman. --}}
                                    @if($koreksi->hasProofBefore())
                                        <p class="mt-1 text-xs text-slate-500">Bukti bayar sebelum koreksi tersimpan.</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Satu-satunya jalan keluar bagi transaksi selesai yang
                     dibayar menyusul, karena statusnya sudah terkunci. --}}
                @if($transaction->status === 'completed' && $transaction->payment_status === 'unpaid')
                    <form method="post" action="{{ route('admin.transactions.mark-paid', $transaction) }}" enctype="multipart/form-data"
                          class="mt-4 space-y-3 rounded-xl bg-amber-50 px-4 py-3 ring-1 ring-amber-900/10"
                          onsubmit="return confirm('Tandai transaksi ini sudah dibayar ke penyetor?')">
                        @csrf
                        <p class="text-sm leading-6 text-amber-900">Penyetor belum menerima Rp{{ number_format($transaction->total_value, 0, ',', '.') }}.</p>
                        {{-- Dulu ini satu tombol tanpa isian. Bukti transfer
                             menjadikannya form, sebab pembayaran menyusul
                             justru yang paling perlu dibuktikan: tidak ada
                             saksi serah terima seperti pada pembayaran tunai. --}}
                        <label class="block">
                            <span class="text-sm font-bold text-amber-900">Bukti transfer</span>
                            <input name="payment_proof" type="file" accept="image/*" required class="mt-1.5 w-full rounded-xl border border-amber-900/20 bg-white px-3 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-1.5 file:text-sm file:font-bold file:text-amber-900 outline-none transition focus:border-amber-500 focus:ring-4 focus:ring-amber-100">
                            @error('payment_proof')<span class="mt-1.5 block text-sm font-semibold text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <button class="w-full rounded-xl bg-amber-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-amber-800 sm:w-auto">Tandai Lunas</button>
                    </form>
                @endif
            </div>

            @if($transaction->status === 'rejected' && $transaction->rejection_reason)
                <div class="rounded-2xl bg-rose-50 p-5 ring-1 ring-rose-900/10">
                    <p class="text-xs font-black uppercase tracking-[0.12em] text-rose-700">Alasan penolakan</p>
                    <p class="mt-1.5 text-sm leading-6 text-rose-900">{{ $transaction->rejection_reason }}</p>
                </div>
            @endif
        </section>

        @if($adaPanelSamping)
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

            {{-- Panel aksi hanya untuk transaksi yang belum berstatus akhir.
                 Sebelumnya semuanya tetap terlihat, sehingga tombol Tolak
                 menggoda ditekan pada transaksi yang sudah selesai dan baru
                 ditahan setelah dikirim.

                 Dulu di sini ada kotak "Transaksi sudah selesai" yang menyebut
                 statusnya tidak bisa diubah lagi dan menyuruh membuat transaksi
                 baru bila ada kekeliruan. Keterangan itu menjadi keliru sejak
                 koreksi volume tersedia, dan kotaknya sendiri tidak menawarkan
                 tindakan apa pun. --}}
            @if(! $final)
                {{-- Penyelesaian hanya untuk setoran yang diantar sendiri.
                     Transaksi jemput ditakar karyawan di lapangan, di hadapan
                     jelantahnya dan di hadapan penyetornya. Panel ini membuat
                     admin bisa menutupnya dari kantor, yang berarti mengetik
                     volume yang tidak pernah ia timbang dan menandai lunas uang
                     yang tidak pernah ia serahkan.

                     Transaksi jemput yang tersangkut tetap punya jalan keluar:
                     dipindahkan ke karyawan lain lewat halaman Pickup, atau
                     ditolak lewat panel di bawah. --}}
                @if($transaction->method !== \App\Models\Transaction::METHOD_PICKUP)
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5">
                    <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Selesaikan transaksi</h2>
                    @if($transaction->partner && (float) $transaction->partner->capacity_liter > 0)
                        @php
                            $sisaTampung = (float) $transaction->partner->capacity_liter - $transaction->partner->availableLiter();
                        @endphp
                        {{-- Penyelesaian tidak ditahan oleh kapasitas; admin cukup
                             tahu sebelum menekan tombol bahwa stoknya akan meluap. --}}
                        @if($sisaTampung < $estimasi)
                            <p class="mt-3 rounded-xl bg-rose-50 px-3 py-2.5 text-xs font-semibold leading-5 text-rose-800 ring-1 ring-rose-900/10">
                                Sisa daya tampung {{ $transaction->partner->name }} tinggal {{ number_format(max($sisaTampung, 0), 1, ',', '.') }} L. Transaksi tetap bisa diselesaikan, tetapi stok mitra akan melebihi kapasitas.
                            </p>
                        @endif
                    @endif
                    <form method="post" action="{{ route('admin.transactions.verify', $transaction) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
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
                        {{-- accept tanpa capture: bukti transfer berupa
                             tangkapan layar yang sudah ada di galeri. --}}
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Bukti pembayaran</span>
                            <input name="payment_proof" type="file" accept="image/*" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-sm file:font-bold file:text-emerald-800 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                            <span class="mt-1.5 block text-xs text-slate-500">Wajib bila status pembayaran dipilih sudah dibayar.</span>
                            @error('payment_proof')<span class="mt-1.5 block text-sm font-semibold text-rose-700">{{ $message }}</span>@enderror
                        </label>
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Catatan</span>
                            <textarea name="notes" rows="3" maxlength="700" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('notes', $transaction->notes) }}</textarea>
                        </label>
                        <button class="w-full rounded-xl bg-emerald-700 px-4 py-3 text-sm font-black text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">Selesaikan Transaksi</button>
                    </form>
                </div>
                @endif

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
        @endif
    </div>

    <script>
        (() => {
            const form = document.querySelector('[data-correct-form]');

            if (! form) {
                return;
            }

            const liter = form.querySelector('[data-correct-liter]');
            const total = form.querySelector('[data-correct-total]');
            const harga = Number(form.dataset.price || 0);
            const ongkir = Number(form.dataset.fee || 0);
            const rupiah = new Intl.NumberFormat('id-ID');

            const hitung = () => {
                const nilai = parseFloat(liter.value);

                if (! Number.isFinite(nilai) || nilai <= 0) {
                    total.textContent = '\u2014';

                    return;
                }

                total.textContent = 'Rp' + rupiah.format(Math.max(Math.round(nilai * harga) - ongkir, 0));
            };

            liter.addEventListener('input', hitung);
            hitung();
        })();
    </script>
</x-layouts.admin>

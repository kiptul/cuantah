<x-layouts.app title="Detail Transaksi">
    <x-slot:head>
        @vite('resources/js/map.js')
    </x-slot:head>
    <div class="mb-5"><a href="{{ route('transactions.index') }}" class="text-sm font-bold text-emerald-700">Kembali</a><h1 class="mt-2 text-3xl font-black">{{ $transaction->code }}</h1></div>
    <div class="grid gap-6 lg:grid-cols-[1fr_360px]">
        <section class="rounded-lg border border-slate-200 bg-white p-5">
            <div id="map" class="h-[360px] rounded-lg border border-slate-200"></div>
            <div class="mt-5">
                <p class="font-bold">Lokasi {{ $transaction->method === 'pickup' ? 'Rumah Tangga/UMKM' : 'Pengumpulan CUANTAH' }}</p>
                <p class="mt-1 text-slate-600">{{ $transaction->pickup?->address }}</p>
                @if($transaction->pickup)
                    <a target="_blank" class="mt-4 inline-flex rounded-md border border-slate-300 px-4 py-2 text-sm font-bold" href="https://www.google.com/maps?q={{ $transaction->pickup->latitude }},{{ $transaction->pickup->longitude }}">Buka Navigasi</a>
                @endif
            </div>
        </section>
        <aside class="h-fit rounded-lg border border-slate-200 bg-white p-5">
            <x-status-badge :status="$transaction->status" />
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between"><dt>Estimasi liter</dt><dd class="font-bold">{{ number_format($transaction->estimated_liter, 2, ',', '.') }} L</dd></div>
                <div class="flex justify-between"><dt>Mitra</dt><dd class="font-bold">{{ $transaction->partner?->name ?? '-' }}</dd></div>
                <div class="flex justify-between"><dt>Actual liter</dt><dd class="font-bold">{{ $transaction->actual_liter ? number_format($transaction->actual_liter, 2, ',', '.').' L' : '-' }}</dd></div>
                <div class="flex justify-between"><dt>Harga/L</dt><dd class="font-bold">Rp{{ number_format($transaction->price_per_liter, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt>Ongkir jemput</dt><dd class="font-bold">Rp{{ number_format($transaction->pickup_fee, 0, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt>Total</dt><dd class="font-black"><x-transaction-amount :transaction="$transaction" /></dd></div>
                <div class="flex justify-between"><dt>Pembayaran</dt><dd class="font-bold">{{ $transaction->payment_method ? str($transaction->payment_method)->title() : '-' }}</dd></div>
                <div class="flex justify-between"><dt>Status bayar</dt><dd>{{ $transaction->payment_status ? '' : '-' }}@if($transaction->payment_status)<x-status-badge :status="$transaction->payment_status" />@endif</dd></div>
                <div class="flex justify-between"><dt>Karyawan</dt><dd class="font-bold">{{ $transaction->pickup?->assignedUser?->name ?? '-' }}</dd></div>
            </dl>
            {{-- Sanggahan takaran. Verifikasi sebelumnya satu arah sepenuhnya:
                 angka karyawan langsung jadi dasar bayaran tanpa bisa dibantah. --}}
            @if($transaction->disputed_at)
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                    <p class="text-xs font-black uppercase tracking-[0.12em] text-amber-700">Keberatanmu</p>
                    <p class="mt-1.5 text-sm leading-6 text-amber-900">{{ $transaction->dispute_reason }}</p>
                    <p class="mt-2 text-xs text-amber-700">Dikirim {{ $transaction->disputed_at->diffForHumans() }}</p>

                    @if($transaction->dispute_resolved_at)
                        <div class="mt-3 border-t border-amber-200 pt-3">
                            <p class="text-xs font-black uppercase tracking-[0.12em] text-emerald-700">Tanggapan mitra</p>
                            <p class="mt-1.5 text-sm leading-6 text-emerald-900">{{ $transaction->dispute_resolution }}</p>
                        </div>
                    @else
                        <p class="mt-3 border-t border-amber-200 pt-3 text-xs font-semibold text-amber-800">Menunggu tanggapan mitra.</p>
                    @endif
                </div>
            @endif

            @can('dispute', $transaction)
                <form method="post" action="{{ route('transactions.dispute', $transaction) }}" class="mt-5 border-t border-slate-100 pt-5">
                    @csrf
                    <label class="text-sm font-bold text-slate-700">Takarannya tidak sesuai?</label>
                    <p class="mt-1 text-sm leading-6 text-slate-600">Kamu bisa mengajukan keberatan dalam tiga hari setelah transaksi selesai.</p>
                    <textarea name="dispute_reason" rows="3" required minlength="10" maxlength="500"
                              placeholder="Contoh: saya menyetor sekitar 10 liter, tetapi tercatat 6 liter."
                              class="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100">{{ old('dispute_reason') }}</textarea>
                    @error('dispute_reason')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                    <button class="mt-3 w-full rounded-xl border border-amber-300 px-4 py-2.5 text-sm font-black text-amber-800 transition hover:bg-amber-50">Ajukan Keberatan</button>
                </form>
            @endcan

            {{-- Pembatalan hanya selama status masih pending. Sesudah itu karyawan
                 sudah terkunci atau sudah berangkat, sehingga pembatalan sepihak
                 merugikan pihak lain. --}}
            @can('cancel', $transaction)
                <form method="post" action="{{ route('transactions.cancel', $transaction) }}" class="mt-5 border-t border-slate-100 pt-5"
                      onsubmit="return confirm('Batalkan setoran ini? Tindakan ini tidak bisa dibatalkan kembali.')">
                    @csrf
                    <p class="mb-3 text-sm leading-6 text-slate-600">Belum ada karyawan yang mengerjakan setoran ini, jadi kamu masih bisa membatalkannya.</p>
                    <button class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-black text-slate-700 transition hover:border-rose-300 hover:text-rose-700">Batalkan Setoran</button>
                </form>
            @endcan

            {{-- Alasan penolakan ditampilkan kepada penyetor. Tanpa ini ia hanya
                 melihat lencana "rejected" tanpa tahu apa yang perlu diperbaiki. --}}
            @if($transaction->status === 'rejected' && $transaction->rejection_reason)
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                    <p class="text-xs font-black uppercase tracking-[0.12em] text-rose-700">Alasan penolakan</p>
                    <p class="mt-1.5 text-sm leading-6 text-rose-900">{{ $transaction->rejection_reason }}</p>
                </div>
            @endif

            @if($transaction->method === 'drop_off')
                <a href="{{ route('transactions.barcode', $transaction) }}" class="mt-5 block rounded-md bg-emerald-700 px-4 py-3 text-center font-bold text-white">Lihat Barcode</a>
            @endif
        </aside>
    </div>
    @if($transaction->pickup)
        {{-- Dibungkus DOMContentLoaded karena pustaka petanya kini ikut dibundel
             lewat app.js. Berkas modul dieksekusi tertunda, jadi skrip sebaris
             seperti ini berjalan lebih dulu dan belum melihat window.L. --}}
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const lat = {{ $transaction->pickup->latitude }};
                const lng = {{ $transaction->pickup->longitude }};
                const map = L.map('map').setView([lat, lng], 14);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
                L.marker([lat, lng]).addTo(map);
            });
        </script>
    @endif
</x-layouts.app>

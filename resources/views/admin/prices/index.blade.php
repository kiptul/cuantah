<x-layouts.admin title="Harga Jelantah">
    <x-page-header eyebrow="Master Data" title="Harga Jelantah">
        Harga yang aktif dipakai sebagai patokan setiap transaksi baru dan disimpan sebagai snapshot saat transaksi dibuat.
    </x-page-header>

    <form method="post" action="{{ route('admin.prices.store') }}" class="mb-6 rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
        @csrf
        <p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Tambah Harga</p>
        {{-- Tombol sengaja ditaruh paling akhir. Bila ia berada sebelum kolom
             catatan, urutan baca di layar kecil menjadi janggal karena aksi
             muncul sebelum isian terakhir. --}}
        <div class="mt-4 grid gap-3 md:grid-cols-3">
            {{-- Tanggal berlaku sebelumnya tidak punya keterangan sama sekali,
                 sehingga kotaknya hanya berbunyi dd/mm/yyyy tanpa memberi tahu
                 tanggal apa yang diminta. --}}
            <label class="block">
                <span class="text-sm font-bold text-slate-700">Harga per liter (Rp)</span>
                <input name="price_per_liter" type="number" min="0" value="{{ old('price_per_liter') }}" placeholder="4000" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            </label>
            <label class="block">
                <span class="text-sm font-bold text-slate-700">Berlaku mulai</span>
                <input name="effective_date" type="date" value="{{ old('effective_date') }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            </label>
            <label class="flex items-center gap-2 text-sm font-bold text-slate-700"><input name="is_active" value="1" type="checkbox" class="h-5 w-5 shrink-0 cursor-pointer accent-emerald-700 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-emerald-200" checked> Jadikan harga aktif</label>
            <label class="block md:col-span-3">
                <span class="text-sm font-bold text-slate-700">Catatan <span class="font-medium text-slate-500">(opsional)</span></span>
                <textarea name="notes" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('notes') }}</textarea>
            </label>
            <button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800 md:col-span-3">Simpan</button>
        </div>
        <p class="mt-3 text-xs leading-5 text-slate-500">Menyimpan harga aktif akan menonaktifkan harga aktif sebelumnya, sehingga hanya ada satu harga yang berlaku.</p>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        @forelse($prices as $price)
            <div @class([
                'flex items-center justify-between gap-3 p-4',
                'border-b border-slate-100' => ! $loop->last,
                'bg-emerald-50/40' => $price->is_active,
            ])>
                <div class="min-w-0">
                    <p class="text-lg font-black tracking-tight text-emerald-950">Rp{{ number_format($price->price_per_liter, 0, ',', '.') }}<span class="text-sm font-bold text-slate-500">/L</span></p>
                    <p class="mt-0.5 text-sm text-slate-600">Berlaku {{ $price->effective_date->format('d M Y') }}</p>
                </div>
                <x-status-badge :status="$price->is_active ? 'active' : 'inactive'" />
            </div>
        @empty
            <div class="p-4"><x-empty-state title="Belum ada harga" /></div>
        @endforelse
    </div>

    <div class="mt-4">{{ $prices->links() }}</div>
</x-layouts.admin>

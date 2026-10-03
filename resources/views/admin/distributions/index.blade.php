<x-layouts.admin title="Penyaluran">
    <x-page-header eyebrow="Operasional" title="Penyaluran">
        Catatan jelantah yang sudah diteruskan mitra ke tujuan pengolahan.
    </x-page-header>

    <form method="post" action="{{ route('admin.distributions.store') }}" class="mb-6 rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
        @csrf
        <p class="text-xs font-black uppercase tracking-[0.14em] text-emerald-700">Catat Penyaluran</p>
        <div class="mt-4 grid gap-3 md:grid-cols-4">
            {{-- Sisa liter disebut di tiap pilihan supaya admin tahu batasnya
                 sebelum mengisi, bukan setelah pencatatannya ditolak. --}}
            <label class="block">
                <span class="text-sm font-bold text-slate-700">Mitra asal</span>
                <select name="partner_id" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}">{{ $partner->name }} — sisa {{ rtrim(rtrim(number_format($partner->available_liter, 2, ',', '.'), '0'), ',') }} L</option>
                    @endforeach
                </select>
            </label>
            <label class="block">
                <span class="text-sm font-bold text-slate-700">Volume (liter)</span>
                <input name="volume_liter" type="number" step="0.01" min="0" value="{{ old('volume_liter') }}" placeholder="100" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            </label>
            <label class="block">
                <span class="text-sm font-bold text-slate-700">Tujuan penyaluran</span>
                <input name="destination" value="{{ old('destination') }}" placeholder="Pabrik biodiesel" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            </label>
            {{-- Tanggal penyaluran sebelumnya tidak punya keterangan sama sekali. --}}
            <label class="block">
                <span class="text-sm font-bold text-slate-700">Tanggal penyaluran</span>
                <input name="distributed_at" type="date" value="{{ old('distributed_at') }}" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            </label>
            <label class="block md:col-span-4">
                <span class="text-sm font-bold text-slate-700">Catatan <span class="font-medium text-slate-400">(opsional)</span></span>
                <textarea name="notes" rows="2" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">{{ old('notes') }}</textarea>
            </label>
            <button class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800 md:col-span-4">Catat Penyaluran</button>
        </div>
    </form>

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        @forelse($distributions as $distribution)
            <div @class(['p-4', 'border-b border-slate-100' => ! $loop->last])>
                <p class="font-black tracking-tight text-emerald-950">{{ $distribution->destination }}</p>
                <p class="mt-0.5 text-sm text-slate-600">{{ $distribution->partner->name }} · {{ number_format($distribution->volume_liter, 2, ',', '.') }} L · {{ $distribution->distributed_at->format('d M Y') }}</p>
            </div>
        @empty
            <div class="p-4"><x-empty-state title="Belum ada penyaluran" /></div>
        @endforelse
    </div>

    <div class="mt-4">{{ $distributions->links() }}</div>
</x-layouts.admin>

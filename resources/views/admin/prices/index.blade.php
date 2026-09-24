<x-layouts.admin title="Harga Jelantah">
    <h1 class="mb-5 text-2xl font-black sm:text-3xl">Harga Jelantah</h1>
    <form method="post" action="{{ route('admin.prices.store') }}" class="mb-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
        @csrf
        <input name="price_per_liter" type="number" placeholder="Harga/L" class="rounded-md border border-slate-300 px-3 py-2" required>
        <input name="effective_date" type="date" class="rounded-md border border-slate-300 px-3 py-2" required>
        <label class="flex items-center gap-2 text-sm font-bold"><input name="is_active" value="1" type="checkbox" checked> Aktif</label>
        <button class="rounded-md bg-emerald-700 px-4 py-2 font-bold text-white">Simpan</button>
        <textarea name="notes" placeholder="Catatan" class="rounded-md border border-slate-300 px-3 py-2 md:col-span-4"></textarea>
    </form>
    <div class="rounded-lg border border-slate-200 bg-white">
        @foreach($prices as $price)
            <div class="flex items-center justify-between border-b border-slate-100 p-4"><div><p class="font-black">Rp{{ number_format($price->price_per_liter, 0, ',', '.') }}/L</p><p class="text-sm text-slate-600">{{ $price->effective_date->format('d M Y') }}</p></div><x-status-badge :status="$price->is_active ? 'active' : 'inactive'" /></div>
        @endforeach
    </div>
    <div class="mt-4">{{ $prices->links() }}</div>
</x-layouts.admin>

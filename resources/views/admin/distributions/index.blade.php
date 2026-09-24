<x-layouts.admin title="Penyaluran">
    <h1 class="mb-5 text-2xl font-black sm:text-3xl">Penyaluran</h1>
    <form method="post" action="{{ route('admin.distributions.store') }}" class="mb-6 grid gap-3 rounded-lg border border-slate-200 bg-white p-4 md:grid-cols-4">
        @csrf
        <select name="partner_id" class="rounded-md border border-slate-300 px-3 py-2" required>@foreach($partners as $partner)<option value="{{ $partner->id }}">{{ $partner->name }}</option>@endforeach</select>
        <input name="volume_liter" type="number" step="0.01" placeholder="Volume liter" class="rounded-md border border-slate-300 px-3 py-2" required>
        <input name="destination" placeholder="Tujuan" class="rounded-md border border-slate-300 px-3 py-2" required>
        <input name="distributed_at" type="date" class="rounded-md border border-slate-300 px-3 py-2" required>
        <textarea name="notes" placeholder="Catatan" class="rounded-md border border-slate-300 px-3 py-2 md:col-span-4"></textarea>
        <button class="rounded-md bg-emerald-700 px-4 py-2 font-bold text-white md:col-span-4">Catat Penyaluran</button>
    </form>
    <div class="rounded-lg border border-slate-200 bg-white">
        @foreach($distributions as $distribution)
            <div class="border-b border-slate-100 p-4"><p class="font-black">{{ $distribution->destination }}</p><p class="text-sm text-slate-600">{{ $distribution->partner->name }} · {{ number_format($distribution->volume_liter, 2, ',', '.') }} L · {{ $distribution->distributed_at->format('d M Y') }}</p></div>
        @endforeach
    </div>
    <div class="mt-4">{{ $distributions->links() }}</div>
</x-layouts.admin>

@props(['partners', 'selected' => []])

@php
    $selectedIds = collect($selected)->map(fn ($id) => (int) $id)->all();
@endphp

<div>
    <span class="text-sm font-bold text-slate-700">Mitra <span class="font-normal text-slate-500">(wajib untuk admin dan karyawan)</span></span>
    <div class="mt-1.5 grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
        @forelse($partners as $partner)
            <label class="flex items-center gap-2.5 rounded-xl border border-slate-200 px-3 py-2.5 text-sm transition hover:border-emerald-300 has-checked:border-emerald-400 has-checked:bg-emerald-50">
                <input type="checkbox" name="partner_ids[]" value="{{ $partner->id }}"
                       class="rounded border-slate-300 text-emerald-700 focus:ring-emerald-200"
                       @checked(in_array($partner->id, $selectedIds, true))>
                <span class="min-w-0 truncate">{{ $partner->name }}</span>
            </label>
        @empty
            <p class="text-sm text-slate-500">Belum ada mitra yang bisa dipilih.</p>
        @endforelse
    </div>
    @error('partner_ids')
        <span class="mt-1.5 block text-xs font-semibold text-rose-700">{{ $message }}</span>
    @enderror
</div>

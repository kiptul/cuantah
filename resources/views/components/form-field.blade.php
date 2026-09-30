@props(['label', 'name', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false])

{{-- Ruas isian dengan label dan pesan galat yang menempel pada ruasnya
     sendiri. Sebelumnya formulir admin hanya berisi placeholder, sehingga
     begitu diisi tidak ada lagi yang menerangkan isi kolomnya. --}}
<label class="block">
    <span class="text-sm font-bold text-slate-700">{{ $label }}@if($required)<span class="text-rose-600">*</span>@endif</span>
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        @if($type !== 'password') value="{{ $value }}" @endif
        @required($required)
        {{ $attributes->merge(['class' => 'mt-1.5 w-full rounded-xl border px-3 py-2.5 text-sm outline-none transition focus:ring-4 '.($errors->has($name) ? 'border-rose-400 focus:border-rose-400 focus:ring-rose-100' : 'border-slate-300 focus:border-emerald-400 focus:ring-emerald-100')]) }}
    >
    @error($name)
        <span class="mt-1.5 block text-xs font-semibold text-rose-700">{{ $message }}</span>
    @else
        @if($hint)
            <span class="mt-1.5 block text-xs text-slate-500">{{ $hint }}</span>
        @endif
    @enderror
</label>

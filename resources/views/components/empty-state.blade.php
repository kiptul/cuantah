@props(['title', 'body' => null])
<div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center">
    <p class="font-semibold text-slate-900">{{ $title }}</p>
    @if($body)
        <p class="mt-2 text-sm text-slate-500">{{ $body }}</p>
    @endif
</div>

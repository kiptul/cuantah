<x-layouts.app :title="$title ?? 'Admin CUANTAH'">
    <x-slot:head>
        {{ $head ?? '' }}
    </x-slot:head>
    <div class="grid gap-4 lg:grid-cols-[220px_1fr] lg:gap-6">
        <aside class="h-fit overflow-x-auto rounded-lg border border-slate-200 bg-white p-2 lg:p-3">
            <div class="flex min-w-max gap-1 lg:block lg:min-w-0">
            @foreach([
                ['Admin Dashboard', route('admin.dashboard')],
                ['User', route('admin.users.index')],
                ['Transaksi', route('admin.transactions.index')],
                ['Pickup', route('admin.pickups.index')],
                ['Mitra', route('admin.partners.index')],
                ['Harga', route('admin.prices.index')],
                ['Penyaluran', route('admin.distributions.index')],
                ['Laporan', route('admin.reports.index')],
            ] as [$label, $url])
                <a href="{{ $url }}" class="block rounded-md px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-emerald-50 hover:text-emerald-800">{{ $label }}</a>
            @endforeach
            </div>
        </aside>
        <section>{{ $slot }}</section>
    </div>
</x-layouts.app>

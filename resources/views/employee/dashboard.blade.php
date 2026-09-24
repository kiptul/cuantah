<x-layouts.app title="Dashboard Karyawan">
    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-sm font-bold uppercase text-emerald-700">Karyawan CUANTAH</p>
            <h1 class="text-2xl font-black sm:text-3xl">Dashboard Pickup</h1>
        </div>
        <div class="grid gap-2 sm:flex">
            <a href="{{ route('employee.scan') }}" class="rounded-md bg-emerald-700 px-4 py-2 text-center text-sm font-bold text-white">Scan Barcode</a>
            <a href="{{ route('employee.transactions.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-bold text-slate-700">Transaksi Saya</a>
        </div>
    </div>

    <section class="grid gap-4 md:grid-cols-3">
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Pickup aktif</p><p class="mt-2 text-2xl font-black sm:text-3xl">{{ $active_pickups }}</p></div>
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Pickup selesai</p><p class="mt-2 text-2xl font-black sm:text-3xl">{{ $completed_pickups }}</p></div>
        <div class="rounded-lg border border-slate-200 bg-white p-5"><p class="text-sm text-slate-500">Liter selesai</p><p class="mt-2 text-2xl font-black sm:text-3xl">{{ number_format($completed_liter, 2, ',', '.') }} L</p></div>
    </section>

    <section class="mt-4 grid gap-4 md:grid-cols-2">
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm font-bold uppercase text-emerald-700">Jemput</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-md bg-slate-50 p-3"><p class="text-sm text-slate-500">Transaksi</p><p class="text-2xl font-black">{{ $pickup_count }}</p></div>
                <div class="rounded-md bg-slate-50 p-3"><p class="text-sm text-slate-500">Liter</p><p class="text-2xl font-black">{{ number_format($pickup_liter, 2, ',', '.') }} L</p></div>
            </div>
        </div>
        <div class="rounded-lg border border-slate-200 bg-white p-5">
            <p class="text-sm font-bold uppercase text-emerald-700">Proses di Lokasi Mitra</p>
            <div class="mt-3 grid grid-cols-2 gap-3">
                <div class="rounded-md bg-slate-50 p-3"><p class="text-sm text-slate-500">Transaksi</p><p class="text-2xl font-black">{{ $drop_off_count }}</p></div>
                <div class="rounded-md bg-slate-50 p-3"><p class="text-sm text-slate-500">Liter</p><p class="text-2xl font-black">{{ number_format($drop_off_liter, 2, ',', '.') }} L</p></div>
            </div>
        </div>
    </section>

    <section class="mt-6 rounded-lg border border-slate-200 bg-white">
        <div class="border-b border-slate-200 p-4"><h2 class="font-black">Pickup terbaru</h2></div>
        <div class="divide-y divide-slate-100">
            @forelse($latest_pickups as $pickup)
                <a href="{{ route('employee.transactions.show', $pickup->transaction) }}" class="block p-4 hover:bg-slate-50">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div><p class="font-bold">{{ $pickup->transaction->code }}</p><p class="text-sm text-slate-500">{{ $pickup->address }}</p></div>
                        <x-status-badge :status="$pickup->status" />
                    </div>
                </a>
            @empty
                <div class="p-4"><x-empty-state title="Belum ada pickup" /></div>
            @endforelse
        </div>
    </section>
</x-layouts.app>

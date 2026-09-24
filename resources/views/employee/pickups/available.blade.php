<x-layouts.app title="Pickup Tersedia">
    <h1 class="mb-5 text-2xl font-black sm:text-3xl">Pickup Tersedia</h1>
    <div class="grid gap-3">
        @forelse($pickups as $pickup)
            <article class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <p class="font-black">{{ $pickup->transaction->code }}</p>
                        <p class="text-sm text-slate-600">{{ $pickup->partner?->name ?? '-' }} · {{ $pickup->address }}</p>
                    </div>
                    <form method="post" action="{{ route('employee.pickups.claim', $pickup) }}">
                        @csrf
                        <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Ambil Pickup</button>
                    </form>
                </div>
            </article>
        @empty
            <x-empty-state title="Tidak ada pickup tersedia" body="Drop-off yang sudah discan dan belum diambil akan muncul di sini." />
        @endforelse
    </div>
    <div class="mt-4">{{ $pickups->links() }}</div>
</x-layouts.app>

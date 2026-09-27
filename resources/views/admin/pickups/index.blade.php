<x-layouts.admin title="Pickup & Assignment">
    <x-slot:head>
        @vite('resources/js/leaflet.js')
    </x-slot:head>

    <div class="mb-6 flex flex-col justify-between gap-3 sm:flex-row sm:items-end">
        <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Operasional</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">Pickup &amp; Assignment Karyawan</h1>
            <p class="mt-2 text-sm text-slate-600">Pickup jemput muncul otomatis. Drop-off baru muncul setelah barcode discan karyawan.</p>
        </div>
    </div>

    <div class="grid gap-4">
        @forelse($pickups as $pickup)
            <article class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="grid gap-0 lg:grid-cols-[1fr_320px]">
                    <div class="p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-black">{{ $pickup->transaction->code }}</h2>
                                <p class="text-sm text-slate-500">{{ $pickup->transaction->method === 'pickup' ? 'Jemput' : 'Drop-off' }} · {{ $pickup->partner?->name ?? '-' }} · {{ $pickup->transaction->user->name }}</p>
                            </div>
                            <x-status-badge :status="$pickup->status" />
                        </div>

                        <div class="mt-4 grid gap-3 text-sm md:grid-cols-3">
                            <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Tanggal</p><p class="font-bold">{{ $pickup->pickup_date?->format('d M Y') ?? '-' }}</p></div>
                            <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Waktu</p><p class="font-bold">{{ $pickup->pickup_time ?? '-' }}</p></div>
                            <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Estimasi</p><p class="font-bold">{{ number_format($pickup->transaction->estimated_liter, 2, ',', '.') }} L</p></div>
                        </div>

                        <p class="mt-4 text-sm font-semibold text-slate-700">Lokasi</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $pickup->address }}</p>
                        <div id="pickup-map-{{ $pickup->id }}" class="mt-4 h-72 rounded-lg border border-slate-200"></div>
                        <a target="_blank" class="mt-3 inline-flex w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 text-sm font-bold text-slate-700 hover:bg-slate-50" href="https://www.google.com/maps?q={{ $pickup->latitude }},{{ $pickup->longitude }}">Buka Navigasi</a>
                    </div>

                    <aside class="border-t border-slate-200 bg-slate-50 p-5 lg:border-l lg:border-t-0">
                        <p class="text-sm font-bold uppercase text-slate-500">Assignment</p>
                        <p class="mt-2 text-sm text-slate-600">Karyawan saat ini</p>
                        <p class="font-black">{{ $pickup->assignedUser?->name ?? 'Belum di-assign' }}</p>

                        <form method="post" action="{{ route('admin.pickups.assign', $pickup) }}" class="mt-5">
                            @csrf
                            <label class="text-sm font-bold">Pilih karyawan</label>
                            <select name="assigned_user_id" class="mt-2 w-full rounded-md border border-slate-300 bg-white px-3 py-2" required>
                                @foreach($employees as $employee)
                                    @if($employee->partners->contains('id', $pickup->transaction->partner_id))
                                        <option value="{{ $employee->id }}" @selected($pickup->assigned_user_id === $employee->id)>{{ $employee->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button class="mt-3 w-full rounded-md bg-emerald-700 px-4 py-2 font-bold text-white hover:bg-emerald-800">Assign / Override</button>
                        </form>

                        @if($pickup->assigned_user_id)
                            <form method="post" action="{{ route('admin.pickups.unassign', $pickup) }}" class="mt-2">
                                @csrf
                                <button class="w-full rounded-md border border-slate-300 px-4 py-2 font-bold text-slate-700 hover:bg-white">Unassign</button>
                            </form>
                        @endif
                    </aside>
                </div>
            </article>
        @empty
            <x-empty-state title="Belum ada pickup aktif" body="Pickup jemput dan drop-off yang sudah discan akan muncul di sini." />
        @endforelse
    </div>

    <div class="mt-4">{{ $pickups->links() }}</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            @foreach($pickups as $pickup)
                (() => {
                    const lat = {{ $pickup->latitude }};
                    const lng = {{ $pickup->longitude }};
                    const map = L.map('pickup-map-{{ $pickup->id }}', { scrollWheelZoom: false }).setView([lat, lng], 14);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
                    L.marker([lat, lng]).addTo(map).bindPopup('Lokasi setoran');
                    setTimeout(() => map.invalidateSize(), 150);
                })();
            @endforeach
        });
    </script>
</x-layouts.admin>

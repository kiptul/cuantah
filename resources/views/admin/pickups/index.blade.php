<x-layouts.admin title="Pickup & Assignment">
    <x-slot:head>
        @vite('resources/js/map.js')
    </x-slot:head>

    <x-page-header eyebrow="Operasional" title="Pickup & Assignment Karyawan">
        Pickup jemput muncul otomatis. Drop-off baru muncul setelah barcode discan karyawan.
    </x-page-header>

    {{-- Kategori dipisah supaya pickup yang perlu ditugaskan tidak tenggelam
         di antara ratusan pickup yang sudah selesai. Paginasi tetap berlaku
         di dalam tiap kategori. --}}
    <nav class="mb-5 flex gap-2 overflow-x-auto pb-1" aria-label="Kategori pickup">
        @foreach($categories as $key => $item)
            <a href="{{ route('admin.pickups.index', ['kategori' => $key]) }}"
               @if($category === $key) aria-current="page" @endif
               @class([
                   'inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold transition',
                   'bg-emerald-700 text-white shadow-sm shadow-emerald-900/20' => $category === $key,
                   'bg-white text-slate-600 ring-1 ring-slate-900/10 hover:text-emerald-800 hover:ring-emerald-300' => $category !== $key,
               ])>
                {{ $item['label'] }}
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs font-black tabular-nums',
                    'bg-white/20 text-white' => $category === $key,
                    'bg-amber-100 text-amber-800' => $category !== $key && $key === 'menunggu' && $item['count'] > 0,
                    'bg-slate-100 text-slate-500' => $category !== $key && ! ($key === 'menunggu' && $item['count'] > 0),
                ])>{{ $item['count'] }}</span>
            </a>
        @endforeach
    </nav>

    @if($pickupPoints !== [])
        {{-- Satu peta untuk seluruh halaman, bukan satu peta per baris. Selain
             jauh lebih ringan, sebaran titik justru baru terbaca ketika semua
             pickup berada di peta yang sama: itu yang dipakai admin untuk
             menilai karyawan mana yang paling dekat. --}}
        <section class="mb-5 overflow-hidden rounded-2xl border border-emerald-100 bg-white shadow-sm shadow-emerald-950/5">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-100 px-5 py-4">
                <h2 class="font-black tracking-tight text-emerald-950">Sebaran lokasi</h2>
                <p class="text-sm text-slate-600">{{ count($pickupPoints) }} titik di halaman ini</p>
            </div>
            <div id="pickupMap" class="h-64 w-full sm:h-80"></div>
        </section>
    @endif

    <div class="grid gap-3">
        @forelse($pickups as $pickup)
            <article
                id="pickup-{{ $pickup->id }}"
                class="rounded-2xl border border-emerald-100 bg-white p-4 shadow-sm shadow-emerald-950/5 sm:p-5"
            >
                <div class="grid gap-4 lg:grid-cols-[1fr_320px] lg:gap-6">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <h2 class="text-base font-black tracking-tight text-emerald-950">{{ $pickup->transaction->code }}</h2>
                            <x-status-badge :status="$pickup->status" />
                        </div>

                        <p class="mt-1 text-sm text-slate-600">
                            {{ $pickup->transaction->method === 'pickup' ? 'Jemput' : 'Drop-off' }}
                            · {{ $pickup->partner?->name ?? '-' }}
                            · {{ $pickup->transaction->user->name }}
                        </p>

                        <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                            <div class="flex gap-1.5">
                                <dt class="text-slate-500">Tanggal</dt>
                                <dd class="font-bold text-emerald-950">{{ $pickup->pickup_date?->format('d M Y') ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-1.5">
                                <dt class="text-slate-500">Waktu</dt>
                                <dd class="font-bold text-emerald-950">{{ $pickup->pickup_time ?? '-' }}</dd>
                            </div>
                            <div class="flex gap-1.5">
                                <dt class="text-slate-500">Estimasi</dt>
                                <dd class="font-bold text-emerald-950">{{ number_format($pickup->transaction->estimated_liter, 2, ',', '.') }} L</dd>
                            </div>
                        </dl>

                        <p class="mt-3 text-sm text-slate-600">{{ $pickup->address }}</p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                data-focus-pickup="{{ $pickup->id }}"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 outline-none transition hover:bg-emerald-50 hover:text-emerald-800 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                            >
                                Lihat di peta
                            </button>
                            <a
                                target="_blank"
                                href="https://www.google.com/maps?q={{ $pickup->latitude }},{{ $pickup->longitude }}"
                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-bold text-slate-700 outline-none transition hover:bg-emerald-50 hover:text-emerald-800 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                            >
                                Buka Navigasi
                            </a>
                        </div>
                    </div>

                    <aside class="border-t border-emerald-100 pt-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0">
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Assignment</p>
                        <p class="mt-2 text-sm text-slate-500">Karyawan saat ini</p>
                        <p class="font-black text-emerald-950">{{ $pickup->assignedUser?->name ?? 'Belum di-assign' }}</p>

                        @if($pickup->isAssignable())
                            <form method="post" action="{{ route('admin.pickups.assign', $pickup) }}" class="mt-4">
                                @csrf
                                <label for="assign-{{ $pickup->id }}" class="text-sm font-bold text-slate-700">Pilih karyawan</label>
                                <select
                                    id="assign-{{ $pickup->id }}"
                                    name="assigned_user_id"
                                    required
                                    class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-emerald-950 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"
                                >
                                    @foreach($employees as $employee)
                                        @if($employee->partners->contains('id', $pickup->transaction->partner_id))
                                            <option value="{{ $employee->id }}" @selected($pickup->assigned_user_id === $employee->id)>{{ $employee->name }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button class="mt-3 w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-950/15 transition hover:bg-emerald-800">
                                    Assign / Override
                                </button>
                            </form>

                            @if($pickup->assigned_user_id)
                                <form method="post" action="{{ route('admin.pickups.unassign', $pickup) }}" class="mt-2">
                                    @csrf
                                    <button class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-bold text-slate-700 outline-none transition hover:bg-slate-50 focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                        Unassign
                                    </button>
                                </form>
                            @endif
                        @else
                            <p class="mt-4 rounded-xl bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
                                Pickup sudah {{ ['completed' => 'selesai', 'cancelled' => 'dibatalkan'][$pickup->status] ?? 'ditolak' }}, assignment tidak bisa diubah.
                            </p>
                        @endif
                    </aside>
                </div>
            </article>
        @empty
            @if($category === 'menunggu')
                <x-empty-state title="Tidak ada pickup yang menunggu" body="Semua pickup sudah ditugaskan ke karyawan. Pickup jemput baru dan drop-off yang sudah discan akan muncul di sini." />
            @else
                <x-empty-state title="Belum ada pickup {{ strtolower($categories[$category]['label']) }}" body="Pilih kategori lain untuk melihat pickup lainnya." />
            @endif
        @endforelse
    </div>

    <div class="mt-4">{{ $pickups->links() }}</div>

    @if($pickupPoints !== [])
        {{-- type="module" wajib: @vite memuat map.js sebagai modul yang
             ditunda, jadi window.L belum ada bila skrip ini diurai sinkron. --}}
        <script type="module">
            const points = @json($pickupPoints);
            const map = L.map('pickupMap', { scrollWheelZoom: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);

            const markers = new Map();
            for (const point of points) {
                markers.set(point.id, L.marker([point.latitude, point.longitude])
                    .addTo(map)
                    .bindPopup(`<strong>${point.code}</strong><br>${point.name}<br>${point.address}`));
            }

            map.fitBounds(points.map(point => [point.latitude, point.longitude]), { padding: [40, 40], maxZoom: 15 });

            document.querySelectorAll('[data-focus-pickup]').forEach(button => button.addEventListener('click', () => {
                const marker = markers.get(Number(button.dataset.focusPickup));
                if (! marker) {
                    return;
                }

                document.getElementById('pickupMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
                map.setView(marker.getLatLng(), 15);
                marker.openPopup();
            }));
        </script>
    @endif
</x-layouts.admin>

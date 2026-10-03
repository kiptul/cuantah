<x-layouts.admin title="Pickup & Penugasan">
    <x-slot:head>
        @vite('resources/js/map.js')
    </x-slot:head>

    <x-page-header eyebrow="Operasional" title="Pickup & Penugasan Karyawan">
        Pickup jemput muncul otomatis. Drop-off baru muncul setelah QR dipindai karyawan.
    </x-page-header>

    {{-- Kategori sebagai tautan, bukan select dengan tombol Terapkan.
         Halaman ini tidak punya penyaring lain yang perlu dikirim bersamaan,
         jadi dua langkah hanya memperlambat tindakan yang paling sering
         dipakai: melihat mana yang belum dipegang siapa pun. --}}
    @php
        $kategoriChips = ['' => 'Semua'];
        foreach (\App\Models\Pickup::CATEGORIES as $kunci => $meta) {
            $kategoriChips[$kunci] = $meta['label'];
        }
    @endphp
    <nav class="mb-5 flex flex-wrap gap-2" aria-label="Kategori pickup">
        @foreach($kategoriChips as $kunci => $label)
            @php $aktif = $category === ($kunci === '' ? null : $kunci); @endphp
            <a
                href="{{ $kunci === '' ? route('admin.pickups.index') : route('admin.pickups.index', ['kategori' => $kunci]) }}"
                @if($aktif) aria-current="page" @endif
                @class([
                    'inline-flex items-center gap-2 rounded-xl border px-3.5 py-2 text-sm font-bold transition',
                    'border-emerald-700 bg-emerald-700 text-white shadow-sm shadow-emerald-950/15' => $aktif,
                    'border-slate-200 bg-white text-slate-700 hover:bg-emerald-50 hover:text-emerald-800' => ! $aktif,
                ])
            >
                {{ $label }}
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs font-black tabular-nums',
                    'bg-white/20 text-white' => $aktif,
                    'bg-slate-100 text-slate-600' => ! $aktif,
                ])>{{ number_format($categoryCounts[$kunci === '' ? 'semua' : $kunci] ?? 0, 0, ',', '.') }}</span>
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
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Penugasan</p>
                        <p class="mt-2 text-sm text-slate-500">Karyawan saat ini</p>
                        <p class="font-black text-emerald-950">{{ $pickup->assignedUser?->name ?? 'Belum di-assign' }}</p>

                        @if($pickup->isAssignable())
                            {{-- Tombolnya mengikuti keadaan, bukan memuat dua kata
                                 sekaligus. Label "Assign / Override" tidak pernah
                                 berubah, sehingga admin tidak bisa membaca dari
                                 tombolnya apakah pickup ini sudah bertuan, dan
                                 Unassign yang sebenarnya sudah ada terbaca sebagai
                                 tombol kedua yang entah untuk apa. --}}
                            @if($pickup->assigned_user_id)
                                <form method="post" action="{{ route('admin.pickups.unassign', $pickup) }}" class="mt-4">
                                    @csrf
                                    <button class="w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-950/15 transition hover:bg-emerald-800">
                                        Unassign
                                    </button>
                                </form>

                                {{-- Pemindahan tetap mungkin tanpa melepas tugasnya
                                     lebih dulu, tetapi tidak lagi berdiri sejajar
                                     dengan tindakan yang paling sering dipakai. --}}
                                <details class="mt-2">
                                    <summary class="cursor-pointer list-none rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                                        Pindahkan ke karyawan lain
                                    </summary>
                                    <form method="post" action="{{ route('admin.pickups.assign', $pickup) }}" class="mt-3">
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
                                        <button class="mt-3 w-full rounded-xl border border-emerald-700 px-4 py-2.5 text-sm font-bold text-emerald-800 transition hover:bg-emerald-50">
                                            Pindahkan
                                        </button>
                                    </form>
                                </details>
                            @else
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
                                                <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                    <button class="mt-3 w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-950/15 transition hover:bg-emerald-800">
                                        Tugaskan
                                    </button>
                                </form>
                            @endif
                        @else
                            <p class="mt-4 rounded-xl bg-slate-50 px-3 py-2.5 text-sm text-slate-600">
                                Pickup sudah {{ $pickup->status === 'completed' ? 'selesai' : 'ditolak' }}, assignment tidak bisa diubah.
                            </p>
                        @endif
                    </aside>
                </div>
            </article>
        @empty
            {{-- Keadaan kosong menyebut kategori yang sedang dipilih. Tanpa itu
                 daftar yang kosong karena penyaringan terbaca sama persis
                 dengan tidak adanya pickup sama sekali. --}}
            @if($category)
                <x-empty-state
                    title="Tidak ada pickup berkategori {{ \App\Models\Pickup::CATEGORIES[$category]['label'] }}"
                    body="Kategori lain mungkin masih berisi. Pilih Semua untuk melihat seluruhnya."
                />
            @else
                <x-empty-state title="Belum ada pickup aktif" body="Pickup jemput dan drop-off yang sudah discan akan muncul di sini." />
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

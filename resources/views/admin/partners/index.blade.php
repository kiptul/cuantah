<x-layouts.admin title="Mitra">
    <x-slot:head>
        @vite('resources/js/leaflet.js')
    </x-slot:head>

    <div class="mb-6">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Master Data</p>
        <h1 class="mt-1 text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">Mitra</h1>
        <p class="mt-2 text-sm text-slate-600">Lokasi mitra dipakai untuk drop-off, radius pickup, pembatasan akses admin/karyawan, dan laporan.</p>
    </div>

    <form method="post" action="{{ route('admin.partners.store') }}" class="mb-6 rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5 shadow-sm">
        @csrf
        <p class="font-black">Tambah Mitra</p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <input name="name" placeholder="Nama mitra" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            <input name="type" placeholder="Tipe" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            <input name="phone" placeholder="Telepon" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            <input name="capacity_liter" type="number" min="1" placeholder="Kapasitas liter" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
            <button type="button" data-target="new" class="gps-btn rounded-md border border-slate-300 px-4 py-2 font-bold text-slate-700">Gunakan GPS</button>
            <textarea name="address" placeholder="Alamat" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 md:col-span-3" required></textarea>
            <input name="latitude" id="lat-new" type="hidden" value="-6.3055">
            <input name="longitude" id="lng-new" type="hidden" value="107.3053">
        </div>
        <div id="partner-map-new" class="mt-4 h-56 rounded-lg border border-slate-200"></div>
        <div class="mt-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-bold">Aturan ongkir jemput</p>
                <button type="button" data-fee-add="new" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 text-sm font-bold text-slate-700">Tambah Range</button>
            </div>
            <div data-fee-list="new" class="mt-3 grid gap-2"></div>
            <p class="mt-2 text-xs text-slate-500">Kosongkan jarak maksimal untuk range terakhir seperti di atas 10 km. Radius di bawah range pertama otomatis gratis.</p>
        </div>
        <button class="mt-4 rounded-md bg-emerald-700 px-4 py-2 font-bold text-white">Tambah Mitra</button>
    </form>

    <div class="grid gap-4">
        @foreach($partners as $partner)
            <form method="post" action="{{ route('admin.partners.update', $partner) }}" class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5 shadow-sm">
                @csrf
                @method('put')
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start">
                    <div>
                        <p class="font-black">{{ $partner->name }}</p>
                        <p class="text-sm text-slate-600">{{ $partner->type }} · {{ $partner->phone }} · {{ $partner->capacity_liter }} L</p>
                    </div>
                    <x-status-badge :status="$partner->status" />
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <input name="name" value="{{ $partner->name }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    <input name="type" value="{{ $partner->type }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    <input name="phone" value="{{ $partner->phone }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    <input name="capacity_liter" type="number" value="{{ $partner->capacity_liter }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    <select name="status" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100"><option value="active" @selected($partner->status === 'active')>Aktif</option><option value="inactive" @selected($partner->status === 'inactive')>Nonaktif</option></select>
                    <button type="button" data-target="{{ $partner->id }}" class="gps-btn rounded-md border border-slate-300 px-4 py-2 font-bold text-slate-700">Gunakan GPS</button>
                    <textarea name="address" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 md:col-span-3" required>{{ $partner->address }}</textarea>
                    <input name="latitude" id="lat-{{ $partner->id }}" type="hidden" value="{{ $partner->latitude }}">
                    <input name="longitude" id="lng-{{ $partner->id }}" type="hidden" value="{{ $partner->longitude }}">
                </div>
                <div id="partner-map-{{ $partner->id }}" class="mt-4 h-56 rounded-lg border border-slate-200"></div>

                <div class="mt-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-bold">Aturan ongkir jemput</p>
                        <button type="button" data-fee-add="{{ $partner->id }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 text-sm font-bold text-slate-700">Tambah Range</button>
                    </div>
                    <div data-fee-list="{{ $partner->id }}" class="mt-3 grid gap-2">
                        @foreach($partner->deliveryFees as $index => $fee)
                            <div data-fee-row class="grid gap-2 rounded-md bg-slate-50 p-3 md:grid-cols-[1fr_1fr_1fr_auto]">
                                <input name="delivery_fees[{{ $index }}][min_distance_km]" type="number" step="0.01" min="0" value="{{ $fee->min_distance_km }}" placeholder="Min km" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                                <input name="delivery_fees[{{ $index }}][max_distance_km]" type="number" step="0.01" min="0" value="{{ $fee->max_distance_km }}" placeholder="Max km kosong = lebih dari" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                                <input name="delivery_fees[{{ $index }}][fee]" type="number" min="0" value="{{ $fee->fee }}" placeholder="Ongkir" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                                <button type="button" data-fee-remove class="rounded-md border border-rose-300 px-3 py-2 text-sm font-bold text-rose-700">Hapus</button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <button class="mt-4 rounded-md bg-emerald-700 px-4 py-2 font-bold text-white">Simpan Perubahan</button>
            </form>
        @endforeach
    </div>
    <div class="mt-4">{{ $partners->links() }}</div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const configs = [
                { id: 'new', lat: -6.3055, lng: 107.3053 },
                @foreach($partners as $partner)
                    { id: '{{ $partner->id }}', lat: {{ $partner->latitude ?? -6.3055 }}, lng: {{ $partner->longitude ?? 107.3053 }} },
                @endforeach
            ];
            const maps = {};
            const feeIndexes = {};

            function setPosition(id, lat, lng) {
                document.getElementById(`lat-${id}`).value = lat.toFixed(7);
                document.getElementById(`lng-${id}`).value = lng.toFixed(7);
                maps[id].marker.setLatLng([lat, lng]);
                maps[id].map.setView([lat, lng], 15);
            }

            function addFeeRow(id, values = {}) {
                const list = document.querySelector(`[data-fee-list="${id}"]`);
                feeIndexes[id] = feeIndexes[id] ?? list.children.length;
                const index = feeIndexes[id]++;
                const row = document.createElement('div');
                row.dataset.feeRow = '';
                row.className = 'grid gap-2 rounded-md bg-slate-50 p-3 md:grid-cols-[1fr_1fr_1fr_auto]';
                row.innerHTML = `
                    <input name="delivery_fees[${index}][min_distance_km]" type="number" step="0.01" min="0" value="${values.min ?? ''}" placeholder="Min km" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    <input name="delivery_fees[${index}][max_distance_km]" type="number" step="0.01" min="0" value="${values.max ?? ''}" placeholder="Max km kosong = lebih dari" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                    <input name="delivery_fees[${index}][fee]" type="number" min="0" value="${values.fee ?? ''}" placeholder="Ongkir" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    <button type="button" data-fee-remove class="rounded-md border border-rose-300 px-3 py-2 text-sm font-bold text-rose-700">Hapus</button>
                `;
                list.appendChild(row);
            }

            configs.forEach(config => {
                const map = L.map(`partner-map-${config.id}`).setView([config.lat, config.lng], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
                const marker = L.marker([config.lat, config.lng], { draggable: true }).addTo(map);
                maps[config.id] = { map, marker };
                marker.on('dragend', e => setPosition(config.id, e.target.getLatLng().lat, e.target.getLatLng().lng));
                map.on('click', e => setPosition(config.id, e.latlng.lat, e.latlng.lng));
                setTimeout(() => map.invalidateSize(), 150);
            });

            document.querySelectorAll('.gps-btn').forEach(button => button.addEventListener('click', () => {
                navigator.geolocation?.getCurrentPosition(
                    pos => setPosition(button.dataset.target, pos.coords.latitude, pos.coords.longitude),
                    () => alert('GPS tidak tersedia atau izin lokasi ditolak.')
                );
            }));

            document.querySelectorAll('[data-fee-add]').forEach(button => button.addEventListener('click', () => addFeeRow(button.dataset.feeAdd)));
            document.addEventListener('click', event => {
                if (event.target.matches('[data-fee-remove]')) {
                    event.target.closest('[data-fee-row]').remove();
                }
            });
            addFeeRow('new', { min: 3, max: 5, fee: 10000 });
            addFeeRow('new', { min: 5, max: 10, fee: 20000 });
            addFeeRow('new', { min: 10, max: '', fee: 30000 });
        });
    </script>
</x-layouts.admin>

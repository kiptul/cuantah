<x-layouts.app title="Setor Jelantah">
    <x-slot:head>
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    </x-slot:head>

    <div class="mb-5">
        <p class="text-sm font-bold uppercase text-emerald-700">Setor Jelantah</p>
        <h1 class="text-2xl font-black sm:text-3xl">Pilih mitra dan metode setoran</h1>
        <p class="mt-2 text-slate-600">Harga aktif: <strong>Rp{{ number_format($price?->price_per_liter ?? 0, 0, ',', '.') }}/L</strong></p>
    </div>

    @if(!$price)
        <x-empty-state title="Harga aktif belum tersedia" body="Admin perlu mengatur harga jelantah sebelum user membuat transaksi." />
    @elseif($partners->isEmpty())
        <x-empty-state title="Mitra aktif belum tersedia" body="Admin perlu mengatur lokasi mitra aktif sebelum user membuat transaksi." />
    @else
        <form method="post" action="{{ route('deposits.store') }}" id="depositForm" class="grid gap-6 lg:grid-cols-[1fr_360px]">
            @csrf
            <section class="rounded-lg border border-slate-200 bg-white p-4">
                <div class="mb-5">
                    <label class="text-sm font-bold">Mitra tujuan</label>
                    <select name="partner_id" id="partner_id" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" required>
                        @foreach($partners as $partner)
                            <option value="{{ $partner['id'] }}">{{ $partner['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <button type="button" data-method="drop_off" class="method-btn rounded-lg border-2 border-emerald-700 bg-emerald-50 p-4 text-left">
                        <span class="block font-black">Antar Sendiri</span>
                        <span class="text-sm text-slate-600">Antar ke lokasi mitra yang dipilih.</span>
                    </button>
                    <button type="button" data-method="pickup" class="method-btn rounded-lg border-2 border-slate-200 p-4 text-left">
                        <span class="block font-black">Jemput</span>
                        <span class="text-sm text-slate-600">Tentukan lokasi rumah/UMKM.</span>
                    </button>
                </div>
                <input type="hidden" name="method" id="method" value="drop_off">
                <input type="hidden" name="latitude" id="latitude" value="{{ $partners[0]['latitude'] }}">
                <input type="hidden" name="longitude" id="longitude" value="{{ $partners[0]['longitude'] }}">

                <div class="mt-5">
                    <div class="mb-3 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <p id="mapHelp" class="text-sm font-semibold text-slate-700">Lokasi mitra terpilih untuk drop-off.</p>
                        <button type="button" id="useGps" class="hidden rounded-md border border-slate-300 px-3 py-2 text-sm font-bold text-slate-700">Gunakan GPS Saya</button>
                    </div>
                    <div id="map" class="h-[420px] rounded-lg border border-slate-200"></div>
                    <div id="feeLegend" class="mt-3 grid gap-2 text-sm sm:grid-cols-2"></div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="text-sm font-bold">Alamat / info lokasi</label>
                        <textarea name="address" id="address" rows="3" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" required>{{ $partners[0]['address'] }}</textarea>
                    </div>
                    <div>
                        <label class="text-sm font-bold">Estimasi volume (L)</label>
                        <input name="estimated_liter" id="estimated_liter" type="number" min="0.5" step="0.1" value="1" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2" required>
                    </div>
                    <div class="pickup-only hidden">
                        <label class="text-sm font-bold">Tanggal pickup</label>
                        <input name="pickup_date" type="date" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2">
                    </div>
                    <div class="pickup-only hidden">
                        <label class="text-sm font-bold">Waktu pickup</label>
                        <input name="pickup_time" type="time" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2">
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-sm font-bold">Catatan</label>
                        <textarea name="notes" rows="2" class="mt-2 w-full rounded-md border border-slate-300 px-3 py-2"></textarea>
                    </div>
                </div>
            </section>

            <aside class="h-fit rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="font-black">Review</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt>Mitra</dt><dd id="partnerLabel" class="text-right font-bold">{{ $partners[0]['name'] }}</dd></div>
                    <div class="flex justify-between"><dt>Metode</dt><dd id="methodLabel" class="font-bold">Antar Sendiri</dd></div>
                    <div class="flex justify-between"><dt>Harga/L</dt><dd class="font-bold">Rp{{ number_format($price->price_per_liter, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt>Bruto</dt><dd id="grossTotal" class="font-bold">Rp{{ number_format($price->price_per_liter, 0, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt>Ongkir jemput</dt><dd id="pickupFee" class="font-bold text-rose-700">Rp0</dd></div>
                    <div class="flex justify-between border-t border-slate-200 pt-3"><dt>Estimasi CUAN</dt><dd id="estimatedTotal" class="font-black text-emerald-800">Rp{{ number_format($price->price_per_liter, 0, ',', '.') }}</dd></div>
                </dl>
                <p id="distanceInfo" class="mt-3 text-sm text-slate-500"></p>
                {{-- Ongkir dipotong dari penerimaan. Bila volumenya terlalu kecil,
                     user perlu tahu sebelum menekan tombol, bukan setelah ditolak. --}}
                <p id="feeWarning" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm font-semibold leading-5 text-amber-900"></p>
                <a id="navLink" href="https://www.google.com/maps?q={{ $partners[0]['latitude'] }},{{ $partners[0]['longitude'] }}" target="_blank" class="mt-5 block rounded-md border border-slate-300 px-4 py-2 text-center text-sm font-bold text-slate-700">Buka Navigasi</a>
                <button id="submitDeposit" class="mt-3 w-full rounded-md bg-emerald-700 px-4 py-3 font-bold text-white transition disabled:cursor-not-allowed disabled:bg-slate-300">Konfirmasi Setor</button>
            </aside>
        </form>
    @endif

    @if($price && $partners->isNotEmpty())
        <script>
            const price = {{ (int) ($price?->price_per_liter ?? 0) }};
            const partners = @json($partners->values());
            const colors = ['#059669', '#2563eb', '#f59e0b', '#dc2626', '#7c3aed'];
            const partnerInput = document.getElementById('partner_id');
            const methodInput = document.getElementById('method');
            const addressInput = document.getElementById('address');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const navLink = document.getElementById('navLink');
            const useGpsButton = document.getElementById('useGps');
            const mapHelp = document.getElementById('mapHelp');
            const feeLegend = document.getElementById('feeLegend');
            const map = L.map('map').setView([partners[0].latitude, partners[0].longitude], 13);
            const circles = [];

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(map);
            const userMarker = L.marker([partners[0].latitude, partners[0].longitude], { draggable: false }).addTo(map);
            partners.forEach(partner => L.marker([partner.latitude, partner.longitude]).addTo(map).bindPopup(`<strong>${partner.name}</strong><br>${partner.address}`));

            function selectedPartner() {
                return partners.find(partner => String(partner.id) === partnerInput.value) || partners[0];
            }

            function rupiah(value) {
                return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);
            }

            function distanceKm(originLat, originLng, destinationLat, destinationLng) {
                const radius = 6371;
                const latDelta = (destinationLat - originLat) * Math.PI / 180;
                const lngDelta = (destinationLng - originLng) * Math.PI / 180;
                const lat1 = originLat * Math.PI / 180;
                const lat2 = destinationLat * Math.PI / 180;
                const a = Math.sin(latDelta / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(lngDelta / 2) ** 2;

                return radius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            }

            function feeForDistance(partner, distance) {
                const rule = partner.delivery_fees.find(fee => distance >= fee.min_distance_km && (fee.max_distance_km === null || distance <= fee.max_distance_km));

                return rule ? rule.fee : 0;
            }

            function updateTotal() {
                const partner = selectedPartner();
                const liter = parseFloat(document.getElementById('estimated_liter').value || 0);
                const gross = liter * price;
                const isPickup = methodInput.value === 'pickup';
                const distance = isPickup ? distanceKm(partner.latitude, partner.longitude, parseFloat(latInput.value), parseFloat(lngInput.value)) : 0;
                const fee = isPickup ? feeForDistance(partner, distance) : 0;

                document.getElementById('grossTotal').textContent = rupiah(gross);
                document.getElementById('pickupFee').textContent = rupiah(fee);
                document.getElementById('estimatedTotal').textContent = rupiah(Math.max(gross - fee, 0));
                document.getElementById('distanceInfo').textContent = isPickup ? `Jarak ke mitra sekitar ${distance.toFixed(2)} km.` : '';

                // Setoran jemput yang nilainya tidak melampaui ongkir akan ditolak
                // server. Tahan di sini supaya user tidak kehilangan waktu.
                const tidakMenghasilkan = isPickup && liter > 0 && gross <= fee;
                const warning = document.getElementById('feeWarning');
                const submit = document.getElementById('submitDeposit');
                const minimal = Math.ceil((fee + 1) / price * 2) / 2;

                warning.classList.toggle('hidden', !tidakMenghasilkan);
                submit.disabled = tidakMenghasilkan;
                if (tidakMenghasilkan) {
                    warning.textContent = `Ongkir jemput ${rupiah(fee)} lebih besar daripada nilai setoranmu, jadi kamu tidak menerima apa pun. Naikkan volume ke minimal ${minimal} liter, atau pilih Antar Sendiri supaya tanpa ongkir.`;
                }
            }

            function clearCircles() {
                while (circles.length) {
                    map.removeLayer(circles.pop());
                }
            }

            function renderCircles() {
                clearCircles();
                feeLegend.innerHTML = '';

                if (methodInput.value !== 'pickup') {
                    return;
                }

                const partner = selectedPartner();
                const sortedFees = [...partner.delivery_fees].sort((a, b) => a.min_distance_km - b.min_distance_km);

                if (sortedFees.length === 0) {
                    feeLegend.innerHTML = '<p class="text-slate-500">Belum ada aturan ongkir. Pickup dihitung gratis.</p>';
                    return;
                }

                const firstMin = sortedFees[0].min_distance_km;
                feeLegend.insertAdjacentHTML('beforeend', `<div class="rounded-md bg-slate-50 px-3 py-2">Di bawah ${firstMin} km: <strong>Gratis</strong></div>`);

                sortedFees.forEach((fee, index) => {
                    const color = colors[index % colors.length];
                    const radiusKm = fee.max_distance_km ?? fee.min_distance_km;
                    const label = fee.max_distance_km === null
                        ? `Di atas ${fee.min_distance_km} km`
                        : `${fee.min_distance_km}-${fee.max_distance_km} km`;
                    circles.push(L.circle([partner.latitude, partner.longitude], {
                        radius: radiusKm * 1000,
                        color,
                        fillColor: color,
                        fillOpacity: fee.max_distance_km === null ? 0.04 : 0.08,
                        dashArray: fee.max_distance_km === null ? '8 8' : null,
                    }).addTo(map));
                    feeLegend.insertAdjacentHTML('beforeend', `<div class="rounded-md bg-slate-50 px-3 py-2"><span style="color:${color}">●</span> ${label}: <strong>${rupiah(fee.fee)}</strong></div>`);
                });
            }

            function setPosition(lat, lng, address = '', updateAddress = true) {
                latInput.value = Number(lat).toFixed(7);
                lngInput.value = Number(lng).toFixed(7);
                if (updateAddress) {
                    addressInput.value = address;
                }
                userMarker.setLatLng([lat, lng]);
                map.setView([lat, lng], 15);
                navLink.href = `https://www.google.com/maps?q=${lat},${lng}`;
                updateTotal();
            }

            function setPartner() {
                const partner = selectedPartner();
                document.getElementById('partnerLabel').textContent = partner.name;
                renderCircles();

                if (methodInput.value === 'drop_off') {
                    setPosition(partner.latitude, partner.longitude, partner.address);
                } else {
                    map.setView([partner.latitude, partner.longitude], 13);
                    updateTotal();
                }
            }

            document.querySelectorAll('.method-btn').forEach(button => button.addEventListener('click', () => {
                const method = button.dataset.method;
                const partner = selectedPartner();
                methodInput.value = method;
                document.querySelectorAll('.method-btn').forEach(item => item.className = 'method-btn rounded-lg border-2 border-slate-200 p-4 text-left');
                button.className = 'method-btn rounded-lg border-2 border-emerald-700 bg-emerald-50 p-4 text-left';
                document.getElementById('methodLabel').textContent = method === 'pickup' ? 'Jemput' : 'Antar Sendiri';
                document.querySelectorAll('.pickup-only').forEach(el => el.classList.toggle('hidden', method !== 'pickup'));
                useGpsButton.classList.toggle('hidden', method !== 'pickup');
                mapHelp.textContent = method === 'pickup'
                    ? 'Pilih titik rumah/UMKM pada map atau gunakan GPS perangkat.'
                    : 'Lokasi mitra terpilih untuk drop-off.';
                userMarker.dragging[method === 'pickup' ? 'enable' : 'disable']();
                renderCircles();

                if (method === 'pickup') {
                    addressInput.value = '';
                    setPosition(partner.latitude, partner.longitude, '', false);
                } else {
                    setPosition(partner.latitude, partner.longitude, partner.address);
                }
            }));

            useGpsButton.addEventListener('click', () => {
                navigator.geolocation?.getCurrentPosition(
                    pos => setPosition(pos.coords.latitude, pos.coords.longitude, '', false),
                    () => alert('GPS tidak tersedia atau izin lokasi ditolak.')
                );
            });

            partnerInput.addEventListener('change', setPartner);
            userMarker.on('dragend', e => setPosition(e.target.getLatLng().lat, e.target.getLatLng().lng, addressInput.value));
            map.on('click', e => {
                if (methodInput.value === 'pickup') {
                    setPosition(e.latlng.lat, e.latlng.lng, '', false);
                }
            });
            document.getElementById('estimated_liter').addEventListener('input', updateTotal);
            setPartner();
        </script>
    @endif
</x-layouts.app>

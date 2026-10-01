<x-layouts.app title="Scan Drop-off">
    <x-slot:head>
        @vite('resources/js/scanner.js')
    </x-slot:head>
    <section class="mx-auto max-w-xl">
        <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 4H5a1 1 0 0 0-1 1v2M17 4h2a1 1 0 0 1 1 1v2M7 20H5a1 1 0 0 1-1-1v-2M17 20h2a1 1 0 0 0 1-1v-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-3xl font-black tracking-tight">Pindai Barcode</h1>
                        <p class="text-sm font-semibold text-slate-500 sm:text-base">Posisikan barcode di dalam bingkai</p>
                    </div>
                </div>
                <a href="{{ route('employee.dashboard') }}" class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 hover:bg-slate-200" aria-label="Tutup scanner">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </a>
            </div>

            <div class="relative mt-6 overflow-hidden rounded-[1.5rem] bg-slate-900">
                <video id="scannerVideo" class="aspect-[4/5] w-full object-cover sm:aspect-[4/3]" playsinline muted></video>
                <div class="pointer-events-none absolute inset-0 bg-slate-950/20"></div>
                <div class="pointer-events-none absolute left-1/2 top-1/2 h-32 w-[82%] -translate-x-1/2 -translate-y-1/2 rounded-2xl border-4 border-emerald-400/90 shadow-[0_0_0_999px_rgba(15,23,42,.28)]">
                    <div class="absolute left-8 right-8 top-1/2 h-0.5 -translate-y-1/2 rounded-full bg-white/90"></div>
                </div>
            </div>

            <p id="scannerStatus" class="mt-5 text-center text-sm font-semibold text-slate-500">Memulai kamera...</p>
            <button type="button" id="startScanner" class="mt-4 w-full rounded-md border border-slate-300 px-4 py-3 font-bold text-slate-700">Mulai Kamera</button>

            <form method="post" action="{{ route('employee.scan.store') }}" id="scanForm" class="mt-5 rounded-lg bg-slate-50 p-4">
                @csrf
                <label class="text-sm font-bold">Kode transaksi</label>
                <input name="code" id="codeInput" autocomplete="off" placeholder="CNT-260919-ABC123" class="mt-2 w-full rounded-md border border-slate-300 px-4 py-3 text-base font-bold tracking-wide" required>
                <button class="mt-3 w-full rounded-md bg-emerald-700 px-4 py-3 font-bold text-white">Tampilkan & Assign ke Saya</button>
            </form>
        </div>
    </section>

    {{-- Modul: dieksekusi setelah scanner.js yang dimuat @vite di atas,
         sehingga window.CuantahScanner dijamin sudah tersedia saat auto-start. --}}
    <script type="module">
        const video = document.getElementById('scannerVideo');
        const statusText = document.getElementById('scannerStatus');
        const startButton = document.getElementById('startScanner');
        const codeInput = document.getElementById('codeInput');
        const form = document.getElementById('scanForm');
        const submitButton = form.querySelector('button[type=submit], button:not([type])');
        let controls = null;
        let scanning = false;

        async function startScanner() {
            if (!navigator.mediaDevices?.getUserMedia) {
                statusText.textContent = 'Browser tidak mengizinkan akses kamera. Masukkan kode secara manual.';
                codeInput.focus();
                return;
            }

            if (!window.CuantahScanner?.BrowserMultiFormatReader) {
                statusText.textContent = 'Scanner belum selesai dimuat. Coba muat ulang halaman.';
                return;
            }

            controls?.stop();
            const reader = new window.CuantahScanner.BrowserMultiFormatReader();
            scanning = true;
            statusText.textContent = 'Dekatkan barcode ke bingkai sampai garisnya terlihat tajam.';

            controls = await reader.decodeFromConstraints({
                video: {
                    facingMode: { ideal: 'environment' },
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                },
                audio: false,
            }, video, (result) => {
                if (result && scanning) {
                    scanning = false;
                    const code = result.getText();
                    codeInput.value = code;
                    controls?.stop();

                    /**
                     * Tidak langsung dikirim. Pemindaian menugaskan transaksi
                     * itu kepada karyawan yang memindainya, dan barcode yang
                     * salah terbaca akan menugaskan transaksi orang lain tanpa
                     * sempat dilihat. Kodenya ditampilkan lebih dulu agar bisa
                     * dicocokkan dengan yang tertera di layar penyetor.
                     */
                    statusText.textContent = `Barcode terbaca: ${code}. Cocokkan dengan kode di layar penyetor, lalu tekan tombol di bawah.`;
                    submitButton?.focus();
                }
            });
        }

        startButton.addEventListener('click', () => {
            startScanner().catch(() => {
                statusText.textContent = 'Kamera tidak bisa dibuka. Pastikan izin kamera aktif atau input kode manual.';
                codeInput.focus();
            });
        });

        startScanner().catch(() => {
            statusText.textContent = 'Tekan Mulai Kamera atau masukkan kode secara manual.';
        });
    </script>
</x-layouts.app>

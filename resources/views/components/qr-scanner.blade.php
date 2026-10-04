@props([
    'action',
    'close',
    'submitLabel',
    'title' => 'Pindai QR',
    'subtitle' => 'Posisikan QR di dalam bingkai',
    'confirmHint' => 'Cocokkan dengan kode di layar penyetor, lalu tekan tombol di bawah.',
])

{{-- Pemindai QR dipakai dua peran: karyawan menerima drop-off di lapangan,
     admin menanganinya di lokasi mitra ketika seluruh karyawan sedang
     menjemput. Markupnya sama, termasuk seluruh penanganan kamera.

     Disatukan di sini sejak awal, bukan disalin lalu disatukan nanti. Kebiasaan
     menggambar ulang sudah membuat lambang CUANTAH menyimpang di lima tempat
     dan pemicu laci duduk di dua sisi berbeda; tidak ada alasan mengulanginya
     untuk blok sepanjang ini.

     Yang berbeda antar peran hanya alamat kirimnya, tujuan tombol tutup,
     tulisan tombolnya, dan kalimat setelah QR terbaca, jadi keempatnya
     menjadi prop. --}}
<section class="mx-auto max-w-xl">
    <div class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M7 4H5a1 1 0 0 0-1 1v2M17 4h2a1 1 0 0 1 1 1v2M7 20H5a1 1 0 0 1-1-1v-2M17 20h2a1 1 0 0 0 1-1v-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h1 class="text-3xl font-black tracking-tight">{{ $title }}</h1>
                    <p class="text-sm font-semibold text-slate-500 sm:text-base">{{ $subtitle }}</p>
                </div>
            </div>
            <a href="{{ $close }}" class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 hover:bg-slate-200" aria-label="Tutup pemindai">
                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </a>
        </div>

        <div class="relative mt-6 overflow-hidden rounded-[1.5rem] bg-slate-900">
            <video id="scannerVideo" class="aspect-[4/5] w-full object-cover sm:aspect-[4/3]" playsinline muted></video>
            <div class="pointer-events-none absolute inset-0 bg-slate-950/20"></div>
            <div class="pointer-events-none absolute left-1/2 top-1/2 aspect-square w-[72%] -translate-x-1/2 -translate-y-1/2 rounded-2xl border-4 border-emerald-400/90 shadow-[0_0_0_999px_rgba(15,23,42,.28)]"></div>
        </div>

        <p id="scannerStatus" class="mt-5 text-center text-sm font-semibold text-slate-500">Memulai kamera...</p>
        <button type="button" id="startScanner" class="mt-4 min-h-11 w-full rounded-md border border-slate-300 px-4 py-3 font-bold text-slate-700">Mulai Kamera</button>

        {{-- Kolom kode selalu tersedia, bukan hanya ketika kamera gagal.
             Izin kamera bisa ditolak permanen, dan QR yang buram atau layar
             yang silau membuat pemindaian gagal tanpa ada yang rusak. --}}
        <form method="post" action="{{ $action }}" id="scanForm" class="mt-5 rounded-lg bg-slate-50 p-4">
            @csrf
            <label class="text-sm font-bold" for="codeInput">Kode transaksi</label>
            <input name="code" id="codeInput" value="{{ old('code') }}" autocomplete="off" placeholder="CNT-260919-ABC123" class="mt-2 min-h-11 w-full rounded-md border border-slate-300 px-4 py-3 text-base font-bold tracking-wide" required>
            @error('code')<p class="mt-2 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
            <button class="mt-3 min-h-11 w-full rounded-md bg-emerald-700 px-4 py-3 font-bold text-white">{{ $submitLabel }}</button>
        </form>
    </div>
</section>

{{-- Modul: dieksekusi setelah scanner.js yang dimuat @vite oleh halamannya,
     sehingga window.CuantahScanner dijamin sudah tersedia saat auto-start. --}}
<script type="module">
    const video = document.getElementById('scannerVideo');
    const statusText = document.getElementById('scannerStatus');
    const startButton = document.getElementById('startScanner');
    const codeInput = document.getElementById('codeInput');
    const form = document.getElementById('scanForm');
    const submitButton = form.querySelector('button[type=submit], button:not([type])');
    const confirmHint = @json($confirmHint);
    let controls = null;
    let scanning = false;

    async function startScanner() {
        if (!navigator.mediaDevices?.getUserMedia) {
            statusText.textContent = 'Browser tidak mengizinkan akses kamera. Masukkan kode secara manual.';
            codeInput.focus();
            return;
        }

        if (!window.CuantahScanner?.BrowserQRCodeReader) {
            statusText.textContent = 'Pemindai belum selesai dimuat. Coba muat ulang halaman.';
            return;
        }

        controls?.stop();
        const reader = new window.CuantahScanner.BrowserQRCodeReader();
        scanning = true;
        statusText.textContent = 'Dekatkan QR ke bingkai sampai seluruh kotaknya masuk.';

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
                 * Tidak langsung dikirim. QR yang salah terbaca akan membawa
                 * transaksi orang lain tanpa sempat dilihat, dan pada sisi
                 * karyawan pemindaian sekalian menugaskan transaksinya.
                 * Kodenya ditampilkan lebih dulu agar bisa dicocokkan dengan
                 * yang tertera di layar penyetor.
                 */
                statusText.textContent = `QR terbaca: ${code}. ${confirmHint}`;
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

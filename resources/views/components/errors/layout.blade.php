@props(['kode', 'judul', 'pesan'])

{{-- Halaman galat dalam bahasa yang sama dengan sisa aplikasi. Bawaan
     Laravel berbahasa Inggris dan tidak menawarkan jalan keluar, sehingga
     pengunjung yang tersesat berhenti di situ. --}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $kode }} — {{ $judul }} | CUANTAH</title>
    {{-- Gaya ditulis di tempat, bukan lewat @vite. Halaman inilah yang dipakai
         ketika ada yang salah; menggantungkannya pada manifest build membuat
         sebuah 404 berubah menjadi 500 begitu asetnya belum dibangun. --}}
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
            background: #f8fafc;
            color: #0f172a;
            font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        main { width: 100%; max-width: 28rem; text-align: center; }
        .kode { margin: 0; font-size: clamp(4rem, 18vw, 6rem); font-weight: 900; line-height: 1; letter-spacing: -0.03em; color: #065f46; font-variant-numeric: tabular-nums; }
        h1 { margin: 1.25rem 0 0; font-size: 1.375rem; font-weight: 800; letter-spacing: -0.01em; }
        .pesan { margin: 0.75rem 0 0; font-size: 0.9375rem; line-height: 1.6; color: #475569; }
        .aksi { margin-top: 2rem; display: flex; flex-direction: column; gap: 0.5rem; }
        @media (min-width: 640px) { .aksi { flex-direction: row; justify-content: center; } }
        a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.25rem;
            border-radius: 0.75rem;
            font-size: 0.875rem;
            font-weight: 700;
            text-decoration: none;
            transition: background-color .15s, box-shadow .15s;
        }
        .utama { background: #047857; color: #fff; }
        .utama:hover { background: #065f46; }
        .kedua { background: #fff; color: #334155; box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .12); }
        .kedua:hover { box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .25); }
    </style>
</head>
<body>
    <main>
        <p class="kode">{{ $kode }}</p>
        <h1>{{ $judul }}</h1>
        <p class="pesan">{{ $pesan }}</p>

        <div class="aksi">
            <a class="utama" href="{{ url('/') }}">Kembali ke Beranda</a>
            @auth
                <a class="kedua" href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : (auth()->user()->isEmployee() ? route('employee.dashboard') : route('dashboard')) }}">Ke Dasbor</a>
            @else
                <a class="kedua" href="{{ route('login') }}">Login</a>
            @endauth
        </div>
    </main>
</body>
</html>

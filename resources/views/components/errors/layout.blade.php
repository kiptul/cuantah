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
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-12 text-slate-900 antialiased">
    <main class="w-full max-w-md text-center">
        <p class="text-7xl font-black tracking-tight text-emerald-800 tabular-nums sm:text-8xl">{{ $kode }}</p>
        <h1 class="mt-4 text-xl font-black tracking-tight text-slate-900 sm:text-2xl">{{ $judul }}</h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">{{ $pesan }}</p>

        <div class="mt-8 flex flex-col justify-center gap-2 sm:flex-row">
            <a href="{{ url('/') }}" class="inline-flex items-center justify-center rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">
                Kembali ke Beranda
            </a>
            @auth
                <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : (auth()->user()->isEmployee() ? route('employee.dashboard') : route('dashboard')) }}"
                   class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-slate-900/10 transition hover:ring-slate-900/20">
                    Ke Dasbor
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-700 shadow-sm ring-1 ring-slate-900/10 transition hover:ring-slate-900/20">
                    Login
                </a>
            @endauth
        </div>
    </main>
</body>
</html>

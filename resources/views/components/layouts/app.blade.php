<!doctype html>
<html lang="id">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CUANTAH App' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <nav class="relative mx-auto flex max-w-7xl items-center justify-between gap-3 px-3 py-3 sm:flex-wrap sm:px-4">
            @php
                $homeRoute = match (true) {
                    auth()->user()?->isAdmin() => route('admin.dashboard'),
                    auth()->user()?->isEmployee() => route('employee.dashboard'),
                    default => route('dashboard'),
                };
            @endphp
            @php
                $adalahAdmin = auth()->user()?->isAdmin() ?? false;

                /**
                 * Satu daftar menu untuk header dan laci.
                 *
                 * Sebelumnya menu penyetor ditulis langsung sebagai dua tautan
                 * di dalam header, terpisah dari menu karyawan yang sudah
                 * berupa daftar. Memisahkan keduanya berarti setiap perubahan
                 * harus diingat dua kali, dan penanda halaman aktif yang sudah
                 * ada di menu karyawan tidak pernah sampai ke penyetor.
                 */
                $menuUtama = match (true) {
                    $adalahAdmin => [],
                    auth()->user()?->isEmployee() => [
                        ['label' => 'Beranda', 'url' => route('employee.dashboard'), 'route' => 'employee.dashboard', 'icon' => 'grid'],
                        ['label' => 'Scan', 'url' => route('employee.scan'), 'route' => 'employee.scan', 'icon' => 'scan'],
                        ['label' => 'Transaksi', 'url' => route('employee.transactions.index'), 'route' => 'employee.transactions.*', 'icon' => 'receipt'],
                    ],
                    auth()->check() => [
                        ['label' => 'Beranda', 'url' => route('dashboard'), 'route' => 'dashboard', 'icon' => 'grid'],
                        ['label' => 'Setor Jelantah', 'url' => route('deposits.create'), 'route' => 'deposits.*', 'icon' => 'drop'],
                        ['label' => 'Transaksi', 'url' => route('transactions.index'), 'route' => 'transactions.*', 'icon' => 'receipt'],
                    ],
                    default => [],
                };
            @endphp

            <a href="{{ $homeRoute }}" class="flex items-center gap-2.5 font-black text-emerald-800">
                @if($adalahAdmin)
                    {{-- Panel admin tetap memakai penanda sendiri: ia perlu
                         menyebut bahwa yang terbuka adalah sisi pengelola,
                         bukan aplikasi yang sama dengan yang dipakai penyetor
                         dan karyawan. --}}
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm shadow-emerald-900/20">C</span>
                    <span>
                        <span class="block leading-none">CUANTAH</span>
                        <span class="mt-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700/70">Admin panel</span>
                    </span>
                @else
                    <x-brand-lockup />
                @endif
            </a>
            <div class="flex max-w-full items-center gap-2 overflow-x-auto text-sm">
                @if($adalahAdmin)
                    <span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 sm:inline-flex">Admin CUANTAH</span>

                    {{-- Pemicu laci admin duduk di navbar, sebaris dengan
                         lonceng dan menu akun, sama seperti sisi penyetor dan
                         karyawan. Sebelumnya ia berupa tombol terpisah di
                         badan halaman, sehingga tiga peran membuka menunya
                         dari tiga tempat yang berbeda.

                         Saklarnya tetap tinggal di layout admin karena
                         peer-checked menuntut hubungan saudara dengan
                         lacinya; yang berpindah hanya labelnya, dan label
                         bekerja dari mana pun lewat atribut for. --}}
                    <label
                        for="adminDrawer"
                        class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-xl text-slate-700 transition hover:bg-slate-100 lg:hidden"
                        aria-label="Buka menu operasional"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7H20M4 12H20M4 17H20" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                        </svg>
                    </label>
                @elseif($menuUtama !== [])
                    {{-- Di sm ke atas menu tetap di header seperti semula. Di
                         bawah itu header membungkus menjadi dua baris setinggi
                         113px, yaitu 14 persen layar ponsel sebelum satu pun
                         isi terlihat, jadi menunya pindah ke laci. --}}
                    <div class="hidden items-center gap-2 sm:flex">
                        @foreach($menuUtama as $item)
                            <a
                                href="{{ $item['url'] }}"
                                @class([
                                    'flex shrink-0 items-center gap-2 rounded-md px-3 py-2 font-semibold transition',
                                    'bg-emerald-700 text-white' => request()->routeIs($item['route']),
                                    'text-slate-700 hover:bg-slate-100' => ! request()->routeIs($item['route']),
                                ])
                                @if(request()->routeIs($item['route'])) aria-current="page" @endif
                            >{{ $item['label'] }}</a>
                        @endforeach
                    </div>

                    <label
                        for="appDrawer"
                        class="flex h-11 w-11 shrink-0 cursor-pointer items-center justify-center rounded-xl text-slate-700 transition hover:bg-slate-100 sm:hidden"
                        aria-label="Buka menu"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7H20M4 12H20M4 17H20" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" />
                        </svg>
                    </label>
                @endif
                @auth
                    <x-notification-bell />
                    <x-account-menu />
                @endauth
            </div>

        </nav>
    </header>

    {{-- Laci dipasang di luar <header>, bukan di dalamnya.

         Properti seperti backdrop-filter, transform, dan filter membentuk
         containing block bagi elemen position:fixed di dalamnya, sehingga
         inset-y-0 berhenti berarti setinggi layar dan menjadi setinggi
         pembungkusnya. Header aplikasi ini belum memakai satu pun dari
         ketiganya, tetapi header publik memakai backdrop-blur dan lacinya
         memang menciut menjadi kotak pendek menempel di atas. Menaruhnya di
         luar header membuat kekeliruan itu tidak mungkin terulang ketika
         suatu saat header ini ikut diberi efek serupa.

         Pemicunya tetap tinggal di navbar; label bekerja dari mana pun lewat
         atribut for. --}}
        {{-- Saklar laci. sr-only berarti position:absolute, sehingga ia
             tidak ikut menempati kolom flex; begitu pula lapisan gelap dan
             lacinya yang fixed. Ketiganya harus bersaudara langsung, sebab
             peer-checked memakai pemilih saudara. --}}
        @if($menuUtama !== [])
            <input id="appDrawer" type="checkbox" class="peer sr-only" aria-label="Buka menu">
        @endif

        @if($menuUtama !== [])
            {{-- Lapisan gelap merangkap tombol tutup. Tanpa ini laci hanya
                 bisa ditutup lewat tombol yang tertutup oleh laci sendiri.
                 z-index-nya di atas 1000 karena kontrol Leaflet berada di
                 sana, dan beberapa halaman memuat peta. --}}
            <label
                for="appDrawer"
                class="fixed inset-0 z-[1050] hidden bg-slate-950/50 peer-checked:block sm:hidden"
                aria-hidden="true"
            ></label>

            <aside class="fixed inset-y-0 left-0 z-[1100] w-72 max-w-[85vw] -translate-x-full overflow-y-auto border-r border-slate-200 bg-white p-3 shadow-xl shadow-slate-950/10 transition-transform duration-200 ease-out peer-checked:translate-x-0 sm:hidden">
                <label
                    for="appDrawer"
                    class="mb-2 flex min-h-11 cursor-pointer items-center justify-between rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-slate-50"
                >
                    Tutup menu
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </label>

                <nav class="border-t border-slate-100 pt-2" aria-label="Menu utama">
                    @foreach($menuUtama as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @class([
                                'mb-1 flex min-h-11 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition',
                                'bg-emerald-700 text-white shadow-sm shadow-emerald-900/20' => request()->routeIs($item['route']),
                                'text-slate-700 hover:bg-emerald-50 hover:text-emerald-800' => ! request()->routeIs($item['route']),
                            ])
                            @if(request()->routeIs($item['route'])) aria-current="page" @endif
                        >
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-current/10" aria-hidden="true">
                            @if($item['icon'] === 'grid')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4H10V10H4V4ZM14 4H20V10H14V4ZM4 14H10V20H4V14ZM14 14H20V20H14V14Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
                            @elseif($item['icon'] === 'scan')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M4 12h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                            @elseif($item['icon'] === 'drop')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3s6 6.5 6 10.5a6 6 0 0 1-12 0C6 9.5 12 3 12 3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
                            @elseif($item['icon'] === 'receipt')
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4H19V20H5V4ZM8 8H16M8 12H16M8 16H13" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                            @endif
                            </span>
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>
        @endif
    <main class="mx-auto max-w-7xl px-3 py-4 sm:px-4 sm:py-6">
        @if(session('success'))
            <div class="mb-5 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-5 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif
        {{ $slot }}
    </main>
</body>
</html>

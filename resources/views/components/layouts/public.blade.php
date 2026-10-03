<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CUANTAH' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f7faf5] text-slate-900 antialiased">
    @php
        $publicNavItems = [
            ['label' => 'Beranda', 'url' => route('home'), 'active' => request()->routeIs('home')],
            ['label' => 'Tentang', 'url' => route('public.page', 'tentang'), 'active' => request()->is('tentang')],
            ['label' => 'Cara Kerja', 'url' => route('public.page', 'cara-kerja'), 'active' => request()->is('cara-kerja')],
            ['label' => 'Harga', 'url' => route('public.page', 'harga'), 'active' => request()->is('harga')],
            ['label' => 'Dampak', 'url' => route('public.page', 'dampak'), 'active' => request()->is('dampak')],
            ['label' => 'Edukasi', 'url' => route('public.page', 'edukasi'), 'active' => request()->is('edukasi')],
            ['label' => 'FAQ', 'url' => route('public.page', 'faq'), 'active' => request()->is('faq')],
        ];
    @endphp

    <header class="sticky top-0 z-40 border-b border-emerald-950/10 bg-white/95 shadow-sm shadow-emerald-950/5 backdrop-blur">
        <nav class="mx-auto flex max-w-[1500px] items-center justify-between gap-3 px-4 py-3 sm:gap-6 sm:py-4 lg:px-8">
            <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-emerald-700 text-white shadow-lg shadow-emerald-900/20 sm:h-12 sm:w-12">
                    <svg class="h-7 w-7 sm:h-8 sm:w-8" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                        <path d="M20 4C13.5 10.8 8 17.6 8 25.2C8 32.1 13.4 36 20 36C26.6 36 32 32.1 32 25.2C32 17.6 26.5 10.8 20 4Z" stroke="currentColor" stroke-width="4" stroke-linejoin="round" />
                        <path d="M20 13V31" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                        <path d="M20 24C16.6 23.6 14.3 21.8 13 18.5" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                    </svg>
                </span>
                <span>
                    <span class="block text-xl font-black leading-none tracking-tight text-emerald-800 sm:text-2xl">CUANTAH</span>
                    <span class="mt-1 hidden text-[10px] font-black uppercase tracking-[0.24em] text-emerald-700/70 sm:block">Cuan dari minyak jelantah</span>
                </span>
            </a>
            <div class="hidden items-center gap-7 text-sm font-bold text-slate-600 xl:flex">
                @foreach ($publicNavItems as $item)
                    <a
                        href="{{ $item['url'] }}"
                        @class([
                            'group relative px-1 py-4 transition duration-200 hover:text-emerald-700',
                            'text-emerald-800' => $item['active'],
                        ])
                    >
                        {{ $item['label'] }}
                        <span
                            @class([
                                'absolute inset-x-0 bottom-0 h-1 rounded-full bg-emerald-600 transition-all duration-300',
                                'scale-x-100 opacity-100' => $item['active'],
                                'scale-x-0 opacity-0 group-hover:scale-x-100 group-hover:opacity-100' => ! $item['active'],
                            ])
                        ></span>
                    </a>
                @endforeach
            </div>
            <div class="flex items-center gap-2">
                {{-- Laci, bukan dropdown.

                     Dropdown menggantung dari tombolnya dan lebarnya dibatasi
                     max-w-72, sehingga tujuh menu publik menumpuk di pojok
                     kanan atas layar ponsel. Laci memakai pola yang sama
                     dengan sisi admin dan sisi aplikasi: checkbox dan peer,
                     tanpa JavaScript. --}}
                <label
                    for="publicDrawer"
                    class="flex h-11 w-11 cursor-pointer items-center justify-center rounded-xl text-emerald-900 transition hover:bg-emerald-50 xl:hidden"
                    aria-label="Buka menu navigasi"
                >
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M4 7H20M4 12H20M4 17H20" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" />
                    </svg>
                </label>
                @auth
                    <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : (auth()->user()->isEmployee() ? route('employee.dashboard') : route('dashboard')) }}" class="hidden rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-900/20 sm:inline-flex">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="hidden px-4 py-3 text-sm font-bold text-slate-700 xl:inline-flex">Login</a>
                    <a href="{{ route('register') }}" class="hidden items-center gap-2 rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-800 sm:inline-flex">
                        <span class="hidden md:inline">Setor Sekarang</span>
                        <span class="md:hidden">Setor</span>
                        <span aria-hidden="true">→</span>
                    </a>
                @endauth


            </div>

            {{-- Saklar laci. sr-only berarti position:absolute, sehingga ia
                 tidak menempati kolom flex; begitu pula lapisan gelap dan
                 lacinya yang fixed. Ketiganya harus bersaudara langsung,
                 sebab peer-checked memakai pemilih saudara. --}}
            <input id="publicDrawer" type="checkbox" class="peer sr-only" aria-label="Buka menu navigasi">

            <label
                for="publicDrawer"
                class="fixed inset-0 z-[1050] hidden bg-slate-950/50 peer-checked:block xl:hidden"
                aria-hidden="true"
            ></label>

            <aside class="fixed inset-y-0 left-0 z-[1100] w-72 max-w-[85vw] -translate-x-full overflow-y-auto border-r border-emerald-100 bg-white p-3 shadow-xl shadow-emerald-950/10 transition-transform duration-200 ease-out peer-checked:translate-x-0 xl:hidden">
                <label
                    for="publicDrawer"
                    class="mb-2 flex cursor-pointer items-center justify-between rounded-xl px-3 py-2.5 text-sm font-bold text-slate-600 transition hover:bg-emerald-50"
                >
                    Tutup menu
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                </label>

                <nav class="border-t border-emerald-100 pt-2" aria-label="Navigasi publik">
                    @foreach ($publicNavItems as $item)
                        <a
                            href="{{ $item['url'] }}"
                            @class([
                                'mb-1 flex min-h-11 items-center rounded-xl px-4 py-2.5 text-sm font-bold transition',
                                'bg-emerald-700 text-white shadow-sm shadow-emerald-900/20' => $item['active'],
                                'text-slate-700 hover:bg-emerald-50 hover:text-emerald-800' => ! $item['active'],
                            ])
                            @if($item['active']) aria-current="page" @endif
                        >{{ $item['label'] }}</a>
                    @endforeach
                </nav>

                <div class="mt-2 border-t border-emerald-100 pt-2">
                    @auth
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : (auth()->user()->isEmployee() ? route('employee.dashboard') : route('dashboard')) }}" class="flex min-h-11 items-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="mb-1 flex min-h-11 items-center rounded-xl px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:bg-emerald-50 hover:text-emerald-800">Login</a>
                        <a href="{{ route('register') }}" class="flex min-h-11 items-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800">Setor Sekarang</a>
                    @endauth
                </div>
            </aside>
        </nav>
    </header>
    <main>{{ $slot }}</main>

    <footer class="border-t border-emerald-900/10 bg-emerald-950 text-emerald-50">
            <div class="mx-auto max-w-[1500px] px-4 py-10 sm:py-12 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-[minmax(0,.8fr)_minmax(0,1.2fr)] lg:gap-20">
                    <div class="max-w-sm">
                        <a href="{{ route('home') }}" class="inline-flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-emerald-700 shadow-lg shadow-black/15 sm:h-11 sm:w-11 sm:rounded-2xl">
                                <svg class="h-6 w-6 sm:h-7 sm:w-7" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                                    <path d="M20 4C13.5 10.8 8 17.6 8 25.2C8 32.1 13.4 36 20 36C26.6 36 32 32.1 32 25.2C32 17.6 26.5 10.8 20 4Z" stroke="currentColor" stroke-width="4" stroke-linejoin="round" />
                                    <path d="M20 13V31" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                                    <path d="M20 24C16.6 23.6 14.3 21.8 13 18.5" stroke="currentColor" stroke-width="4" stroke-linecap="round" />
                                </svg>
                            </span>
                            <span>
                                <span class="block text-xl font-black leading-none tracking-tight sm:text-2xl">CUANTAH</span>
                                <span class="mt-1 block text-[10px] font-black uppercase tracking-[0.2em] text-emerald-200">Cuan dari minyak jelantah</span>
                            </span>
                        </a>
                        <p class="mt-5 text-sm leading-6 text-emerald-100/75">
                            Menghubungkan rumah tangga dan UMKM dengan pengelolaan minyak jelantah yang lebih mudah, transparan, dan terorganisir.
                        </p>
                    </div>

                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-200">Jelajahi CUANTAH</p>
                        <nav class="mt-4 grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-3 lg:grid-cols-4" aria-label="Navigasi footer">
                            @foreach ($publicNavItems as $item)
                                <a href="{{ $item['url'] }}" class="rounded-lg py-2 text-sm font-semibold text-emerald-100/75 transition hover:bg-white/10 hover:px-2 hover:text-white">
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </nav>
                    </div>
                </div>

                <div class="mt-9 border-t border-white/15 pt-5 text-xs font-semibold text-emerald-100/60">
                    <p>&copy; 2026 CUANTAH. Seluruh hak cipta dilindungi.</p>
                </div>
            </div>
    </footer>
</body>
</html>

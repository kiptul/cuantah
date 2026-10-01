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
        <nav class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-3 py-3 sm:px-4">
            @php
                $homeRoute = match (true) {
                    auth()->user()?->isAdmin() => route('admin.dashboard'),
                    auth()->user()?->isEmployee() => route('employee.dashboard'),
                    default => route('dashboard'),
                };
            @endphp
            @php
                $adalahPenyetor = ! auth()->user()?->isAdmin() && ! auth()->user()?->isEmployee();
            @endphp
            <a href="{{ $homeRoute }}" class="flex items-center gap-2.5 font-black text-emerald-800">
                @if($adalahPenyetor)
                    <x-brand-lockup />
                @else
                    @if(auth()->user()?->isAdmin())
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm shadow-emerald-900/20">C</span>
                    @endif
                    <span>
                        <span class="block leading-none">CUANTAH</span>
                        @if(auth()->user()?->isAdmin())
                            <span class="mt-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700/70">Admin panel</span>
                        @endif
                    </span>
                @endif
            </a>
            <div class="flex max-w-full items-center gap-2 overflow-x-auto text-sm">
                @if(auth()->user()?->isAdmin())
                    <span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 sm:inline-flex">Admin CUANTAH</span>
                @elseif(auth()->user()?->isEmployee())
                    @php
                        $menuKaryawan = [
                            ['label' => 'Beranda', 'url' => route('employee.dashboard'), 'route' => 'employee.dashboard', 'icon' => 'grid'],
                            ['label' => 'Scan', 'url' => route('employee.scan'), 'route' => 'employee.scan', 'icon' => 'scan'],
                            ['label' => 'Pickup', 'url' => route('employee.pickups.available'), 'route' => 'employee.pickups.*', 'icon' => 'truck'],
                            ['label' => 'Transaksi', 'url' => route('employee.transactions.index'), 'route' => 'employee.transactions.*', 'icon' => 'receipt'],
                        ];
                    @endphp
                    {{-- Yang disembunyikan di layar sempit adalah labelnya, bukan
                         tautannya. Sebelumnya Pickup dan Transaksi lenyap sama
                         sekali di bawah 640px, padahal karyawanlah yang paling
                         mungkin memakai ponsel karena bekerja di lapangan.
                         Menggulir mendatar bukan jalan keluar: tidak ada isyarat
                         bahwa area ini bisa digeser. --}}
                    @foreach($menuKaryawan as $item)
                        <a
                            href="{{ $item['url'] }}"
                            title="{{ $item['label'] }}"
                            @class([
                                'flex shrink-0 items-center gap-2 rounded-md px-3 py-2 font-semibold transition',
                                'bg-emerald-700 text-white' => request()->routeIs($item['route']),
                                'text-slate-700 hover:bg-slate-100' => ! request()->routeIs($item['route']),
                            ])
                            @if(request()->routeIs($item['route'])) aria-current="page" @endif
                        >
                            <span class="sm:hidden">
                                @if($item['icon'] === 'grid')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 4H10V10H4V4ZM14 4H20V10H14V4ZM4 14H10V20H4V14ZM14 14H20V20H14V14Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
                                @elseif($item['icon'] === 'scan')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 7V5a1 1 0 0 1 1-1h2M17 4h2a1 1 0 0 1 1 1v2M20 17v2a1 1 0 0 1-1 1h-2M7 20H5a1 1 0 0 1-1-1v-2M4 12h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                @elseif($item['icon'] === 'truck')
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M3 6H14V16H3V6ZM14 9H18L21 12V16H14V9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /><path d="M7 19A2 2 0 1 0 7 15A2 2 0 0 0 7 19ZM17 19A2 2 0 1 0 17 15A2 2 0 0 0 17 19Z" fill="currentColor" /></svg>
                                @else
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4H19V20H5V4ZM8 8H16M8 12H16M8 16H13" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                                @endif
                            </span>
                            <span class="hidden sm:inline">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                @else
                    <a href="{{ route('deposits.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 font-semibold text-white">Setor</a>
                    <a href="{{ route('transactions.index') }}" class="rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100">Transaksi</a>
                @endif
                @auth
                    <x-notification-bell />
                    <x-account-menu />
                @endauth
            </div>
        </nav>
    </header>
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

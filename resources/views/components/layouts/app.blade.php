<!doctype html>
<html lang="id">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CUANTAH App' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
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
            <a href="{{ $homeRoute }}" class="flex items-center gap-2.5 font-black text-emerald-800">
                @if(auth()->user()?->isAdmin())
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-700 text-white shadow-sm shadow-emerald-900/20">C</span>
                @endif
                <span>
                    <span class="block leading-none">CUANTAH</span>
                    @if(auth()->user()?->isAdmin())
                        <span class="mt-1 block text-[10px] font-bold uppercase tracking-[0.16em] text-emerald-700/70">Admin panel</span>
                    @endif
                </span>
            </a>
            <div class="flex max-w-full items-center gap-2 overflow-x-auto text-sm">
                @if(auth()->user()?->isAdmin())
                    <span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 sm:inline-flex">Admin CUANTAH</span>
                @elseif(auth()->user()?->isEmployee())
                    <a href="{{ route('employee.dashboard') }}" class="rounded-md bg-emerald-700 px-3 py-2 font-semibold text-white">Karyawan</a>
                    <a href="{{ route('employee.scan') }}" class="rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100">Scan</a>
                    <a href="{{ route('employee.pickups.available') }}" class="hidden rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex">Pickup</a>
                    <a href="{{ route('employee.transactions.index') }}" class="hidden rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex">Transaksi</a>
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
    @if(session('transaction_completed'))
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                alert(@json(session('transaction_completed')));
            });
        </script>
    @endif
</body>
</html>

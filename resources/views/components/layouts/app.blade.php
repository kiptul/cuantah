<!doctype html>
<html lang="id">
<head>
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
            <a href="{{ $homeRoute }}" class="font-black text-emerald-800">CUANTAH</a>
            <div class="flex max-w-full items-center gap-2 overflow-x-auto text-sm">
                @if(auth()->user()?->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100">Admin</a>
                @elseif(auth()->user()?->isEmployee())
                    <a href="{{ route('employee.dashboard') }}" class="rounded-md bg-emerald-700 px-3 py-2 font-semibold text-white">Karyawan</a>
                    <a href="{{ route('employee.scan') }}" class="rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100">Scan</a>
                    <a href="{{ route('employee.pickups.available') }}" class="hidden rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex">Pickup</a>
                    <a href="{{ route('employee.transactions.index') }}" class="hidden rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100 sm:inline-flex">Transaksi</a>
                @else
                    <a href="{{ route('deposits.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 font-semibold text-white">Setor</a>
                    <a href="{{ route('transactions.index') }}" class="rounded-md px-3 py-2 font-semibold text-slate-700 hover:bg-slate-100">Transaksi</a>
                @endif
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-md px-3 py-2 font-semibold text-slate-600 hover:bg-slate-100">Logout</button>
                </form>
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

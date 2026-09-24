<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Barcode {{ $transaction->code }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-950">
    <main class="mx-auto flex min-h-screen max-w-2xl items-center justify-center px-4 py-8">
        <section class="w-full rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-bold uppercase text-emerald-700">CUANTAH Drop-off</p>
            <h1 class="mt-2 text-3xl font-black">{{ $transaction->code }}</h1>
            <p class="mt-2 text-sm text-slate-600">Tunjukkan barcode ini ke karyawan di drop-off point.</p>

            <div class="mx-auto mt-8 max-w-md rounded-md bg-white p-4">
                <svg viewBox="0 0 {{ $barcode['width'] }} {{ $barcode['height'] }}" class="h-28 w-full" role="img" aria-label="Barcode {{ $transaction->code }}">
                    <rect width="{{ $barcode['width'] }}" height="{{ $barcode['height'] }}" fill="#fff"></rect>
                    @foreach($barcode['rects'] as $rect)
                        <rect x="{{ $rect['x'] }}" y="0" width="{{ $rect['width'] }}" height="{{ $barcode['height'] }}" fill="#020617"></rect>
                    @endforeach
                </svg>
            </div>

            <div class="mt-8 grid gap-3 text-left text-sm sm:grid-cols-2">
                <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Estimasi</p><p class="font-bold">{{ number_format($transaction->estimated_liter, 2, ',', '.') }} L</p></div>
                <div class="rounded-md bg-slate-50 p-3"><p class="text-slate-500">Harga/L</p><p class="font-bold">Rp{{ number_format($transaction->price_per_liter, 0, ',', '.') }}</p></div>
            </div>

            <button onclick="window.print()" class="no-print mt-8 w-full rounded-md bg-emerald-700 px-4 py-3 font-bold text-white">Download PDF / Print</button>
            <a href="{{ route('transactions.show', $transaction) }}" class="no-print mt-3 block rounded-md border border-slate-300 px-4 py-3 font-bold text-slate-700">Kembali</a>
        </section>
    </main>
</body>
</html>

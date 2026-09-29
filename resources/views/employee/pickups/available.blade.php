<x-layouts.app title="Pickup Tersedia">
    <x-page-header eyebrow="Lapangan" title="Pickup Tersedia">
        Diurutkan menurut jadwal yang dipilih penyetor. Yang paling dekat waktunya berada di atas.
    </x-page-header>

    <div class="grid gap-3">
        @forelse($pickups as $pickup)
            @php
                $terjadwal = $pickup->pickup_date !== null;
                $lewatJadwal = $terjadwal && $pickup->pickup_date->isPast() && ! $pickup->pickup_date->isToday();
                $hariIni = $terjadwal && $pickup->pickup_date->isToday();
            @endphp
            <article @class([
                'rounded-2xl border bg-white p-4',
                'border-rose-200' => $lewatJadwal,
                'border-amber-200' => $hariIni,
                'border-slate-200' => ! $lewatJadwal && ! $hariIni,
            ])>
                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-black text-emerald-700">{{ $pickup->transaction->code }}</p>
                            {{-- Jadwal sebelumnya hanya terlihat admin, padahal karyawanlah
                                 yang berangkat. Tanpa ini ia tidak tahu mana yang mendesak. --}}
                            @if($lewatJadwal)
                                <span class="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-bold text-rose-800">Lewat jadwal</span>
                            @elseif($hariIni)
                                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">Hari ini</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $pickup->partner?->name ?? '-' }} · {{ $pickup->address }}</p>
                        <p class="mt-1 text-sm font-bold text-slate-700">
                            @if($terjadwal)
                                Dijadwalkan {{ $pickup->pickup_date->translatedFormat('d M Y') }}@if($pickup->pickup_time), pukul {{ \Illuminate\Support\Str::of($pickup->pickup_time)->substr(0, 5) }}@endif
                            @else
                                Drop-off, tanpa jadwal jemput
                            @endif
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Estimasi {{ number_format($pickup->transaction->estimated_liter, 2, ',', '.') }} L · {{ $pickup->transaction->user->name }}
                        </p>
                    </div>
                    <form method="post" action="{{ route('employee.pickups.claim', $pickup) }}" class="shrink-0">
                        @csrf
                        <button class="w-full rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-black text-white transition hover:bg-emerald-800 sm:w-auto">Ambil Pickup</button>
                    </form>
                </div>
            </article>
        @empty
            <x-empty-state title="Tidak ada pickup tersedia" body="Drop-off yang sudah discan dan belum diambil akan muncul di sini." />
        @endforelse
    </div>

    <div class="mt-4">{{ $pickups->links() }}</div>
</x-layouts.app>

@props(['status'])
@php
    $colors = [
        'pending' => 'bg-amber-100 text-amber-800',
        'scheduled' => 'bg-sky-100 text-sky-800',
        'picked_up' => 'bg-indigo-100 text-indigo-800',
        'verification' => 'bg-violet-100 text-violet-800',
        'completed' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-rose-100 text-rose-800',
        'cancelled' => 'bg-slate-200 text-slate-700',
        'assigned' => 'bg-sky-100 text-sky-800',
        'awaiting_dropoff' => 'bg-amber-100 text-amber-800',
        'scanned' => 'bg-indigo-100 text-indigo-800',
        'paid' => 'bg-emerald-100 text-emerald-800',
        'unpaid' => 'bg-amber-100 text-amber-800',
        'active' => 'bg-emerald-100 text-emerald-800',
        'inactive' => 'bg-slate-100 text-slate-700',
    ];

    /**
     * Sebutan dalam bahasa Indonesia. Sebelumnya kunci status bahasa Inggris
     * ditampilkan apa adanya ("Picked Up", "Awaiting Dropoff").
     */
    $labels = [
        'pending' => 'Menunggu',
        'scheduled' => 'Dijadwalkan',
        'picked_up' => 'Dijemput',
        'verification' => 'Verifikasi',
        'completed' => 'Selesai',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'assigned' => 'Ditugaskan',
        'awaiting_dropoff' => 'Menunggu diantar',
        'scanned' => 'Dipindai',
        'paid' => 'Lunas',
        'unpaid' => 'Belum dibayar',
        'active' => 'Aktif',
        'inactive' => 'Nonaktif',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold '.($colors[$status] ?? 'bg-slate-100 text-slate-700')]) }}>
    {{ $labels[$status] ?? str($status)->replace('_', ' ')->title() }}
</span>

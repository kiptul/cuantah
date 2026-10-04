@props(['transaction'])

{{-- Nilai transaksi menurut tahapannya, bukan apa pun yang kebetulan terisi.
     Sebelumnya seluruh view memakai total_value ?? estimated_total, sehingga
     setoran yang ditolak menampilkan estimasinya sebagai rupiah yang diterima,
     lengkap dengan lencana "Ditolak" di sebelahnya. --}}
@php
    $nilai = $transaction->settledValue();
    $tanpaPembayaran = in_array($transaction->status, [
        \App\Models\Transaction::STATUS_REJECTED,
        \App\Models\Transaction::STATUS_CANCELLED,
    ], true);
@endphp

@if($nilai !== null)
    Rp{{ number_format($nilai, 0, ',', '.') }}
@elseif($tanpaPembayaran)
    <span class="text-slate-400">–</span>
@else
    {{-- Masih berjalan: angkanya perkiraan, dan ditandai begitu. --}}
    <span class="text-slate-500">±Rp{{ number_format($transaction->estimated_total, 0, ',', '.') }}</span>
@endif

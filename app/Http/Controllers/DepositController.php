<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepositRequest;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Services\TransactionService;

class DepositController extends Controller
{
    public function create()
    {
        // Sisa daya tampung ikut dikirim ke halaman. Kapasitas mitra sudah
        // menahan setoran di sisi layanan, tetapi penolakannya baru terasa
        // sesudah seluruh formulir diisi; lebih baik terlihat sejak awal.
        $partners = Partner::query()
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('deliveryFees')
            ->withAvailableLiter()
            ->orderBy('name')
            ->get()
            ->map(function (Partner $partner) {
                $terpakai = $partner->availableLiter();
                $sisa = max((float) $partner->capacity_liter - $terpakai, 0);

                return [
                    'id' => $partner->id,
                    'name' => $partner->name,
                    'address' => $partner->address,
                    'latitude' => (float) $partner->latitude,
                    'longitude' => (float) $partner->longitude,
                    'sisa_liter' => round($sisa, 2),
                    'penuh' => $terpakai >= (float) $partner->capacity_liter,
                    'delivery_fees' => $partner->deliveryFees->map(fn ($fee) => [
                        'min_distance_km' => (float) $fee->min_distance_km,
                        'max_distance_km' => $fee->max_distance_km === null ? null : (float) $fee->max_distance_km,
                        'fee' => (int) $fee->fee,
                    ])->values(),
                ];
            })
            // Mitra yang penuh diletakkan paling bawah supaya pilihan bawaan
            // selalu jatuh pada mitra yang benar-benar masih bisa menerima.
            ->sortBy('penuh')
            ->values();

        return view('user.deposits.create', [
            'price' => OilPrice::current(),
            'partners' => $partners,
            'alamatTerakhir' => $this->alamatJemputTerakhir(),
        ]);
    }

    /**
     * Alamat penjemputan yang terakhir dipakai penyetor ini.
     *
     * Tanpa ini alamat rumah diketik ulang pada setiap setoran, padahal
     * hampir selalu sama, dan mengetik ulang justru mengundang salah ketik
     * pada satu-satunya keterangan yang dipakai karyawan untuk menemukannya.
     *
     * @return array{address: string, latitude: float, longitude: float}|null
     */
    private function alamatJemputTerakhir(): ?array
    {
        $pickup = Pickup::query()
            ->whereHas('transaction', fn ($transaction) => $transaction
                ->where('user_id', auth()->id())
                ->where('method', Transaction::METHOD_PICKUP))
            ->latest('id')
            ->first();

        if ($pickup === null) {
            return null;
        }

        return [
            'address' => (string) $pickup->address,
            'latitude' => (float) $pickup->latitude,
            'longitude' => (float) $pickup->longitude,
        ];
    }

    public function store(StoreDepositRequest $request, TransactionService $service)
    {
        $transaction = $service->createDeposit($request->user(), $request->validated());

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', 'Pengajuan setor berhasil dibuat.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDepositRequest;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Services\TransactionService;

class DepositController extends Controller
{
    public function create()
    {
        $partners = Partner::query()
            ->where('status', 'active')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with('deliveryFees')
            ->orderBy('name')
            ->get()
            ->map(fn (Partner $partner) => [
                'id' => $partner->id,
                'name' => $partner->name,
                'address' => $partner->address,
                'latitude' => (float) $partner->latitude,
                'longitude' => (float) $partner->longitude,
                'delivery_fees' => $partner->deliveryFees->map(fn ($fee) => [
                    'min_distance_km' => (float) $fee->min_distance_km,
                    'max_distance_km' => $fee->max_distance_km === null ? null : (float) $fee->max_distance_km,
                    'fee' => (int) $fee->fee,
                ])->values(),
            ]);

        return view('user.deposits.create', [
            'price' => OilPrice::current(),
            'partners' => $partners,
        ]);
    }

    public function store(StoreDepositRequest $request, TransactionService $service)
    {
        $transaction = $service->createDeposit($request->user(), $request->validated());

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', 'Pengajuan setor berhasil dibuat.');
    }
}

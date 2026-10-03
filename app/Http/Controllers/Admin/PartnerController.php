<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePartnerRequest;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class PartnerController extends Controller
{
    public function index()
    {
        return view('admin.partners.index', [
            'partners' => Partner::with('deliveryFees')
                ->accessibleTo(Auth::user())
                ->latest()
                ->paginate(10),
        ]);
    }

    public function store(StorePartnerRequest $request)
    {
        $data = $request->validated();
        $previousPartnerIds = Partner::pluck('id')->all();

        $partner = Partner::create(Arr::except($data, 'delivery_fees'));
        $this->syncDeliveryFees($partner, $data['delivery_fees'] ?? []);
        $this->syncNewPartnerUsers($partner, $previousPartnerIds);

        return back()->with('success', 'Mitra berhasil ditambahkan.');
    }

    public function update(StorePartnerRequest $request, Partner $partner)
    {
        abort_unless(Auth::user()->canAccessPartnerId($partner->id), 403);

        $data = $request->validated();
        $partner->update(Arr::except($data, 'delivery_fees'));
        $this->syncDeliveryFees($partner, $data['delivery_fees'] ?? []);

        return back()->with('success', 'Mitra berhasil diperbarui.');
    }

    /**
     * @param  array<int, array<string, mixed>>  $fees
     */
    private function syncDeliveryFees(Partner $partner, array $fees): void
    {
        $partner->deliveryFees()->delete();

        foreach ($fees as $fee) {
            $partner->deliveryFees()->create([
                'min_distance_km' => $fee['min_distance_km'],
                'max_distance_km' => blank($fee['max_distance_km'] ?? null) ? null : $fee['max_distance_km'],
                'fee' => $fee['fee'],
            ]);
        }
    }

    /**
     * @param  array<int>  $previousPartnerIds
     */
    private function syncNewPartnerUsers(Partner $partner, array $previousPartnerIds): void
    {
        $creator = Auth::user();
        $partner->users()->syncWithoutDetaching([$creator->id]);

        User::query()
            ->whereIn('role', ['admin', 'employee'])
            ->whereKeyNot($creator->id)
            ->with('partners:id')
            ->get()
            ->each(function (User $user) use ($partner, $previousPartnerIds): void {
                $userPartnerIds = $user->partners->pluck('id')->all();
                $hadAllPartners = $previousPartnerIds === [] || empty(array_diff($previousPartnerIds, $userPartnerIds));

                if ($hadAllPartners) {
                    $user->partners()->syncWithoutDetaching([$partner->id]);
                }
            });
    }
}

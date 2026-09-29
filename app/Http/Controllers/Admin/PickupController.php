<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignPickupRequest;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PickupController extends Controller
{
    public function index()
    {
        $pickups = Pickup::query()
            ->with('transaction.user', 'assignedUser', 'partner')
            ->visibleTo(auth()->user())
            ->where(function ($query) {
                $query->whereHas('transaction', fn ($transaction) => $transaction->where('method', Transaction::METHOD_PICKUP))
                    ->orWhereNotNull('scanned_at');
            })
            ->latest()
            ->paginate(12);

        return view('admin.pickups.index', [
            'pickups' => $pickups,
            'pickupPoints' => $this->mapPoints($pickups),
            'employees' => User::where('role', 'employee')
                ->whereHas('partners', fn ($partner) => $partner->whereIn('partners.id', auth()->user()->accessiblePartnerIds()))
                ->with('partners:id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Titik peta untuk satu halaman pickup.
     *
     * @param  LengthAwarePaginator<int, Pickup>  $pickups
     * @return array<int, array{id: int, code: string, name: string, address: string, latitude: float, longitude: float}>
     */
    private function mapPoints($pickups): array
    {
        return $pickups->map(fn (Pickup $pickup) => [
            'id' => $pickup->id,
            'code' => $pickup->transaction->code,
            'name' => $pickup->transaction->user->name,
            'address' => $pickup->address,
            'latitude' => (float) $pickup->latitude,
            'longitude' => (float) $pickup->longitude,
        ])->values()->all();
    }

    public function assign(AssignPickupRequest $request, Pickup $pickup, TransactionService $service)
    {
        abort_unless(auth()->user()->canAccessPartnerId($pickup->transaction->partner_id), 403);
        abort_unless($pickup->isAssignable(), 422, 'Pickup yang sudah selesai atau ditolak tidak bisa di-assign.');

        $employee = User::findOrFail($request->validated('assigned_user_id'));
        abort_unless($employee->canAccessPartnerId($pickup->transaction->partner_id), 403);

        $service->assignPickupToEmployee($pickup, (int) $request->validated('assigned_user_id'));

        return back()->with('success', 'Pickup berhasil di-assign ke karyawan.');
    }

    public function unassign(Pickup $pickup, TransactionService $service)
    {
        abort_unless(auth()->user()->canAccessPartnerId($pickup->transaction->partner_id), 403);
        abort_unless($pickup->isAssignable(), 422, 'Pickup yang sudah selesai atau ditolak tidak bisa dilepas.');

        $service->unassignPickup($pickup);

        return back()->with('success', 'Assignment pickup berhasil dilepas.');
    }
}

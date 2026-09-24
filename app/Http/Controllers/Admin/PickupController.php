<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignPickupRequest;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;

class PickupController extends Controller
{
    public function index()
    {
        return view('admin.pickups.index', [
            'pickups' => Pickup::query()
                ->with('transaction.user', 'assignedUser', 'partner')
                ->visibleTo(auth()->user())
                ->where(function ($query) {
                    $query->whereHas('transaction', fn ($transaction) => $transaction->where('method', Transaction::METHOD_PICKUP))
                        ->orWhereNotNull('scanned_at');
                })
                ->latest()
                ->paginate(12),
            'employees' => User::where('role', 'employee')
                ->whereHas('partners', fn ($partner) => $partner->whereIn('partners.id', auth()->user()->accessiblePartnerIds()))
                ->with('partners:id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function assign(AssignPickupRequest $request, Pickup $pickup, TransactionService $service)
    {
        abort_unless(auth()->user()->canAccessPartnerId($pickup->transaction->partner_id), 403);

        $employee = User::findOrFail($request->validated('assigned_user_id'));
        abort_unless($employee->canAccessPartnerId($pickup->transaction->partner_id), 403);

        $service->assignPickupToEmployee($pickup, (int) $request->validated('assigned_user_id'));

        return back()->with('success', 'Pickup berhasil di-assign ke karyawan.');
    }

    public function unassign(Pickup $pickup, TransactionService $service)
    {
        abort_unless(auth()->user()->canAccessPartnerId($pickup->transaction->partner_id), 403);

        $service->unassignPickup($pickup);

        return back()->with('success', 'Assignment pickup berhasil dilepas.');
    }
}

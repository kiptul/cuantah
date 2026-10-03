<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignPickupRequest;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PickupController extends Controller
{
    public function index(Request $request)
    {
        $category = $this->onlyAllowed($request->query('kategori'), Pickup::categories());

        $pickups = $this->baseQuery()
            ->when(
                $category,
                fn ($query, string $kategori) => $query->whereIn('status', Pickup::statusesForCategory($kategori))
            )
            ->latest()
            ->paginate(12)
            /**
             * Tanpa withQueryString, tautan halaman 2 kehilangan kategorinya
             * dan admin dikembalikan ke seluruh pickup tanpa pemberitahuan.
             */
            ->withQueryString();

        return view('admin.pickups.index', [
            'pickups' => $pickups,
            'pickupPoints' => $this->mapPoints($pickups),
            'category' => $category,
            'categoryCounts' => $this->categoryCounts(),
            'employees' => User::where('role', 'employee')
                ->whereHas('partners', fn ($partner) => $partner->whereIn('partners.id', auth()->user()->accessiblePartnerIds()))
                ->with('partners:id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Pickup yang berhak dilihat admin ini: setoran jemput, ditambah drop-off
     * yang sudah dipindai karyawan.
     *
     * @return Builder<Pickup>
     */
    private function baseQuery()
    {
        return Pickup::query()
            ->with('transaction.user', 'assignedUser', 'partner')
            ->visibleTo(auth()->user())
            ->where(function ($query) {
                $query->whereHas('transaction', fn ($transaction) => $transaction->where('method', Transaction::METHOD_PICKUP))
                    ->orWhereNotNull('scanned_at');
            });
    }

    /**
     * Jumlah pickup per kategori, plus 'semua'.
     *
     * Dihitung dari satu query yang dikelompokkan per status, bukan satu
     * query per kategori, supaya menambah kategori tidak menambah query.
     *
     * @return array<string, int>
     */
    private function categoryCounts(): array
    {
        $perStatus = $this->baseQuery()
            ->reorder()
            ->toBase()
            ->selectRaw('status, count(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        $counts = ['semua' => (int) $perStatus->sum()];

        foreach (Pickup::CATEGORIES as $kunci => $kategori) {
            $counts[$kunci] = (int) collect($kategori['statuses'])
                ->sum(fn (string $status) => (int) $perStatus->get($status, 0));
        }

        return $counts;
    }

    private function onlyAllowed(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
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

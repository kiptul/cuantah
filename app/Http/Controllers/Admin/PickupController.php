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
    /**
     * Kategori daftar pickup beserta status transaksi yang termasuk di dalamnya.
     *
     * Dikelompokkan menurut status transaksi, bukan status pickup, karena
     * status transaksilah yang menentukan apakah pickup masih bisa diatur.
     *
     * @var array<string, array{label: string, statuses: array<int, string>}>
     */
    private const CATEGORIES = [
        'menunggu' => ['label' => 'Menunggu', 'statuses' => [Transaction::STATUS_PENDING]],
        'ditugaskan' => ['label' => 'Ditugaskan', 'statuses' => [Transaction::STATUS_SCHEDULED, Transaction::STATUS_PICKED_UP, Transaction::STATUS_VERIFICATION]],
        'selesai' => ['label' => 'Selesai', 'statuses' => [Transaction::STATUS_COMPLETED]],
        'dibatalkan' => ['label' => 'Dibatalkan', 'statuses' => [Transaction::STATUS_CANCELLED, Transaction::STATUS_REJECTED]],
    ];

    public function index(Request $request)
    {
        /**
         * Kategori bawaan "menunggu" karena itulah yang perlu ditindaklanjuti.
         * Nilai di luar daftar dari URL yang diubah tangan dikembalikan ke
         * bawaan alih-alih menghasilkan daftar kosong.
         */
        $category = $request->query('kategori');
        $category = is_string($category) && ($category === 'semua' || array_key_exists($category, self::CATEGORIES))
            ? $category
            : 'menunggu';

        $visiblePickups = fn (): Builder => Pickup::query()
            ->visibleTo($request->user())
            ->where(function (Builder $query) {
                $query->whereHas('transaction', fn (Builder $transaction) => $transaction->where('method', Transaction::METHOD_PICKUP))
                    ->orWhereNotNull('scanned_at');
            });

        $pickups = $visiblePickups()
            ->with('transaction.user', 'assignedUser', 'partner')
            ->when($category !== 'semua', fn (Builder $query) => $query->whereHas(
                'transaction',
                fn (Builder $transaction) => $transaction->whereIn('status', self::CATEGORIES[$category]['statuses']),
            ))
            // Yang menunggu diurutkan menurut jadwal terdekat agar yang paling
            // mendesak ditugaskan lebih dulu; kategori lain terbaru di atas.
            ->when(
                $category === 'menunggu',
                fn (Builder $query) => $query->orderByRaw('pickup_date is null')->orderBy('pickup_date')->orderBy('pickup_time'),
                fn (Builder $query) => $query->latest(),
            )
            ->paginate(12)
            ->withQueryString();

        $countsByStatus = $visiblePickups()
            ->join('transactions', 'transactions.id', '=', 'pickups.transaction_id')
            ->groupBy('transactions.status')
            ->selectRaw('transactions.status as status, COUNT(*) as total')
            ->toBase()
            ->pluck('total', 'status');

        $categories = collect(self::CATEGORIES)
            ->map(fn (array $definition) => [
                'label' => $definition['label'],
                'count' => (int) collect($definition['statuses'])->sum(fn (string $status) => $countsByStatus->get($status, 0)),
            ])
            ->put('semua', ['label' => 'Semua', 'count' => (int) $countsByStatus->sum()]);

        return view('admin.pickups.index', [
            'pickups' => $pickups,
            'category' => $category,
            'categories' => $categories,
            'pickupPoints' => $this->mapPoints($pickups),
            'employees' => User::where('role', 'employee')
                ->whereHas('partners', fn ($partner) => $partner->whereIn('partners.id', $request->user()->accessiblePartnerIds()))
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

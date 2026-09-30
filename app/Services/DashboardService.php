<?php

namespace App\Services;

use App\Models\OilPrice;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function userSummary(User $user): array
    {
        $completed = fn () => $user->transactions()->where('status', Transaction::STATUS_COMPLETED);

        return [
            'total_liter' => (float) $completed()->sum('actual_liter'),
            'total_value' => (int) $completed()->sum('total_value'),
            'month_liter' => (float) $completed()->where('created_at', '>=', now()->startOfMonth())->sum('actual_liter'),
            'unpaid_value' => (int) $completed()->where('payment_status', 'unpaid')->sum('total_value'),
            'total_transactions' => $user->transactions()->count(),
            'current_price' => OilPrice::current(),
            'active_transactions' => $user->transactions()
                ->whereNotIn('status', Transaction::FINAL_STATUSES)
                ->with('partner', 'pickup.assignedUser')
                ->latest()
                ->get(),
            'latest_transactions' => $user->transactions()
                ->whereIn('status', Transaction::FINAL_STATUSES)
                ->latest()
                ->limit(5)
                ->get(),
            'disputable' => $this->disputable($user),
        ];
    }

    /**
     * Transaksi yang baru selesai dan takarannya masih bisa disanggah.
     *
     * Konfirmasi pembayaran oleh penyetor sudah dihapus: begitu karyawan
     * mengirim form penjemputan, transaksi selesai. Yang tersisa bagi
     * penyetor hanyalah hak menyanggah takaran dalam tiga hari.
     *
     * @return Collection<int, Transaction>
     */
    private function disputable(User $user): Collection
    {
        return $user->transactions()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereNull('disputed_at')
            ->where('updated_at', '>', now()->subDays(3))
            ->latest('updated_at')
            ->limit(3)
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function employeeSummary(User $employee): array
    {
        $completed = fn (): Builder => Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereHas('pickup', fn (Builder $pickup) => $pickup->where('assigned_user_id', $employee->id));

        return [
            /**
             * Tugas terbuka diurutkan menurut jadwal penyetor. Drop-off tidak
             * berjadwal sehingga diletakkan sesudahnya.
             */
            'tasks' => Transaction::query()
                ->with('user', 'pickup')
                ->join('pickups', 'pickups.transaction_id', '=', 'transactions.id')
                ->where('pickups.assigned_user_id', $employee->id)
                ->whereNotIn('transactions.status', Transaction::FINAL_STATUSES)
                ->select('transactions.*')
                ->orderByRaw('pickups.pickup_date is null')
                ->orderBy('pickups.pickup_date')
                ->orderBy('pickups.pickup_time')
                ->oldest('transactions.created_at')
                ->get(),
            'available_count' => Pickup::query()
                ->visibleTo($employee)
                ->whereNull('assigned_user_id')
                ->whereHas('transaction', fn (Builder $transaction) => $transaction
                    ->where('method', Transaction::METHOD_PICKUP)
                    ->whereNotIn('status', Transaction::FINAL_STATUSES))
                ->count(),
            'today_count' => $completed()->whereDate('updated_at', today())->count(),
            'today_liter' => (float) $completed()->whereDate('updated_at', today())->sum('actual_liter'),
            'month_count' => $completed()->where('updated_at', '>=', now()->startOfMonth())->count(),
            'month_liter' => (float) $completed()->where('updated_at', '>=', now()->startOfMonth())->sum('actual_liter'),
            'total_count' => $completed()->count(),
            'total_liter' => (float) $completed()->sum('actual_liter'),
            'recent_completions' => $completed()
                ->with('user')
                ->latest('updated_at')
                ->limit(5)
                ->get(),
        ];
    }
}

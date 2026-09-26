<?php

namespace App\Services;

use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    public function summary(): array
    {
        $user = auth()->user();

        $monthExpression = $this->monthExpression();

        $monthly = Transaction::query()
            ->visibleTo($user)
            ->selectRaw("{$monthExpression} as month")
            ->selectRaw('COALESCE(SUM(actual_liter), 0) as volume')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total_value), 0) as value')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy(DB::raw($monthExpression))
            ->orderBy('month')
            ->get();

        return [
            'total_users' => User::where('role', 'user')->count(),
            'total_liter' => Transaction::visibleTo($user)->where('status', Transaction::STATUS_COMPLETED)->sum('actual_liter'),
            'total_transactions' => Transaction::visibleTo($user)->count(),
            'total_value' => Transaction::visibleTo($user)->where('status', Transaction::STATUS_COMPLETED)->sum('total_value'),
            'pending_pickups' => Pickup::visibleTo($user)->where('status', 'pending')->count(),
            'completed_transactions' => Transaction::visibleTo($user)->where('status', Transaction::STATUS_COMPLETED)->count(),
            'monthly' => $monthly,
        ];
    }

    /**
     * Ekspresi SQL untuk mengelompokkan transaksi per bulan (format: YYYY-MM).
     *
     * Setiap driver punya fungsi format tanggalnya sendiri, jadi dipilih
     * berdasarkan koneksi aktif agar query berjalan di MySQL maupun SQLite.
     */
    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            'pgsql' => "to_char(created_at, 'YYYY-MM')",
            default => 'DATE_FORMAT(created_at, "%Y-%m")',
        };
    }
}

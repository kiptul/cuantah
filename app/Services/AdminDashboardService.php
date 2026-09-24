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

        $monthly = Transaction::query()
            ->visibleTo($user)
            ->selectRaw('DATE_FORMAT(created_at, "%Y-%m") as month')
            ->selectRaw('COALESCE(SUM(actual_liter), 0) as volume')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(total_value), 0) as value')
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
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
}

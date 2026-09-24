<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;

class DashboardService
{
    public function userSummary(User $user): array
    {
        $base = $user->transactions();

        return [
            'total_liter' => (clone $base)->where('status', Transaction::STATUS_COMPLETED)->sum('actual_liter'),
            'total_transactions' => (clone $base)->count(),
            'total_value' => (clone $base)->where('status', Transaction::STATUS_COMPLETED)->sum('total_value'),
            'latest_transactions' => (clone $base)->with('pickup.partner')->latest()->limit(5)->get(),
            'latest_request' => (clone $base)->with('pickup.partner')->latest()->first(),
            'notifications' => $user->notifications()->latest()->limit(5)->get(),
        ];
    }
}

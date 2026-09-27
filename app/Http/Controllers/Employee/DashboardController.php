<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Pickup;
use App\Models\Transaction;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();
        $assigned = Pickup::query()
            ->with('transaction.user')
            ->where('assigned_user_id', $user->id);
        $completedTransactions = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereHas('pickup', fn ($pickup) => $pickup->where('assigned_user_id', $user->id));

        return view('employee.dashboard', [
            'active_pickups' => (clone $assigned)->whereNotIn('status', [Pickup::STATUS_COMPLETED, Pickup::STATUS_REJECTED])->count(),
            'completed_pickups' => (clone $assigned)->where('status', Pickup::STATUS_COMPLETED)->count(),
            'completed_liter' => (clone $completedTransactions)->sum('actual_liter'),
            'pickup_count' => (clone $completedTransactions)->where('method', Transaction::METHOD_PICKUP)->count(),
            'pickup_liter' => (clone $completedTransactions)->where('method', Transaction::METHOD_PICKUP)->sum('actual_liter'),
            'drop_off_count' => (clone $completedTransactions)->where('method', Transaction::METHOD_DROP_OFF)->count(),
            'drop_off_liter' => (clone $completedTransactions)->where('method', Transaction::METHOD_DROP_OFF)->sum('actual_liter'),
            'latest_pickups' => (clone $assigned)->latest()->limit(5)->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index()
    {
        $partnerIds = Auth::user()->accessiblePartnerIds();
        $employeeReports = User::query()
            ->where('role', 'employee')
            ->whereHas('partners', fn ($partner) => $partner->whereIn('partners.id', $partnerIds))
            ->orderBy('name')
            ->get();

        $metricsByEmployee = Pickup::query()
            ->join('transactions', 'transactions.id', '=', 'pickups.transaction_id')
            ->whereIn('transactions.partner_id', $partnerIds)
            ->where('pickups.status', 'completed')
            ->whereNotNull('pickups.assigned_user_id')
            ->groupBy('pickups.assigned_user_id')
            ->selectRaw('pickups.assigned_user_id')
            ->selectRaw('COUNT(*) as completed_count')
            ->selectRaw('COALESCE(SUM(transactions.actual_liter), 0) as total_liter')
            ->selectRaw("SUM(CASE WHEN transactions.method = 'pickup' THEN 1 ELSE 0 END) as pickup_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.method = 'pickup' THEN transactions.actual_liter ELSE 0 END), 0) as pickup_liter")
            ->selectRaw("SUM(CASE WHEN transactions.method = 'drop_off' THEN 1 ELSE 0 END) as drop_off_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN transactions.method = 'drop_off' THEN transactions.actual_liter ELSE 0 END), 0) as drop_off_liter")
            ->get()
            ->keyBy('assigned_user_id');

        $employeeReports->each(function (User $employee) use ($metricsByEmployee) {
            $metrics = $metricsByEmployee->get($employee->id);
            $employee->completed_pickups_count = (int) ($metrics->completed_count ?? 0);
            $employee->completed_liter_sum = (float) ($metrics->total_liter ?? 0);
            $employee->pickup_count = (int) ($metrics->pickup_count ?? 0);
            $employee->pickup_liter = (float) ($metrics->pickup_liter ?? 0);
            $employee->drop_off_count = (int) ($metrics->drop_off_count ?? 0);
            $employee->drop_off_liter = (float) ($metrics->drop_off_liter ?? 0);
        });

        return view('admin.reports.index', [
            'transactions' => Transaction::with('user', 'partner', 'pickup.assignedUser')
                ->visibleTo(Auth::user())
                ->where('status', Transaction::STATUS_COMPLETED)
                ->latest()
                ->paginate(15),
            'employeeReports' => $employeeReports,
        ]);
    }

    public function employee(User $user)
    {
        abort_unless($user->isEmployee(), 404);
        abort_unless($user->partners()->whereIn('partners.id', Auth::user()->accessiblePartnerIds())->exists(), 403);

        $completed = Transaction::query()
            ->with('user', 'partner', 'pickup')
            ->visibleTo(Auth::user())
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereHas('pickup', fn ($pickup) => $pickup->where('assigned_user_id', $user->id));

        $summary = [
            'completed_count' => (clone $completed)->count(),
            'total_liter' => (clone $completed)->sum('actual_liter'),
            'total_value' => (clone $completed)->sum('total_value'),
            'pickup_count' => (clone $completed)->where('method', Transaction::METHOD_PICKUP)->count(),
            'pickup_liter' => (clone $completed)->where('method', Transaction::METHOD_PICKUP)->sum('actual_liter'),
            'drop_off_count' => (clone $completed)->where('method', Transaction::METHOD_DROP_OFF)->count(),
            'drop_off_liter' => (clone $completed)->where('method', Transaction::METHOD_DROP_OFF)->sum('actual_liter'),
        ];

        return view('admin.reports.employee', [
            'employee' => $user,
            'summary' => $summary,
            'transactions' => (clone $completed)->latest()->paginate(20),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard)
    {
        return view('employee.dashboard', $dashboard->employeeSummary(auth()->user()));
    }
}

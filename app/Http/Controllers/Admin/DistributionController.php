<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDistributionRequest;
use App\Models\Distribution;
use App\Models\Partner;

class DistributionController extends Controller
{
    public function index()
    {
        return view('admin.distributions.index', [
            'distributions' => Distribution::with('partner')
                ->whereHas('partner', fn ($partner) => $partner->accessibleTo(auth()->user()))
                ->latest('distributed_at')
                ->paginate(10),
            'partners' => Partner::where('status', 'active')
                ->accessibleTo(auth()->user())
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreDistributionRequest $request)
    {
        abort_unless(auth()->user()->canAccessPartnerId((int) $request->validated('partner_id')), 403);

        Distribution::create($request->validated());

        return back()->with('success', 'Penyaluran berhasil dicatat.');
    }
}

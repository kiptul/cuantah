<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDistributionRequest;
use App\Models\Distribution;
use App\Models\Partner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class DistributionController extends Controller
{
    public function index()
    {
        return view('admin.distributions.index', [
            'distributions' => Distribution::with('partner')
                ->whereHas('partner', fn ($partner) => $partner->accessibleTo(Auth::user()))
                ->latest('distributed_at')
                ->paginate(10),
            'partners' => Partner::where('status', 'active')
                ->accessibleTo(Auth::user())
                ->withAvailableLiter()
                ->orderBy('name')
                ->get()
                ->map(fn (Partner $partner) => tap($partner, fn () => $partner->setAttribute('available_liter', $partner->availableLiter()))),
        ]);
    }

    public function store(StoreDistributionRequest $request)
    {
        abort_unless(Auth::user()->canAccessPartnerId((int) $request->validated('partner_id')), 403);

        $partner = Partner::findOrFail($request->validated('partner_id'));
        $tersedia = $partner->availableLiter();
        $diminta = (float) $request->validated('volume_liter');

        // Volume keluar tidak boleh melampaui yang pernah masuk. Tanpa penjagaan
        // ini, penyaluran dapat dicatat dengan angka bebas dan laporan rantai
        // pasok berhenti mencerminkan keadaan sebenarnya.
        if ($diminta > $tersedia) {
            throw ValidationException::withMessages([
                'volume_liter' => sprintf(
                    'Mitra ini baru mengumpulkan %s L yang belum disalurkan, sedangkan kamu mencatat %s L. Selesaikan dulu transaksi yang masuk, atau turunkan volumenya.',
                    rtrim(rtrim(number_format($tersedia, 2, ',', '.'), '0'), ','),
                    rtrim(rtrim(number_format($diminta, 2, ',', '.'), '0'), ','),
                ),
            ]);
        }

        Distribution::create($request->validated());

        return back()->with('success', 'Penyaluran berhasil dicatat.');
    }
}

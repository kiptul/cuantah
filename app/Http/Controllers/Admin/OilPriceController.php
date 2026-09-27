<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOilPriceRequest;
use App\Models\OilPrice;
use Illuminate\Support\Facades\DB;

class OilPriceController extends Controller
{
    public function index()
    {
        return view('admin.prices.index', [
            'prices' => OilPrice::latest('effective_date')->paginate(10),
        ]);
    }

    /**
     * Menyimpan harga baru.
     *
     * Hanya boleh ada satu harga aktif. Sebelumnya harga lama dibiarkan
     * tetap aktif, sehingga daftar menampilkan beberapa baris bertanda
     * "Active" sekaligus dan admin tidak bisa memastikan mana yang dipakai.
     */
    public function store(StoreOilPriceRequest $request)
    {
        $isActive = $request->boolean('is_active');

        DB::transaction(function () use ($request, $isActive): void {
            if ($isActive) {
                OilPrice::where('is_active', true)->update(['is_active' => false]);
            }

            OilPrice::create([
                ...$request->validated(),
                'is_active' => $isActive,
            ]);
        });

        return back()->with('success', 'Harga jelantah berhasil disimpan.');
    }
}

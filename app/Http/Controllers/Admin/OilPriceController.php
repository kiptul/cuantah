<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOilPriceRequest;
use App\Models\OilPrice;

class OilPriceController extends Controller
{
    public function index()
    {
        return view('admin.prices.index', [
            'prices' => OilPrice::latest('effective_date')->paginate(10),
        ]);
    }

    public function store(StoreOilPriceRequest $request)
    {
        OilPrice::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Harga jelantah berhasil disimpan.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\OilPrice;

class PublicController extends Controller
{
    public function home()
    {
        return view('public.home', ['price' => OilPrice::current()]);
    }

    public function page(string $page)
    {
        abort_unless(in_array($page, ['tentang', 'cara-kerja', 'harga', 'dampak', 'edukasi', 'faq'], true), 404);

        return view('public.'.$page, ['price' => OilPrice::current()]);
    }
}

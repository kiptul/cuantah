<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Menandai seluruh notifikasi terbaca.
     *
     * Dipanggil saat lonceng dibuka, bukan saat halaman dimuat, supaya
     * titik merahnya hilang hanya ketika isinya benar-benar dilihat.
     */
    public function markRead(): JsonResponse
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengembalikan drop-off yang dilepas dari karyawan ke awaiting_dropoff.
 *
 * Melepas penugasan drop-off dulu menyetel status pickup menjadi 'scanned',
 * status yang tidak termasuk kategori mana pun di halaman Pickup admin.
 * Drop-off seperti itu hanya terlihat di tab Semua, padahal ia menunggu
 * dipegang karyawan baru.
 *
 * down() sengaja kosong: 'scanned' adalah keadaan keliru, dan barisnya tidak
 * bisa dibedakan lagi dari drop-off yang memang menunggu sejak awal.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('pickups')
            ->where('status', 'scanned')
            ->whereNull('assigned_user_id')
            ->update(['status' => 'awaiting_dropoff', 'updated_at' => now()]);
    }

    public function down(): void
    {
        //
    }
};

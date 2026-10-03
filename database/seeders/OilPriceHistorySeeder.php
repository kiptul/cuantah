<?php

namespace Database\Seeders;

use App\Models\OilPrice;
use Illuminate\Database\Seeder;

/**
 * Riwayat harga jelantah selama setahun terakhir.
 *
 * Harga hari ini tetap dibuat oleh DatabaseSeeder. Riwayat ini dipakai
 * TransactionSeeder supaya transaksi lama tercatat dengan harga yang
 * berlaku pada tanggalnya, bukan harga hari ini.
 */
class OilPriceHistorySeeder extends Seeder
{
    public function run(): void
    {
        $history = [
            12 => [3500, 'Harga pembukaan program'],
            9 => [3600, 'Penyesuaian harga pengepul'],
            6 => [3750, 'Kenaikan permintaan biodiesel'],
            3 => [3900, 'Penyesuaian kuartalan'],
        ];

        foreach ($history as $monthsAgo => [$price, $notes]) {
            OilPrice::updateOrCreate(
                ['effective_date' => now()->subMonthsNoOverflow($monthsAgo)->startOfMonth()->toDateString()],
                ['price_per_liter' => $price, 'is_active' => true, 'notes' => $notes],
            );
        }

        // Harga yang pernah diumumkan lalu dibatalkan, agar daftar harga
        // admin juga memperlihatkan entri nonaktif.
        OilPrice::updateOrCreate(
            ['effective_date' => now()->subMonthsNoOverflow(2)->startOfMonth()->addDays(14)->toDateString()],
            ['price_per_liter' => 4500, 'is_active' => false, 'notes' => 'Salah input, dibatalkan'],
        );
    }
}

<?php

namespace Database\Seeders;

use App\Models\Distribution;
use App\Models\Partner;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

/**
 * Penyaluran jelantah dari mitra ke pengolah, dihitung dari stok yang benar-benar terkumpul.
 *
 * Setiap akhir bulan, mitra yang stoknya melewati 60% kapasitas menyalurkan
 * sebagian hingga tersisa sekitar 25%. Dengan begitu penyaluran tidak pernah
 * melebihi yang masuk dan tidak ada mitra yang penuh, sebab mitra penuh
 * menolak setoran baru.
 */
class DistributionSeeder extends Seeder
{
    private const DESTINATIONS = [
        'PT Biodiesel Nusantara, Cikarang',
        'Pabrik Sabun Hijau Lestari, Purwakarta',
        'Koperasi Energi Terbarukan Karawang',
    ];

    private const NOTES = 'Data demo';

    public function run(): void
    {
        if (Distribution::where('notes', self::NOTES)->exists()) {
            $this->command?->warn('  Penyaluran demo sudah ada, DistributionSeeder dilewati.');

            return;
        }

        foreach (Partner::where('status', 'active')->get() as $partner) {
            $this->distributeFor($partner);
        }
    }

    private function distributeFor(Partner $partner): void
    {
        $capacity = (float) $partner->capacity_liter;

        if ($capacity <= 0) {
            return;
        }

        $collectedPerMonth = Transaction::query()
            ->where('partner_id', $partner->id)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->get(['actual_liter', 'completed_at'])
            ->groupBy(fn (Transaction $transaction) => $transaction->completed_at->format('Y-m'))
            ->map(fn ($transactions) => (float) $transactions->sum('actual_liter'));

        $stock = 0.0;

        foreach (range(11, 0) as $monthsAgo) {
            $month = now()->subMonthsNoOverflow($monthsAgo);
            $stock += $collectedPerMonth->get($month->format('Y-m'), 0.0);

            $threshold = $monthsAgo === 0 ? 0.5 : 0.6;

            if ($stock <= $capacity * $threshold) {
                continue;
            }

            $volume = round($stock - $capacity * 0.25, 2);
            $stock -= $volume;

            Distribution::create([
                'partner_id' => $partner->id,
                'volume_liter' => $volume,
                'destination' => self::DESTINATIONS[$monthsAgo % count(self::DESTINATIONS)],
                'distributed_at' => ($monthsAgo === 0 ? now()->subDay() : $month->copy()->endOfMonth())->toDateString(),
                'notes' => self::NOTES,
            ]);
        }
    }
}

<?php

namespace Database\Factories;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $liter = fake()->randomFloat(2, 3, 20);
        $harga = 4000;

        return [
            'code' => 'CNT-'.now()->format('ymd').'-'.fake()->unique()->bothify('??####'),
            'user_id' => User::factory()->state(['role' => 'user']),
            'oil_price_id' => OilPrice::factory(),
            'partner_id' => Partner::factory(),
            'estimated_liter' => $liter,
            'price_per_liter' => $harga,
            'estimated_total' => (int) round($liter * $harga),
            'pickup_fee' => 0,
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_PENDING,
        ];
    }

    public function pickup(): static
    {
        return $this->state(fn () => ['method' => Transaction::METHOD_PICKUP]);
    }

    /**
     * Transaksi yang sudah selesai diverifikasi beserta volume aktualnya.
     *
     * completed_at ikut diisi. Transaksi berstatus selesai tanpa kolom itu
     * tidak pernah terjadi lewat alur verifikasi, sehingga membiarkannya kosong
     * membuat factory ini menghasilkan keadaan yang mustahil: tenggat sanggahan
     * dihitung dari completed_at, dan yang kosong berarti jendela sanggahannya
     * tertutup sejak awal. Hitungan harian dan bulanan di ketiga dasbor juga
     * berpatokan padanya, jadi tanpa kolom ini transaksi yang sudah selesai
     * tidak terhitung di periode mana pun.
     *
     * Waktunya bisa ditimpa seperti atribut lain bila testnya memang menguji
     * transaksi lama.
     */
    public function completed(?float $actualLiter = null): static
    {
        return $this->state(function (array $attributes) use ($actualLiter) {
            $liter = $actualLiter ?? (float) $attributes['estimated_liter'];

            return [
                'status' => Transaction::STATUS_COMPLETED,
                'actual_liter' => $liter,
                'total_value' => max((int) round($liter * $attributes['price_per_liter']) - (int) $attributes['pickup_fee'], 0),
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'paid_at' => now(),
                'completed_at' => now(),
            ];
        });
    }

    public function rejected(string $reason = 'Jelantah tercampur air.'): static
    {
        return $this->state(fn () => [
            'status' => Transaction::STATUS_REJECTED,
            'rejection_reason' => $reason,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pickup>
 */
class PickupFactory extends Factory
{
    protected $model = Pickup::class;

    public function definition(): array
    {
        return [
            'transaction_id' => Transaction::factory(),
            'partner_id' => Partner::factory(),
            'address' => fake()->streetAddress(),
            'latitude' => fake()->randomFloat(7, -6.35, -6.25),
            'longitude' => fake()->randomFloat(7, 107.25, 107.35),
            'status' => 'pending',
        ];
    }

    /**
     * Dijadwalkan sekian hari dari sekarang. Dipakai untuk menguji urutan antrian.
     */
    public function scheduledInDays(int $days, string $time = '08:00'): static
    {
        return $this->state(fn () => [
            'pickup_date' => now()->addDays($days)->toDateString(),
            'pickup_time' => $time,
        ]);
    }

    public function assignedTo(User $employee): static
    {
        return $this->state(fn () => [
            'assigned_user_id' => $employee->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);
    }
}

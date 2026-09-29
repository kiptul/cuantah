<?php

namespace Database\Factories;

use App\Models\Distribution;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Distribution>
 */
class DistributionFactory extends Factory
{
    protected $model = Distribution::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'volume_liter' => fake()->randomFloat(2, 5, 50),
            'destination' => 'Pabrik Biodiesel '.fake()->city(),
            'distributed_at' => now()->toDateString(),
        ];
    }
}

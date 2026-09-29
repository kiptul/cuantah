<?php

namespace Database\Factories;

use App\Models\OilPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OilPrice>
 */
class OilPriceFactory extends Factory
{
    protected $model = OilPrice::class;

    public function definition(): array
    {
        return [
            'price_per_liter' => 4000,
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function perLiter(int $harga): static
    {
        return $this->state(fn () => ['price_per_liter' => $harga]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partner>
 */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'name' => 'Mitra '.fake()->unique()->city(),
            'type' => fake()->randomElement(['Collector', 'Processor']),
            'phone' => '08'.fake()->numerify('##########'),
            'address' => fake()->streetAddress(),
            // Koordinat sekitar Karawang, wilayah operasi awal CUANTAH.
            'latitude' => fake()->randomFloat(7, -6.35, -6.25),
            'longitude' => fake()->randomFloat(7, 107.25, 107.35),
            'capacity_liter' => fake()->numberBetween(100, 1000),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => 'inactive']);
    }

    /**
     * Titik tetap, dipakai bila jarak ke mitra perlu dapat diprediksi.
     */
    public function atKarawang(): static
    {
        return $this->state(fn () => ['latitude' => -6.3055, 'longitude' => 107.3053]);
    }
}

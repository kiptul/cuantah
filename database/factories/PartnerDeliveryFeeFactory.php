<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerDeliveryFee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerDeliveryFee>
 */
class PartnerDeliveryFeeFactory extends Factory
{
    protected $model = PartnerDeliveryFee::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'min_distance_km' => 3,
            'max_distance_km' => 10,
            'fee' => 10000,
        ];
    }
}

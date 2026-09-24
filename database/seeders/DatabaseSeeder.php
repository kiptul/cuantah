<?php

namespace Database\Seeders;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@cuantah.test'],
            ['name' => 'Admin CUANTAH', 'phone' => '081200000001', 'role' => 'admin', 'password' => Hash::make('password')]
        );

        User::updateOrCreate(
            ['email' => 'user@cuantah.test'],
            ['name' => 'Rumah Tangga Demo', 'phone' => '081200000002', 'role' => 'user', 'password' => Hash::make('password')]
        );

        $employee = User::updateOrCreate(
            ['email' => 'karyawan@cuantah.test'],
            ['name' => 'Karyawan Pickup Demo', 'phone' => '081200000004', 'role' => 'employee', 'password' => Hash::make('password')]
        );

        OilPrice::updateOrCreate(
            ['effective_date' => now()->toDateString()],
            ['price_per_liter' => 4000, 'is_active' => true, 'notes' => 'Harga awal MVP CUANTAH']
        );

        $partner = Partner::updateOrCreate(
            ['name' => 'Mitra Angkut Karawang'],
            [
                'type' => 'Collector',
                'phone' => '081200000003',
                'address' => 'Jl. Tuparev, Karawang',
                'latitude' => -6.3055,
                'longitude' => 107.3053,
                'capacity_liter' => 500,
                'status' => 'active',
            ]
        );

        $partner->deliveryFees()->delete();
        $partner->deliveryFees()->createMany([
            ['min_distance_km' => 3, 'max_distance_km' => 5, 'fee' => 10000],
            ['min_distance_km' => 5, 'max_distance_km' => 10, 'fee' => 20000],
            ['min_distance_km' => 10, 'max_distance_km' => null, 'fee' => 30000],
        ]);

        $admin->partners()->syncWithoutDetaching([$partner->id]);
        $employee->partners()->syncWithoutDetaching([$partner->id]);
    }
}

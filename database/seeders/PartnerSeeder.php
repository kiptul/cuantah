<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

/**
 * Mitra tambahan di sekitar Karawang beserta tarif ongkir jemputnya.
 *
 * Mitra utama "Mitra Angkut Karawang" tetap dibuat oleh DatabaseSeeder;
 * seeder ini menambah mitra lain supaya pemilihan mitra, pembatasan akses
 * per mitra, dan stok per mitra punya data untuk ditampilkan.
 */
class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $partners = [
            [
                'name' => 'Bank Jelantah Cikampek',
                'type' => 'Pengepul',
                'phone' => '081200000011',
                'address' => 'Jl. Ahmad Yani No. 21, Cikampek, Karawang',
                'latitude' => -6.4097,
                'longitude' => 107.4594,
                'capacity_liter' => 400,
                'status' => 'active',
                'fees' => [
                    ['min_distance_km' => 3, 'max_distance_km' => 7, 'fee' => 8000],
                    ['min_distance_km' => 7, 'max_distance_km' => null, 'fee' => 15000],
                ],
            ],
            [
                'name' => 'Olah Minyak Telukjambe',
                'type' => 'Pengolah',
                'phone' => '081200000012',
                'address' => 'Jl. Raya Telukjambe No. 8, Telukjambe Timur, Karawang',
                'latitude' => -6.3268,
                'longitude' => 107.2810,
                'capacity_liter' => 800,
                'status' => 'active',
                'fees' => [
                    ['min_distance_km' => 2, 'max_distance_km' => 6, 'fee' => 7000],
                    ['min_distance_km' => 6, 'max_distance_km' => 12, 'fee' => 14000],
                    ['min_distance_km' => 12, 'max_distance_km' => null, 'fee' => 25000],
                ],
            ],
            [
                'name' => 'Pengepul Rengasdengklok',
                'type' => 'Pengepul',
                'phone' => '081200000013',
                'address' => 'Jl. Proklamasi, Rengasdengklok, Karawang',
                'latitude' => -6.1588,
                'longitude' => 107.2970,
                'capacity_liter' => 250,
                'status' => 'inactive',
                'fees' => [],
            ],
        ];

        foreach ($partners as $data) {
            $fees = $data['fees'];
            unset($data['fees']);

            $partner = Partner::updateOrCreate(['name' => $data['name']], $data);

            $partner->deliveryFees()->delete();
            $partner->deliveryFees()->createMany($fees);
        }
    }
}

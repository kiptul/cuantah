<?php

namespace Database\Seeders;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $password = $this->passwordAkunContoh();

        $admin = User::updateOrCreate(
            ['email' => 'admin@cuantah.test'],
            // ['name' => 'Admin CUANTAH', 'phone' => '081200000001', 'role' => 'admin', 'password' => Hash::make($password)]
            ['name' => 'Admin CUANTAH', 'phone' => '081200000001', 'role' => 'admin', 'password' => Hash::make('admin')]
        );

        User::updateOrCreate(
            ['email' => 'user@cuantah.test'],
            // ['name' => 'Rumah Tangga Demo', 'phone' => '081200000002', 'role' => 'user', 'password' => Hash::make($password)]
            ['name' => 'Rumah Tangga Demo', 'phone' => '081200000002', 'role' => 'user', 'password' => Hash::make('user')]
        );

        $employee = User::updateOrCreate(
            ['email' => 'karyawan@cuantah.test'],
            // ['name' => 'Karyawan Pickup Demo', 'phone' => '081200000004', 'role' => 'employee', 'password' => Hash::make($password)]
            ['name' => 'Karyawan Pickup Demo', 'phone' => '081200000004', 'role' => 'employee', 'password' => Hash::make('karyawan')]
        );

        OilPrice::updateOrCreate(
            ['effective_date' => now()->toDateString()],
            ['price_per_liter' => 4000, 'is_active' => true, 'notes' => 'Harga awal MVP CUANTAH']
        );

        $partner = Partner::updateOrCreate(
            ['name' => 'Mitra Angkut Karawang'],
            [
                'type' => 'Pengepul',
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

        $this->call([
            PartnerSeeder::class,
            OilPriceHistorySeeder::class,
        ]);
        $this->callWith([StaffSeeder::class, DepositorSeeder::class], ['password' => $password]);
        $this->call([
            TransactionSeeder::class,
            DistributionSeeder::class,
        ]);
    }

    /**
     * Kata sandi untuk ketiga akun contoh.
     *
     * Sebelumnya nilainya tertulis sebagai "password" di dalam berkas ini.
     * Begitu seeder dijalankan di server, tiga akun termasuk admin terbuka
     * bagi siapa pun yang pernah membaca repositori ini. Sekarang nilainya
     * diambil dari SEED_PASSWORD, dan bila tidak diisi dibuatkan acak lalu
     * ditampilkan sekali di layar.
     */
    private function passwordAkunContoh(): string
    {
        $dariEnv = trim((string) config('cuantah.seed_password'));

        if ($dariEnv !== '') {
            return $dariEnv;
        }

        $acak = Str::password(16, symbols: false);

        $this->command?->getOutput()->writeln(
            "  <fg=yellow>Password akun contoh (admin/karyawan/user@cuantah.test): <options=bold>{$acak}</></>"
        );
        $this->command?->getOutput()->writeln(
            '  <fg=gray>Catat sekarang, nilainya tidak disimpan. Atur SEED_PASSWORD di .env untuk menentukan sendiri.</>'
        );

        return $acak;
    }
}

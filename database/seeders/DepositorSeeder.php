<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Penyetor rumah tangga dan UMKM.
 *
 * Aplikasi tidak menyimpan alamat tetap penyetor; halaman setor mengingat
 * alamat dari pickup terakhir. Karena itu lokasi di sini hanya dipakai
 * TransactionSeeder sebagai alamat penjemputan, dan lewat pickup itulah form
 * setor akun demo langsung terisi.
 */
class DepositorSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: float, 3: float}>
     */
    private const DEPOSITORS = [
        ['Warung Makan Bu Ani', 'Jl. Kertabumi No. 12, Karawang Kulon', -6.3011, 107.2985],
        ['Rina Wulandari', 'Perum Galuh Mas Blok C3 No. 7, Telukjambe', -6.3290, 107.2960],
        ['Pecel Lele Mas Joko', 'Jl. Galuh Mas Raya, Teluk Jambe', -6.3238, 107.2893],
        ['Hendra Gunawan', 'Jl. Arteri Tol Karawang Barat No. 4', -6.3367, 107.2752],
        ['Gorengan Pak Darto', 'Jl. Tuparev No. 88, Karawang Wetan', -6.3121, 107.3178],
        ['Nur Aisyah', 'Perum Bumi Teluk Jambe Blok F2', -6.3483, 107.2817],
        ['Katering Sari Rasa', 'Jl. Kosambi Raya No. 5, Klari', -6.3620, 107.3605],
        ['Fajar Nugroho', 'Jl. Pangkal Perjuangan No. 31, Karawang Barat', -6.2905, 107.2876],
        ['Ayam Geprek Cikampek', 'Jl. Jend. Sudirman No. 17, Cikampek', -6.4021, 107.4512],
        ['Maya Kartika', 'Perum Dawuan Indah Blok B5, Cikampek', -6.4185, 107.4402],
        ['Bakso Pak Kumis', 'Jl. Raya Kota Baru No. 3, Cikampek', -6.4252, 107.4700],
        ['Yusuf Maulana', 'Jl. Interchange Karawang Timur No. 9', -6.3256, 107.3391],
        ['Kedai Kopi Senja', 'Jl. Ahmad Yani No. 45, Karawang', -6.3074, 107.3010],
        ['Lina Marlina', 'Perum Puri Kosambi Blok A1, Klari', -6.3741, 107.3520],
    ];

    /**
     * Lokasi penjemputan tiap akun penyetor demo, termasuk user@cuantah.test.
     *
     * @return array<string, array{address: string, latitude: float, longitude: float}>
     */
    public static function locations(): array
    {
        $locations = [
            'user@cuantah.test' => ['address' => 'Perum Karaba Indah Blok D No. 14, Karawang', 'latitude' => -6.2861, 'longitude' => 107.3009],
        ];

        foreach (self::DEPOSITORS as $index => [, $address, $latitude, $longitude]) {
            $locations[self::emailFor($index)] = ['address' => $address, 'latitude' => $latitude, 'longitude' => $longitude];
        }

        return $locations;
    }

    public function run(string $password): void
    {
        $hashed = Hash::make($password);

        foreach (self::DEPOSITORS as $index => [$name]) {
            User::updateOrCreate(
                ['email' => self::emailFor($index)],
                ['name' => $name, 'phone' => '08131000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), 'role' => 'user', 'password' => $hashed],
            );
        }
    }

    private static function emailFor(int $index): string
    {
        return 'penyetor'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'@cuantah.test';
    }
}

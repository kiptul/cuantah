<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Penyetor rumah tangga dan UMKM beserta alamat penjemputannya.
 */
class DepositorSeeder extends Seeder
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string, 3: float, 4: float}>
     */
    private const DEPOSITORS = [
        ['Warung Makan Bu Ani', 'Jl. Kertabumi No. 12, Karawang Kulon', 'UMKM', -6.3011, 107.2985],
        ['Rina Wulandari', 'Perum Galuh Mas Blok C3 No. 7, Telukjambe', 'Rumah', -6.3290, 107.2960],
        ['Pecel Lele Mas Joko', 'Jl. Galuh Mas Raya, Teluk Jambe', 'UMKM', -6.3238, 107.2893],
        ['Hendra Gunawan', 'Jl. Arteri Tol Karawang Barat No. 4', 'Rumah', -6.3367, 107.2752],
        ['Gorengan Pak Darto', 'Jl. Tuparev No. 88, Karawang Wetan', 'UMKM', -6.3121, 107.3178],
        ['Nur Aisyah', 'Perum Bumi Teluk Jambe Blok F2', 'Rumah', -6.3483, 107.2817],
        ['Katering Sari Rasa', 'Jl. Kosambi Raya No. 5, Klari', 'UMKM', -6.3620, 107.3605],
        ['Fajar Nugroho', 'Jl. Pangkal Perjuangan No. 31, Karawang Barat', 'Rumah', -6.2905, 107.2876],
        ['Ayam Geprek Cikampek', 'Jl. Jend. Sudirman No. 17, Cikampek', 'UMKM', -6.4021, 107.4512],
        ['Maya Kartika', 'Perum Dawuan Indah Blok B5, Cikampek', 'Rumah', -6.4185, 107.4402],
        ['Bakso Pak Kumis', 'Jl. Raya Kota Baru No. 3, Cikampek', 'UMKM', -6.4252, 107.4700],
        ['Yusuf Maulana', 'Jl. Interchange Karawang Timur No. 9', 'Rumah', -6.3256, 107.3391],
        ['Kedai Kopi Senja', 'Jl. Ahmad Yani No. 45, Karawang', 'UMKM', -6.3074, 107.3010],
        ['Lina Marlina', 'Perum Puri Kosambi Blok A1, Klari', 'Rumah', -6.3741, 107.3520],
    ];

    public function run(string $password): void
    {
        $hashed = Hash::make($password);

        foreach (self::DEPOSITORS as $index => [$name, $address, $label, $latitude, $longitude]) {
            $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);

            $user = User::updateOrCreate(
                ['email' => "penyetor{$number}@cuantah.test"],
                ['name' => $name, 'phone' => '08131000'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT), 'role' => 'user', 'password' => $hashed],
            );

            UserAddress::updateOrCreate(
                ['user_id' => $user->id, 'label' => $label],
                ['address' => $address, 'latitude' => $latitude, 'longitude' => $longitude],
            );
        }

        // Akun demo utama juga diberi alamat agar form setor langsung terisi.
        $demo = User::where('email', 'user@cuantah.test')->first();

        if ($demo) {
            UserAddress::updateOrCreate(
                ['user_id' => $demo->id, 'label' => 'Rumah'],
                ['address' => 'Perum Karaba Indah Blok D No. 14, Karawang', 'latitude' => -6.2861, 'longitude' => 107.3009],
            );
        }
    }
}

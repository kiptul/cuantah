<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Admin dan karyawan tambahan, masing-masing terikat pada mitranya.
 */
class StaffSeeder extends Seeder
{
    public function run(string $password): void
    {
        $hashed = Hash::make($password);
        $partners = Partner::pluck('id', 'name');

        $staff = [
            ['admin.cikampek@cuantah.test', 'Admin Cikampek', '081200000021', 'admin', ['Bank Jelantah Cikampek']],
            ['admin.telukjambe@cuantah.test', 'Admin Telukjambe', '081200000022', 'admin', ['Olah Minyak Telukjambe']],
            ['budi@cuantah.test', 'Budi Santoso', '081200000031', 'employee', ['Mitra Angkut Karawang']],
            ['siti@cuantah.test', 'Siti Rahmawati', '081200000032', 'employee', ['Mitra Angkut Karawang', 'Olah Minyak Telukjambe']],
            ['agus@cuantah.test', 'Agus Pratama', '081200000033', 'employee', ['Bank Jelantah Cikampek']],
            ['dewi@cuantah.test', 'Dewi Lestari', '081200000034', 'employee', ['Olah Minyak Telukjambe']],
        ];

        foreach ($staff as [$email, $name, $phone, $role, $partnerNames]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                ['name' => $name, 'phone' => $phone, 'role' => $role, 'password' => $hashed],
            );

            $user->partners()->syncWithoutDetaching(
                collect($partnerNames)->map(fn (string $partnerName) => $partners[$partnerName])->all()
            );
        }

        // Admin utama memantau seluruh mitra aktif.
        User::where('email', 'admin@cuantah.test')->first()?->partners()->syncWithoutDetaching(
            Partner::where('status', 'active')->pluck('id')->all()
        );
    }
}

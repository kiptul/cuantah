<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kata sandi akun contoh tidak boleh tertulis di dalam repositori.
 *
 * Seeder dijalankan di server pada setiap penyebaran. Kata sandi yang tertanam
 * di berkas ini berarti tiga akun, termasuk admin, terbuka bagi siapa pun yang
 * pernah membuka repositorinya, dan repositori ini publik.
 *
 * Mekanismenya sendiri sudah ada sejak lama: SEED_PASSWORD, dengan kata sandi
 * acak bila dibiarkan kosong. Yang terjadi adalah baris yang memakainya
 * dikomentari dan diganti nilai tetap. Test ini menjaga agar jalan itu tidak
 * ditempuh lagi tanpa disadari.
 */
class SeedPasswordTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private const AKUN_CONTOH = [
        'admin@cuantah.test',
        'karyawan@cuantah.test',
        'user@cuantah.test',
    ];

    public function test_every_sample_account_uses_the_password_from_the_environment(): void
    {
        config(['cuantah.seed_password' => 'KataSandiUji2026']);

        $this->seed(DatabaseSeeder::class);

        foreach (self::AKUN_CONTOH as $email) {
            $user = User::where('email', $email)->first();

            $this->assertNotNull($user, "Akun contoh {$email} tidak dibuat seeder.");
            $this->assertTrue(
                Hash::check('KataSandiUji2026', $user->password),
                "Akun {$email} tidak memakai SEED_PASSWORD, jadi ada nilai tetap di seeder.",
            );
        }
    }

    public function test_no_sample_account_keeps_a_password_that_is_written_in_the_repository(): void
    {
        config(['cuantah.seed_password' => 'KataSandiUji2026']);

        $this->seed(DatabaseSeeder::class);

        // Nilai yang pernah tertanam di berkas seeder, beserta tebakan pertama
        // siapa pun yang melihat ketiga alamat surel itu.
        $pernahTertanam = ['admin', 'password', 'karyawan', 'user', 'cuantah', '12345678'];

        foreach (self::AKUN_CONTOH as $email) {
            $user = User::where('email', $email)->firstOrFail();

            foreach ($pernahTertanam as $tebakan) {
                $this->assertFalse(
                    Hash::check($tebakan, $user->password),
                    "Akun {$email} bisa dimasuki dengan \"{$tebakan}\".",
                );
            }
        }
    }

    public function test_an_empty_setting_produces_a_password_that_is_not_guessable(): void
    {
        // Dibiarkan kosong berarti seeder membuatkan sendiri, bukan jatuh ke
        // nilai bawaan yang sama di setiap pemasangan.
        config(['cuantah.seed_password' => '']);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@cuantah.test')->firstOrFail();

        foreach (['admin', 'password', 'karyawan', '', 'cuantah'] as $tebakan) {
            $this->assertFalse(
                Hash::check($tebakan, $admin->password),
                "Tanpa SEED_PASSWORD, akun admin masih bisa dimasuki dengan \"{$tebakan}\".",
            );
        }
    }
}

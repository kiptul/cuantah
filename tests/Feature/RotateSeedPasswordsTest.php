<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RotateSeedPasswordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    public function test_password_bawaan_akun_seed_diganti_dan_remember_token_diputar(): void
    {
        $admin = User::factory()->create(['email' => 'admin@cuantah.test', 'role' => 'admin']);
        $karyawan = User::factory()->create(['email' => 'karyawan@cuantah.test', 'role' => 'employee']);
        $pengguna = User::factory()->create(['email' => 'user@cuantah.test', 'role' => 'user']);

        $this->artisan('cuantah:rotate-seed-passwords')->assertSuccessful();

        foreach ([$admin, $karyawan, $pengguna] as $sebelum) {
            $sesudah = $sebelum->fresh();

            $this->assertFalse(
                Hash::check('password', $sesudah->password),
                "Password bawaan {$sesudah->email} seharusnya sudah tidak berlaku."
            );
            $this->assertNotSame(
                $sebelum->remember_token,
                $sesudah->remember_token,
                "Remember token {$sesudah->email} seharusnya diputar agar cookie lama tidak sah."
            );
        }
    }

    public function test_akun_yang_passwordnya_sudah_diganti_dilewati(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@cuantah.test',
            'role' => 'admin',
            'password' => 'password-yang-sudah-diganti',
        ]);

        $this->artisan('cuantah:rotate-seed-passwords')->assertSuccessful();

        $this->assertTrue(
            Hash::check('password-yang-sudah-diganti', $admin->fresh()->password),
            'Password yang sudah dirotasi seharusnya tidak diganti lagi.'
        );
    }

    public function test_opsi_force_merotasi_akun_yang_passwordnya_sudah_diganti(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@cuantah.test',
            'role' => 'admin',
            'password' => 'password-yang-sudah-diganti',
        ]);

        $this->artisan('cuantah:rotate-seed-passwords', ['--force' => true])->assertSuccessful();

        $this->assertFalse(
            Hash::check('password-yang-sudah-diganti', $admin->fresh()->password),
            'Dengan --force password seharusnya tetap dirotasi.'
        );
    }

    public function test_hanya_merotasi_email_yang_diminta(): void
    {
        $admin = User::factory()->create(['email' => 'admin@cuantah.test', 'role' => 'admin']);
        $karyawan = User::factory()->create(['email' => 'karyawan@cuantah.test', 'role' => 'employee']);

        $this->artisan('cuantah:rotate-seed-passwords', ['--email' => ['admin@cuantah.test']])
            ->assertSuccessful();

        $this->assertFalse(Hash::check('password', $admin->fresh()->password));
        $this->assertTrue(
            Hash::check('password', $karyawan->fresh()->password),
            'Akun di luar --email seharusnya tidak tersentuh.'
        );
    }

    public function test_gagal_bila_tidak_ada_akun_yang_cocok(): void
    {
        $this->artisan('cuantah:rotate-seed-passwords', ['--email' => ['tidak-ada@cuantah.test']])
            ->assertFailed();
    }
}

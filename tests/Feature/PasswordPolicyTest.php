<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Requests\Auth\NewPasswordRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Satu kebijakan kata sandi untuk semua pintu masuk.
 *
 * Aturannya sebelumnya ditulis ulang di lima tempat, dan ketiga pintu yang
 * paling terbuka justru yang paling longgar: pendaftaran mandiri dan reset
 * kata sandi hanya menuntut delapan karakter, sedangkan mengubah kata sandi
 * sendiri menuntut huruf dan angka. Orang bisa mendaftar dengan "12345678",
 * lalu ditolak ketika menetapkan kata sandi yang sama dari pengaturan akun.
 *
 * Yang dijaga di sini bukan ketatnya aturan, melainkan bahwa aturannya sama di
 * setiap pintu. Aturan yang ditulis ulang per berkas akan melenceng lagi.
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Berkas permintaan beserta kolom kata sandinya.
     *
     * @return array<string, class-string>
     */
    private function pintuMasuk(): array
    {
        return [
            'pendaftaran mandiri' => RegisterRequest::class,
            'reset kata sandi' => NewPasswordRequest::class,
            'ganti kata sandi sendiri' => UpdatePasswordRequest::class,
            'admin membuat akun' => StoreUserRequest::class,
            'admin menyunting akun' => UpdateUserRequest::class,
        ];
    }

    private function diterima(string $kelas, string $sandi): bool
    {
        $aturan = (new $kelas)->rules()['password'];

        return ! Validator::make(
            ['password' => $sandi, 'password_confirmation' => $sandi],
            ['password' => $aturan],
        )->fails();
    }

    public function test_no_entry_point_accepts_a_password_without_letters(): void
    {
        foreach ($this->pintuMasuk() as $nama => $kelas) {
            $this->assertFalse(
                $this->diterima($kelas, '12345678'),
                "Pintu \"{$nama}\" menerima \"12345678\", kata sandi tanpa satu pun huruf.",
            );
        }
    }

    public function test_no_entry_point_accepts_a_password_without_numbers(): void
    {
        foreach ($this->pintuMasuk() as $nama => $kelas) {
            $this->assertFalse(
                $this->diterima($kelas, 'rahasiabanget'),
                "Pintu \"{$nama}\" menerima kata sandi tanpa satu pun angka.",
            );
        }
    }

    public function test_no_entry_point_accepts_a_password_shorter_than_eight(): void
    {
        foreach ($this->pintuMasuk() as $nama => $kelas) {
            $this->assertFalse(
                $this->diterima($kelas, 'abc123'),
                "Pintu \"{$nama}\" menerima kata sandi enam karakter.",
            );
        }
    }

    public function test_every_entry_point_accepts_the_same_valid_password(): void
    {
        foreach ($this->pintuMasuk() as $nama => $kelas) {
            $this->assertTrue(
                $this->diterima($kelas, 'jelantah2026'),
                "Pintu \"{$nama}\" menolak kata sandi yang memenuhi kebijakan.",
            );
        }
    }

    public function test_registration_rejects_the_password_the_account_settings_would_reject(): void
    {
        // Diuji lewat HTTP, bukan hanya lewat aturan, sebab inilah yang dialami
        // orang: mendaftar berhasil, lalu kata sandinya sendiri tidak sah.
        $this->post(route('register'), [
            'name' => 'Uji Kebijakan',
            'email' => 'uji.kebijakan@contoh.test',
            'phone' => '081200008888',
            'password' => '12345678',
            'password_confirmation' => '12345678',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'uji.kebijakan@contoh.test']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Pengaturan akun milik pengguna sendiri.
 *
 * Sebelum ini tidak ada jalan apa pun untuk mengganti kata sandi dari
 * dalam aplikasi, sehingga akun bawaan bertahan dengan kata sandi contoh.
 */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    public function test_guest_cannot_open_the_account_page(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_depositor_can_update_their_own_details(): void
    {
        $user = User::factory()->create(['role' => 'user', 'phone' => null]);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => 'Nama Baru',
                'email' => 'nama.baru@cuantah.test',
                'phone' => '081200000009',
            ])
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('081200000009', $user->phone);
    }

    public function test_email_cannot_collide_with_another_account(): void
    {
        User::factory()->create(['email' => 'sudah.dipakai@cuantah.test']);
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->put(route('profile.update'), [
                'name' => $user->name,
                'email' => 'sudah.dipakai@cuantah.test',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-lama-1')]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'tebakan-salah',
                'password' => 'password-baru-2',
                'password_confirmation' => 'password-baru-2',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password-lama-1', $user->fresh()->password));
    }

    public function test_password_must_contain_letters_and_numbers(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-lama-1')]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password-lama-1',
                'password' => 'hanyahurufsaja',
                'password_confirmation' => 'hanyahurufsaja',
            ])
            ->assertSessionHasErrors('password');
    }

    /**
     * Status respons ikut diperiksa. Tanpa itu sebuah galat 500 tetap lolos,
     * sebab halaman galat tidak meninggalkan pesan kesalahan di sesi
     * sementara kata sandinya sendiri sudah terlanjur berubah.
     */
    public function test_depositor_can_change_their_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-lama-1')]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('profile.password'), [
                'current_password' => 'password-lama-1',
                'password' => 'password-baru-2',
                'password_confirmation' => 'password-baru-2',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('password-baru-2', $user->fresh()->password));
    }

    /**
     * Sesi yang masih memakai sidik kata sandi lama harus berhenti berlaku.
     * Itulah alasan orang mengganti kata sandinya.
     */
    public function test_sessions_still_holding_the_old_password_stop_working(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-lama-1')]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password-lama-1',
                'password' => 'password-baru-2',
                'password_confirmation' => 'password-baru-2',
            ])
            ->assertSessionHasNoErrors();

        // Sesi lain menyimpan sidik kata sandi yang lama.
        $this->flushSession();
        $this->withSession(['password_hash_web' => Hash::make('password-lama-1')]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_the_session_that_changed_the_password_stays_signed_in(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password-lama-1')]);

        $this->actingAs($user)
            ->put(route('profile.password'), [
                'current_password' => 'password-lama-1',
                'password' => 'password-baru-2',
                'password_confirmation' => 'password-baru-2',
            ])
            ->assertSessionHasNoErrors();

        $this->get(route('dashboard'))->assertOk();
    }
}

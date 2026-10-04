<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas wewenang yang tidak terlihat dari daftar rute.
 *
 * Rute karyawan terbuka bagi admin, dan halaman pengguna melayani semua
 * admin. Keduanya sempat melewatkan pemeriksaan mitra, sehingga admin satu
 * mitra dapat menjangkau data dan akun mitra lain.
 */
class AccessBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    /**
     * Satu transaksi milik mitra lain, lengkap dengan pickup-nya.
     */
    private function transaksiMitraLain(): Transaction
    {
        $harga = OilPrice::factory()->create();
        $mitraLain = Partner::factory()->create(['name' => 'Mitra Lain']);

        $transaksi = Transaction::factory()->create([
            'partner_id' => $mitraLain->id,
            'oil_price_id' => $harga->id,
            'status' => Transaction::STATUS_VERIFICATION,
        ]);

        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $mitraLain->id,
            'status' => 'verification',
        ]);

        return $transaksi;
    }

    private function adminMitraSendiri(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach(Partner::factory()->create(['name' => 'Mitra Sendiri'])->id);

        return $admin;
    }

    public function test_admin_cannot_open_another_partners_transaction_through_the_employee_route(): void
    {
        $transaksi = $this->transaksiMitraLain();

        $this->actingAs($this->adminMitraSendiri())
            ->get(route('employee.transactions.show', $transaksi))
            ->assertForbidden();
    }

    public function test_admin_cannot_verify_another_partners_transaction_through_the_employee_route(): void
    {
        $transaksi = $this->transaksiMitraLain();

        $this->actingAs($this->adminMitraSendiri())
            ->post(route('employee.transactions.verify', $transaksi), [
                'actual_liter' => 9,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ])
            ->assertForbidden();

        $this->assertSame(Transaction::STATUS_VERIFICATION, $transaksi->fresh()->status);
    }

    public function test_employee_assigned_to_the_pickup_still_reaches_their_own_transaction(): void
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($mitra->id);

        $transaksi = Transaction::factory()->create([
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
            'status' => Transaction::STATUS_VERIFICATION,
        ]);
        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $mitra->id,
            'assigned_user_id' => $karyawan->id,
            'status' => 'verification',
        ]);

        $this->actingAs($karyawan)
            ->get(route('employee.transactions.show', $transaksi))
            ->assertOk();
    }

    public function test_user_list_hides_staff_of_other_partners(): void
    {
        $admin = $this->adminMitraSendiri();

        $adminLain = User::factory()->create(['role' => 'admin', 'name' => 'Admin Mitra Lain']);
        $adminLain->partners()->attach(Partner::factory()->create()->id);

        $penyetor = User::factory()->create(['role' => 'user', 'name' => 'Penyetor Umum']);

        $terlihat = $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->viewData('users')
            ->pluck('id');

        $this->assertTrue($terlihat->contains($admin->id), 'Admin harus melihat dirinya sendiri.');
        $this->assertTrue($terlihat->contains($penyetor->id), 'Penyetor tidak terikat mitra, jadi tetap tampil.');
        $this->assertFalse($terlihat->contains($adminLain->id), 'Admin mitra lain tidak boleh tampil.');
    }

    public function test_admin_cannot_change_the_account_of_another_partners_admin(): void
    {
        $admin = $this->adminMitraSendiri();

        $korban = User::factory()->create(['role' => 'admin', 'email' => 'korban@cuantah.test']);
        $mitraKorban = Partner::factory()->create();
        $korban->partners()->attach($mitraKorban->id);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $korban), [
                'name' => 'Diambil Alih',
                'email' => 'penyerang@contoh.test',
                'role' => 'admin',
                'partner_ids' => [$mitraKorban->id],
            ])
            ->assertForbidden();

        $this->assertSame('korban@cuantah.test', $korban->fresh()->email);
    }

    /**
     * Admin boleh menyetel kata sandi dan mengganti email pengguna, dan
     * keduanya cukup untuk mengambil alih akun. Pemiliknya harus bisa
     * melihat bahwa itu terjadi.
     */
    public function test_owner_is_notified_when_an_admin_changes_their_credentials(): void
    {
        $admin = $this->adminMitraSendiri();
        $penyetor = User::factory()->create(['role' => 'user', 'email' => 'lama@cuantah.test']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $penyetor), [
                'name' => $penyetor->name,
                'email' => 'baru@cuantah.test',
                'role' => 'user',
                'password' => 'password-baru-9',
                'password_confirmation' => 'password-baru-9',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $penyetor->id,
            'title' => 'Data masuk akunmu diubah admin',
        ]);
    }

    public function test_editing_only_the_name_leaves_no_credential_notice(): void
    {
        $admin = $this->adminMitraSendiri();
        $penyetor = User::factory()->create(['role' => 'user']);

        $this->actingAs($admin)
            ->put(route('admin.users.update', $penyetor), [
                'name' => 'Nama Dikoreksi',
                'email' => $penyetor->email,
                'role' => 'user',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('notifications', ['user_id' => $penyetor->id]);
        $this->assertSame('Nama Dikoreksi', $penyetor->fresh()->name);
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = $this->adminMitraSendiri();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'user',
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    /**
     * Tombol yang tidak mungkin berhasil sebaiknya tidak ditawarkan.
     */
    public function test_admin_detail_page_hides_actions_on_a_finished_transaction(): void
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($mitra->id);

        $selesai = Transaction::factory()->completed(4)->create([
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
        ]);

        // Yang dijaga adalah tindakannya disembunyikan. Kotak "Transaksi sudah
        // selesai" yang dulu ikut diperiksa di sini sudah dibuang: isinya
        // menyuruh mencatat penyesuaian lewat penyaluran, dan itu keliru sejak
        // koreksi volume tersedia tepat di bawahnya.
        $this->actingAs($admin)
            ->get(route('admin.transactions.show', $selesai))
            ->assertOk()
            ->assertDontSee('Tolak Transaksi')
            ->assertDontSee('Selesaikan Transaksi');
    }

    public function test_admin_detail_page_offers_actions_while_the_transaction_runs(): void
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($mitra->id);

        $berjalan = Transaction::factory()->create([
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
            'status' => Transaction::STATUS_VERIFICATION,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.transactions.show', $berjalan))
            ->assertOk()
            ->assertSee('Selesaikan Transaksi')
            ->assertSee('Tolak Transaksi');
    }

    public function test_staff_account_cannot_be_saved_without_a_partner(): void
    {
        $admin = $this->adminMitraSendiri();

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Karyawan Tanpa Mitra',
                'email' => 'tanpa.mitra@cuantah.test',
                'role' => 'employee',
                'password' => 'rahasia-sekali',
            ])
            ->assertSessionHasErrors('partner_ids');

        $this->assertDatabaseMissing('users', ['email' => 'tanpa.mitra@cuantah.test']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penolakan setoran oleh karyawan di lokasi.
 *
 * Karyawan yang tiba di tempat dan menemukan jelantah bercampur air tidak
 * punya jalan keluar: menyelesaikan transaksi berarti membayar barang yang
 * ditolak, membiarkannya berarti tugas menggantung, dan penyetor pun sudah
 * tidak bisa membatalkan sebab statusnya bukan lagi menunggu.
 *
 * Dipakai status ditolak, bukan dibatalkan. Pembatalan tidak menyimpan alasan
 * dan memberi tahu penyetor bahwa dialah yang membatalkan, padahal bukan.
 */
class EmployeeRejectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        /**
         * Dilewati hanya bila test memang akan memakai sqlite. Suite ini tidak
         * bergantung pada satu driver, jadi mengikat seluruh berkas pada satu
         * extension akan membuatnya tak pernah dieksekusi di mesin yang
         * menjalankannya lewat MySQL.
         */
        $connection = $_SERVER['DB_CONNECTION'] ?? (getenv('DB_CONNECTION') ?: 'sqlite');

        if ($connection === 'sqlite' && ! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    private Partner $mitra;

    private User $karyawan;

    private User $admin;

    private User $penyetor;

    private function siapkan(): void
    {
        $this->mitra = Partner::factory()->create();

        $this->karyawan = User::factory()->create(['role' => 'employee', 'name' => 'Karyawan Lapangan']);
        $this->karyawan->partners()->sync([$this->mitra->id]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->partners()->sync([$this->mitra->id]);

        $this->penyetor = User::factory()->create(['role' => 'user']);
    }

    private function tugas(): Transaction
    {
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => $this->penyetor->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_SCHEDULED,
        ]);

        $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'assigned',
            'assigned_user_id' => $this->karyawan->id,
        ]);

        return $transaksi;
    }

    public function test_karyawan_bisa_menolak_setoran_yang_ditugaskan_kepadanya(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->karyawan)
            ->post(route('employee.transactions.reject', $transaksi), [
                'rejection_reason' => 'Jelantah bercampur air dan sisa makanan.',
            ])
            ->assertSessionHas('success');

        $transaksi->refresh();

        $this->assertSame(Transaction::STATUS_REJECTED, $transaksi->status);
        $this->assertSame('Jelantah bercampur air dan sisa makanan.', $transaksi->rejection_reason);
        $this->assertSame('rejected', $transaksi->pickup->status);
    }

    public function test_alasan_wajib_diisi(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->karyawan)
            ->post(route('employee.transactions.reject', $transaksi), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(
            Transaction::STATUS_SCHEDULED,
            $transaksi->refresh()->status,
            'Tanpa alasan, penyetor tidak akan tahu apa yang perlu diperbaiki.'
        );
    }

    public function test_alasan_terlalu_singkat_ditolak(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->karyawan)
            // Tiga huruf, di bawah batas lima. "jelek" tepat lima dan justru sah.
            ->post(route('employee.transactions.reject', $transaksi), ['rejection_reason' => 'air'])
            ->assertSessionHasErrors('rejection_reason');
    }

    public function test_penyetor_menerima_alasannya_bukan_kabar_membatalkan_sendiri(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->karyawan)->post(route('employee.transactions.reject', $transaksi), [
            'rejection_reason' => 'Jelantah bercampur air.',
        ]);

        $kabar = Notification::where('user_id', $this->penyetor->id)->latest('id')->first();

        $this->assertNotNull($kabar);
        $this->assertSame('Transaksi ditolak', $kabar->title);
        $this->assertStringContainsString('Jelantah bercampur air.', $kabar->message);
        $this->assertStringNotContainsString(
            'Kamu membatalkan',
            $kabar->message,
            'Bukan penyetor yang membatalkan, jadi pesannya tidak boleh mengatakan begitu.'
        );
    }

    public function test_admin_mitra_dikabari_ada_penolakan_di_lapangan(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->karyawan)->post(route('employee.transactions.reject', $transaksi), [
            'rejection_reason' => 'Jelantah bercampur air.',
        ]);

        $kabar = Notification::where('user_id', $this->admin->id)->latest('id')->first();

        $this->assertNotNull($kabar, 'Admin mitra perlu tahu ada penjemputan yang gagal.');
        $this->assertStringContainsString('Karyawan Lapangan', $kabar->message);
    }

    public function test_penolakan_oleh_admin_tidak_mengabari_dirinya_sendiri(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->admin)->post(route('admin.transactions.reject', $transaksi), [
            'rejection_reason' => 'Jelantah bercampur air.',
        ])->assertSessionHas('success');

        $this->assertSame(
            0,
            Notification::where('user_id', $this->admin->id)->count(),
            'Mengabari seseorang tentang tindakannya sendiri hanya menambah kebisingan.'
        );
    }

    public function test_karyawan_lain_tidak_bisa_menolak_tugas_yang_bukan_miliknya(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $karyawanLain = User::factory()->create(['role' => 'employee']);
        $karyawanLain->partners()->sync([$this->mitra->id]);

        $this->actingAs($karyawanLain)
            ->post(route('employee.transactions.reject', $transaksi), ['rejection_reason' => 'Coba-coba menolak.'])
            ->assertForbidden();

        $this->assertSame(Transaction::STATUS_SCHEDULED, $transaksi->refresh()->status);
    }

    public function test_transaksi_yang_sudah_selesai_tidak_bisa_ditolak(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();
        $transaksi->update([
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => 3,
            'total_value' => 12000,
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);

        $this->actingAs($this->karyawan)
            ->post(route('employee.transactions.reject', $transaksi), ['rejection_reason' => 'Terlambat menolak.'])
            ->assertSessionHasErrors();

        $this->assertSame(Transaction::STATUS_COMPLETED, $transaksi->refresh()->status);
    }

    public function test_tombol_penolakan_ada_di_halaman_proses(): void
    {
        $this->siapkan();

        $this->actingAs($this->karyawan)
            ->get(route('employee.transactions.show', $this->tugas()))
            ->assertOk()
            ->assertSee('Tolak Setoran')
            ->assertSee('Alasan penolakan');
    }
}

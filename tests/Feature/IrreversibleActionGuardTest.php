<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jeda sebelum aksi yang tidak bisa dibatalkan di sisi karyawan.
 *
 * Proyek ini sudah menuntut konfirmasi pada penolakan transaksi dan pembatalan
 * setoran. Penyelesaian transaksi justru tidak, padahal ia yang paling
 * berkonsekuensi: nilainya langsung menjadi final dan satu-satunya jalan
 * keluar bagi penyetor adalah menyanggah dalam tiga hari.
 *
 * Pemindaian barcode juga langsung mengirim formnya, sehingga barcode yang
 * salah terbaca menugaskan transaksi orang lain tanpa sempat dilihat.
 */
class IrreversibleActionGuardTest extends TestCase
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

    private function siapkan(): void
    {
        $this->mitra = Partner::factory()->create();
        $this->karyawan = User::factory()->create(['role' => 'employee']);
        $this->karyawan->partners()->sync([$this->mitra->id]);
    }

    private function tugas(): Transaction
    {
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
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

    private function halamanProses(Transaction $transaksi): string
    {
        return $this->actingAs($this->karyawan)
            ->get(route('employee.transactions.show', $transaksi))
            ->assertOk()
            ->getContent();
    }

    public function test_penyelesaian_transaksi_meminta_konfirmasi(): void
    {
        $this->siapkan();
        $isi = $this->halamanProses($this->tugas());

        $this->assertStringContainsString(
            'window.confirm',
            $isi,
            'Penyelesaian transaksi tidak bisa dibatalkan, jadi ia perlu jeda seperti aksi tak terbalikkan lainnya.'
        );
    }

    public function test_konfirmasi_menyebut_angka_yang_akan_tercatat(): void
    {
        $this->siapkan();
        $isi = $this->halamanProses($this->tugas());

        /**
         * Bahaya yang nyata di sini adalah salah ketik volume. Pertanyaan umum
         * tidak akan menangkapnya, jadi pesannya wajib membacakan angkanya.
         */
        $this->assertStringContainsString('Volume ', $isi);
        $this->assertStringContainsString('penyetor menerima', $isi);
        $this->assertStringContainsString('tidak bisa diubah setelah ini', $isi);
    }

    public function test_konfirmasi_menyebut_kode_transaksinya(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->assertStringContainsString(
            'data-code="'.$transaksi->code.'"',
            $this->halamanProses($transaksi),
            'Kode dibawa ke form supaya pesannya menyebut transaksi yang benar.'
        );
    }

    public function test_pemindaian_tidak_langsung_mengirim_form(): void
    {
        $this->siapkan();

        $isi = $this->actingAs($this->karyawan)->get(route('employee.scan'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'form.submit()',
            $isi,
            'Barcode yang salah terbaca tidak boleh menugaskan transaksi orang lain tanpa sempat dilihat.'
        );
    }

    public function test_pemindaian_menampilkan_kodenya_untuk_dicocokkan(): void
    {
        $this->siapkan();

        $this->actingAs($this->karyawan)->get(route('employee.scan'))->assertOk()
            ->assertSee('Cocokkan dengan kode di layar penyetor');
    }

    public function test_aksi_tak_terbalikkan_lain_tetap_berkonfirmasi(): void
    {
        $this->siapkan();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([$this->mitra->id]);

        $isi = $this->actingAs($admin)
            ->get(route('admin.transactions.show', $this->tugas()))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'Statusnya tidak bisa dikembalikan',
            $isi,
            'Konfirmasi yang sudah ada tidak boleh ikut hilang.'
        );
    }
}

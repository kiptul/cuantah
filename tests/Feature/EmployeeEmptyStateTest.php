<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keadaan kosong pada bagian "Tugas saya" di dasbor karyawan.
 *
 * Penjemputan hanya sampai ke karyawan lewat penugasan admin. Keadaan kosong
 * karenanya tidak boleh menjanjikan pekerjaan yang bisa diambil sendiri, dan
 * satu-satunya tindakan mandiri yang tersisa adalah memindai QR penyetor
 * yang datang ke mitra.
 */
class EmployeeEmptyStateTest extends TestCase
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

    private function siapkanKaryawan(): void
    {
        $this->mitra = Partner::factory()->create();
        $this->karyawan = User::factory()->create(['role' => 'employee']);
        $this->karyawan->partners()->sync([$this->mitra->id]);
    }

    /**
     * Setoran jemput yang sudah diajukan penyetor tetapi belum ditugaskan
     * admin kepada siapa pun.
     */
    private function setoranBelumDitugaskan(): Transaction
    {
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_PENDING,
        ]);

        $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Belum Ditugaskan',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'pickup_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
            'assigned_user_id' => null,
        ]);

        return $transaksi;
    }

    private function blokKeadaanKosong(): string
    {
        $isi = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()->getContent();

        preg_match('/Tidak ada tugas terbuka.*?<\/div>/s', $isi, $cocok);

        return $cocok[0] ?? '';
    }

    public function test_mengarahkan_ke_scan_sebagai_satu_satunya_tindakan_mandiri(): void
    {
        $this->siapkanKaryawan();

        $blok = $this->blokKeadaanKosong();

        $this->assertStringContainsString('setelah admin menugaskannya kepadamu', $blok);
        $this->assertStringContainsString(route('employee.scan'), $blok);
    }

    public function test_tidak_menjanjikan_pekerjaan_yang_bisa_diambil_sendiri(): void
    {
        $this->siapkanKaryawan();
        $this->setoranBelumDitugaskan();

        $blok = $this->blokKeadaanKosong();

        /**
         * Ada setoran menunggu di mitra ini, tetapi belum ditugaskan. Karyawan
         * tidak punya cara mengambilnya, jadi menyebutnya hanya menimbulkan
         * harapan yang tidak bisa ditindaklanjuti.
         */
        $this->assertStringNotContainsString('pickup menunggu diambil', $blok);
        $this->assertStringNotContainsString('Cari Pickup', $blok);
    }

    public function test_setoran_yang_belum_ditugaskan_tidak_masuk_daftar_tugas(): void
    {
        $this->siapkanKaryawan();
        $this->setoranBelumDitugaskan();

        $halaman = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $this->assertSame(0, $halaman->viewData('tasks_count'));
        $halaman->assertSee('Tidak ada tugas terbuka');
    }

    public function test_keadaan_kosong_hilang_setelah_admin_menugaskan(): void
    {
        $this->siapkanKaryawan();
        $transaksi = $this->setoranBelumDitugaskan();

        app(TransactionService::class)
            ->assignPickupToEmployee($transaksi->pickup, $this->karyawan->id);

        $halaman = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $this->assertSame(1, $halaman->viewData('tasks_count'));
        $halaman->assertDontSee('Tidak ada tugas terbuka');
    }
}

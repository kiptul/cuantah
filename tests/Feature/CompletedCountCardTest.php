<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu jumlah setoran di dasbor penyetor.
 *
 * Kartu ini berdiri bersebelahan dengan CUAN diterima dan liter terkumpul,
 * sehingga angkanya terbaca sebagai pencapaian. Sebelumnya ia menghitung
 * seluruh transaksi termasuk yang ditolak dan dibatalkan, sehingga penolakan
 * ikut tampil sebagai keberhasilan.
 */
class CompletedCountCardTest extends TestCase
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

    private function transaksi(User $penyetor, string $status): Transaction
    {
        return Transaction::factory()->create([
            'user_id' => $penyetor->id,
            'partner_id' => Partner::factory(),
            'oil_price_id' => OilPrice::factory(),
            'status' => $status,
            'actual_liter' => $status === Transaction::STATUS_COMPLETED ? 1.0 : null,
            'total_value' => $status === Transaction::STATUS_COMPLETED ? 4000 : null,
            'payment_status' => $status === Transaction::STATUS_COMPLETED ? 'paid' : null,
        ]);
    }

    public function test_yang_ditolak_dan_dibatalkan_tidak_ikut_terhitung(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $this->transaksi($penyetor, Transaction::STATUS_COMPLETED);
        $this->transaksi($penyetor, Transaction::STATUS_COMPLETED);
        $this->transaksi($penyetor, Transaction::STATUS_REJECTED);
        $this->transaksi($penyetor, Transaction::STATUS_CANCELLED);

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(
            2,
            $halaman->viewData('completed_transactions'),
            'Setoran yang ditolak dan dibatalkan bukan pencapaian, jadi tidak boleh ikut terhitung.'
        );
    }

    public function test_yang_masih_berjalan_belum_terhitung_tetapi_disebut_di_keterangan(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $this->transaksi($penyetor, Transaction::STATUS_COMPLETED);
        $this->transaksi($penyetor, Transaction::STATUS_PENDING);
        $this->transaksi($penyetor, Transaction::STATUS_SCHEDULED);

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(1, $halaman->viewData('completed_transactions'));
        $halaman->assertSee('2 sedang berjalan.');
    }

    public function test_label_kartu_menyebut_apa_yang_benar_benar_dihitung(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->transaksi($penyetor, Transaction::STATUS_COMPLETED);

        $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->assertSee('Setoran selesai');
    }

    public function test_setoran_milik_orang_lain_tidak_ikut_terhitung(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $orangLain = User::factory()->create(['role' => 'user']);

        $this->transaksi($penyetor, Transaction::STATUS_COMPLETED);
        $this->transaksi($orangLain, Transaction::STATUS_COMPLETED);
        $this->transaksi($orangLain, Transaction::STATUS_COMPLETED);

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(1, $halaman->viewData('completed_transactions'));
    }
}

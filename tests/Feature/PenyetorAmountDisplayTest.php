<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Angka rupiah yang dilihat penyetor.
 *
 * Seluruh halaman penyetor dulu memakai total_value ?? estimated_total.
 * Transaksi yang ditolak meninggalkan total_value kosong, sehingga estimasinya
 * tampil sebagai rupiah yang diterima, berdampingan dengan lencana "Ditolak".
 * Berkas ini menjaga agar angka yang tidak pernah menjadi uang tidak muncul
 * sebagai uang.
 */
class PenyetorAmountDisplayTest extends TestCase
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

    private function penyetor(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    /**
     * Nilai sengaja dibuat ganjil. OilPriceFactory memasang harga Rp4.000 per
     * liter, sehingga angka bulat seperti itu juga muncul di halaman sebagai
     * harga dan membuat assertion lolos atau gagal karena sebab yang keliru.
     */
    private function transaksi(User $penyetor, array $atribut): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'user_id' => $penyetor->id,
            'partner_id' => Partner::factory(),
            'oil_price_id' => OilPrice::factory(),
            'estimated_total' => 123456,
            'estimated_liter' => 1,
        ], $atribut));
    }

    public function test_transaksi_ditolak_tidak_menampilkan_estimasi_sebagai_rupiah(): void
    {
        $penyetor = $this->penyetor();
        $this->transaksi($penyetor, ['status' => Transaction::STATUS_REJECTED, 'total_value' => null]);

        $isi = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'Rp123.456',
            $isi,
            'Estimasi setoran yang ditolak tidak boleh tampil sebagai rupiah yang diterima.'
        );
        $this->assertStringContainsString('Tidak dibayar', $isi);
    }

    public function test_transaksi_dibatalkan_juga_tidak_menampilkan_rupiah(): void
    {
        $penyetor = $this->penyetor();
        $this->transaksi($penyetor, ['status' => Transaction::STATUS_CANCELLED, 'total_value' => null]);

        $isi = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Rp123.456', $isi);
        $this->assertStringContainsString('Tidak dibayar', $isi);
    }

    public function test_transaksi_selesai_tetap_menampilkan_nilai_sebenarnya(): void
    {
        $penyetor = $this->penyetor();
        $this->transaksi($penyetor, [
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => 0.9,
            'total_value' => 98765,
            'payment_status' => 'paid',
        ]);

        $isi = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Rp98.765', $isi);
        $this->assertStringContainsString('0,90 L', $isi);
        $this->assertStringNotContainsString('Tidak dibayar', $isi);
    }

    public function test_riwayat_penyetor_ikut_menahan_estimasi_yang_ditolak(): void
    {
        $penyetor = $this->penyetor();
        $this->transaksi($penyetor, ['status' => Transaction::STATUS_REJECTED, 'total_value' => null]);

        $isi = $this->actingAs($penyetor)->get(route('transactions.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Rp123.456', $isi, 'Halaman riwayat memuat cacat yang sama.');
    }

    public function test_detail_transaksi_ditolak_ikut_menahan_estimasi(): void
    {
        $penyetor = $this->penyetor();
        $transaksi = $this->transaksi($penyetor, ['status' => Transaction::STATUS_REJECTED, 'total_value' => null]);

        $isi = $this->actingAs($penyetor)->get(route('transactions.show', $transaksi))->assertOk()->getContent();

        $this->assertStringNotContainsString('Rp123.456', $isi, 'Halaman detail memuat cacat yang sama.');
    }

    public function test_transaksi_berjalan_menandai_angkanya_sebagai_perkiraan(): void
    {
        $penyetor = $this->penyetor();
        $transaksi = $this->transaksi($penyetor, ['status' => Transaction::STATUS_PENDING]);

        $isi = $this->actingAs($penyetor)->get(route('transactions.show', $transaksi))->assertOk()->getContent();

        $this->assertStringContainsString(
            '±Rp123.456',
            $isi,
            'Transaksi yang belum selesai harus menandai angkanya sebagai perkiraan, bukan menyajikannya sebagai final.'
        );
    }

    public function test_settled_value_kosong_untuk_status_yang_bukan_selesai(): void
    {
        $penyetor = $this->penyetor();

        foreach ([Transaction::STATUS_PENDING, Transaction::STATUS_REJECTED, Transaction::STATUS_CANCELLED] as $status) {
            $transaksi = $this->transaksi($penyetor, ['status' => $status, 'total_value' => 9999]);

            $this->assertNull(
                $transaksi->settledValue(),
                "Status {$status} seharusnya tidak punya nilai yang dianggap sudah menjadi hak penyetor."
            );
        }
    }
}

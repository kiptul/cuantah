<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Angka utama dasbor penyetor.
 *
 * Kartu itu berlabel "CUAN diterima", dan sebelumnya ia menjumlah seluruh
 * transaksi selesai termasuk yang uangnya belum diserahkan. Kartu yang sama
 * lalu menyebut sebagian dari jumlah itu masih akan dibayarkan, sehingga dua
 * kalimat berdampingan saling membantah.
 */
class PenyetorDashboardValueTest extends TestCase
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
     * liter, sehingga angka bulat juga muncul di halaman sebagai harga dan
     * membuat assertion lolos atau gagal karena sebab yang keliru.
     */
    private function selesai(User $penyetor, int $nilai, float $liter, ?string $statusBayar): Transaction
    {
        return Transaction::factory()->create([
            'user_id' => $penyetor->id,
            'partner_id' => Partner::factory(),
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => $liter,
            'total_value' => $nilai,
            'payment_status' => $statusBayar,
        ]);
    }

    public function test_cuan_diterima_hanya_menghitung_yang_sudah_dibayar(): void
    {
        $penyetor = $this->penyetor();
        $this->selesai($penyetor, 123456, 3.5, 'paid');
        $this->selesai($penyetor, 987654, 7.25, 'unpaid');

        $data = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(123456, $data->viewData('paid_value'), 'Angka utama tidak boleh memuat yang belum dibayar.');
        $this->assertSame(987654, $data->viewData('unpaid_value'));
    }

    public function test_liter_pendamping_mengikuti_angka_utama_bukan_seluruh_setoran(): void
    {
        $penyetor = $this->penyetor();
        $this->selesai($penyetor, 123456, 3.5, 'paid');
        $this->selesai($penyetor, 987654, 7.25, 'unpaid');

        $data = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(3.5, $data->viewData('paid_liter'), 'Liter di bawah angka utama harus berasal dari transaksi yang sama.');
        $this->assertSame(10.75, $data->viewData('total_liter'), 'Total liter sepanjang waktu tetap menghitung seluruh setoran selesai.');
    }

    public function test_halaman_tidak_lagi_menyebut_uang_belum_dibayar_sebagai_diterima(): void
    {
        $penyetor = $this->penyetor();
        $this->selesai($penyetor, 123456, 3.5, 'paid');
        $this->selesai($penyetor, 987654, 7.25, 'unpaid');

        $isi = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Rp123.456', $isi);
        $this->assertStringContainsString('Rp987.654 masih akan dibayarkan mitra', $isi);
        $this->assertStringNotContainsString(
            'Rp1.111.110',
            $isi,
            'Jumlah gabungan keduanya tidak boleh muncul sebagai CUAN diterima.'
        );
    }

    public function test_status_bayar_kosong_dihitung_sebagai_belum_dibayar(): void
    {
        $penyetor = $this->penyetor();
        $this->selesai($penyetor, 555111, 2.0, null);

        $data = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(0, $data->viewData('paid_value'));
        $this->assertSame(
            555111,
            $data->viewData('unpaid_value'),
            'Transaksi selesai yang status bayarnya kosong tidak boleh hilang dari kedua angka.'
        );
    }

    public function test_transaksi_ditolak_tidak_masuk_hitungan_mana_pun(): void
    {
        $penyetor = $this->penyetor();
        Transaction::factory()->create([
            'user_id' => $penyetor->id,
            'partner_id' => Partner::factory(),
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_REJECTED,
            'estimated_total' => 777333,
            'total_value' => null,
        ]);

        $data = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(0, $data->viewData('paid_value'));
        $this->assertSame(0, $data->viewData('unpaid_value'));
    }
}

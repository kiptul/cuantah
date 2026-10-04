<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar "Setoran berjalan" di dasbor penyetor.
 *
 * Daftar itu tidak punya batas jumlah, sehingga penyetor dengan tiga puluh
 * setoran berjalan mendapat tiga puluh kartu dan mendorong riwayat jauh ke
 * bawah halaman. Membatasinya saja tidak cukup: keterangan dan keadaan kosong
 * di halaman yang sama ikut bertumpu pada daftar ini, dan akan menyesatkan
 * begitu daftarnya dipangkas.
 */
class ActiveTransactionsLimitTest extends TestCase
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

    private function berjalan(User $penyetor, int $jumlah, string $status = Transaction::STATUS_PENDING): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();

        for ($i = 0; $i < $jumlah; $i++) {
            Transaction::factory()->create([
                'user_id' => $penyetor->id,
                'partner_id' => $mitra->id,
                'oil_price_id' => $harga->id,
                'status' => $status,
            ]);
        }
    }

    public function test_daftar_dipangkas_tetapi_jumlahnya_tetap_jujur(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->berjalan($penyetor, 9);

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertCount(4, $halaman->viewData('active_transactions'), 'Kartu dibatasi agar riwayat tidak terdorong jauh ke bawah.');
        $this->assertSame(9, $halaman->viewData('active_count'));
        $halaman->assertSee('5 setoran lain juga sedang berjalan');
    }

    public function test_keterangan_kartu_menyebut_jumlah_sebenarnya_bukan_yang_tampil(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->berjalan($penyetor, 9);

        $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()
            ->assertSee('9 sedang berjalan.')
            ->assertDontSee('4 sedang berjalan.');
    }

    public function test_tidak_ada_baris_sisa_ketika_semuanya_muat(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->berjalan($penyetor, 4);

        $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()
            ->assertDontSee('juga sedang berjalan');
    }

    public function test_keadaan_kosong_tetap_muncul_ketika_tidak_ada_yang_berjalan(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()
            ->assertSee('Belum ada transaksi')
            ->assertSee('Buat setoran pertama');
    }

    public function test_keadaan_kosong_tidak_muncul_ketika_ada_yang_berjalan(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->berjalan($penyetor, 9);

        $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()
            ->assertDontSee('Buat setoran pertama')
            ->assertSee('Setoran yang sudah selesai akan tercatat di sini.');
    }

    public function test_setoran_yang_sudah_berakhir_tidak_ikut_terhitung(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->berjalan($penyetor, 2);
        $this->berjalan($penyetor, 3, Transaction::STATUS_REJECTED);
        $this->berjalan($penyetor, 1, Transaction::STATUS_CANCELLED);

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(2, $halaman->viewData('active_count'));
    }
}

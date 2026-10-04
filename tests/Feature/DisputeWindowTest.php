<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jendela waktu penyetor boleh menyanggah takaran.
 *
 * Jendela itu dulu diukur dari updated_at, yaitu waktu tulis terakhir. Satu
 * kali admin menyunting catatan atau menutup sanggahan lain, hitungan tiga
 * hari dimulai lagi dari nol untuk transaksi yang sudah lama selesai. Dasbor
 * dan policy sama-sama memakai patokan itu, jadi keduanya keliru bersamaan
 * dan tidak ada yang saling mengoreksi.
 */
class DisputeWindowTest extends TestCase
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

    /**
     * Setoran selesai dengan waktu selesai dan waktu sentuh terakhir yang
     * ditentukan terpisah. Keduanya ditulis lewat query builder sebab Eloquent
     * menimpa updated_at pada setiap penyimpanan.
     */
    private function setoran(User $penyetor, int $selesaiHariLalu, int $disentuhHariLalu): Transaction
    {
        $transaksi = Transaction::factory()->create([
            'user_id' => $penyetor->id,
            'partner_id' => Partner::factory(),
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => 2.0,
            'total_value' => 8000,
            'payment_status' => 'paid',
            'disputed_at' => null,
        ]);

        DB::table('transactions')->where('id', $transaksi->id)->update([
            'completed_at' => now()->subDays($selesaiHariLalu),
            'updated_at' => now()->subDays($disentuhHariLalu),
        ]);

        return $transaksi->refresh();
    }

    public function test_sentuhan_baru_tidak_membuka_kembali_jendela_yang_sudah_lewat(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        // Selesai sepuluh hari lalu, tetapi baru saja tersentuh lagi.
        $transaksi = $this->setoran($penyetor, selesaiHariLalu: 10, disentuhHariLalu: 0);

        $this->assertFalse(
            $transaksi->withinDisputeWindow(),
            'Menyentuh transaksi lama tidak boleh menghidupkan kembali hak menyanggah.'
        );

        $this->actingAs($penyetor)
            ->post(route('transactions.dispute', $transaksi), ['dispute_reason' => 'Takarannya kurang menurut saya.'])
            ->assertForbidden();
    }

    public function test_setoran_lama_yang_tersentuh_tidak_muncul_lagi_di_panel(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->setoran($penyetor, selesaiHariLalu: 10, disentuhHariLalu: 0);

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(0, $halaman->viewData('disputable_count'));
    }

    public function test_setoran_yang_baru_selesai_tetap_bisa_disanggah(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        // Selesai kemarin, dan sejak itu tidak pernah disentuh lagi.
        $transaksi = $this->setoran($penyetor, selesaiHariLalu: 1, disentuhHariLalu: 1);

        $this->assertTrue($transaksi->withinDisputeWindow());

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();
        $this->assertSame(1, $halaman->viewData('disputable_count'));
    }

    public function test_policy_dan_dasbor_memakai_patokan_yang_sama(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $masih = $this->setoran($penyetor, selesaiHariLalu: 1, disentuhHariLalu: 0);
        $habis = $this->setoran($penyetor, selesaiHariLalu: 5, disentuhHariLalu: 0);

        $terdaftar = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()
            ->viewData('disputable')->pluck('id')->all();

        $this->assertSame([$masih->id], $terdaftar);
        $this->assertTrue($masih->withinDisputeWindow(), 'Yang terdaftar di panel harus diizinkan policy.');
        $this->assertFalse($habis->withinDisputeWindow(), 'Yang ditolak policy tidak boleh terdaftar di panel.');
    }

    public function test_sisa_waktu_ditampilkan_pada_tiap_baris(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->setoran($penyetor, selesaiHariLalu: 1, disentuhHariLalu: 1);

        $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->assertSee('Sisa');
    }

    public function test_batas_akhir_dihitung_dari_waktu_selesai(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $transaksi = $this->setoran($penyetor, selesaiHariLalu: 1, disentuhHariLalu: 0);

        $this->assertTrue(
            $transaksi->disputeDeadline()->equalTo($transaksi->completed_at->copy()->addDays(Transaction::DISPUTE_WINDOW_DAYS)),
            'Batas akhir harus bertumpu pada waktu selesai, bukan waktu sentuh terakhir.'
        );
    }

    public function test_transaksi_selesai_tanpa_waktu_selesai_ditolak_bukan_dibuka_selamanya(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $transaksi = $this->setoran($penyetor, selesaiHariLalu: 1, disentuhHariLalu: 0);

        DB::table('transactions')->where('id', $transaksi->id)->update(['completed_at' => null]);

        $this->assertNull($transaksi->refresh()->disputeDeadline());
        $this->assertFalse($transaksi->withinDisputeWindow());
    }
}

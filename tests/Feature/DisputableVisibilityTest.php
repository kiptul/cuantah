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
 * Panel "Baru selesai" di dasbor penyetor.
 *
 * Panel ini menampilkan paling banyak tiga setoran, tanpa tautan ke sisanya.
 * Hak menyanggah takaran hangus dalam tiga hari, sehingga setoran keempat
 * yang tidak pernah ditampilkan membuat haknya lewat tanpa penyetor pernah
 * tahu bahwa ada yang menunggu.
 */
class DisputableVisibilityTest extends TestCase
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
     * Setoran selesai yang masih di dalam jendela sanggahan.
     *
     * updated_at ditulis lewat query builder sebab Eloquent menimpanya pada
     * setiap penyimpanan, padahal justru nilai itu yang menentukan apakah
     * setoran masih bisa disanggah.
     */
    private function bisaDisanggah(User $penyetor, int $umurHari = 0): Transaction
    {
        $transaksi = Transaction::factory()->create([
            'user_id' => $penyetor->id,
            'partner_id' => Partner::factory(),
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => 1.5,
            'total_value' => 6000,
            'payment_status' => 'paid',
            'disputed_at' => null,
        ]);

        DB::table('transactions')->where('id', $transaksi->id)->update([
            'updated_at' => now()->subDays($umurHari),
            'completed_at' => now()->subDays($umurHari),
        ]);

        return $transaksi->refresh();
    }

    public function test_sisa_setoran_yang_tidak_muat_disebut_jumlahnya(): void
    {
        $penyetor = $this->penyetor();

        for ($i = 0; $i < 5; $i++) {
            $this->bisaDisanggah($penyetor);
        }

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(5, $halaman->viewData('disputable_count'));
        $this->assertCount(3, $halaman->viewData('disputable'), 'Panel tetap menampilkan tiga agar tidak mendominasi halaman.');
        $halaman->assertSee('2 setoran lain juga masih bisa disanggah');
    }

    public function test_tidak_ada_baris_sisa_ketika_semuanya_muat(): void
    {
        $penyetor = $this->penyetor();

        for ($i = 0; $i < 3; $i++) {
            $this->bisaDisanggah($penyetor);
        }

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(3, $halaman->viewData('disputable_count'));
        $halaman->assertDontSee('juga masih bisa disanggah');
    }

    public function test_jumlah_memakai_syarat_yang_sama_dengan_daftarnya(): void
    {
        $penyetor = $this->penyetor();

        $this->bisaDisanggah($penyetor);

        // Sudah disanggah: tidak boleh ikut terhitung maupun tampil.
        $sudah = $this->bisaDisanggah($penyetor);
        $sudah->update(['disputed_at' => now(), 'dispute_reason' => 'Takarannya kurang.']);

        // Lewat tiga hari: haknya sudah habis.
        $this->bisaDisanggah($penyetor, umurHari: 4);

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(
            1,
            $halaman->viewData('disputable_count'),
            'Jumlah yang disebut di layar harus berasal dari syarat yang sama dengan isinya.'
        );
    }

    public function test_setoran_milik_orang_lain_tidak_ikut_terhitung(): void
    {
        $penyetor = $this->penyetor();
        $orangLain = $this->penyetor();

        $this->bisaDisanggah($penyetor);
        $this->bisaDisanggah($orangLain);
        $this->bisaDisanggah($orangLain);

        $halaman = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk();

        $this->assertSame(1, $halaman->viewData('disputable_count'));
    }
}

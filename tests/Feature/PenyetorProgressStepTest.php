<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Penanda kemajuan pada kartu "Setoran berjalan".
 *
 * Penanda itu dulu menyimpulkan tahapan dari ada-tidaknya karyawan yang
 * ditugaskan, bukan dari status transaksinya. Penugasan bukan kemajuan:
 * begitu seorang karyawan mengambil jadwal, tahap terakhir ikut menyala dan
 * setoran yang penjemputannya masih dua hari lagi tampak hampir selesai.
 */
class PenyetorProgressStepTest extends TestCase
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
     * @return array<string, array{0: string, 1: int}>
     */
    public static function pemetaanStatus(): array
    {
        return [
            'menunggu' => [Transaction::STATUS_PENDING, 0],
            'dijadwalkan' => [Transaction::STATUS_SCHEDULED, 1],
            'dijemput' => [Transaction::STATUS_PICKED_UP, 1],
            'verifikasi' => [Transaction::STATUS_VERIFICATION, 2],
            'selesai' => [Transaction::STATUS_COMPLETED, 2],
        ];
    }

    #[DataProvider('pemetaanStatus')]
    public function test_tahapan_mengikuti_status_transaksi(string $status, int $tahapan): void
    {
        $transaksi = new Transaction(['status' => $status]);

        $this->assertSame($tahapan, $transaksi->progressStep());
    }

    private function setoranBerjalan(User $penyetor, string $status, bool $adaKaryawan): Transaction
    {
        $mitra = Partner::factory()->create();

        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => $penyetor->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => $status,
        ]);

        $transaksi->pickup()->create([
            'partner_id' => $mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => $adaKaryawan ? 'assigned' : 'pending',
            'assigned_user_id' => $adaKaryawan ? User::factory()->create(['role' => 'employee'])->id : null,
        ]);

        return $transaksi;
    }

    /**
     * Mengambil label tahap yang ditandai sebagai tahap berjalan di halaman.
     */
    private function tahapBerjalanDiHalaman(User $penyetor): ?string
    {
        $isi = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<li[^>]*aria-current="step"[^>]*>(.*?)<\/li>/s', $isi, $cocok);

        if ($cocok === []) {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', strip_tags($cocok[1])));
    }

    public function test_penugasan_karyawan_tidak_membuat_tahap_selesai_menyala(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->setoranBerjalan($penyetor, Transaction::STATUS_SCHEDULED, adaKaryawan: true);

        $this->assertSame(
            'Dijemput karyawan',
            $this->tahapBerjalanDiHalaman($penyetor),
            'Karyawan yang baru ditugaskan berarti penjemputan sedang berjalan, bukan setoran hampir selesai.'
        );
    }

    public function test_setoran_yang_belum_ditangani_berhenti_di_tahap_pertama(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->setoranBerjalan($penyetor, Transaction::STATUS_PENDING, adaKaryawan: false);

        $this->assertSame('Diajukan', $this->tahapBerjalanDiHalaman($penyetor));
    }

    public function test_tahap_berjalan_ditandai_untuk_pembaca_layar(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->setoranBerjalan($penyetor, Transaction::STATUS_SCHEDULED, adaKaryawan: true);

        $isi = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->getContent();

        $this->assertSame(
            1,
            substr_count($isi, 'aria-current="step"'),
            'Tepat satu tahap boleh ditandai sebagai tahap berjalan, kalau tidak penanda jadi ambigu.'
        );
    }
}

<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penuaan tugas pada kartu dasbor karyawan.
 *
 * Penanda "Terlewat" bertumpu pada pickup_date, sedangkan drop-off tidak
 * punya tanggal sama sekali. Akibatnya drop-off tidak pernah menua: kartunya
 * tetap berlencana abu-abu berhari-hari meski jelantahnya sudah ada di mitra
 * dan belum ditimbang.
 */
class PickupAgingTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $atribut
     */
    private function tugas(string $metode, array $atribut): Pickup
    {
        $transaksi = Transaction::factory()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'method' => $metode,
            'status' => Transaction::STATUS_SCHEDULED,
        ]);

        return $transaksi->pickup()->create(array_merge([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'assigned',
            'assigned_user_id' => $this->karyawan->id,
        ], $atribut));
    }

    public function test_drop_off_yang_discan_kemarin_sudah_menua(): void
    {
        $this->siapkan();
        $pickup = $this->tugas(Transaction::METHOD_DROP_OFF, ['scanned_at' => now()->subDays(2)]);

        $this->assertTrue($pickup->isOverdue(), 'Jelantahnya sudah dua hari di mitra dan belum ditimbang.');
        $this->assertSame(2, $pickup->daysWaiting());
    }

    public function test_drop_off_yang_baru_discan_belum_menua(): void
    {
        $this->siapkan();
        $pickup = $this->tugas(Transaction::METHOD_DROP_OFF, ['scanned_at' => now()]);

        $this->assertFalse($pickup->isOverdue());
        $this->assertSame(0, $pickup->daysWaiting());
    }

    public function test_drop_off_tanpa_pemindaian_dinilai_dari_waktu_penugasan(): void
    {
        $this->siapkan();

        // Admin dapat menugaskan drop-off tanpa barcode pernah discan, sehingga
        // scanned_at kosong dan satu-satunya patokan adalah waktu penugasan.
        $pickup = $this->tugas(Transaction::METHOD_DROP_OFF, [
            'scanned_at' => null,
            'assigned_at' => now()->subDays(3),
        ]);

        $this->assertTrue($pickup->isOverdue());
        $this->assertSame(3, $pickup->daysWaiting());
    }

    public function test_tanpa_patokan_apa_pun_tidak_dianggap_menua(): void
    {
        $this->siapkan();
        $pickup = $this->tugas(Transaction::METHOD_DROP_OFF, ['scanned_at' => null, 'assigned_at' => null]);

        $this->assertFalse($pickup->isOverdue(), 'Tanpa patokan, menuduh terlewat lebih buruk daripada diam.');
        $this->assertNull($pickup->daysWaiting());
    }

    public function test_penjemputan_tetap_dinilai_dari_tanggal_yang_dijanjikan(): void
    {
        $this->siapkan();

        $kemarin = $this->tugas(Transaction::METHOD_PICKUP, [
            'pickup_date' => today()->subDay(),
            'assigned_at' => now(),
        ]);
        $besok = $this->tugas(Transaction::METHOD_PICKUP, [
            'pickup_date' => today()->addDay(),
            'assigned_at' => now()->subDays(5),
        ]);

        $this->assertTrue($kemarin->isOverdue());
        $this->assertFalse(
            $besok->isOverdue(),
            'Penjemputan dinilai dari janji kepada penyetor, bukan dari sejak kapan ditugaskan.'
        );
    }

    public function test_kartu_menyebut_lama_menunggu_pada_drop_off_yang_menua(): void
    {
        $this->siapkan();
        $this->tugas(Transaction::METHOD_DROP_OFF, ['scanned_at' => now()->subDays(2)]);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('menunggu 2 hari');
    }

    public function test_kartu_drop_off_yang_segar_tidak_menyebut_lama_menunggu(): void
    {
        $this->siapkan();
        $this->tugas(Transaction::METHOD_DROP_OFF, ['scanned_at' => now()]);

        $isi = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('Antar sendiri', $isi);

        /**
         * Dicocokkan pada bentuk yang hanya ada di lencana. Kata "menunggu"
         * saja juga muncul di hero, sehingga memeriksanya begitu saja akan
         * gagal karena sebab yang keliru.
         */
        $this->assertDoesNotMatchRegularExpression('/menunggu \d+ hari/', $isi);
    }
}

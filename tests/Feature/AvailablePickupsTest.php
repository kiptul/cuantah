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
 * Daftar "Cari Pickup" di sisi karyawan.
 *
 * Daftar itu tidak menyaring setoran yang sudah berakhir, sedangkan angka di
 * dasbor menyaringnya. Setoran yang dibatalkan penyetor karenanya tetap
 * ditawarkan di halaman meski dasbor menyatakan tidak ada apa-apa, dan
 * karyawan yang mencoba mengambilnya ditolak dengan alasan yang keliru.
 */
class AvailablePickupsTest extends TestCase
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

    private function pickupBelumDiambil(string $status, string $metode = Transaction::METHOD_PICKUP): Pickup
    {
        $transaksi = Transaction::factory()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'method' => $metode,
            'status' => $status,
        ]);

        return $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'pickup_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
            'assigned_user_id' => null,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function idDiHalaman(): array
    {
        return $this->actingAs($this->karyawan)->get(route('employee.pickups.available'))->assertOk()
            ->viewData('pickups')->pluck('id')->all();
    }

    public function test_setoran_yang_dibatalkan_tidak_ditawarkan(): void
    {
        $this->siapkan();
        $batal = $this->pickupBelumDiambil(Transaction::STATUS_CANCELLED);

        $this->assertNotContains(
            $batal->id,
            $this->idDiHalaman(),
            'Setoran yang sudah dibatalkan penyetor tidak boleh ditawarkan sebagai pekerjaan.'
        );
    }

    public function test_setoran_yang_ditolak_tidak_ditawarkan(): void
    {
        $this->siapkan();
        $tolak = $this->pickupBelumDiambil(Transaction::STATUS_REJECTED);

        $this->assertNotContains($tolak->id, $this->idDiHalaman());
    }

    public function test_setoran_yang_masih_berjalan_tetap_ditawarkan(): void
    {
        $this->siapkan();
        $terbuka = $this->pickupBelumDiambil(Transaction::STATUS_PENDING);

        $this->assertContains(
            $terbuka->id,
            $this->idDiHalaman(),
            'Penyaringan tidak boleh ikut membuang pekerjaan yang memang tersedia.'
        );
    }

    public function test_halaman_sepakat_dengan_angka_di_dasbor(): void
    {
        $this->siapkan();
        $this->pickupBelumDiambil(Transaction::STATUS_CANCELLED);
        $this->pickupBelumDiambil(Transaction::STATUS_PENDING);

        $diHalaman = count($this->idDiHalaman());
        $diDasbor = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->viewData('available_count');

        $this->assertSame(
            $diDasbor,
            $diHalaman,
            'Angka di dasbor dan isi halaman harus berasal dari syarat yang sama.'
        );
    }

    public function test_penolakan_pengambilan_tidak_menuduh_karyawan_lain(): void
    {
        $this->siapkan();
        $batal = $this->pickupBelumDiambil(Transaction::STATUS_CANCELLED);

        $this->actingAs($this->karyawan)
            ->post(route('employee.pickups.claim', $batal))
            ->assertSessionHas('error');

        $pesan = session('error');

        $this->assertStringContainsString(
            'dibatalkan',
            $pesan,
            'Pesannya harus menyebut kemungkinan yang sebenarnya, bukan hanya menuduh karyawan lain.'
        );
    }

    public function test_pickup_mitra_lain_tetap_tidak_terlihat(): void
    {
        $this->siapkan();

        $mitraLain = Partner::factory()->create();
        $transaksi = Transaction::factory()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $mitraLain->id,
            'oil_price_id' => OilPrice::factory(),
            'method' => Transaction::METHOD_PICKUP,
            'status' => Transaction::STATUS_PENDING,
        ]);
        $asing = $transaksi->pickup()->create([
            'partner_id' => $mitraLain->id,
            'address' => 'Jl. Mitra Lain',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'pending',
            'assigned_user_id' => null,
        ]);

        $this->assertNotContains($asing->id, $this->idDiHalaman(), 'Penyaringan baru tidak boleh melonggarkan batas mitra.');
    }
}

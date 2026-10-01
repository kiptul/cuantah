<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keadaan kosong pada bagian "Tugas saya" di dasbor karyawan.
 *
 * Keadaan itu hanya memeriksa jumlah tugas dan tidak pernah melihat berapa
 * pickup yang tersedia, padahal angka tersebut sudah dipakai hero tepat di
 * atasnya. Akibatnya satu layar memuat tiga pernyataan yang bertabrakan: hero
 * menyatakan nol pickup menunggu, paragraf di bawahnya menyuruh mengambil
 * pickup dari daftar, dan tombolnya mengantar ke halaman yang mengulangi
 * bahwa tidak ada apa-apa.
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
     * Pickup jemput yang belum diambil siapa pun di mitra karyawan tersebut.
     */
    private function pickupTersedia(int $jumlah): void
    {
        $harga = OilPrice::factory()->create();

        for ($i = 0; $i < $jumlah; $i++) {
            $transaksi = Transaction::factory()->pickup()->create([
                'user_id' => User::factory()->create(['role' => 'user'])->id,
                'partner_id' => $this->mitra->id,
                'oil_price_id' => $harga->id,
                'status' => Transaction::STATUS_PENDING,
            ]);

            $transaksi->pickup()->create([
                'partner_id' => $this->mitra->id,
                'address' => 'Jl. Tersedia '.$i,
                'latitude' => -6.3,
                'longitude' => 107.3,
                'pickup_date' => now()->addDay()->toDateString(),
                'status' => 'pending',
                'assigned_user_id' => null,
            ]);
        }
    }

    /**
     * Mengambil blok keadaan kosong saja, supaya tombol "Cari Pickup" dan
     * "Scan Barcode" yang selalu ada di header tidak ikut terbaca.
     */
    private function blokKeadaanKosong(): string
    {
        $isi = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()->getContent();

        preg_match('/Tidak ada tugas terbuka.*?<\/div>/s', $isi, $cocok);

        return $cocok[0] ?? '';
    }

    public function test_tanpa_pickup_tersedia_mengarahkan_ke_scan(): void
    {
        $this->siapkanKaryawan();

        $blok = $this->blokKeadaanKosong();

        $this->assertStringContainsString('Belum ada pickup yang bisa diambil', $blok);
        $this->assertStringContainsString(route('employee.scan'), $blok, 'Scan adalah satu-satunya tindakan yang benar-benar bisa dilakukan.');
        $this->assertStringNotContainsString(
            route('employee.pickups.available'),
            $blok,
            'Tombolnya tidak boleh mengantar ke daftar pickup yang sudah pasti kosong.'
        );
    }

    public function test_dengan_pickup_tersedia_mengarahkan_ke_daftar_pickup(): void
    {
        $this->siapkanKaryawan();
        $this->pickupTersedia(2);

        $blok = $this->blokKeadaanKosong();

        $this->assertStringContainsString('2 pickup menunggu diambil', $blok);
        $this->assertStringContainsString(route('employee.pickups.available'), $blok);
    }

    public function test_keadaan_kosong_tidak_membantah_angka_di_hero(): void
    {
        $this->siapkanKaryawan();

        $halaman = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $this->assertSame(0, $halaman->viewData('available_count'));

        /**
         * Menjaga sifatnya, bukan kalimat lamanya. Memeriksa ketiadaan teks
         * yang memang sudah dihapus akan selalu lolos tanpa menguji apa pun.
         */
        $this->assertStringNotContainsString(
            'pickup menunggu diambil',
            $this->blokKeadaanKosong(),
            'Hero menyatakan nol pickup menunggu, jadi keadaan kosong tidak boleh menjanjikan ada yang bisa diambil.'
        );
    }

    public function test_keadaan_kosong_tidak_muncul_ketika_ada_tugas(): void
    {
        $this->siapkanKaryawan();

        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_SCHEDULED,
        ]);

        $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Tugas',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'pickup_date' => now()->toDateString(),
            'status' => 'assigned',
            'assigned_user_id' => $this->karyawan->id,
        ]);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertDontSee('Tidak ada tugas terbuka');
    }
}

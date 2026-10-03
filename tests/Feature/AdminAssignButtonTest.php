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
 * Tombol penugasan pada halaman Pickup admin.
 *
 * Labelnya dulu selalu "Assign / Override", apa pun keadaannya. Satu tombol
 * memikul dua arti sekaligus, sehingga admin tidak bisa membaca dari
 * tombolnya apakah pickup ini sudah bertuan, dan tombol Unassign yang
 * sebenarnya sudah ada terbaca sebagai tombol kedua yang entah untuk apa.
 */
class AdminAssignButtonTest extends TestCase
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

    private User $admin;

    private User $karyawan;

    private function siapkan(): void
    {
        $this->mitra = Partner::factory()->create();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->partners()->sync([$this->mitra->id]);

        $this->karyawan = User::factory()->create(['role' => 'employee', 'name' => 'Karyawan Satu']);
        $this->karyawan->partners()->sync([$this->mitra->id]);
    }

    private function pickup(?int $ditugaskanKe = null, string $status = 'pending'): Pickup
    {
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
        ]);

        return $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => $status,
            'assigned_user_id' => $ditugaskanKe,
        ]);
    }

    private function halaman(): string
    {
        return $this->actingAs($this->admin)->get(route('admin.pickups.index'))->assertOk()->getContent();
    }

    public function test_belum_ditugaskan_hanya_menawarkan_tugaskan(): void
    {
        $this->siapkan();
        $this->pickup();

        $isi = $this->halaman();

        $this->assertStringContainsString('Tugaskan', $isi);
        $this->assertStringNotContainsString('Unassign', $isi, 'Tidak ada yang bisa dilepas ketika belum ada yang memegang.');
        $this->assertStringNotContainsString('Pindahkan ke karyawan lain', $isi);
    }

    public function test_sudah_ditugaskan_menawarkan_unassign(): void
    {
        $this->siapkan();
        $this->pickup($this->karyawan->id, 'assigned');

        $isi = $this->halaman();

        $this->assertStringContainsString('Unassign', $isi);
        $this->assertStringContainsString('Pindahkan ke karyawan lain', $isi);
    }

    public function test_label_lama_yang_memikul_dua_arti_sudah_hilang(): void
    {
        $this->siapkan();
        $this->pickup($this->karyawan->id, 'assigned');

        $this->assertStringNotContainsString(
            'Assign / Override',
            $this->halaman(),
            'Label itu tidak pernah berubah, sehingga tidak mengabarkan apa pun tentang keadaan pickup.'
        );
    }

    public function test_pemindahan_tetap_bisa_dilakukan_tanpa_melepas_lebih_dulu(): void
    {
        $this->siapkan();
        $pickup = $this->pickup($this->karyawan->id, 'assigned');

        $karyawanLain = User::factory()->create(['role' => 'employee', 'name' => 'Karyawan Dua']);
        $karyawanLain->partners()->sync([$this->mitra->id]);

        $this->actingAs($this->admin)
            ->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $karyawanLain->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $karyawanLain->id,
            $pickup->refresh()->assigned_user_id,
            'Menyembunyikan pemindahan di balik details tidak boleh ikut mematikan rutenya.'
        );
    }

    public function test_pickup_yang_sudah_berakhir_tidak_menawarkan_keduanya(): void
    {
        $this->siapkan();
        $this->pickup($this->karyawan->id, 'completed');

        $isi = $this->halaman();

        $this->assertStringContainsString('assignment tidak bisa diubah', $isi);
        $this->assertStringNotContainsString('Unassign', $isi);
        $this->assertStringNotContainsString('Tugaskan', $isi);
    }
}

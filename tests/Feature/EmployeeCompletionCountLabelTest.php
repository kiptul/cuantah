<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keterangan jumlah pada panel "Baru saya selesaikan".
 *
 * Panel itu dulu menutup kepalanya dengan teks abu-abu kecil berbunyi
 * "1 total", tanpa menyebut total apa. Daftar di bawahnya hanya memuat lima
 * terbaru, sehingga rentang yang dimaksud pun tidak terbaca dari isinya.
 */
class EmployeeCompletionCountLabelTest extends TestCase
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

    private function selesai(int $jumlah): void
    {
        $harga = OilPrice::factory()->create();

        for ($i = 0; $i < $jumlah; $i++) {
            $transaksi = Transaction::factory()->pickup()->create([
                'user_id' => User::factory()->create(['role' => 'user'])->id,
                'partner_id' => $this->mitra->id,
                'oil_price_id' => $harga->id,
                'status' => Transaction::STATUS_COMPLETED,
                'actual_liter' => 1,
                'total_value' => 4000,
                'payment_status' => 'paid',
                'completed_at' => now()->subDays($i),
            ]);

            $transaksi->pickup()->create([
                'partner_id' => $this->mitra->id,
                'address' => 'Jl. Uji',
                'latitude' => -6.3,
                'longitude' => 107.3,
                'status' => 'completed',
                'assigned_user_id' => $this->karyawan->id,
            ]);
        }
    }

    public function test_keterangan_menyebut_satuan_dan_rentangnya(): void
    {
        $this->siapkan();
        $this->selesai(3);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('3 setoran sepanjang waktu');
    }

    public function test_angkanya_seluruh_riwayat_bukan_yang_tertampil(): void
    {
        $this->siapkan();
        $this->selesai(7);

        $halaman = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $this->assertCount(
            5,
            $halaman->viewData('recent_completions'),
            'Daftarnya memang hanya memuat lima terbaru.'
        );
        $halaman->assertSee('7 setoran sepanjang waktu');
        $halaman->assertDontSee('5 setoran sepanjang waktu');
    }

    public function test_keterangan_disembunyikan_ketika_belum_ada_yang_selesai(): void
    {
        $this->siapkan();

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertDontSee('sepanjang waktu')
            ->assertSee('Belum ada transaksi yang kamu selesaikan.');
    }

    public function test_setoran_yang_diselesaikan_karyawan_lain_tidak_ikut_terhitung(): void
    {
        $this->siapkan();
        $this->selesai(2);

        $karyawanLain = User::factory()->create(['role' => 'employee']);
        $karyawanLain->partners()->sync([$this->mitra->id]);
        $sayaDulu = $this->karyawan;
        $this->karyawan = $karyawanLain;
        $this->selesai(4);
        $this->karyawan = $sayaDulu;

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('2 setoran sepanjang waktu');
    }
}

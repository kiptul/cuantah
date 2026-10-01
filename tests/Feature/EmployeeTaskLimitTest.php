<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Daftar "Tugas saya" di dasbor karyawan.
 *
 * Daftar itu tidak punya batas jumlah, sehingga karyawan dengan tiga puluh
 * tugas mendapat tiga puluh kartu. Membatasinya saja tidak cukup: angka besar
 * "Tugas terbuka" dan keadaan kosong di halaman yang sama bertumpu pada
 * koleksi tersebut, dan akan ikut menyesatkan begitu daftarnya dipangkas.
 */
class EmployeeTaskLimitTest extends TestCase
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

    private function tugas(int $jumlah, string $status = Transaction::STATUS_SCHEDULED): void
    {
        $harga = OilPrice::factory()->create();

        for ($i = 0; $i < $jumlah; $i++) {
            $transaksi = Transaction::factory()->pickup()->create([
                'user_id' => User::factory()->create(['role' => 'user'])->id,
                'partner_id' => $this->mitra->id,
                'oil_price_id' => $harga->id,
                'status' => $status,
            ]);

            $transaksi->pickup()->create([
                'partner_id' => $this->mitra->id,
                'address' => 'Jl. Uji '.$i,
                'latitude' => -6.3,
                'longitude' => 107.3,
                'pickup_date' => now()->addDays($i)->toDateString(),
                'status' => 'assigned',
                'assigned_user_id' => $this->karyawan->id,
            ]);
        }
    }

    public function test_daftar_dipangkas_tetapi_jumlahnya_tetap_jujur(): void
    {
        $this->siapkanKaryawan();
        $this->tugas(10);

        $halaman = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $this->assertCount(6, $halaman->viewData('tasks'), 'Kartu dibatasi agar daftar tidak memanjang tanpa akhir.');
        $this->assertSame(10, $halaman->viewData('tasks_count'));
        $halaman->assertSee('4 tugas lain belum tertampil');
    }

    public function test_angka_tugas_terbuka_menyebut_jumlah_sebenarnya(): void
    {
        $this->siapkanKaryawan();
        $this->tugas(10);

        $isi = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('>10</p>', $isi, 'Angka besar harus menyebut seluruh tugas, bukan yang tertampil.');
        $this->assertStringNotContainsString('>6</p>', $isi);
    }

    public function test_tidak_ada_baris_sisa_ketika_semuanya_muat(): void
    {
        $this->siapkanKaryawan();
        $this->tugas(6);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertDontSee('belum tertampil');
    }

    public function test_keadaan_kosong_muncul_ketika_tidak_ada_tugas(): void
    {
        $this->siapkanKaryawan();

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('Tidak ada tugas terbuka');
    }

    public function test_keadaan_kosong_tidak_muncul_ketika_ada_tugas(): void
    {
        $this->siapkanKaryawan();
        $this->tugas(10);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertDontSee('Tidak ada tugas terbuka');
    }

    public function test_tugas_yang_sudah_berakhir_tidak_ikut_terhitung(): void
    {
        $this->siapkanKaryawan();
        $this->tugas(2);
        $this->tugas(3, Transaction::STATUS_COMPLETED);
        $this->tugas(1, Transaction::STATUS_REJECTED);

        $halaman = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $this->assertSame(2, $halaman->viewData('tasks_count'));
    }

    public function test_tugas_milik_karyawan_lain_tidak_ikut_terhitung(): void
    {
        $this->siapkanKaryawan();
        $this->tugas(2);

        $karyawanLain = User::factory()->create(['role' => 'employee']);
        $karyawanLain->partners()->sync([$this->mitra->id]);
        $sayaDulu = $this->karyawan;
        $this->karyawan = $karyawanLain;
        $this->tugas(4);
        $this->karyawan = $sayaDulu;

        $halaman = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $this->assertSame(2, $halaman->viewData('tasks_count'));
    }
}

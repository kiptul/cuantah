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
 * Mitra tujuan pada kartu tugas karyawan.
 *
 * Seorang karyawan dapat terhubung ke lebih dari satu mitra, sedangkan kartu
 * tugas hanya menyebut penyetor, kode, alamat, dan perkiraan liter. Ke mana
 * jelantahnya harus disetorkan tidak pernah disebut, padahal itulah tujuan
 * perjalanannya.
 */
class EmployeeTaskPartnerTest extends TestCase
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

    private User $karyawan;

    private function siapkanKaryawan(Partner ...$mitra): void
    {
        $this->karyawan = User::factory()->create(['role' => 'employee']);
        $this->karyawan->partners()->sync(collect($mitra)->pluck('id')->all());
    }

    private function tugas(Partner $mitra): Transaction
    {
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_SCHEDULED,
        ]);

        $transaksi->pickup()->create([
            'partner_id' => $mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'pickup_date' => now()->toDateString(),
            'status' => 'assigned',
            'assigned_user_id' => $this->karyawan->id,
        ]);

        return $transaksi;
    }

    public function test_kartu_tugas_menyebut_mitra_tujuan(): void
    {
        $mitra = Partner::factory()->create(['name' => 'Mitra Tujuan Uji']);
        $this->siapkanKaryawan($mitra);
        $this->tugas($mitra);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('Setor ke Mitra Tujuan Uji');
    }

    public function test_tiap_kartu_menyebut_mitranya_sendiri(): void
    {
        $mitraA = Partner::factory()->create(['name' => 'Mitra Alfa']);
        $mitraB = Partner::factory()->create(['name' => 'Mitra Beta']);
        $this->siapkanKaryawan($mitraA, $mitraB);

        $this->tugas($mitraA);
        $this->tugas($mitraB);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('Setor ke Mitra Alfa')
            ->assertSee('Setor ke Mitra Beta');
    }

    /**
     * Menghitung kueri yang dijalankan saat dasbor dirender.
     */
    private function jumlahKueri(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }

    public function test_menambah_tugas_tidak_menambah_kueri(): void
    {
        $mitra = Partner::factory()->create();
        $this->siapkanKaryawan($mitra);
        $this->tugas($mitra);

        $satuTugas = $this->jumlahKueri();

        // Tiap tugas memakai mitra berbeda, supaya satu kueri mitra yang
        // digabung benar-benar diuji dan bukan kebetulan mitranya sama.
        foreach (range(1, 4) as $ignored) {
            $this->tugas(Partner::factory()->create());
        }

        $limaTugas = $this->jumlahKueri();

        /**
         * Yang dijaga adalah jumlah kueri tidak tumbuh seiring jumlah kartu,
         * bukan angkanya sama persis. Menuntut kesamaan membuat test ini gagal
         * oleh selisih yang tidak ada hubungannya dengan kartu tugas.
         */
        $this->assertLessThanOrEqual(
            $satuTugas,
            $limaTugas,
            "Menyebut mitra pada tiap kartu tidak boleh memicu satu kueri per kartu ({$satuTugas} kueri untuk 1 tugas, {$limaTugas} untuk 5)."
        );
    }
}

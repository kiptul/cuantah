<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Form penjemputan di halaman proses transaksi.
 *
 * Kotak berlabel "Diterima penyetor" dirender dengan estimasi sebagai nilai
 * awal, padahal volume aktualnya belum diisi. Karyawan membaca angka itu di
 * depan penyetor, sehingga perkiraan dapat tersebut sebagai jumlah yang akan
 * diterima sebelum apa pun ditimbang.
 */
class EmployeeVerifyFormTest extends TestCase
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

    private function tugas(array $atribut = [], bool $denganPickup = true): Transaction
    {
        $transaksi = Transaction::factory()->pickup()->create(array_merge([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_SCHEDULED,
            'estimated_liter' => 5,
            'price_per_liter' => 4000,
            'estimated_total' => 20000,
        ], $atribut));

        if ($denganPickup) {
            $transaksi->pickup()->create([
                'partner_id' => $this->mitra->id,
                'address' => 'Jl. Penjemputan 7',
                'latitude' => -6.3,
                'longitude' => 107.3,
                'status' => 'assigned',
                'assigned_user_id' => $this->karyawan->id,
            ]);
        }

        return $transaksi;
    }

    /**
     * Mengambil isi kotak "Diterima penyetor" apa adanya.
     */
    private function angkaDiterimaPenyetor(Transaction $transaksi): string
    {
        $isi = $this->actingAs($this->karyawan)
            ->get(route('employee.transactions.show', $transaksi))
            ->assertOk()
            ->getContent();

        preg_match('/data-total[^>]*>(.*?)<\/span>/s', $isi, $cocok);

        return trim($cocok[1] ?? '');
    }

    public function test_nilai_awal_bukan_estimasi(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->assertStringNotContainsString(
            '20.000',
            $this->angkaDiterimaPenyetor($transaksi),
            'Estimasi tidak boleh berdiri di bawah label "Diterima penyetor" sebelum apa pun ditimbang.'
        );
    }

    public function test_nilai_awal_berupa_penanda_kosong(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->assertStringContainsString('&mdash;', $this->angkaDiterimaPenyetor($transaksi));
    }

    public function test_estimasi_tetap_disebut_di_tempat_yang_benar(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->karyawan)->get(route('employee.transactions.show', $transaksi))->assertOk()
            ->assertSee('Estimasi penyetor 5,00 L')
            ->assertSee('Estimasi total');
    }

    public function test_transaksi_yang_sudah_selesai_tidak_menampilkan_form(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas([
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => 4,
            'total_value' => 16000,
            'payment_status' => 'paid',
            'completed_at' => now(),
        ]);

        $this->actingAs($this->karyawan)->get(route('employee.transactions.show', $transaksi))->assertOk()
            ->assertSee('Transaksi selesai')
            ->assertDontSee('Kirim &amp; Selesaikan', false);
    }

    public function test_tanpa_lokasi_tidak_menyisakan_kotak_peta_kosong(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas([], denganPickup: false);

        // Tanpa pickup, karyawan hanya bisa membukanya sebagai admin mitra.
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([$this->mitra->id]);

        $this->actingAs($admin)->get(route('employee.transactions.show', $transaksi))->assertOk()
            ->assertDontSee('id="employee-map"', false)
            ->assertSee('Lokasi belum tercatat.');
    }
}

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
 * Admin memindai QR setoran antar sendiri.
 *
 * Cadangan untuk saat seluruh karyawan sedang menjemput dan penyetor terlanjur
 * datang ke lokasi mitra. Pemindaiannya hanya mengantar ke halaman rincian;
 * yang mencatat penerimaannya adalah panel Selesaikan di sana, beserta volume,
 * bukti bayar, dan waktunya.
 */
class AdminScanTest extends TestCase
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

    private function siapkan(): void
    {
        $this->mitra = Partner::factory()->create();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->partners()->sync([$this->mitra->id]);
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function transaksi(array $ganti = [], ?Partner $mitra = null): Transaction
    {
        $mitra ??= $this->mitra;

        $transaksi = Transaction::factory()->create(array_merge([
            'user_id' => User::factory()->create(['role' => 'user']),
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_PENDING,
            'actual_liter' => null,
            'total_value' => null,
            'payment_status' => null,
            'completed_at' => null,
        ], $ganti));

        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $mitra->id,
        ]);

        return $transaksi;
    }

    private function pindai(string $kode)
    {
        return $this->actingAs($this->admin)->post(route('admin.transactions.scan.store'), ['code' => $kode]);
    }

    private function pesanGalat(): string
    {
        return (string) session('errors')->first('code');
    }

    public function test_halaman_pemindai_bisa_dibuka_admin(): void
    {
        $this->siapkan();

        $this->actingAs($this->admin)
            ->get(route('admin.transactions.scan'))
            ->assertOk()
            ->assertSee('Kode transaksi');
    }

    public function test_tautan_pemindai_ada_di_halaman_transaksi(): void
    {
        $this->siapkan();

        $this->actingAs($this->admin)
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->assertSee(route('admin.transactions.scan'));
    }

    public function test_kode_antar_sendiri_mengantar_ke_rincian_transaksinya(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksi();

        $this->pindai($transaksi->code)
            ->assertRedirect(route('admin.transactions.show', $transaksi))
            ->assertSessionHas('success');
    }

    /**
     * Pemindaian tidak boleh meninggalkan jejak apa pun.
     *
     * Pemindaian karyawan sekalian menugaskan transaksinya kepada dirinya.
     * Menyalin perilaku itu untuk admin akan mengotori peringkat karyawan,
     * dan menulis scanned_at akan memunculkan drop-off ini di antrean Pickup
     * yang sedetik lagi ia tinggalkan karena langsung diselesaikan.
     */
    public function test_pemindaian_admin_tidak_mengubah_data(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksi();
        $sebelum = $transaksi->pickup->only(['status', 'assigned_user_id', 'scanned_at', 'assigned_at']);

        $this->pindai($transaksi->code)->assertRedirect();

        $transaksi->refresh();

        $this->assertSame(Transaction::STATUS_PENDING, $transaksi->status);
        $this->assertEquals($sebelum, $transaksi->pickup->refresh()->only(['status', 'assigned_user_id', 'scanned_at', 'assigned_at']));
    }

    /**
     * Sebab kegagalannya disebut, bukan diseragamkan.
     *
     * "Kode tidak ditemukan" untuk transaksi jemput akan membuat admin
     * memindai ulang QR yang sebenarnya sudah terbaca benar, lalu
     * menyimpulkan pemindainya rusak.
     */
    public function test_kode_transaksi_jemput_ditolak_dengan_sebabnya(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksi(['method' => Transaction::METHOD_PICKUP]);

        $this->pindai($transaksi->code)->assertSessionHasErrors('code');

        $pesan = $this->pesanGalat();

        $this->assertStringContainsString('jemput', $pesan);
        $this->assertStringNotContainsString('tidak ditemukan', $pesan);
    }

    public function test_kode_transaksi_yang_sudah_selesai_ditolak_dengan_sebabnya(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksi(['status' => Transaction::STATUS_COMPLETED, 'completed_at' => now()]);

        $this->pindai($transaksi->code)->assertSessionHasErrors('code');

        $this->assertStringContainsString('selesai', $this->pesanGalat());
    }

    public function test_kode_yang_tidak_ada_ditolak(): void
    {
        $this->siapkan();

        $this->pindai('CNT-260101-TIDAKADA')->assertSessionHasErrors('code');
    }

    /**
     * Transaksi mitra lain tidak boleh bocor lewat pemindai.
     *
     * Halaman rinciannya sudah dijaga, tetapi pemindai menerima kode mentah
     * dari siapa pun yang mengetiknya, dan kode transaksi mudah ditebak
     * polanya.
     */
    public function test_kode_milik_mitra_lain_tidak_bisa_dipindai(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksi([], Partner::factory()->create());

        $this->pindai($transaksi->code)->assertSessionHasErrors('code');

        $this->assertStringContainsString('tidak ditemukan', $this->pesanGalat());
    }

    public function test_karyawan_tidak_bisa_membuka_pemindai_admin(): void
    {
        $this->siapkan();

        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([$this->mitra->id]);

        $this->actingAs($karyawan)->get(route('admin.transactions.scan'))->assertForbidden();
        $this->actingAs($karyawan)->post(route('admin.transactions.scan.store'), ['code' => 'CNT-X'])->assertForbidden();
    }
}

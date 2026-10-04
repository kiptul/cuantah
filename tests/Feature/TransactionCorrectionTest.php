<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\Notification;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Koreksi volume transaksi oleh admin.
 *
 * Karyawan menakar di lapangan dan mengetik angkanya di ponsel, jadi salah
 * ketik tidak terhindarkan. Sebelum ini tidak ada jalan membetulkannya:
 * transaksi selesai terkunci, dan penyelesaian sanggahan hanya menulis
 * tanggapan tanpa menyentuh angkanya, sehingga satu-satunya "perbaikan"
 * yang tersedia adalah permintaan maaf tertulis.
 */
class TransactionCorrectionTest extends TestCase
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

    private User $penyetor;

    private function siapkan(): void
    {
        $this->mitra = Partner::factory()->create();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Mitra']);
        $this->admin->partners()->sync([$this->mitra->id]);

        $this->penyetor = User::factory()->create(['role' => 'user']);
    }

    /**
     * Transaksi selesai dengan harga yang dibekukan pada Rp4.000 per liter
     * dan tanpa ongkir, supaya aritmetikanya mudah diperiksa.
     */
    private function selesai(float $liter, string $statusBayar = 'paid'): Transaction
    {
        return Transaction::factory()->create([
            'user_id' => $this->penyetor->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => $liter,
            'price_per_liter' => 4000,
            'pickup_fee' => 0,
            'total_value' => (int) ($liter * 4000),
            'payment_method' => 'cash',
            'payment_status' => $statusBayar,
            'paid_at' => $statusBayar === 'paid' ? now() : null,
            'payment_proof_path' => 'bukti-bayar/lama.jpg',
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function koreksi(Transaction $transaksi, array $ganti = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.transactions.correct', $transaksi), array_merge([
            'actual_liter' => 15,
            'reason' => 'Karyawan salah ketik, hasil timbangan sebenarnya 15 liter.',
        ], $ganti));
    }

    public function test_volume_dan_nilai_dihitung_ulang(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $this->koreksi($transaksi)->assertSessionHas('success');

        $transaksi->refresh();

        $this->assertSame('15.00', $transaksi->actual_liter);
        $this->assertSame(60000, (int) $transaksi->total_value);
    }

    public function test_harga_memakai_yang_tersimpan_bukan_harga_hari_ini(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        OilPrice::factory()->create(['price_per_liter' => 9999, 'is_active' => true]);

        $this->koreksi($transaksi);

        /**
         * 15 liter kali 4.000 yang dibekukan di transaksi, bukan 9.999 yang
         * berlaku hari ini. Mengoreksi salah ketik bulan lalu dengan harga
         * bulan ini mengubah dua hal sekaligus, dan yang kedua tidak diminta
         * siapa pun.
         */
        $this->assertSame(60000, (int) $transaksi->refresh()->total_value);
    }

    public function test_koreksi_naik_mengembalikan_status_ke_belum_dibayar(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $this->koreksi($transaksi);

        $transaksi->refresh();

        $this->assertSame('unpaid', $transaksi->payment_status);
        $this->assertNull($transaksi->paid_at, 'Kekurangannya belum dibayar, jadi tanggal bayar lama tidak boleh bertahan.');
    }

    public function test_koreksi_turun_tetap_lunas(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(15);

        $this->koreksi($transaksi, ['actual_liter' => 1.5]);

        $transaksi->refresh();

        /**
         * Uangnya sudah terlanjur keluar, malah kelebihan. Menandainya belum
         * dibayar berarti mencatat sesuatu yang tidak terjadi, sedangkan
         * aplikasi ini tidak punya alur pengembalian dana.
         */
        $this->assertSame('paid', $transaksi->payment_status);
        $this->assertNotNull($transaksi->paid_at);
        $this->assertSame(6000, (int) $transaksi->total_value);
    }

    public function test_riwayat_menyimpan_keadaan_sebelumnya(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $this->koreksi($transaksi);

        $koreksi = $transaksi->refresh()->corrections()->first();

        $this->assertNotNull($koreksi);
        $this->assertSame('1.50', $koreksi->liter_before);
        $this->assertSame('15.00', $koreksi->liter_after);
        $this->assertSame(6000, $koreksi->value_before);
        $this->assertSame(60000, $koreksi->value_after);
        $this->assertSame('paid', $koreksi->payment_status_before);
        $this->assertSame($this->admin->id, $koreksi->corrected_by);
    }

    public function test_bukti_bayar_lama_ikut_tercatat(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $this->koreksi($transaksi);

        /**
         * Begitu kekurangannya dilunasi, kolom bukti di transaksi menunjuk
         * foto yang baru. Tanpa baris ini bukti pembayaran pertama kehilangan
         * tautannya dari halaman.
         */
        $this->assertSame('bukti-bayar/lama.jpg', $transaksi->corrections()->first()->payment_proof_path_before);
    }

    public function test_penyetor_dikabari_beserta_alasannya(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $this->koreksi($transaksi);

        $kabar = Notification::where('user_id', $this->penyetor->id)->latest('id')->first();

        $this->assertNotNull($kabar, 'Perubahan nominal secara diam-diam lebih buruk daripada salah ketiknya.');
        $this->assertSame('Volume transaksi dikoreksi', $kabar->title);
        $this->assertStringContainsString('salah ketik', $kabar->message);
    }

    public function test_alasan_wajib_dan_tidak_boleh_sekadar_satu_kata(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $this->koreksi($transaksi, ['reason' => 'typo'])->assertSessionHasErrors('reason');

        $this->assertSame('1.50', $transaksi->refresh()->actual_liter, 'Volume tidak boleh berubah ketika alasannya ditolak.');
    }

    public function test_koreksi_ditahan_bila_membuat_stok_mitra_minus(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(15);

        Distribution::create([
            'partner_id' => $this->mitra->id,
            'volume_liter' => 14,
            'destination' => 'Pabrik biodiesel',
            'distributed_at' => now()->toDateString(),
        ]);

        /**
         * Empat belas liter sudah keluar dari lima belas yang masuk. Koreksi
         * ke 1,5 L membuat yang tersalur melebihi yang pernah terkumpul, dan
         * laporan rantai pasoknya berhenti berarti.
         */
        $this->koreksi($transaksi, ['actual_liter' => 1.5])->assertSessionHasErrors('actual_liter');

        $this->assertSame('15.00', $transaksi->refresh()->actual_liter);
    }

    public function test_transaksi_yang_belum_selesai_tidak_bisa_dikoreksi(): void
    {
        $this->siapkan();

        $transaksi = Transaction::factory()->create([
            'user_id' => $this->penyetor->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_SCHEDULED,
        ]);

        $this->koreksi($transaksi)->assertSessionHasErrors('actual_liter');
    }

    public function test_admin_mitra_lain_ditolak_dengan_403(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $adminLain = User::factory()->create(['role' => 'admin']);
        $adminLain->partners()->sync([Partner::factory()->create()->id]);

        $this->actingAs($adminLain)
            ->post(route('admin.transactions.correct', $transaksi), [
                'actual_liter' => 99,
                'reason' => 'Mencoba mengoreksi transaksi mitra lain.',
            ])
            ->assertForbidden();

        $this->assertSame('1.50', $transaksi->refresh()->actual_liter);
    }

    public function test_karyawan_tidak_boleh_mengoreksi(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([$this->mitra->id]);

        /**
         * Karyawanlah yang salah ketik, dan admin adalah gerbang tunggal
         * untuk urusan yang menyentuh uang.
         */
        $this->actingAs($karyawan)
            ->post(route('admin.transactions.correct', $transaksi), [
                'actual_liter' => 99,
                'reason' => 'Membetulkan ketikan saya sendiri.',
            ])
            ->assertForbidden();
    }

    public function test_panel_koreksi_muncul_di_halaman_transaksi_selesai(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $this->actingAs($this->admin)
            ->get(route('admin.transactions.show', $transaksi))
            ->assertOk()
            ->assertSee('Koreksi volume')
            ->assertSee('Alasan koreksi');
    }

    public function test_strip_tandai_lunas_muncul_sendiri_sesudah_koreksi_naik(): void
    {
        $this->siapkan();
        $transaksi = $this->selesai(1.5);

        $this->koreksi($transaksi);

        /**
         * Syarat strip itu memang selesai dan belum dibayar, jadi koreksi naik
         * memunculkannya tanpa perlu tombol baru. Di situlah kekurangannya
         * dilunasi, lengkap dengan kewajiban bukti bayar.
         */
        $this->actingAs($this->admin)
            ->get(route('admin.transactions.show', $transaksi))
            ->assertOk()
            ->assertSee('Tandai Lunas')
            ->assertSee('Bukti transfer');
    }
}

<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Susunan kolom di halaman rincian transaksi admin.
 *
 * Kolom kanan hanya pernah berisi dua hal: keberatan penyetor dan panel aksi.
 * Keduanya bisa sama-sama tidak ada, dan sejak kotak "Transaksi sudah selesai"
 * dibuang, transaksi selesai tanpa sanggahan menyisakan kolom itu kosong
 * sepenuhnya: separuh lebar halaman menganggur sementara seluruh isinya
 * berdesakan di kolom kiri. Jalan keluarnya bukan memindahkan kartu ke sana,
 * sebab yang pindah akan meninggalkan kekosongan di tempat asalnya, melainkan
 * tidak membagi halaman ketika memang tidak ada yang perlu didampingkan.
 */
class AdminTransactionLayoutTest extends TestCase
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

    /**
     * @param  array<string, mixed>  $ganti
     */
    private function rincian(array $ganti = []): string
    {
        $mitra = Partner::factory()->create();

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([$mitra->id]);

        $transaksi = Transaction::factory()->create(array_merge([
            'user_id' => User::factory()->create(['role' => 'user']),
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => 15,
            'price_per_liter' => 4000,
            'pickup_fee' => 0,
            'total_value' => 60000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
            'completed_at' => now(),
            'disputed_at' => null,
        ], $ganti));

        return $this->actingAs($admin)
            ->get(route('admin.transactions.show', $transaksi))
            ->assertOk()
            ->getContent();
    }

    public function test_transaksi_selesai_tanpa_sanggahan_tidak_menyisakan_kolom_kosong(): void
    {
        $isi = $this->rincian();

        $this->assertStringNotContainsString('<aside class="space-y-5"', $isi, 'Kolom kanan tidak boleh dirender bila tidak ada isinya.');
        $this->assertStringNotContainsString('lg:grid-cols-[1fr_380px]', $isi, 'Halaman tidak boleh dibagi dua bila kolom kanannya kosong.');
    }

    /**
     * Isinya tetap lengkap, hanya susunannya yang berubah.
     *
     * Tanpa ini "kolom kanan dihapus" bisa dipenuhi dengan menghapus
     * separuh halaman.
     */
    public function test_isi_halaman_tetap_lengkap_dalam_satu_kolom(): void
    {
        $isi = $this->rincian();

        $this->assertStringContainsString('Informasi</h2>', $isi);
        $this->assertStringContainsString('Volume &amp; nilai</h2>', $isi);
        $this->assertStringContainsString('data-correct-form', $isi, 'Panel koreksi harus tetap ada.');
    }

    public function test_transaksi_belum_selesai_tetap_dua_kolom(): void
    {
        $isi = $this->rincian([
            'status' => Transaction::STATUS_PENDING,
            'actual_liter' => null,
            'total_value' => null,
            'payment_status' => null,
            'payment_method' => null,
            'paid_at' => null,
            'completed_at' => null,
        ]);

        $this->assertStringContainsString('lg:grid-cols-[1fr_380px]', $isi, 'Panel aksi harus tetap mendampingi rinciannya.');
        $this->assertStringContainsString('Selesaikan transaksi', $isi);
    }

    /**
     * Sanggahan menghidupkan kembali kolom kanan walau transaksinya selesai.
     *
     * Keberatan penyetor menuntut tanggapan, jadi ia tetap berdampingan
     * dengan angka yang dipersoalkan, bukan terdorong jauh ke bawah.
     */
    public function test_sanggahan_pada_transaksi_selesai_memunculkan_kolom_kanan(): void
    {
        $isi = $this->rincian([
            'disputed_at' => now(),
            'dispute_reason' => 'Volume yang dicatat lebih kecil dari yang saya serahkan.',
        ]);

        $this->assertStringContainsString('lg:grid-cols-[1fr_380px]', $isi, 'Sanggahan harus berdampingan dengan rinciannya.');
        $this->assertStringContainsString('Keberatan penyetor', $isi);
    }

    /**
     * Bukti bayar hampir selalu tangkapan layar ponsel yang jangkung.
     *
     * Dengan w-full ia terbingkai kotak putih selebar kolom dan gambarnya
     * sendiri tinggal sepita tipis di tengah; lebarnya harus mengikuti
     * gambar, bukan kolomnya.
     */
    public function test_bukti_bayar_mengikuti_rasio_gambarnya(): void
    {
        $isi = $this->rincian(['payment_proof_path' => 'bukti-bayar/contoh.jpg']);

        $this->assertSame(1, preg_match('/<img[^>]*alt="Bukti pembayaran[^"]*"[^>]*>/', $isi, $tag), 'Gambar bukti bayar tidak ditemukan.');
        $this->assertSame(1, preg_match('/class="([^"]*)"/', $tag[0], $kelas), 'Gambar bukti bayar tidak punya kelas.');

        $daftar = preg_split('/\s+/', trim($kelas[1]));

        $this->assertContains('w-auto', $daftar, 'Lebar bukti bayar harus mengikuti gambarnya.');
        $this->assertNotContains('w-full', $daftar, 'Bukti bayar tidak boleh dipaksa selebar kolomnya.');
    }
}

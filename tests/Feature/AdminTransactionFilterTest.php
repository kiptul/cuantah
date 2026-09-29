<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencarian dan penyaringan daftar transaksi admin.
 *
 * Daftarnya dibatasi dua belas per halaman, jadi tanpa penyaring admin harus
 * menyusuri halaman demi halaman untuk menemukan satu kode. Berkas ini
 * menjaga penyaring itu tetap bekerja, dan yang sama pentingnya: tetap tunduk
 * pada pembatasan antar mitra.
 */
class AdminTransactionFilterTest extends TestCase
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

    private function adminMitra(Partner $partner): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($partner->id);

        return $admin;
    }

    /**
     * Kode transaksi yang muncul di daftar admin untuk query string tertentu.
     *
     * @param  array<string, string>  $query
     * @return array<int, string>
     */
    private function kodeTerlihat(User $admin, array $query = []): array
    {
        return $this->actingAs($admin)
            ->get(route('admin.transactions.index', $query))
            ->assertOk()
            ->viewData('transactions')
            ->pluck('code')
            ->all();
    }

    public function test_pencarian_menemukan_transaksi_lewat_kode(): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();
        $dicari = Transaction::factory()->create(['code' => 'CNT-260930-TARGET', 'partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);
        $lain = Transaction::factory()->create(['code' => 'CNT-260930-LAINNYA', 'partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);

        $kode = $this->kodeTerlihat($this->adminMitra($mitra), ['q' => 'TARGET']);

        $this->assertContains($dicari->code, $kode);
        $this->assertNotContains($lain->code, $kode);
    }

    public function test_pencarian_menemukan_transaksi_lewat_nama_penyetor(): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();
        $siti = User::factory()->create(['role' => 'user', 'name' => 'Siti Aminah']);
        $budi = User::factory()->create(['role' => 'user', 'name' => 'Budi Santoso']);
        $punyaSiti = Transaction::factory()->create(['user_id' => $siti->id, 'partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);
        $punyaBudi = Transaction::factory()->create(['user_id' => $budi->id, 'partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);

        $kode = $this->kodeTerlihat($this->adminMitra($mitra), ['q' => 'Aminah']);

        $this->assertContains($punyaSiti->code, $kode);
        $this->assertNotContains($punyaBudi->code, $kode);
    }

    public function test_penyaring_status_membatasi_daftar(): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();
        $selesai = Transaction::factory()->completed(5)->create(['partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);
        $menunggu = Transaction::factory()->create(['partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);

        $kode = $this->kodeTerlihat($this->adminMitra($mitra), ['status' => Transaction::STATUS_COMPLETED]);

        $this->assertSame([$selesai->code], $kode);
        $this->assertNotContains($menunggu->code, $kode);
    }

    public function test_penyaring_metode_membatasi_daftar(): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();
        $jemput = Transaction::factory()->pickup()->create(['partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);
        $antar = Transaction::factory()->create(['partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);

        $kode = $this->kodeTerlihat($this->adminMitra($mitra), ['method' => Transaction::METHOD_PICKUP]);

        $this->assertSame([$jemput->code], $kode);
        $this->assertNotContains($antar->code, $kode);
    }

    public function test_penyaring_digabung_sebagai_irisan_bukan_gabungan(): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();
        $cocok = Transaction::factory()->pickup()->completed(5)->create(['code' => 'CNT-260930-COCOK', 'partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);
        $statusSaja = Transaction::factory()->completed(5)->create(['code' => 'CNT-260930-ANTAR', 'partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);
        $metodeSaja = Transaction::factory()->pickup()->create(['code' => 'CNT-260930-PENDING', 'partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);

        $kode = $this->kodeTerlihat($this->adminMitra($mitra), [
            'status' => Transaction::STATUS_COMPLETED,
            'method' => Transaction::METHOD_PICKUP,
        ]);

        $this->assertSame([$cocok->code], $kode);
        $this->assertNotContains($statusSaja->code, $kode);
        $this->assertNotContains($metodeSaja->code, $kode);
    }

    public function test_status_di_luar_daftar_diabaikan_bukan_mengosongkan_daftar(): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();
        $transaksi = Transaction::factory()->create(['partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);

        $kode = $this->kodeTerlihat($this->adminMitra($mitra), ['status' => 'bukan-status']);

        $this->assertSame([$transaksi->code], $kode, 'Status asing seharusnya diabaikan, bukan diteruskan ke where.');
    }

    public function test_wildcard_like_dicari_sebagai_karakter_biasa(): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();
        Transaction::factory()->create(['code' => 'CNT-260930-BIASA', 'partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);

        $kode = $this->kodeTerlihat($this->adminMitra($mitra), ['q' => '%']);

        $this->assertSame([], $kode, 'Tanda persen seharusnya dicari apa adanya, bukan mencocokkan semua baris.');
    }

    public function test_penyaring_tetap_tunduk_pada_pembatasan_antar_mitra(): void
    {
        $harga = OilPrice::factory()->create();
        $mitraA = Partner::factory()->create();
        $mitraB = Partner::factory()->create();
        $milikA = Transaction::factory()->completed(5)->create(['partner_id' => $mitraA->id, 'oil_price_id' => $harga->id]);
        $milikB = Transaction::factory()->completed(5)->create(['partner_id' => $mitraB->id, 'oil_price_id' => $harga->id]);

        $kode = $this->kodeTerlihat($this->adminMitra($mitraA), ['status' => Transaction::STATUS_COMPLETED]);

        $this->assertSame([$milikA->code], $kode);
        $this->assertNotContains($milikB->code, $kode, 'Penyaring tidak boleh menembus batas mitra.');
    }

    public function test_tautan_halaman_membawa_penyaring(): void
    {
        $mitra = Partner::factory()->create();
        $harga = OilPrice::factory()->create();
        Transaction::factory()->count(15)->completed(5)->create(['partner_id' => $mitra->id, 'oil_price_id' => $harga->id]);

        $response = $this->actingAs($this->adminMitra($mitra))
            ->get(route('admin.transactions.index', ['status' => Transaction::STATUS_COMPLETED]))
            ->assertOk();

        $this->assertStringContainsString(
            'status='.Transaction::STATUS_COMPLETED,
            $response->viewData('transactions')->nextPageUrl(),
            'Tautan halaman berikutnya harus membawa penyaring, kalau tidak admin terlempar ke seluruh transaksi.'
        );
    }
}

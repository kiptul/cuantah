<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kejelasan isian dan angka di halaman admin.
 *
 * Formulir di sini mewarisi dua kebiasaan yang sama-sama menghilangkan
 * keterangan tepat ketika ia paling dibutuhkan. Yang pertama, keterangan
 * hanya ditaruh di placeholder, padahal placeholder lenyap begitu kotaknya
 * diisi. Yang kedua, formulir edit bahkan tidak punya placeholder sebab
 * kotaknya memang selalu terisi, sehingga enam kotak berjajar hanya berisi
 * nilai tanpa satu pun penjelasan: angka 500 bisa berarti apa saja.
 *
 * Halaman Pengguna sudah memakai label sejak awal, jadi polanya memang
 * sudah ada di repositori; tiga halaman inilah yang tertinggal.
 */
class AdminFormClarityTest extends TestCase
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

    private function masukSebagaiAdmin(): void
    {
        $this->mitra = Partner::factory()->create();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->partners()->sync([$this->mitra->id]);

        $this->actingAs($this->admin);
    }

    private function isi(string $rute): string
    {
        return $this->get(route($rute))->assertOk()->getContent();
    }

    /**
     * Memastikan sebuah kotak isian berada di dalam label yang ada tulisannya.
     *
     * Label yang sekadar berdekatan tidak diperiksa, sebab yang menolong
     * pembaca adalah keterikatannya: keterangan harus ikut terbaca bersama
     * kotaknya, dan mengkliknya memindahkan fokus ke kotak yang tepat.
     */
    private function assertBerlabel(string $isi, string $nama, string $label): void
    {
        $this->assertMatchesRegularExpression(
            '/<label[^>]*>\s*<span[^>]*>'.preg_quote($label, '/').'[^<]*<\/span>\s*<(?:input|select|textarea)[^>]*name="'.preg_quote($nama, '/').'"/s',
            $isi,
            'Kotak "'.$nama.'" harus berada di dalam label "'.$label.'".'
        );
    }

    /**
     * Isi formulir edit mitra saja.
     *
     * Halaman ini memuat dua formulir yang bentuknya mirip. Memeriksa
     * seluruh halaman membuat label di formulir tambah menutupi hilangnya
     * label di formulir edit, sehingga pemeriksaannya tetap hijau padahal
     * justru formulir editlah yang dulu tidak punya keterangan apa pun.
     */
    private function formulirEdit(): string
    {
        preg_match(
            '/<form[^>]*action="[^"]*\/admin\/partners\/\d+"[^>]*>.*?<\/form>/s',
            $this->isi('admin.partners.index'),
            $cocok
        );

        $this->assertNotEmpty($cocok, 'Formulir edit mitra harus ditemukan.');

        return $cocok[0];
    }

    public function test_formulir_edit_mitra_menyebut_arti_tiap_kotak(): void
    {
        $this->masukSebagaiAdmin();

        $isi = $this->formulirEdit();

        foreach ([
            'name' => 'Nama mitra',
            'type' => 'Tipe mitra',
            'phone' => 'Telepon',
            'capacity_liter' => 'Kapasitas (liter)',
            'status' => 'Status',
            'address' => 'Alamat',
        ] as $nama => $label) {
            $this->assertBerlabel($isi, $nama, $label);
        }
    }

    public function test_kapasitas_menyebut_satuannya(): void
    {
        $this->masukSebagaiAdmin();

        /**
         * Nilainya tersimpan sebagai angka polos. Tanpa satuan, "500" sama
         * saja dengan tidak ada keterangan.
         */
        $this->assertStringContainsString('Kapasitas (liter)', $this->isi('admin.partners.index'));
    }

    public function test_formulir_harga_menyebut_arti_tiap_kotak(): void
    {
        $this->masukSebagaiAdmin();

        $isi = $this->isi('admin.prices.index');

        $this->assertBerlabel($isi, 'price_per_liter', 'Harga per liter (Rp)');
        $this->assertBerlabel($isi, 'effective_date', 'Berlaku mulai');
    }

    public function test_formulir_penyaluran_menyebut_arti_tiap_kotak(): void
    {
        $this->masukSebagaiAdmin();

        $isi = $this->isi('admin.distributions.index');

        $this->assertBerlabel($isi, 'partner_id', 'Mitra asal');
        $this->assertBerlabel($isi, 'volume_liter', 'Volume (liter)');
        $this->assertBerlabel($isi, 'destination', 'Tujuan penyaluran');
        $this->assertBerlabel($isi, 'distributed_at', 'Tanggal penyaluran');
    }

    public function test_dua_kotak_tanggal_tidak_lagi_tanpa_keterangan(): void
    {
        $this->masukSebagaiAdmin();

        /**
         * Keduanya dulu hanya berbunyi dd/mm/yyyy. Kotak tanggal memang
         * memperlihatkan bentuknya sendiri, tetapi tidak pernah memberi tahu
         * tanggal apa yang diminta.
         */
        $this->assertStringContainsString('Berlaku mulai', $this->isi('admin.prices.index'));
        $this->assertStringContainsString('Tanggal penyaluran', $this->isi('admin.distributions.index'));
    }

    /**
     * Transaksi selesai pada bulan tertentu dengan volume yang ditentukan.
     */
    private function transaksiSelesai(string $kapan, float $liter): void
    {
        Transaction::factory()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => $liter,
            'total_value' => (int) ($liter * 4000),
            'payment_status' => 'paid',
            'completed_at' => $kapan,
            /**
             * Keduanya diisi supaya test ini menguji tampilan angkanya saja.
             * Kolom mana yang menjadi patokan periode diuji tersendiri di
             * CompletedAtAnchorTest.
             */
            'created_at' => $kapan,
        ]);
    }

    public function test_pertumbuhan_ribuan_persen_dinyatakan_sebagai_kelipatan(): void
    {
        $this->masukSebagaiAdmin();

        $this->transaksiSelesai(now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateTimeString(), 1);
        $this->transaksiSelesai(now()->startOfMonth()->addDay()->toDateTimeString(), 20);

        $isi = $this->isi('admin.dashboard');

        /**
         * Dalam format Indonesia titik adalah pemisah ribuan, sehingga 1900
         * persen tercetak "1.900%" dan dapat terbaca sebagai satu koma sembilan
         * persen. Kelipatan tidak punya keraguan itu.
         */
        $this->assertDoesNotMatchRegularExpression(
            '/\d\.\d{3}%/',
            $isi,
            'Persentase berpemisah ribuan terbaca dua cara.'
        );
        $this->assertStringContainsString('×', $isi, 'Pertumbuhan sebesar itu dinyatakan sebagai kelipatan.');
    }

    public function test_pertumbuhan_di_bawah_seribu_persen_tetap_memakai_persen(): void
    {
        $this->masukSebagaiAdmin();

        $this->transaksiSelesai(now()->subMonthNoOverflow()->startOfMonth()->addDay()->toDateTimeString(), 10);
        $this->transaksiSelesai(now()->startOfMonth()->addDay()->toDateTimeString(), 20);

        /**
         * Di bawah seribu, angkanya tidak pernah memuat titik, jadi tidak ada
         * alasan menggantinya menjadi kelipatan yang justru kurang lazim.
         */
        $this->assertStringContainsString('100%', $this->isi('admin.dashboard'));
    }

    public function test_arti_lencana_terbaca_tanpa_mengarahkan_tetikus(): void
    {
        $this->masukSebagaiAdmin();
        $this->transaksiSelesai(now()->startOfMonth()->addDay()->toDateTimeString(), 5);

        $isi = $this->isi('admin.dashboard');

        /**
         * Artinya dulu hanya tersimpan di atribut title. Tooltip tidak pernah
         * muncul di layar sentuh, sehingga di ponsel lencana itu berupa angka
         * tanpa keterangan apa pun.
         */
        $tanpaAtribut = preg_replace('/<[^>]+>/', ' ', $isi);

        $this->assertStringContainsString('membandingkan bulan ini dengan bulan lalu', $tanpaAtribut);
    }

    public function test_istilah_inggris_di_halaman_admin_sudah_diterjemahkan(): void
    {
        $this->masukSebagaiAdmin();

        /**
         * Judul "Penugasan" berada di dalam kartu pickup. Tanpa satu pun
         * pickup, kedua kata sama-sama tidak pernah dirender dan
         * pemeriksaannya tidak menguji apa pun.
         */
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
        ]);
        $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'pending',
        ]);

        $halamanPickup = $this->isi('admin.pickups.index');

        $this->assertStringContainsString('>Penugasan</p>', $halamanPickup, 'Kartu pickup harus benar-benar dirender.');
        $this->assertStringNotContainsString('Assignment', $halamanPickup);
        $this->assertStringNotContainsString('>Dashboard</h1>', $this->isi('admin.dashboard'));
    }
}

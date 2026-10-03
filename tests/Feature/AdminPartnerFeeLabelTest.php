<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerDeliveryFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keterangan pada aturan ongkir jemput di halaman Mitra.
 *
 * Ketiga kotaknya dulu hanya berbekal placeholder. Placeholder lenyap
 * begitu kotaknya diisi, sehingga baris yang sudah tersimpan berubah
 * menjadi tiga angka telanjang: 3, 5, 10000. Keterangannya justru paling
 * dibutuhkan sesudah terisi, yaitu ketika admin membacanya kembali atau
 * membaca aturan yang dibuat orang lain.
 *
 * Barisnya dibuat di dua tempat: Blade untuk aturan yang sudah tersimpan,
 * dan template JavaScript untuk baris yang baru ditambahkan. Keduanya
 * diperiksa di sini, sebab memperbaiki satu dan melupakan yang lain
 * menghasilkan dua baris yang tampak berbeda di halaman yang sama.
 */
class AdminPartnerFeeLabelTest extends TestCase
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
     * @var array<int, string>
     */
    private array $label = ['Jarak minimum (km)', 'Jarak maksimum (km)', 'Ongkir (Rp)'];

    private function halaman(): string
    {
        $mitra = Partner::factory()->create();

        PartnerDeliveryFee::factory()->create([
            'partner_id' => $mitra->id,
            'min_distance_km' => 3,
            'max_distance_km' => 5,
            'fee' => 10000,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([$mitra->id]);

        return $this->actingAs($admin)->get(route('admin.partners.index'))->assertOk()->getContent();
    }

    /**
     * Isi baris aturan yang sudah tersimpan, tanpa template JavaScript.
     */
    private function barisTersimpan(string $isi): string
    {
        $tanpaSkrip = preg_replace('/<script.*?<\/script>/s', '', $isi);

        preg_match('/<div data-fee-row.*?<\/div>\s*<\/div>/s', $tanpaSkrip, $cocok);

        $this->assertNotEmpty($cocok, 'Baris aturan yang tersimpan harus ditemukan untuk bisa diperiksa.');

        return $cocok[0];
    }

    public function test_baris_yang_sudah_terisi_tetap_menyebut_arti_tiap_angka(): void
    {
        $baris = $this->barisTersimpan($this->halaman());

        foreach ($this->label as $label) {
            $this->assertStringContainsString($label, $baris);
        }
    }

    public function test_tiap_kotak_terikat_pada_labelnya(): void
    {
        $baris = $this->barisTersimpan($this->halaman());

        /**
         * Label yang sekadar berdekatan tidak cukup. Yang diperiksa adalah
         * kotaknya benar-benar berada di dalam label, sehingga mengklik
         * keterangannya memindahkan fokus ke kotak yang tepat.
         */
        foreach (['min_distance_km', 'max_distance_km', 'fee'] as $nama) {
            $this->assertMatchesRegularExpression(
                '/<label[^>]*>\s*<span[^>]*>[^<]+<\/span>\s*<input[^>]*'.preg_quote($nama, '/').'/s',
                $baris,
                'Kotak '.$nama.' harus berada di dalam labelnya.'
            );
        }
    }

    public function test_baris_yang_dibuat_javascript_memakai_label_yang_sama(): void
    {
        preg_match_all('/<script.*?<\/script>/s', $this->halaman(), $cocok);

        /**
         * Halaman ini memuat beberapa script. Yang dicari adalah yang
         * menyusun baris aturan, bukan yang pertama kebetulan ditemukan.
         */
        $template = collect($cocok[0])->first(
            fn (string $skrip) => str_contains($skrip, 'delivery_fees[${index}]')
        );

        $this->assertNotNull($template, 'Template penyusun baris aturan harus ditemukan.');

        foreach ($this->label as $label) {
            $this->assertStringContainsString(
                $label,
                $template,
                'Baris baru harus tampak sama dengan baris yang sudah tersimpan.'
            );
        }
    }

    public function test_placeholder_lama_yang_menyamar_sebagai_keterangan_sudah_hilang(): void
    {
        $isi = $this->halaman();

        foreach (['placeholder="Min km"', 'placeholder="Max km kosong = lebih dari"', 'placeholder="Ongkir"'] as $usang) {
            $this->assertStringNotContainsString($usang, $isi);
        }
    }

    public function test_aturan_jarak_maksimal_kosong_dijelaskan_di_kedua_formulir(): void
    {
        /**
         * Satu di formulir tambah mitra, satu di tiap formulir edit. Dulu
         * keterangannya hanya ada di formulir tambah, padahal di formulir
         * editlah admin membaca ulang aturan yang dibuat orang lain.
         */
        $this->assertSame(
            2,
            substr_count($this->halaman(), 'Kosongkan jarak maksimal untuk range terakhir'),
            'Keterangan harus muncul di formulir tambah dan di formulir edit mitra.'
        );
    }

    public function test_tombol_tambah_range_tidak_lagi_menyamar_sebagai_kotak_isian(): void
    {
        preg_match_all('/<button[^>]*data-fee-add[^>]*>/', $this->halaman(), $cocok);

        $this->assertNotEmpty($cocok[0]);

        foreach ($cocok[0] as $tombol) {
            $this->assertStringNotContainsString(
                'border-slate-300',
                $tombol,
                'Tombol ini memakai kelas kotak isian, sehingga terbaca sebagai kolom yang bisa diketik.'
            );
            $this->assertStringNotContainsString('w-full', $tombol);
        }
    }
}

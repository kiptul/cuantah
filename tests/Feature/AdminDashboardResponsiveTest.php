<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu grafik di dasbor admin pada layar sempit.
 *
 * Canvas punya lebar minimum intrinsik 300px. Kartunya adalah grid item,
 * dan grid item mewarisi min-width:auto, sehingga kolomnya menolak menyusut
 * di bawah 300px ditambah padding kartu. Di layar 320px seluruh halaman
 * ikut tergeser dan teks di tepi kiri terpotong.
 *
 * Gejalanya hanya muncul di bawah breakpoint xl, sebab di atasnya kolomnya
 * sudah dibatasi minmax(0,1fr). Itulah sebabnya ia lolos dari pemeriksaan
 * yang dilakukan di layar lebar.
 */
class AdminDashboardResponsiveTest extends TestCase
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

    private function dasbor(): string
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([Partner::factory()->create()->id]);

        return $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
    }

    /**
     * Isi section yang memuat kartu grafik, dari pembuka sampai penutupnya.
     */
    private function sectionGrafik(string $isi): string
    {
        preg_match(
            '/<section[^>]*xl:grid-cols-\[minmax\(0,1fr\)_340px\][^>]*>(.*?)<\/section>/s',
            $isi,
            $cocok
        );

        $this->assertNotEmpty($cocok, 'Section kartu grafik harus ditemukan untuk bisa diperiksa.');
        $this->assertStringContainsString('monthlyChart', $cocok[1], 'Section yang diperiksa harus yang memuat grafik.');

        return $cocok[1];
    }

    /**
     * Daftar kelas pada kartu grafik, yaitu elemen pertama di dalam section.
     *
     * Sengaja hanya elemen terluar yang diambil. Pemeriksaan yang sekadar
     * mencari "min-w-0" di mana saja akan tetap hijau ketika kelas itu
     * dicabut dari kartunya, sebab elemen di dalamnya juga memakainya.
     */
    private function kelasKartu(string $isi): string
    {
        preg_match('/^\s*<div class="([^"]*)"/', $this->sectionGrafik($isi), $cocok);

        $this->assertNotEmpty($cocok, 'Kartu grafik harus menjadi elemen pertama di dalam section.');

        return $cocok[1];
    }

    public function test_kartu_grafik_boleh_menyusut_di_bawah_lebar_intrinsik_canvas(): void
    {
        $this->assertStringContainsString(
            'min-w-0',
            $this->kelasKartu($this->dasbor()),
            'Tanpa min-w-0 pada kartunya sendiri, grid item menolak menyusut dan halaman meluber di layar sempit.'
        );
    }

    public function test_canvas_berada_di_dalam_kartu_itu_bukan_di_sebelahnya(): void
    {
        $section = $this->sectionGrafik($this->dasbor());

        $posisiCanvas = strpos($section, 'id="monthlyChart"');
        $posisiAside = strpos($section, '<aside');

        $this->assertNotFalse($posisiCanvas);
        $this->assertNotFalse($posisiAside, 'Kartu kedua section ini adalah aside stok mitra.');

        /**
         * Memindahkan canvas ke kolom kedua mengembalikan luberannya tanpa
         * mengubah satu pun kelas, jadi letaknya ikut diperiksa.
         */
        $this->assertLessThan(
            $posisiAside,
            $posisiCanvas,
            'Canvas harus berada di kartu pertama, yaitu kartu yang diberi min-w-0.'
        );
    }

    public function test_grafik_tetap_dipasang_responsif(): void
    {
        /**
         * min-w-0 hanya mengizinkan kartunya menyusut. Yang membuat canvas
         * ikut menyusut adalah responsive:true milik Chart.js; tanpa itu
         * kartunya mengecil sementara canvas tetap 300px dan keluar dari
         * batas kartu.
         */
        $this->assertStringContainsString('responsive: true', $this->dasbor());
    }
}

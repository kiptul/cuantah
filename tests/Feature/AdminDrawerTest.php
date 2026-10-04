<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu operasional admin pada layar sempit.
 *
 * Di bawah lg, kedelapan menu dulu membungkus menjadi blok kartu di atas isi
 * halaman, sehingga satu layar ponsel habis oleh navigasi sebelum satu pun
 * data terlihat. Sekarang ia menjadi laci yang menggeser masuk dari kiri,
 * dan di lg ke atas kembali menjadi sidebar seperti semula.
 *
 * Dibuat dengan checkbox dan peer, bukan JavaScript, mengikuti menu publik
 * yang juga tidak memakai skrip.
 */
class AdminDrawerTest extends TestCase
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

    private function halaman(): string
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([Partner::factory()->create()->id]);

        return $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();
    }

    /**
     * Tag pembuka aside laci.
     */
    private function tagAside(string $isi): string
    {
        preg_match('/<aside[^>]*peer-checked:translate-x-0[^>]*>/', $isi, $cocok);

        $this->assertNotEmpty($cocok, 'Aside laci harus ditemukan untuk bisa diperiksa.');

        return $cocok[0];
    }

    public function test_laci_punya_pemicu_dan_saklarnya(): void
    {
        $isi = $this->halaman();

        $this->assertStringContainsString('id="adminDrawer"', $isi);
        $this->assertStringContainsString('for="adminDrawer"', $isi);
    }

    public function test_pemicu_laci_berada_di_navbar_bukan_di_badan_halaman(): void
    {
        $isi = $this->halaman();

        preg_match('/<header.*?<\/header>/s', $isi, $header);

        $this->assertNotEmpty($header, 'Header harus ditemukan.');
        $this->assertStringContainsString(
            'for="adminDrawer"',
            $header[0],
            'Pemicunya duduk sebaris dengan lonceng, sama seperti sisi penyetor dan karyawan.'
        );

        /**
         * Dulu ia tombol terpisah bertuliskan "Menu operasional" di atas isi
         * halaman, sehingga tiga peran membuka menunya dari tiga tempat yang
         * berbeda.
         */
        $tanpaHeader = preg_replace('/<header.*?<\/header>/s', '', $isi);

        $this->assertStringNotContainsString('Menu operasional', $tanpaHeader);
    }

    public function test_halaman_admin_tanpa_laci_tidak_menampilkan_pemicunya(): void
    {
        /**
         * Halaman Akun memakai layout dasar, bukan layouts/admin, jadi tidak
         * punya laci. Pemicunya dulu tetap tampil dan tidak membuka apa pun.
         */
        $admin = User::factory()->create(['role' => 'admin']);

        $isi = $this->actingAs($admin)->get(route('profile.edit'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id="adminDrawer"', $isi);
        $this->assertStringNotContainsString('for="adminDrawer"', $isi);
    }

    public function test_saklar_bersaudara_langsung_dengan_lacinya(): void
    {
        /**
         * peer-checked memakai pemilih saudara. Memindahkan checkbox ke dalam
         * pembungkus lain membuat lacinya tidak pernah terbuka, tanpa satu pun
         * kelas berubah, jadi letaknya ikut diperiksa.
         */
        $this->assertMatchesRegularExpression(
            '/<input id="adminDrawer"[^>]*>\s*(?:<[^>]+>[^<]*<\/[a-z]+>\s*)*<aside/s',
            $this->halaman(),
            'Checkbox harus berada tepat sebelum aside, dalam induk yang sama.'
        );
    }

    public function test_laci_tertutup_sampai_saklarnya_dicentang(): void
    {
        $aside = $this->tagAside($this->halaman());

        $this->assertStringContainsString('-translate-x-full', $aside, 'Keadaan awalnya tertutup.');
        $this->assertStringContainsString('peer-checked:translate-x-0', $aside, 'Dan terbuka hanya ketika saklarnya dicentang.');
    }

    public function test_di_layar_lebar_kembali_menjadi_sidebar(): void
    {
        $aside = $this->tagAside($this->halaman());

        $this->assertStringContainsString('lg:translate-x-0', $aside, 'Tanpa ini sidebar desktop ikut tergeser keluar layar.');
        $this->assertStringContainsString('lg:sticky', $aside);
    }

    public function test_pemicunya_tidak_muncul_di_layar_lebar(): void
    {
        preg_match_all('/<label[^>]*for="adminDrawer"[^>]*>/', $this->halaman(), $cocok);

        $this->assertNotEmpty($cocok[0]);

        foreach ($cocok[0] as $label) {
            $this->assertMatchesRegularExpression(
                '/lg:hidden|lg:peer-checked:hidden/',
                $label,
                'Di lg sidebar-nya sudah terlihat, jadi pemicu laci hanya menambah kebisingan.'
            );
        }
    }

    public function test_laci_berada_di_atas_peta_leaflet(): void
    {
        /**
         * Leaflet menaruh pane petanya di z-index 400 dan kotak kontrolnya di
         * 1000, sedangkan wadah peta hanya position:relative tanpa z-index
         * sehingga tidak membentuk konteks penumpukan sendiri. Dengan z-index
         * laci di bawah atau sama dengan 1000, peta di halaman Pickup menimpa
         * laci yang sedang terbuka.
         */
        preg_match('/<aside[^>]*\sz-\[(\d+)\][^>]*peer-checked:translate-x-0/', $this->halaman(), $cocok);

        $this->assertNotEmpty($cocok, 'Laci harus memakai z-index yang dinyatakan, bukan bawaan.');
        $this->assertGreaterThan(1000, (int) $cocok[1], 'Kontrol Leaflet berada di z-index 1000.');
    }

    public function test_seluruh_menu_admin_terjangkau_dari_dalam_laci(): void
    {
        $isi = $this->halaman();

        preg_match('/<aside[^>]*peer-checked:translate-x-0.*?<\/aside>/s', $isi, $cocok);

        $this->assertNotEmpty($cocok);

        foreach ([
            route('admin.dashboard'),
            route('admin.transactions.index'),
            route('admin.pickups.index'),
            route('admin.partners.index'),
            route('admin.users.index'),
            route('admin.prices.index'),
            route('admin.distributions.index'),
            route('admin.reports.index'),
        ] as $tujuan) {
            $this->assertStringContainsString($tujuan, $cocok[0]);
        }
    }
}

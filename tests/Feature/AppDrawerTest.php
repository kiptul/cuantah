<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laci menu di sisi penyetor, karyawan, dan halaman publik.
 *
 * Header membungkus menjadi dua baris setinggi 113px di layar 375px, yaitu
 * 14 persen tinggi layar habis oleh navigasi sebelum satu pun isi terlihat.
 * Penyetor dan karyawan sama-sama begitu, sebab keduanya memakai layout yang
 * sama.
 *
 * Menunya dipindahkan seluruhnya ke laci, bukan sebagian. Mempertahankan
 * tombol Setor di header mengembalikan pembungkusan: logo 145, tombol 48,
 * hamburger 40, lonceng 40, dan avatar 68 berjumlah 373px, sedangkan ruang
 * yang tersedia hanya 351px. Pilihan lain adalah memangkas logo menjadi
 * lambang saja, dan logo itu dirancang khusus.
 *
 * Halaman publik sebelumnya memakai dropdown yang menggantung dari tombolnya
 * dengan lebar dibatasi, sehingga tujuh menu menumpuk di pojok kanan atas.
 */
class AppDrawerTest extends TestCase
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

    private function penyetor(): User
    {
        return User::factory()->create(['role' => 'user']);
    }

    private function karyawan(): User
    {
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([Partner::factory()->create()->id]);

        return $karyawan;
    }

    /**
     * Tag pembuka aside laci pada sebuah halaman.
     */
    private function tagAside(string $isi): string
    {
        preg_match('/<aside[^>]*peer-checked:translate-x-0[^>]*>/', $isi, $cocok);

        $this->assertNotEmpty($cocok, 'Aside laci harus ditemukan untuk bisa diperiksa.');

        return $cocok[0];
    }

    /**
     * Memastikan aside laci benar-benar bersaudara dengan saklarnya.
     *
     * Diperiksa lewat pohon DOM, bukan pola teks. Pola teks dengan .* akan
     * menembus tag penutup pembungkus, sehingga membungkus checkbox di dalam
     * div tetap dianggap lolos padahal pemilih saudara peer-checked langsung
     * berhenti bekerja dan lacinya tidak pernah terbuka.
     */
    private function assertSaklarBersaudaraDenganLaci(string $isi, string $id): void
    {
        $sebelumnya = libxml_use_internal_errors(true);

        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$isi);

        libxml_clear_errors();
        libxml_use_internal_errors($sebelumnya);

        $saklar = $dom->getElementById($id);

        $this->assertNotNull($saklar, 'Saklar #'.$id.' harus ada di halaman.');

        $ketemu = false;
        $node = $saklar->nextSibling;

        while ($node !== null) {
            if ($node instanceof \DOMElement && $node->tagName === 'aside') {
                $ketemu = str_contains($node->getAttribute('class'), 'peer-checked:translate-x-0');

                break;
            }

            $node = $node->nextSibling;
        }

        $this->assertTrue(
            $ketemu,
            'Aside laci harus menjadi saudara yang mengikuti #'.$id.', sebab peer-checked memakai pemilih saudara.'
        );
    }

    /**
     * Memastikan laci tidak terkurung di dalam elemen yang membentuk
     * containing block bagi position:fixed.
     *
     * Properti seperti backdrop-filter, transform, dan filter membuat
     * inset-y-0 berhenti berarti setinggi layar dan menjadi setinggi
     * pembungkusnya. Header publik memakai backdrop-blur, dan lacinya
     * memang menciut menjadi kotak pendek menempel di atas, lengkap dengan
     * bilah gulir sendiri. Header aplikasi belum memakainya, jadi lacinya
     * selamat secara kebetulan saja.
     *
     * Yang diperiksa letaknya, bukan daftar propertinya, sebab properti
     * yang membentuk containing block bisa bertambah kapan saja pada
     * elemen mana pun di atasnya.
     */
    private function assertLaciTidakDiDalamHeader(string $isi, string $id): void
    {
        $sebelumnya = libxml_use_internal_errors(true);

        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$isi);

        libxml_clear_errors();
        libxml_use_internal_errors($sebelumnya);

        $saklar = $dom->getElementById($id);

        $this->assertNotNull($saklar, 'Saklar #'.$id.' harus ada.');

        $induk = $saklar->parentNode;

        while ($induk instanceof \DOMElement) {
            $this->assertNotSame(
                'header',
                $induk->tagName,
                'Laci #'.$id.' berada di dalam <header>, sehingga tingginya ikut tinggi header begitu header diberi backdrop-filter.'
            );

            $induk = $induk->parentNode;
        }
    }

    public function test_laci_tidak_terkurung_di_dalam_header(): void
    {
        $this->assertLaciTidakDiDalamHeader(
            $this->actingAs($this->penyetor())->get(route('dashboard'))->assertOk()->getContent(),
            'appDrawer'
        );

        $this->assertLaciTidakDiDalamHeader(
            $this->get(route('home'))->assertOk()->getContent(),
            'publicDrawer'
        );
    }

    public function test_penyetor_punya_laci(): void
    {
        $isi = $this->actingAs($this->penyetor())->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="appDrawer"', $isi);
        $this->assertStringContainsString('-translate-x-full', $this->tagAside($isi));
        $this->assertStringContainsString('peer-checked:translate-x-0', $this->tagAside($isi));
    }

    public function test_karyawan_punya_laci_yang_sama(): void
    {
        $isi = $this->actingAs($this->karyawan())->get(route('employee.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('id="appDrawer"', $isi);
        $this->assertStringContainsString('peer-checked:translate-x-0', $this->tagAside($isi));
    }

    public function test_saklar_bersaudara_langsung_dengan_lacinya(): void
    {
        /**
         * peer-checked memakai pemilih saudara. Memindahkan checkbox ke dalam
         * pembungkus lain membuat lacinya tidak pernah terbuka, tanpa satu pun
         * kelas berubah, jadi letaknya ikut diperiksa.
         */
        $this->assertSaklarBersaudaraDenganLaci(
            $this->actingAs($this->penyetor())->get(route('dashboard'))->assertOk()->getContent(),
            'appDrawer'
        );
    }

    public function test_laci_hilang_di_layar_lebar(): void
    {
        $isi = $this->actingAs($this->penyetor())->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'sm:hidden',
            $this->tagAside($isi),
            'Di sm ke atas menu kembali ke header, jadi lacinya tidak boleh ikut tampil.'
        );
    }

    public function test_seluruh_menu_penyetor_ada_di_dalam_laci(): void
    {
        $isi = $this->actingAs($this->penyetor())->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<aside[^>]*peer-checked:translate-x-0.*?<\/aside>/s', $isi, $cocok);

        $this->assertNotEmpty($cocok);

        foreach ([route('dashboard'), route('deposits.create'), route('transactions.index')] as $tujuan) {
            $this->assertStringContainsString($tujuan, $cocok[0]);
        }
    }

    public function test_penanda_halaman_aktif_menunjuk_halaman_yang_dibuka(): void
    {
        /**
         * Tombol Setor dulu berlatar hijau terus-menerus karena ia tombol ajakan,
         * bukan penanda posisi. Di halaman Transaksi, yang hijau tetap Setor,
         * sehingga warnanya membaca "kamu di sini" sambil menunjuk halaman lain.
         */
        $isi = $this->actingAs($this->penyetor())->get(route('transactions.index'))->assertOk()->getContent();

        /**
         * Diperiksa di dalam laci, bukan di seluruh halaman. Menu di header
         * juga menandai halaman aktif, sehingga pemeriksaan tingkat halaman
         * tetap hijau ketika penandanya dicabut dari laci.
         */
        preg_match('/<aside[^>]*peer-checked:translate-x-0.*?<\/aside>/s', $isi, $laci);

        $this->assertNotEmpty($laci, 'Laci harus ditemukan.');

        preg_match_all('/<a\s[^>]*aria-current="page"[^>]*>/', $laci[0], $cocok);

        $this->assertNotEmpty($cocok[0], 'Halaman yang sedang dibuka harus ditandai di dalam laci.');

        foreach ($cocok[0] as $tautan) {
            $this->assertStringContainsString(route('transactions.index'), $tautan);
            $this->assertStringNotContainsString(route('deposits.create'), $tautan);
        }
    }

    public function test_admin_tidak_mendapat_laci_kedua(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([Partner::factory()->create()->id]);

        $isi = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->getContent();

        /**
         * Halaman admin menumpuk layout admin di dalam layout aplikasi. Keduanya
         * punya laci sendiri, dan dua saklar pada satu halaman berarti satu
         * tombol membuka laci yang salah.
         */
        $this->assertStringContainsString('id="adminDrawer"', $isi);
        $this->assertStringNotContainsString('id="appDrawer"', $isi);
    }

    public function test_halaman_publik_memakai_laci_yang_sama(): void
    {
        $isi = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('id="publicDrawer"', $isi);
        $this->assertStringContainsString('peer-checked:translate-x-0', $this->tagAside($isi));
        $this->assertStringContainsString('xl:hidden', $this->tagAside($isi));
    }

    public function test_saklar_laci_publik_juga_bersaudara_langsung(): void
    {
        /**
         * Pemeriksaan yang sama untuk laci publik. Tanpa ini, memindahkan
         * checkboxnya ke dalam pembungkus lain membuat lacinya tidak pernah
         * terbuka dan tidak ada test yang menyadarinya.
         */
        $this->assertSaklarBersaudaraDenganLaci(
            $this->get(route('home'))->assertOk()->getContent(),
            'publicDrawer'
        );
    }

    public function test_dropdown_lama_halaman_publik_sudah_dibuang(): void
    {
        $isi = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString(
            'dropdown-content',
            $isi,
            'Dropdown menggantung dari tombolnya dan lebarnya dibatasi, sehingga tujuh menu menumpuk di pojok.'
        );
    }

    public function test_tautan_laci_cukup_besar_untuk_disentuh(): void
    {
        $isi = $this->actingAs($this->penyetor())->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<aside[^>]*peer-checked:translate-x-0.*?<\/aside>/s', $isi, $cocok);
        /**
         * Spasi sesudah "a" wajib ada. Tanpa itu polanya ikut menangkap tag
         * <aside yang membungkusnya, lalu gagal karena aside memang tidak
         * punya min-h-11.
         */
        preg_match_all('/<a\s[^>]*class="([^"]*)"/', $cocok[0], $tautan);

        $this->assertNotEmpty($tautan[1]);

        foreach ($tautan[1] as $kelas) {
            $this->assertMatchesRegularExpression(
                '/min-h-11/',
                $kelas,
                'Sasaran sentuh di header lama hanya 36 sampai 40px, di bawah anjuran 44px.'
            );
        }
    }
}

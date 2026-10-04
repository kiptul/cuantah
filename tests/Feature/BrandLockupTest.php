<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logo pada header tiap peran.
 *
 * Karyawan dan penyetor memakai aplikasi yang sama, sehingga keduanya pantas
 * memakai logo yang sama pula. Header karyawan sebelumnya hanya menampilkan
 * tulisan CUANTAH polos. Panel admin tetap berbeda sebab ia perlu menyebut
 * bahwa yang terbuka adalah sisi pengelola.
 */
class BrandLockupTest extends TestCase
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
     * Mengambil blok header saja, supaya logo yang muncul di badan halaman
     * tidak ikut terbaca sebagai bukti.
     */
    private function blokHeader(User $pengguna, string $rute): string
    {
        $isi = $this->actingAs($pengguna)->get($rute)->assertOk()->getContent();

        preg_match('/<header.*?<\/header>/s', $isi, $cocok);

        return $cocok[0] ?? '';
    }

    private function karyawan(): User
    {
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([Partner::factory()->create()->id]);

        return $karyawan;
    }

    public function test_header_karyawan_memakai_logo_yang_sama_dengan_penyetor(): void
    {
        $header = $this->blokHeader($this->karyawan(), route('employee.dashboard'));

        $this->assertStringContainsString('viewBox="0 0 40 40"', $header, 'Logo digambar sebagai SVG, bukan berkas gambar.');
        $this->assertStringContainsString('CUANTAH', $header);
    }

    public function test_logo_karyawan_dan_penyetor_memang_markup_yang_sama(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $ambilLogo = function (string $header): string {
            preg_match('/<svg viewBox="0 0 40 40".*?<\/svg>/s', $header, $cocok);

            return $cocok[0] ?? '';
        };

        $logoKaryawan = $ambilLogo($this->blokHeader($this->karyawan(), route('employee.dashboard')));
        $logoPenyetor = $ambilLogo($this->blokHeader($penyetor, route('dashboard')));

        $this->assertNotSame('', $logoKaryawan);
        $this->assertSame(
            $logoPenyetor,
            $logoKaryawan,
            'Keduanya harus berasal dari komponen yang sama, bukan dua salinan yang bisa menyimpang.'
        );
    }

    public function test_panel_admin_tetap_memakai_penandanya_sendiri(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([Partner::factory()->create()->id]);

        $header = $this->blokHeader($admin, route('admin.dashboard'));

        $this->assertStringContainsString('Admin panel', $header);
        $this->assertStringNotContainsString(
            'viewBox="0 0 40 40"',
            $header,
            'Panel admin perlu menyebut bahwa yang terbuka adalah sisi pengelola.'
        );
    }

    public function test_logo_tidak_lagi_berupa_berkas_gambar(): void
    {
        $header = $this->blokHeader($this->karyawan(), route('employee.dashboard'));

        $this->assertStringNotContainsString(
            'logo-cuantah.png',
            $header,
            'Logo digambar lewat markup agar tetap tajam dan ikut warna tema.'
        );
    }
}

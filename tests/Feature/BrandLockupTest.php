<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Logo pada header tiap peran.
 *
 * Ketiga peran memakai lambang yang sama, sebab lambangnya memang satu.
 * Header karyawan dulu hanya menampilkan tulisan CUANTAH polos, dan header
 * admin menggambar sekeping huruf "C" yang bukan lambang CUANTAH sama sekali.
 * Yang boleh berbeda di panel admin hanyalah kalimat di bawah namanya.
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

    private function ambilLogo(string $header): string
    {
        preg_match('/<svg viewBox="0 0 40 40".*?<\/svg>/s', $header, $cocok);

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

        $logoKaryawan = $this->ambilLogo($this->blokHeader($this->karyawan(), route('employee.dashboard')));
        $logoPenyetor = $this->ambilLogo($this->blokHeader($penyetor, route('dashboard')));

        $this->assertNotSame('', $logoKaryawan);
        $this->assertSame(
            $logoPenyetor,
            $logoKaryawan,
            'Keduanya harus berasal dari komponen yang sama, bukan dua salinan yang bisa menyimpang.'
        );
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([Partner::factory()->create()->id]);

        return $admin;
    }

    /**
     * Panel admin dulu menggambar penandanya sendiri: sekeping kotak berisi
     * huruf "C". Yang perlu berbeda di sana hanyalah kalimatnya, bukan
     * lambangnya, dan huruf C bukan lambang CUANTAH sama sekali.
     */
    public function test_header_admin_memakai_logo_yang_sama_pula(): void
    {
        $logoAdmin = $this->ambilLogo($this->blokHeader($this->admin(), route('admin.dashboard')));
        $logoPenyetor = $this->ambilLogo($this->blokHeader(User::factory()->create(['role' => 'user']), route('dashboard')));

        $this->assertNotSame('', $logoAdmin, 'Header admin harus menggambar lambang yang sama, bukan huruf.');
        $this->assertSame($logoPenyetor, $logoAdmin, 'Lambangnya harus berasal dari komponen yang sama.');
    }

    /**
     * Yang membedakan panel admin tinggal kalimatnya.
     *
     * Kalimat itu tidak disembunyikan di layar sempit, sebab pil "Admin
     * CUANTAH" di sebelahnya justru hilang di sana, dan tanpa keduanya header
     * admin tidak lagi menyebut sisi mana yang sedang terbuka.
     */
    public function test_panel_admin_tetap_menyebut_dirinya(): void
    {
        $header = $this->blokHeader($this->admin(), route('admin.dashboard'));

        $this->assertSame(1, preg_match('/<span class="([^"]*)">Admin panel<\/span>/', $header, $cocok), 'Keterangan "Admin panel" tidak ditemukan di header.');

        $kelas = preg_split('/\s+/', trim($cocok[1]));

        $this->assertNotContains('hidden', $kelas, 'Keterangan "Admin panel" harus terbaca di lebar mana pun.');
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

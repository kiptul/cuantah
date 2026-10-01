<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keterjangkauan menu karyawan di header.
 *
 * Tautan Pickup dan Transaksi dulu memakai kelas "hidden sm:inline-flex",
 * sehingga keduanya lenyap sama sekali di bawah 640px. Karyawanlah yang
 * paling mungkin memakai ponsel karena bekerja di lapangan, dan begitu ia
 * berpindah dari dasbor, kedua halaman itu tidak punya jalan masuk lagi.
 */
class EmployeeNavReachabilityTest extends TestCase
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

    private function karyawan(): User
    {
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([Partner::factory()->create()->id]);

        return $karyawan;
    }

    /**
     * Mengambil blok menu di header saja, supaya tombol di badan halaman tidak
     * ikut terbaca sebagai bukti bahwa menunya terjangkau.
     */
    private function blokHeader(User $karyawan, string $rute): string
    {
        $isi = $this->actingAs($karyawan)->get($rute)->assertOk()->getContent();

        preg_match('/<header.*?<\/header>/s', $isi, $cocok);

        return $cocok[0] ?? '';
    }

    public function test_keempat_menu_ada_di_header_tanpa_disembunyikan(): void
    {
        $karyawan = $this->karyawan();
        $header = $this->blokHeader($karyawan, route('employee.dashboard'));

        foreach ([
            route('employee.dashboard'),
            route('employee.scan'),
            route('employee.pickups.available'),
            route('employee.transactions.index'),
        ] as $tujuan) {
            $this->assertStringContainsString($tujuan, $header);
        }
    }

    public function test_tautan_tidak_lagi_memakai_kelas_hidden(): void
    {
        $karyawan = $this->karyawan();
        $header = $this->blokHeader($karyawan, route('employee.dashboard'));

        preg_match_all('/<a[^>]*employee\/(pickups|transactions)[^>]*>/', $header, $cocok);

        $this->assertNotEmpty($cocok[0], 'Tautan Pickup dan Transaksi harus ada untuk diperiksa.');

        foreach ($cocok[0] as $tautan) {
            $this->assertStringNotContainsString(
                'hidden',
                $tautan,
                'Yang boleh disembunyikan di layar sempit adalah labelnya, bukan tautannya.'
            );
        }
    }

    public function test_menu_tetap_terjangkau_dari_halaman_selain_dasbor(): void
    {
        $karyawan = $this->karyawan();
        $header = $this->blokHeader($karyawan, route('employee.transactions.index'));

        $this->assertStringContainsString(
            route('employee.pickups.available'),
            $header,
            'Begitu karyawan berpindah halaman, header adalah satu-satunya jalan yang tersisa.'
        );
    }

    public function test_halaman_yang_sedang_dibuka_ditandai(): void
    {
        $karyawan = $this->karyawan();

        $this->assertStringContainsString(
            'aria-current="page"',
            $this->blokHeader($karyawan, route('employee.pickups.available')),
            'Dengan empat menu, penanda posisi berhenti menjadi hiasan.'
        );
    }

    public function test_penanda_posisi_mengikuti_halaman_yang_dibuka(): void
    {
        $karyawan = $this->karyawan();
        $header = $this->blokHeader($karyawan, route('employee.scan'));

        preg_match('/<a[^>]*aria-current="page"[^>]*>/', $header, $cocok);

        $this->assertNotEmpty($cocok);
        $this->assertStringContainsString(
            route('employee.scan'),
            $cocok[0],
            'Penanda harus menunjuk halaman yang sedang dibuka, bukan selalu dasbor.'
        );
    }

    public function test_menu_karyawan_tidak_muncul_untuk_penyetor(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $isi = $this->actingAs($penyetor)->get(route('dashboard'))->assertOk()->getContent();

        preg_match('/<header.*?<\/header>/s', $isi, $cocok);

        $this->assertStringNotContainsString(route('employee.scan'), $cocok[0] ?? '');
    }
}

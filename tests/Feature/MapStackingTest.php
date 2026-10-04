<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Peta tidak boleh menimpa elemen melayang.
 *
 * Leaflet menaruh pane petanya di z-index 400 dan kotak kontrolnya di 1000.
 * Wadah petanya sendiri hanya position:relative tanpa z-index, sehingga ia
 * tidak membentuk konteks penumpukan dan angka-angka itu bocor ke konteks
 * akar. Di sana ia mengalahkan setiap elemen melayang di aplikasi ini:
 * panel notifikasi, menu akun, dan menu navigasi, yang semuanya berada di
 * z-40 dan z-50.
 *
 * Dikurung di sumbernya dengan isolation:isolate, bukan dengan menaikkan
 * z-index tiap elemen melayang satu per satu. Cara kedua hanya menang untuk
 * elemen yang kebetulan sudah dibuat, lalu kalah lagi pada elemen
 * berikutnya: laci admin sempat dinaikkan ke 1100 dan panel notifikasi tetap
 * tertimpa karena tidak ikut dinaikkan.
 */
class MapStackingTest extends TestCase
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
     * Setiap wadah peta Leaflet di seluruh tampilan, beserta kelasnya.
     *
     * Diperiksa dari berkas sumber, bukan dari satu dua halaman yang
     * kebetulan dirender, supaya wadah peta baru yang lupa dikurung ikut
     * tertangkap tanpa perlu menambah test.
     *
     * @return array<int, array{berkas: string, baris: int, kelas: string}>
     */
    private function wadahPeta(): array
    {
        $ditemukan = [];

        $berkas = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($berkas as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (file($file->getPathname()) as $nomor => $baris) {
                /**
                 * Wadah peta dikenali dari id yang memuat kata "map", bukan
                 * dari daftar nama yang ditulis tangan.
                 *
                 * Daftar nama pernah dipakai dan langsung meleset: id
                 * "employee-map" di halaman proses transaksi karyawan tidak
                 * masuk pola "map|pickupMap|partner-map", sehingga petanya
                 * tidak pernah diperiksa sekaligus tidak pernah dikurung.
                 * Pencarian yang ikut buta pada titik yang sama dengan
                 * perbaikannya tidak menjaga apa pun.
                 */
                if (preg_match('/<div id="([^"]*map[^"]*)"[^>]*class="([^"]*)"/i', $baris, $cocok)) {
                    $ditemukan[] = [
                        'berkas' => str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname()),
                        'baris' => $nomor + 1,
                        'kelas' => $cocok[2],
                    ];
                }
            }
        }

        return $ditemukan;
    }

    public function test_setiap_wadah_peta_mengurung_z_index_leaflet(): void
    {
        $wadah = $this->wadahPeta();

        $this->assertGreaterThanOrEqual(
            6,
            count($wadah),
            'Enam wadah peta diketahui ada; bila jumlahnya menyusut, pola pencariannya yang perlu diperiksa.'
        );

        foreach ($wadah as $satu) {
            $this->assertStringContainsString(
                'isolate',
                $satu['kelas'],
                'Peta di '.$satu['berkas'].' baris '.$satu['baris'].' belum dikurung, sehingga akan menimpa panel notifikasi dan menu akun.'
            );
        }
    }

    public function test_elemen_melayang_tetap_punya_z_index(): void
    {
        /**
         * Pengurungan hanya menolong bila lawannya memang berada di atas
         * aliran normal. Elemen yang kehilangan z-index-nya akan tertimpa
         * lagi, kali ini oleh apa pun yang kebetulan digambar sesudahnya.
         *
         * Diperiksa pada tag panelnya, bukan pada seluruh berkas. Berkas
         * lonceng memuat dua elemen berlapis, panel dan lapisan gelapnya,
         * sehingga pemeriksaan tingkat berkas tetap hijau ketika z-index
         * panelnya dicabut dan hanya lapisan gelapnya yang tersisa.
         */
        foreach ([
            'components/notification-bell.blade.php' => 'data-bell-panel',
            'components/account-menu.blade.php' => 'data-account-panel',
        ] as $komponen => $penanda) {
            $isi = file_get_contents(resource_path('views/'.$komponen));

            preg_match('/<div[^>]*'.preg_quote($penanda, '/').'[^>]*>/s', $isi, $cocok);

            $this->assertNotEmpty($cocok, $komponen.': panel bertanda '.$penanda.' harus ditemukan.');
            $this->assertMatchesRegularExpression(
                '/\bz-(\[\d+\]|\d+)/',
                $cocok[0],
                $komponen.': panelnya kehilangan z-index.'
            );
        }
    }

    public function test_halaman_pickup_benar_benar_merender_wadah_yang_terkurung(): void
    {
        $mitra = Partner::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([$mitra->id]);

        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory(),
        ]);
        $transaksi->pickup()->create([
            'partner_id' => $mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'pending',
        ]);

        /**
         * Pemeriksaan berkas sumber tidak membuktikan kelasnya sampai ke
         * halaman: peta hanya dirender bila ada titik yang bisa dipetakan.
         */
        $this->actingAs($admin)
            ->get(route('admin.pickups.index'))
            ->assertOk()
            ->assertSee('id="pickupMap" class="isolate', false);
    }
}

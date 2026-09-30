<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penyuntingan user oleh admin yang hanya memegang sebagian mitra.
 *
 * Daftar centang mitra pada form hanya memuat mitra yang dapat diakses admin
 * yang sedang membukanya, sedangkan sync mencabut semua yang tidak terkirim.
 * Keduanya digabung membuat penyuntingan biasa diam-diam memutus akses yang
 * bahkan tidak terlihat oleh admin tersebut.
 */
class AdminUserFormTest extends TestCase
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

    private function adminMitra(Partner ...$partners): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync(collect($partners)->pluck('id')->all());

        return $admin;
    }

    /**
     * @return array<int, int>
     */
    private function mitraDari(User $user): array
    {
        return $user->partners()->pluck('partners.id')->sort()->values()->all();
    }

    public function test_admin_tidak_mencabut_mitra_yang_tidak_terlihat_olehnya(): void
    {
        $terlihat = Partner::factory()->create(['name' => 'Mitra Terlihat']);
        $tersembunyi = Partner::factory()->create(['name' => 'Mitra Tersembunyi']);

        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([$terlihat->id, $tersembunyi->id]);

        $admin = $this->adminMitra($terlihat);

        $this->actingAs($admin)->put(route('admin.users.update', $karyawan), [
            'name' => $karyawan->name,
            'email' => $karyawan->email,
            'phone' => '081200000009',
            'role' => 'employee',
            'partner_ids' => [$terlihat->id],
        ])->assertSessionHas('success');

        $this->assertSame(
            [$terlihat->id, $tersembunyi->id],
            $this->mitraDari($karyawan),
            'Mitra yang tidak muncul di daftar centang admin seharusnya tidak ikut tercabut.'
        );
    }

    public function test_mitra_yang_terlihat_tetap_bisa_dicabut(): void
    {
        $terlihatA = Partner::factory()->create();
        $terlihatB = Partner::factory()->create();
        $tersembunyi = Partner::factory()->create();

        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([$terlihatA->id, $terlihatB->id, $tersembunyi->id]);

        $admin = $this->adminMitra($terlihatA, $terlihatB);

        $this->actingAs($admin)->put(route('admin.users.update', $karyawan), [
            'name' => $karyawan->name,
            'email' => $karyawan->email,
            'role' => 'employee',
            'partner_ids' => [$terlihatA->id],
        ])->assertSessionHas('success');

        $this->assertSame(
            [$terlihatA->id, $tersembunyi->id],
            $this->mitraDari($karyawan),
            'Mitra terlihat yang tidak dicentang seharusnya tercabut, yang tersembunyi tetap bertahan.'
        );
    }

    public function test_menurunkan_peran_ke_penyetor_mengosongkan_seluruh_mitra(): void
    {
        $terlihat = Partner::factory()->create();
        $tersembunyi = Partner::factory()->create();

        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([$terlihat->id, $tersembunyi->id]);

        $admin = $this->adminMitra($terlihat);

        $this->actingAs($admin)->put(route('admin.users.update', $karyawan), [
            'name' => $karyawan->name,
            'email' => $karyawan->email,
            'role' => 'user',
        ])->assertSessionHas('success');

        $this->assertSame(
            [],
            $this->mitraDari($karyawan),
            'Penyetor tidak boleh menyisakan tautan mitra, termasuk yang tersembunyi.'
        );
    }

    public function test_mitra_tersembunyi_bertahan_saat_admin_mengubah_data_lain(): void
    {
        $terlihat = Partner::factory()->create();
        $tersembunyi = Partner::factory()->create();

        $karyawan = User::factory()->create(['role' => 'employee', 'phone' => '081200000001']);
        $karyawan->partners()->sync([$terlihat->id, $tersembunyi->id]);

        $admin = $this->adminMitra($terlihat);

        $this->actingAs($admin)->put(route('admin.users.update', $karyawan), [
            'name' => $karyawan->name,
            'email' => $karyawan->email,
            'phone' => '081299999999',
            'role' => 'employee',
            'partner_ids' => [$terlihat->id],
        ])->assertSessionHas('success');

        $this->assertSame('081299999999', $karyawan->fresh()->phone);
        $this->assertContains(
            $tersembunyi->id,
            $this->mitraDari($karyawan),
            'Mengubah nomor telepon tidak boleh berakibat pencabutan akses mitra.'
        );
    }
}

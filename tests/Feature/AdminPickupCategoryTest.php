<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kategori pada halaman Pickup admin.
 *
 * Seluruh pickup sebelumnya tertumpuk dalam satu daftar yang diurutkan
 * waktu. Pertanyaan yang paling sering diajukan admin, yaitu mana yang
 * belum dipegang siapa pun, hanya bisa dijawab dengan membaca satu per satu
 * sampai halaman terakhir.
 *
 * Ditolak dan dibatalkan digabung dalam kategori Dibatalkan, sehingga
 * jumlah seluruh kategori sama dengan jumlah "Semua".
 */
class AdminPickupCategoryTest extends TestCase
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

    private function siapkan(): void
    {
        $this->mitra = Partner::factory()->create();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->partners()->sync([$this->mitra->id]);
    }

    private function pickupBerstatus(string $status): Pickup
    {
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
        ]);

        return $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => $status,
        ]);
    }

    public function test_tanpa_kategori_seluruh_pickup_tampil(): void
    {
        $this->siapkan();
        $this->pickupBerstatus('pending');
        $this->pickupBerstatus('assigned');
        $this->pickupBerstatus('completed');

        $halaman = $this->actingAs($this->admin)->get(route('admin.pickups.index'))->assertOk();

        $this->assertSame(3, $halaman->viewData('pickups')->total());
        $this->assertNull($halaman->viewData('category'));
    }

    public function test_kategori_menyaring_daftarnya(): void
    {
        $this->siapkan();
        $this->pickupBerstatus('pending');
        $this->pickupBerstatus('assigned');
        $this->pickupBerstatus('completed');

        $halaman = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index', ['kategori' => 'ditugaskan']))
            ->assertOk();

        $this->assertSame(1, $halaman->viewData('pickups')->total());
        $this->assertSame('assigned', $halaman->viewData('pickups')->first()->status);
    }

    public function test_menunggu_mencakup_jemput_dan_drop_off_yang_belum_dipegang(): void
    {
        $this->siapkan();
        $this->pickupBerstatus('pending');
        $this->pickupBerstatus('awaiting_dropoff')->update(['scanned_at' => now()]);

        $halaman = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index', ['kategori' => 'menunggu']))
            ->assertOk();

        $this->assertSame(
            2,
            $halaman->viewData('pickups')->total(),
            'Keduanya sama-sama menunggu diambil alih, jadi admin tidak perlu memeriksa dua tempat.'
        );
    }

    public function test_hitungan_tiap_kategori_ditampilkan(): void
    {
        $this->siapkan();
        $this->pickupBerstatus('pending');
        $this->pickupBerstatus('assigned');
        $this->pickupBerstatus('assigned');
        $this->pickupBerstatus('completed');

        $hitungan = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index'))->assertOk()
            ->viewData('categoryCounts');

        $this->assertSame(4, $hitungan['semua']);
        $this->assertSame(1, $hitungan['menunggu']);
        $this->assertSame(2, $hitungan['ditugaskan']);
        $this->assertSame(1, $hitungan['selesai']);
    }

    public function test_kategori_yang_tidak_dikenal_jatuh_ke_seluruhnya(): void
    {
        $this->siapkan();
        $this->pickupBerstatus('pending');
        $this->pickupBerstatus('completed');

        /**
         * URL yang diketik tangan tidak boleh menghasilkan daftar kosong yang
         * membingungkan, dan nilainya tidak boleh diteruskan ke query.
         */
        $halaman = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index', ['kategori' => 'ngawur']))
            ->assertOk();

        $this->assertSame(2, $halaman->viewData('pickups')->total());
        $this->assertNull($halaman->viewData('category'));
    }

    public function test_kategori_ikut_terbawa_ke_halaman_berikutnya(): void
    {
        $this->siapkan();

        foreach (range(1, 14) as $ignored) {
            $this->pickupBerstatus('assigned');
        }

        $pickups = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index', ['kategori' => 'ditugaskan']))
            ->assertOk()
            ->viewData('pickups');

        /**
         * Diperiksa pada URL paginator, bukan pada isi halaman. Chip kategori
         * sendiri memuat teks "kategori=ditugaskan", sehingga memeriksa isi
         * halaman tetap hijau meski withQueryString dicabut.
         */
        $this->assertNotNull($pickups->nextPageUrl(), 'Perlu lebih dari satu halaman agar pemeriksaan ini berarti.');
        $this->assertStringContainsString('kategori=ditugaskan', $pickups->nextPageUrl());
    }

    public function test_keadaan_kosong_menyebut_kategorinya(): void
    {
        $this->siapkan();
        $this->pickupBerstatus('completed');

        $this->actingAs($this->admin)
            ->get(route('admin.pickups.index', ['kategori' => 'menunggu']))
            ->assertOk()
            ->assertSee('Tidak ada pickup berkategori Menunggu');
    }

    public function test_dibatalkan_mencakup_yang_ditolak_dan_dibatalkan(): void
    {
        $this->siapkan();
        $this->pickupBerstatus('cancelled');
        $this->pickupBerstatus('rejected');
        $this->pickupBerstatus('completed');

        $halaman = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index', ['kategori' => 'dibatalkan']))
            ->assertOk();

        $this->assertSame(2, $halaman->viewData('pickups')->total());
        $this->assertSame(2, $halaman->viewData('categoryCounts')['dibatalkan']);
        $this->assertSame(3, $halaman->viewData('categoryCounts')['semua']);
    }

    public function test_menunggu_diurutkan_menurut_jadwal_terdekat(): void
    {
        $this->siapkan();
        $lusa = $this->pickupBerstatus('pending');
        $lusa->update(['pickup_date' => now()->addDays(2)->toDateString()]);
        $besok = $this->pickupBerstatus('pending');
        $besok->update(['pickup_date' => now()->addDay()->toDateString()]);

        $urutan = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index', ['kategori' => 'menunggu']))
            ->assertOk()
            ->viewData('pickups')
            ->pluck('id')
            ->all();

        $this->assertSame([$besok->id, $lusa->id], $urutan, 'Yang dijadwalkan lebih dulu harus ditugaskan lebih dulu.');
    }

    public function test_drop_off_yang_dilepas_kembali_ke_kategori_menunggu(): void
    {
        $this->siapkan();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([$this->mitra->id]);
        $transaksi = Transaction::factory()->create([
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_SCHEDULED,
        ]);
        $pickup = $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Mitra',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'assigned',
            'assigned_user_id' => $karyawan->id,
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->admin)->post(route('admin.pickups.unassign', $pickup))->assertSessionHasNoErrors();

        $this->assertSame('awaiting_dropoff', $pickup->fresh()->status);

        $halaman = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index', ['kategori' => 'menunggu']))
            ->assertOk();

        $this->assertSame(1, $halaman->viewData('pickups')->total(), 'Drop-off yang dilepas harus menunggu karyawan baru, bukan hilang dari kategori.');
    }

    public function test_hitungan_tidak_menembus_batas_mitra(): void
    {
        $this->siapkan();
        $this->pickupBerstatus('assigned');

        $mitraLain = Partner::factory()->create();
        $transaksiLain = Transaction::factory()->pickup()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $mitraLain->id,
            'oil_price_id' => OilPrice::factory(),
        ]);
        $transaksiLain->pickup()->create([
            'partner_id' => $mitraLain->id,
            'address' => 'Jl. Mitra Lain',
            'latitude' => -6.4,
            'longitude' => 107.4,
            'status' => 'assigned',
        ]);

        $hitungan = $this->actingAs($this->admin)
            ->get(route('admin.pickups.index'))->assertOk()
            ->viewData('categoryCounts');

        $this->assertSame(1, $hitungan['ditugaskan'], 'Hitungan dipakai untuk mengambil keputusan, jadi ia harus tunduk pada batas yang sama dengan daftarnya.');
        $this->assertSame(1, $hitungan['semua']);
    }
}

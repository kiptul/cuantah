<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notifikasi untuk sisi mitra.
 *
 * Sebelas titik notifikasi di aplikasi ini sebelumnya hampir seluruhnya
 * mengarah ke penyetor. Admin mitra hanya dikabari ketika ada keberatan, dan
 * karyawan tidak pernah dikabari sama sekali. Arahnya satu arah: sistem rajin
 * memberi tahu penyetor tentang apa yang dilakukan staf, tetapi tidak pernah
 * memberi tahu staf tentang apa yang dilakukan penyetor.
 */
class StaffNotificationTest extends TestCase
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

    private User $karyawan;

    private User $penyetor;

    protected function siapkan(): void
    {
        $this->mitra = Partner::factory()->create(['name' => 'Mitra Uji']);

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Mitra']);
        $this->admin->partners()->sync([$this->mitra->id]);

        $this->karyawan = User::factory()->create(['role' => 'employee', 'name' => 'Karyawan Uji']);
        $this->karyawan->partners()->sync([$this->mitra->id]);

        $this->penyetor = User::factory()->create(['role' => 'user', 'name' => 'Penyetor Uji']);

        OilPrice::factory()->create();
    }

    /**
     * @return array<int, string>
     */
    private function judulUntuk(User $penerima): array
    {
        return Notification::where('user_id', $penerima->id)->pluck('title')->all();
    }

    private function setoranJemput(): Transaction
    {
        return app(TransactionService::class)->createDeposit($this->penyetor, [
            'partner_id' => $this->mitra->id,
            'estimated_liter' => 5,
            'method' => Transaction::METHOD_PICKUP,
            'address' => 'Jl. Penjemputan 7',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time' => '08:00',
        ]);
    }

    public function test_admin_mitra_dikabari_saat_setoran_jemput_diajukan(): void
    {
        $this->siapkan();
        $this->setoranJemput();

        $this->assertContains('Setoran baru masuk', $this->judulUntuk($this->admin));
    }

    public function test_admin_mitra_dikabari_saat_setoran_antar_sendiri_diajukan(): void
    {
        $this->siapkan();

        app(TransactionService::class)->createDeposit($this->penyetor, [
            'partner_id' => $this->mitra->id,
            'estimated_liter' => 3,
            'method' => Transaction::METHOD_DROP_OFF,
        ]);

        $kabar = Notification::where('user_id', $this->admin->id)->first();

        /**
         * Keberadaannya diperiksa lebih dulu. Langsung memeriksa isi pesan
         * membuat ketiadaan notifikasi muncul sebagai galat tipe, bukan
         * sebagai keterangan bahwa kabarnya memang tidak pernah dikirim.
         */
        $this->assertNotNull(
            $kabar,
            'Drop-off tidak pernah muncul di daftar pickup sampai discan, jadi kabar ini satu-satunya yang diterima mitra.'
        );
        $this->assertStringContainsString('mengantar sendiri', $kabar->message);
    }

    public function test_admin_mitra_lain_tidak_ikut_dikabari(): void
    {
        $this->siapkan();

        $mitraLain = Partner::factory()->create();
        $adminLain = User::factory()->create(['role' => 'admin']);
        $adminLain->partners()->sync([$mitraLain->id]);

        $this->setoranJemput();

        $this->assertSame([], $this->judulUntuk($adminLain), 'Kabar setoran tidak boleh menembus batas mitra.');
    }

    public function test_karyawan_dikabari_saat_pickup_ditugaskan_kepadanya(): void
    {
        $this->siapkan();
        $transaksi = $this->setoranJemput();

        app(TransactionService::class)->assignPickupToEmployee($transaksi->pickup, $this->karyawan->id);

        $this->assertContains('Pickup ditugaskan kepadamu', $this->judulUntuk($this->karyawan));
    }

    public function test_karyawan_tidak_dikabari_saat_mengambil_pickup_sendiri(): void
    {
        $this->siapkan();
        $transaksi = $this->setoranJemput();

        app(TransactionService::class)->claimPickup($transaksi->pickup, $this->karyawan);

        $this->assertNotContains(
            'Pickup ditugaskan kepadamu',
            $this->judulUntuk($this->karyawan),
            'Mengabari seseorang tentang tindakannya sendiri hanya menambah kebisingan.'
        );
    }

    public function test_karyawan_dikabari_saat_setoran_yang_ditugaskan_dibatalkan(): void
    {
        $this->siapkan();
        $transaksi = $this->setoranJemput();
        app(TransactionService::class)->assignPickupToEmployee($transaksi->pickup, $this->karyawan->id);

        app(TransactionService::class)->cancel($transaksi->refresh());

        $this->assertContains(
            'Penjemputan dibatalkan',
            $this->judulUntuk($this->karyawan),
            'Tanpa kabar ini karyawan dapat berkendara ke alamat yang setorannya sudah dibatalkan.'
        );
    }

    public function test_pembatalan_tanpa_karyawan_tidak_membuat_kabar_menggantung(): void
    {
        $this->siapkan();
        $transaksi = $this->setoranJemput();

        app(TransactionService::class)->cancel($transaksi->refresh());

        $this->assertSame(
            [],
            $this->judulUntuk($this->karyawan),
            'Belum ada karyawan yang ditugaskan, jadi tidak ada yang perlu dikabari.'
        );
    }

    public function test_lonceng_karyawan_menampilkan_kabar_tersebut(): void
    {
        $this->siapkan();
        $transaksi = $this->setoranJemput();
        app(TransactionService::class)->assignPickupToEmployee($transaksi->pickup, $this->karyawan->id);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->assertSee('Pickup ditugaskan kepadamu');
    }
}

<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Transaksi jemput ditutup karyawan, bukan admin.
 *
 * Karyawanlah yang berdiri di depan jelantahnya, menimbang, dan menyerahkan
 * uangnya. Panel "Selesaikan transaksi" di halaman admin membuat transaksi
 * jemput bisa ditutup dari kantor, yang berarti mengetik volume yang tidak
 * pernah ditimbang dan menandai lunas uang yang tidak pernah diserahkan.
 *
 * Setoran antar sendiri tetap milik admin: penyetornya datang ke lokasi mitra,
 * jadi admin di sana memang menerimanya langsung.
 */
class AdminPickupCompletionTest extends TestCase
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

    private function transaksi(string $metode): Transaction
    {
        $transaksi = Transaction::factory()->create([
            'user_id' => User::factory()->create(['role' => 'user']),
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'method' => $metode,
            'status' => Transaction::STATUS_SCHEDULED,
            'actual_liter' => null,
            'total_value' => null,
            'payment_status' => null,
            'payment_method' => null,
            'completed_at' => null,
        ]);

        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $this->mitra->id,
        ]);

        return $transaksi;
    }

    private function halaman(Transaction $transaksi): string
    {
        return $this->actingAs($this->admin)
            ->get(route('admin.transactions.show', $transaksi))
            ->assertOk()
            ->getContent();
    }

    public function test_panel_selesaikan_tidak_muncul_pada_transaksi_jemput(): void
    {
        $this->siapkan();

        $this->assertStringNotContainsString(
            'Selesaikan transaksi',
            $this->halaman($this->transaksi(Transaction::METHOD_PICKUP)),
            'Transaksi jemput diselesaikan karyawan di lapangan, bukan admin dari kantor.'
        );
    }

    public function test_panel_selesaikan_tetap_ada_pada_setoran_antar_sendiri(): void
    {
        $this->siapkan();

        $this->assertStringContainsString(
            'Selesaikan transaksi',
            $this->halaman($this->transaksi(Transaction::METHOD_DROP_OFF)),
            'Setoran antar sendiri diterima admin di lokasi mitra, jadi panelnya harus tetap ada.'
        );
    }

    /**
     * Tolak tetap tersedia pada keduanya.
     *
     * Itulah jalan keluar admin untuk transaksi jemput yang tersangkut,
     * selain memindahkannya ke karyawan lain lewat halaman Pickup.
     */
    public function test_tolak_tetap_tersedia_pada_transaksi_jemput(): void
    {
        $this->siapkan();

        $this->assertStringContainsString(
            'Tolak transaksi',
            $this->halaman($this->transaksi(Transaction::METHOD_PICKUP))
        );
    }

    /**
     * Aturannya berlaku di aplikasinya, bukan hanya di layarnya.
     *
     * Menyembunyikan panel saja menutup pintunya tetapi meninggalkan
     * jendelanya: rutenya tetap menerima kiriman dari tab lama yang masih
     * terbuka, atau dari siapa pun yang tahu alamatnya.
     */
    public function test_rute_verifikasi_menolak_transaksi_jemput(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksi(Transaction::METHOD_PICKUP);

        Storage::fake('local');

        $this->actingAs($this->admin)
            ->post(route('admin.transactions.verify', $transaksi), [
                'actual_liter' => 4.8,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'payment_proof' => UploadedFile::fake()->create('serah-terima.jpg', 120, 'image/jpeg'),
            ])
            ->assertForbidden();

        $transaksi->refresh();

        $this->assertSame(Transaction::STATUS_SCHEDULED, $transaksi->status);
        $this->assertNull($transaksi->actual_liter);
    }

    public function test_rute_verifikasi_tetap_menerima_setoran_antar_sendiri(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksi(Transaction::METHOD_DROP_OFF);

        Storage::fake('local');

        $this->actingAs($this->admin)
            ->post(route('admin.transactions.verify', $transaksi), [
                'actual_liter' => 4.8,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'payment_proof' => UploadedFile::fake()->create('serah-terima.jpg', 120, 'image/jpeg'),
            ])
            ->assertRedirect();

        $this->assertSame(Transaction::STATUS_COMPLETED, $transaksi->refresh()->status);
    }
}

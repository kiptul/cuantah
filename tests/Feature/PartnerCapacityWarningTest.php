<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Setoran yang masih berjalan ikut diperhitungkan terhadap kapasitas mitra.
 *
 * Pemeriksaan kapasitas saat setor hanya membandingkan dengan stok yang sudah
 * ditakar, sehingga beberapa setoran yang diajukan bersamaan masing-masing
 * lolos dan bersama-sama melampauinya. Setoran tetap diterima; yang berubah,
 * admin mitra diperingatkan saat ambangnya pertama kali dilewati.
 */
class PartnerCapacityWarningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    private Partner $mitra;

    private User $admin;

    private function siapkan(int $kapasitas = 100): void
    {
        OilPrice::factory()->create();
        $this->mitra = Partner::factory()->atKarawang()->create(['capacity_liter' => $kapasitas]);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->partners()->attach($this->mitra->id);
    }

    private function setorAntar(float $liter): void
    {
        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->post(route('deposits.store'), [
                'partner_id' => $this->mitra->id,
                'method' => Transaction::METHOD_DROP_OFF,
                'estimated_liter' => $liter,
                'address' => $this->mitra->address,
                'latitude' => $this->mitra->latitude,
                'longitude' => $this->mitra->longitude,
            ])
            ->assertSessionHasNoErrors();
    }

    private function jumlahPeringatan(string $judul): int
    {
        return Notification::where('user_id', $this->admin->id)->where('title', $judul)->count();
    }

    public function test_setoran_yang_melewati_kapasitas_tetap_diterima_dan_admin_diperingatkan(): void
    {
        $this->siapkan(100);

        $this->setorAntar(60);
        $this->assertSame(0, $this->jumlahPeringatan('Kapasitas '.$this->mitra->name.' akan terlampaui'));

        // Masing-masing masih muat di sisa 100 L, tetapi bersama-sama 120 L.
        $this->setorAntar(60);

        $this->assertSame(2, Transaction::count(), 'Setoran kedua tetap diterima.');
        $this->assertSame(1, $this->jumlahPeringatan('Kapasitas '.$this->mitra->name.' akan terlampaui'));
        $this->assertTrue($this->mitra->fresh()->isProjectedOverCapacity());
    }

    public function test_peringatan_proyeksi_hanya_dikirim_sekali_saat_ambang_dilewati(): void
    {
        $this->siapkan(100);

        $this->setorAntar(60);
        $this->setorAntar(60);
        $this->setorAntar(10);

        $this->assertSame(1, $this->jumlahPeringatan('Kapasitas '.$this->mitra->name.' akan terlampaui'));
    }

    public function test_menyelesaikan_transaksi_yang_membuat_stok_meluap_memperingatkan_admin(): void
    {
        $this->siapkan(100);
        Transaction::factory()->completed(90)->create(['partner_id' => $this->mitra->id]);
        $transaksi = Transaction::factory()->create(['partner_id' => $this->mitra->id, 'estimated_liter' => 5]);

        // Diestimasi 5 L, ternyata ditakar 20 L: stok menjadi 110/100 L.
        $this->actingAs($this->admin)
            ->post(route('admin.transactions.verify', $transaksi), [
                'actual_liter' => 20,
                'payment_method' => 'cash',
                'payment_status' => 'unpaid',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(Transaction::STATUS_COMPLETED, $transaksi->fresh()->status, 'Penyelesaian tidak ditahan kapasitas.');
        $this->assertSame(1, $this->jumlahPeringatan('Stok '.$this->mitra->name.' melebihi kapasitas'));
    }

    public function test_tanpa_melewati_kapasitas_tidak_ada_peringatan(): void
    {
        $this->siapkan(100);
        $this->setorAntar(30);
        $transaksi = Transaction::firstOrFail();

        $this->actingAs($this->admin)->post(route('admin.transactions.verify', $transaksi), [
            'actual_liter' => 30,
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
        ]);

        $this->assertSame(0, Notification::where('user_id', $this->admin->id)->where('title', 'like', '%kapasitas%')->count());
    }

    public function test_dasbor_menampilkan_setoran_berjalan_dan_peringatannya(): void
    {
        $this->siapkan(100);
        Transaction::factory()->completed(70)->create(['partner_id' => $this->mitra->id]);
        Transaction::factory()->create(['partner_id' => $this->mitra->id, 'estimated_liter' => 50]);

        $this->actingAs($this->admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('+50,0 L setoran berjalan')
            ->assertSee('Akan melebihi kapasitas sekitar 20 L');
    }

    public function test_detail_transaksi_memperingatkan_sebelum_diselesaikan(): void
    {
        $this->siapkan(100);
        Transaction::factory()->completed(95)->create(['partner_id' => $this->mitra->id]);
        $transaksi = Transaction::factory()->create(['partner_id' => $this->mitra->id, 'estimated_liter' => 10]);

        $this->actingAs($this->admin)
            ->get(route('admin.transactions.show', $transaksi))
            ->assertOk()
            ->assertSee('Sisa daya tampung '.$this->mitra->name.' tinggal 5,0 L');
    }

    public function test_notifikasi_penugasan_drop_off_tidak_menyuruh_menjemput(): void
    {
        $this->siapkan();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($this->mitra->id);
        $transaksi = Transaction::factory()->create(['partner_id' => $this->mitra->id]);
        $pickup = Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $this->mitra->id,
            'status' => 'awaiting_dropoff',
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $karyawan->id])
            ->assertSessionHasNoErrors();

        $pesan = Notification::where('user_id', $karyawan->id)->firstOrFail()->message;
        $this->assertStringStartsWith('Terima dan takar drop-off', $pesan);
    }
}

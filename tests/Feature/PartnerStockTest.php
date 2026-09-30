<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Stok mitra: liter yang sudah masuk dikurangi yang sudah disalurkan.
 *
 * Angka ini menahan pencatatan penyaluran yang melebihi jumlah masuk, dan
 * menahan setoran ke mitra yang sudah penuh. Karena dipakai untuk dua
 * penjagaan sekaligus, hasilnya harus sama baik dihitung satu per satu
 * maupun sekaligus lewat withAvailableLiter().
 */
class PartnerStockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    private function mitraBerisi(float $masuk, float $keluar): Partner
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create(['capacity_liter' => 1000]);

        Transaction::factory()->completed($masuk)->create([
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
        ]);

        if ($keluar > 0) {
            Distribution::factory()->create([
                'partner_id' => $mitra->id,
                'volume_liter' => $keluar,
            ]);
        }

        return $mitra;
    }

    public function test_batched_stock_matches_the_per_partner_calculation(): void
    {
        $this->mitraBerisi(20, 5);
        $this->mitraBerisi(8, 0);
        Partner::factory()->create();

        $satuPerSatu = Partner::orderBy('id')->get()
            ->map(fn (Partner $partner) => $partner->availableLiter())
            ->all();

        $sekaligus = Partner::withAvailableLiter()->orderBy('id')->get()
            ->map(fn (Partner $partner) => $partner->availableLiter())
            ->all();

        $this->assertSame([15.0, 8.0, 0.0], $satuPerSatu);
        $this->assertSame($satuPerSatu, $sekaligus);
    }

    /**
     * Menghitung query yang benar-benar dipakai satu permintaan halaman,
     * terpisah dari query penyiapan data.
     */
    private function jumlahQueryHalamanPenyaluran(User $admin): int
    {
        $admin->forgetAccessiblePartnerIds();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('admin.distributions.index'))->assertOk();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }

    /**
     * Kapasitas mitra sudah ditahan di sisi layanan, tetapi penolakannya baru
     * terasa sesudah seluruh formulir setoran diisi.
     */
    public function test_deposit_form_marks_a_full_partner_and_puts_it_last(): void
    {
        $harga = OilPrice::factory()->create();
        $penuh = Partner::factory()->create(['name' => 'Aa Mitra Penuh', 'capacity_liter' => 10]);
        $lapang = Partner::factory()->create(['name' => 'Zz Mitra Lapang', 'capacity_liter' => 500]);

        Transaction::factory()->completed(10)->create([
            'partner_id' => $penuh->id,
            'oil_price_id' => $harga->id,
        ]);

        $penyetor = User::factory()->create(['role' => 'user']);

        $halaman = $this->actingAs($penyetor)->get(route('deposits.create'))->assertOk();

        $daftar = $halaman->viewData('partners');

        $this->assertSame($lapang->id, $daftar->first()['id'], 'Mitra yang masih lapang harus jadi pilihan bawaan.');
        $this->assertTrue($daftar->last()['penuh']);
        $halaman->assertSee('penuh, belum bisa menerima');
    }

    /**
     * Alamat rumah hampir selalu sama antar setoran, dan mengetiknya ulang
     * mengundang salah ketik pada satu-satunya keterangan yang dipakai
     * karyawan untuk menemukannya.
     */
    public function test_deposit_form_remembers_the_last_pickup_address(): void
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create();
        $penyetor = User::factory()->create(['role' => 'user']);

        $sebelumnya = Transaction::factory()->pickup()->create([
            'user_id' => $penyetor->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
        ]);
        Pickup::factory()->create([
            'transaction_id' => $sebelumnya->id,
            'partner_id' => $mitra->id,
            'address' => 'Jl. Melati No. 7, Karawang',
            'latitude' => -6.31,
            'longitude' => 107.31,
        ]);

        $halaman = $this->actingAs($penyetor)->get(route('deposits.create'))->assertOk();

        $this->assertSame('Jl. Melati No. 7, Karawang', $halaman->viewData('alamatTerakhir')['address']);
        $halaman->assertSee('Memakai alamat penjemputan terakhirmu');
    }

    public function test_deposit_form_has_no_remembered_address_for_a_first_time_depositor(): void
    {
        OilPrice::factory()->create();
        Partner::factory()->create();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('deposits.create'))
            ->assertOk()
            ->assertSee('Tentukan lokasi rumah/UMKM');
    }

    public function test_distribution_page_does_not_grow_its_query_count_with_more_partners(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        for ($i = 0; $i < 3; $i++) {
            $admin->partners()->attach($this->mitraBerisi(10, 2)->id);
        }

        $tigaMitra = $this->jumlahQueryHalamanPenyaluran($admin);

        for ($i = 0; $i < 5; $i++) {
            $admin->partners()->attach($this->mitraBerisi(10, 2)->id);
        }

        $delapanMitra = $this->jumlahQueryHalamanPenyaluran($admin);

        $this->assertSame($tigaMitra, $delapanMitra, 'Jumlah query harus tetap meski jumlah mitra bertambah.');
    }
}

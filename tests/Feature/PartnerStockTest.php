<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\OilPrice;
use App\Models\Partner;
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

<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OilPriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    public function test_koreksi_harga_pada_tanggal_yang_sama_menjadi_harga_berlaku(): void
    {
        OilPrice::create([
            'price_per_liter' => 4500,
            'effective_date' => '2026-09-27',
            'is_active' => true,
        ]);

        $koreksi = OilPrice::create([
            'price_per_liter' => 5000,
            'effective_date' => '2026-09-27',
            'is_active' => true,
        ]);

        $this->travelTo('2026-09-27 10:00:00');

        $this->assertSame($koreksi->id, OilPrice::current()?->id);
        $this->assertSame(5000, OilPrice::current()?->price_per_liter);
    }

    public function test_menyimpan_harga_aktif_menonaktifkan_harga_aktif_sebelumnya(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $lama = OilPrice::create([
            'price_per_liter' => 4000,
            'effective_date' => '2026-09-26',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.prices.store'), [
                'price_per_liter' => 4500,
                'effective_date' => '2026-09-27',
                'is_active' => '1',
            ])
            ->assertSessionHas('success');

        $this->assertFalse($lama->fresh()->is_active, 'Harga lama seharusnya dinonaktifkan.');
        $this->assertSame(1, OilPrice::where('is_active', true)->count(), 'Hanya boleh ada satu harga aktif.');
    }

    public function test_harga_dengan_tanggal_berlaku_di_masa_depan_belum_dipakai(): void
    {
        $sekarang = OilPrice::create([
            'price_per_liter' => 4000,
            'effective_date' => '2026-09-20',
            'is_active' => true,
        ]);

        OilPrice::create([
            'price_per_liter' => 7000,
            'effective_date' => '2026-12-01',
            'is_active' => true,
        ]);

        $this->travelTo('2026-09-27 10:00:00');

        $this->assertSame($sekarang->id, OilPrice::current()?->id);
    }
}

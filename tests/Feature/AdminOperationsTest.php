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
 * Tindakan harian admin: menugaskan pickup ke karyawan, dan mengelola
 * mitra beserta tarif ongkirnya. Keduanya menyentuh data yang dipakai
 * seluruh alur setoran, tetapi sebelumnya tidak punya satu pun test.
 */
class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    /**
     * @return array{admin: User, employee: User, partner: Partner, pickup: Pickup, transaction: Transaction}
     */
    private function mitraDenganPickupMenunggu(): array
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create();

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = User::factory()->create(['role' => 'employee']);
        $admin->partners()->attach($mitra->id);
        $karyawan->partners()->attach($mitra->id);

        $transaksi = Transaction::factory()->pickup()->create([
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
            'status' => Transaction::STATUS_PENDING,
        ]);
        $pickup = Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $mitra->id,
            'status' => 'pending',
        ]);

        return compact('admin', 'karyawan', 'mitra', 'pickup', 'transaksi') + ['employee' => $karyawan, 'partner' => $mitra];
    }

    public function test_admin_assigns_a_pickup_and_the_depositor_is_told(): void
    {
        ['admin' => $admin, 'karyawan' => $karyawan, 'pickup' => $pickup, 'transaksi' => $transaksi] = $this->mitraDenganPickupMenunggu();

        $this->actingAs($admin)
            ->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $karyawan->id])
            ->assertSessionHasNoErrors();

        $pickup->refresh();
        $this->assertSame($karyawan->id, $pickup->assigned_user_id);
        $this->assertSame('assigned', $pickup->status);
        $this->assertSame(Transaction::STATUS_SCHEDULED, $transaksi->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $transaksi->user_id,
            'title' => 'Pickup dijadwalkan',
        ]);
    }

    public function test_unassigning_puts_the_pickup_back_in_the_queue(): void
    {
        ['admin' => $admin, 'karyawan' => $karyawan, 'pickup' => $pickup, 'transaksi' => $transaksi] = $this->mitraDenganPickupMenunggu();

        $this->actingAs($admin)->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $karyawan->id]);
        $this->actingAs($admin)->post(route('admin.pickups.unassign', $pickup))->assertSessionHasNoErrors();

        $pickup->refresh();
        $this->assertNull($pickup->assigned_user_id);
        $this->assertSame('pending', $pickup->status);
        $this->assertSame(Transaction::STATUS_PENDING, $transaksi->fresh()->status);
    }

    /**
     * Karyawan mitra lain tidak boleh ditugaskan, sebab ia pun tidak akan
     * bisa membuka transaksinya.
     */
    public function test_an_employee_from_another_partner_cannot_be_assigned(): void
    {
        ['admin' => $admin, 'pickup' => $pickup] = $this->mitraDenganPickupMenunggu();

        $orangLain = User::factory()->create(['role' => 'employee']);
        $orangLain->partners()->attach(Partner::factory()->create()->id);

        $this->actingAs($admin)
            ->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $orangLain->id])
            ->assertForbidden();

        $this->assertNull($pickup->fresh()->assigned_user_id);
    }

    public function test_a_finished_pickup_can_no_longer_be_assigned(): void
    {
        ['admin' => $admin, 'karyawan' => $karyawan, 'pickup' => $pickup, 'transaksi' => $transaksi] = $this->mitraDenganPickupMenunggu();

        $transaksi->update(['status' => Transaction::STATUS_COMPLETED]);

        $this->actingAs($admin)
            ->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $karyawan->id])
            ->assertSessionHasErrors();

        $this->assertNull($pickup->fresh()->assigned_user_id);
    }

    public function test_admin_creates_a_partner_with_its_delivery_fee_bands(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach(Partner::factory()->create()->id);

        $this->actingAs($admin)
            ->post(route('admin.partners.store'), [
                'name' => 'Mitra Baru Cikampek',
                'type' => 'Collector',
                'phone' => '081200000010',
                'address' => 'Jl. Ahmad Yani, Cikampek',
                'latitude' => -6.41,
                'longitude' => 107.45,
                'capacity_liter' => 800,
                'status' => 'active',
                'delivery_fees' => [
                    ['min_distance_km' => 0, 'max_distance_km' => 5, 'fee' => 10000],
                    ['min_distance_km' => 5, 'max_distance_km' => null, 'fee' => 25000],
                ],
            ])
            ->assertSessionHasNoErrors();

        $mitra = Partner::where('name', 'Mitra Baru Cikampek')->firstOrFail();

        $this->assertCount(2, $mitra->deliveryFees);
        $this->assertSame(10000, $mitra->deliveryFeeForDistance(3.0));
        $this->assertSame(25000, $mitra->deliveryFeeForDistance(40.0));
        $this->assertTrue($mitra->users->contains($admin), 'Pembuatnya harus langsung terhubung ke mitra baru.');
    }

    public function test_a_fee_band_ending_before_it_starts_is_refused(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach(Partner::factory()->create()->id);

        $this->actingAs($admin)
            ->post(route('admin.partners.store'), [
                'name' => 'Mitra Tarif Terbalik',
                'type' => 'Collector',
                'phone' => '081200000011',
                'address' => 'Jl. Contoh',
                'capacity_liter' => 100,
                'status' => 'active',
                'delivery_fees' => [
                    ['min_distance_km' => 10, 'max_distance_km' => 5, 'fee' => 10000],
                ],
            ])
            ->assertSessionHasErrors('delivery_fees.0.max_distance_km');

        $this->assertDatabaseMissing('partners', ['name' => 'Mitra Tarif Terbalik']);
    }

    public function test_admin_cannot_edit_a_partner_they_are_not_linked_to(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach(Partner::factory()->create()->id);

        $milikOrangLain = Partner::factory()->create(['name' => 'Mitra Orang Lain']);

        $this->actingAs($admin)
            ->put(route('admin.partners.update', $milikOrangLain), [
                'name' => 'Diambil Alih',
                'type' => 'Collector',
                'phone' => '081200000012',
                'address' => 'Jl. Contoh',
                'capacity_liter' => 100,
                'status' => 'active',
            ])
            ->assertForbidden();

        $this->assertSame('Mitra Orang Lain', $milikOrangLain->fresh()->name);
    }
}

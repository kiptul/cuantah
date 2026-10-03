<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PickupAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    private function pickupWithStatus(string $status): Pickup
    {
        $partner = Partner::create([
            'name' => 'Mitra Test',
            'type' => 'Collector',
            'phone' => '0812345678',
            'address' => 'Jl. Mitra',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
            'capacity_liter' => 200,
            'status' => 'active',
        ]);

        $transaction = Transaction::create([
            'code' => 'CNT-TEST-'.$status,
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $partner->id,
            'estimated_liter' => 5,
            'price_per_liter' => 4000,
            'estimated_total' => 20000,
            'method' => Transaction::METHOD_PICKUP,
            'status' => Transaction::STATUS_PENDING,
        ]);

        return $transaction->pickup()->create([
            'partner_id' => $partner->id,
            'address' => 'Jl. Pickup',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
            'status' => $status,
        ]);
    }

    private function staffFor(Pickup $pickup, string $role): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->partners()->sync([$pickup->partner_id]);

        return $user;
    }

    public function test_pickup_yang_belum_selesai_bisa_di_assign(): void
    {
        $pickup = $this->pickupWithStatus('pending');
        $admin = $this->staffFor($pickup, 'admin');
        $employee = $this->staffFor($pickup, 'employee');

        $this->actingAs($admin)
            ->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $employee->id])
            ->assertSessionHas('success');

        $this->assertSame($employee->id, $pickup->fresh()->assigned_user_id);
    }

    public function test_pickup_yang_sudah_selesai_tidak_bisa_di_assign(): void
    {
        $pickup = $this->pickupWithStatus('completed');
        $admin = $this->staffFor($pickup, 'admin');
        $employee = $this->staffFor($pickup, 'employee');

        $this->actingAs($admin)
            ->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $employee->id])
            ->assertStatus(422);

        $this->assertNull($pickup->fresh()->assigned_user_id, 'Pickup selesai seharusnya tidak berubah assignment-nya.');
    }

    public function test_pickup_yang_ditolak_tidak_bisa_dilepas(): void
    {
        $pickup = $this->pickupWithStatus('rejected');
        $employee = $this->staffFor($pickup, 'employee');
        $pickup->update(['assigned_user_id' => $employee->id]);
        $admin = $this->staffFor($pickup, 'admin');

        $this->actingAs($admin)
            ->post(route('admin.pickups.unassign', $pickup))
            ->assertStatus(422);

        $this->assertSame($employee->id, $pickup->fresh()->assigned_user_id);
    }

    public function test_daftar_pickup_hanya_memuat_satu_peta(): void
    {
        $pickup = $this->pickupWithStatus('pending');
        $admin = $this->staffFor($pickup, 'admin');

        $response = $this->actingAs($admin)->get(route('admin.pickups.index'));

        $response->assertOk();
        $response->assertSee('id="pickupMap"', false);
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'L.map('),
            'Daftar pickup harus memakai satu peta bersama, bukan satu peta per baris.'
        );
    }

    /**
     * Satu pickup jemput per status transaksi, semuanya di mitra yang sama.
     *
     * @return array{admin: User, pickups: array<string, Pickup>}
     */
    private function pickupDiSetiapStatus(): array
    {
        $partner = Partner::factory()->create();
        $pickups = [];

        foreach (Transaction::statuses() as $status) {
            $transaction = Transaction::factory()->pickup()->create([
                'partner_id' => $partner->id,
                'status' => $status,
                'code' => 'CNT-KAT-'.strtoupper($status),
            ]);
            $pickups[$status] = Pickup::factory()->create(['transaction_id' => $transaction->id, 'partner_id' => $partner->id]);
        }

        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($partner->id);

        return ['admin' => $admin, 'pickups' => $pickups];
    }

    public function test_daftar_pickup_bawaannya_hanya_yang_menunggu(): void
    {
        ['admin' => $admin] = $this->pickupDiSetiapStatus();

        $this->actingAs($admin)
            ->get(route('admin.pickups.index'))
            ->assertOk()
            ->assertSee('CNT-KAT-PENDING')
            ->assertDontSee('CNT-KAT-SCHEDULED')
            ->assertDontSee('CNT-KAT-COMPLETED');
    }

    public function test_setiap_kategori_hanya_memuat_statusnya_sendiri(): void
    {
        ['admin' => $admin] = $this->pickupDiSetiapStatus();

        $harapan = [
            'ditugaskan' => ['SCHEDULED', 'PICKED_UP', 'VERIFICATION'],
            'selesai' => ['COMPLETED'],
            'dibatalkan' => ['CANCELLED', 'REJECTED'],
            'semua' => ['PENDING', 'SCHEDULED', 'COMPLETED', 'CANCELLED'],
        ];

        foreach ($harapan as $kategori => $kode) {
            $response = $this->actingAs($admin)->get(route('admin.pickups.index', ['kategori' => $kategori]))->assertOk();

            foreach ($kode as $status) {
                $response->assertSee('CNT-KAT-'.$status);
            }

            if ($kategori !== 'semua') {
                $response->assertDontSee('CNT-KAT-PENDING');
            }
        }
    }

    public function test_jumlah_tiap_kategori_ditampilkan(): void
    {
        ['admin' => $admin] = $this->pickupDiSetiapStatus();

        $this->actingAs($admin)
            ->get(route('admin.pickups.index'))
            ->assertViewHas('categories', fn ($kategori) => $kategori['menunggu']['count'] === 1
                && $kategori['ditugaskan']['count'] === 3
                && $kategori['selesai']['count'] === 1
                && $kategori['dibatalkan']['count'] === 2
                && $kategori['semua']['count'] === 7);
    }

    public function test_paginasi_tetap_membawa_kategori(): void
    {
        $partner = Partner::factory()->create();
        Transaction::factory()->pickup()->completed()->count(13)->create(['partner_id' => $partner->id])
            ->each(fn (Transaction $transaction) => Pickup::factory()->create(['transaction_id' => $transaction->id, 'partner_id' => $partner->id, 'status' => 'completed']));
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($partner->id);

        $this->actingAs($admin)
            ->get(route('admin.pickups.index', ['kategori' => 'selesai']))
            ->assertOk()
            ->assertSee('kategori=selesai&amp;page=2', false);

        $this->actingAs($admin)
            ->get(route('admin.pickups.index', ['kategori' => 'selesai', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('pickups', fn ($pickups) => $pickups->count() === 1);
    }

    public function test_kategori_tak_dikenal_kembali_ke_menunggu(): void
    {
        ['admin' => $admin] = $this->pickupDiSetiapStatus();

        $this->actingAs($admin)
            ->get(route('admin.pickups.index', ['kategori' => 'ngawur']))
            ->assertOk()
            ->assertViewHas('category', 'menunggu');
    }

    public function test_pickup_dibatalkan_tidak_menawarkan_assignment(): void
    {
        $pickup = $this->pickupWithStatus('cancelled');
        $admin = $this->staffFor($pickup, 'admin');
        $employee = $this->staffFor($pickup, 'employee');

        $this->actingAs($admin)
            ->post(route('admin.pickups.assign', $pickup), ['assigned_user_id' => $employee->id])
            ->assertStatus(422);
    }
}

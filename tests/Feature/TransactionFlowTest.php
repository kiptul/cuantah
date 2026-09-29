<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    public function test_user_can_create_pickup_deposit_with_price_snapshot(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $price = OilPrice::create([
            'price_per_liter' => 4000,
            'effective_date' => now()->toDateString(),
            'is_active' => true,
        ]);
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
        $partner->deliveryFees()->create(['min_distance_km' => 3, 'max_distance_km' => 10, 'fee' => 10000]);

        $response = $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_PICKUP,
            'estimated_liter' => 4.8,
            'address' => 'Jl. Demo Pickup',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
            'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time' => '09:30',
        ]);

        $transaction = Transaction::first();

        $response->assertRedirect(route('transactions.show', $transaction));
        $this->assertSame($price->id, $transaction->oil_price_id);
        $this->assertSame($partner->id, $transaction->partner_id);
        $this->assertSame(4000, $transaction->price_per_liter);
        $this->assertSame(19200, $transaction->estimated_total);
        $this->assertSame(0, $transaction->pickup_fee);
        $this->assertDatabaseHas('pickups', [
            'transaction_id' => $transaction->id,
            'partner_id' => $partner->id,
            'address' => 'Jl. Demo Pickup',
        ]);
    }

    public function test_user_cannot_view_another_users_transaction(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $other = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::create([
            'code' => 'CNT-TEST-001',
            'user_id' => $owner->id,
            'estimated_liter' => 2,
            'price_per_liter' => 4000,
            'estimated_total' => 8000,
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_PENDING,
        ]);

        $this->actingAs($other)
            ->get(route('transactions.show', $transaction))
            ->assertForbidden();
    }

    public function test_admin_verifies_actual_volume_and_direct_payment_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $partner = Partner::create([
            'name' => 'Mitra Test',
            'type' => 'Collector',
            'phone' => '0812345678',
            'address' => 'Jl. Mitra',
            'capacity_liter' => 200,
            'status' => 'active',
        ]);
        $admin->partners()->sync([$partner->id]);
        $transaction = Transaction::create([
            'code' => 'CNT-TEST-002',
            'user_id' => $user->id,
            'partner_id' => $partner->id,
            'estimated_liter' => 5,
            'price_per_liter' => 4000,
            'estimated_total' => 20000,
            'pickup_fee' => 10000,
            'method' => Transaction::METHOD_PICKUP,
            'status' => Transaction::STATUS_SCHEDULED,
        ]);
        $transaction->pickup()->create([
            'partner_id' => $partner->id,
            'address' => 'Jl. Pickup',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
            'status' => 'assigned',
        ]);

        $this->actingAs($admin)->post(route('admin.transactions.verify', $transaction), [
            'actual_liter' => 4.8,
            'payment_method' => 'transfer',
            'payment_status' => 'paid',
        ])->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'actual_liter' => 4.8,
            'total_value' => 9200,
            'payment_method' => 'transfer',
            'payment_status' => 'paid',
            'status' => Transaction::STATUS_COMPLETED,
        ]);
        $this->assertFalse(\Schema::hasTable('wallets'));
        $this->assertFalse(\Schema::hasTable('wallet_transactions'));
    }

    /**
     * Membuat mitra beserta satu aturan ongkir untuk jarak 3 sampai 10 km.
     */
    private function partnerWithPickupFee(int $fee = 10000): Partner
    {
        $partner = Partner::create([
            'name' => 'Mitra Ongkir',
            'type' => 'Collector',
            'phone' => '0812345678',
            'address' => 'Jl. Mitra',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
            'capacity_liter' => 200,
            'status' => 'active',
        ]);
        $partner->deliveryFees()->create(['min_distance_km' => 3, 'max_distance_km' => 10, 'fee' => $fee]);

        return $partner;
    }

    public function test_pickup_deposit_worth_less_than_its_fee_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        // Titik jemput sekitar 4,4 km dari mitra sehingga terkena ongkir Rp10.000,
        // sementara 2 liter hanya bernilai Rp8.000.
        $response = $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_PICKUP,
            'estimated_liter' => 2,
            'address' => 'Jl. Jauh',
            'latitude' => -6.2655,
            'longitude' => 107.3053,
            'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time' => '09:30',
        ]);

        $response->assertSessionHasErrors('estimated_liter');
        $this->assertSame(0, Transaction::count(), 'Transaksi tanpa nilai bersih tidak boleh tersimpan.');
        $this->assertSame(0, Pickup::count(), 'Pickup ikut batal karena dibungkus satu transaksi basis data.');
    }

    public function test_pickup_deposit_above_its_fee_still_succeeds(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        // 5 liter bernilai Rp20.000, masih di atas ongkir Rp10.000.
        $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_PICKUP,
            'estimated_liter' => 5,
            'address' => 'Jl. Jauh',
            'latitude' => -6.2655,
            'longitude' => 107.3053,
            'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time' => '09:30',
        ])->assertSessionHasNoErrors();

        $transaction = Transaction::firstOrFail();
        $this->assertSame(10000, $transaction->pickup_fee);
        $this->assertSame(10000, $transaction->estimated_total, 'Bruto Rp20.000 dikurangi ongkir Rp10.000.');
    }

    public function test_drop_off_deposit_never_charges_a_pickup_fee(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        // Volume kecil yang sama tetap diterima bila diantar sendiri.
        $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_DROP_OFF,
            'estimated_liter' => 2,
            'address' => 'Jl. Mitra',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
        ])->assertSessionHasNoErrors();

        $transaction = Transaction::firstOrFail();
        $this->assertSame(0, $transaction->pickup_fee);
        $this->assertSame(8000, $transaction->estimated_total);
    }

    /**
     * Transaksi selesai yang sudah ditandai lunas oleh karyawan.
     */
    private function paidTransactionFor(User $user): Transaction
    {
        $price = OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        return Transaction::create([
            'code' => 'CNT-TEST-1',
            'user_id' => $user->id,
            'oil_price_id' => $price->id,
            'partner_id' => $partner->id,
            'estimated_liter' => 5,
            'actual_liter' => 5,
            'price_per_liter' => 4000,
            'estimated_total' => 20000,
            'total_value' => 20000,
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_COMPLETED,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    public function test_depositor_can_confirm_receiving_the_payment(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->paidTransactionFor($user);

        $this->actingAs($user)
            ->post(route('transactions.confirm-payment', $transaction))
            ->assertSessionHas('success');

        $this->assertNotNull($transaction->fresh()->payment_confirmed_at);
    }

    public function test_someone_else_cannot_confirm_a_payment_that_is_not_theirs(): void
    {
        $owner = User::factory()->create(['role' => 'user']);
        $penyusup = User::factory()->create(['role' => 'user']);
        $transaction = $this->paidTransactionFor($owner);

        $this->actingAs($penyusup)
            ->post(route('transactions.confirm-payment', $transaction))
            ->assertForbidden();

        $this->assertNull($transaction->fresh()->payment_confirmed_at);
    }

    public function test_staff_cannot_confirm_payment_on_behalf_of_the_depositor(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->paidTransactionFor($user);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($transaction->partner_id);

        // Admin boleh melihat transaksi ini, tetapi tidak boleh membenarkan
        // penerimaan uang milik orang lain. Itulah inti konfirmasinya.
        $this->actingAs($admin)
            ->post(route('transactions.confirm-payment', $transaction))
            ->assertForbidden();

        $this->assertNull($transaction->fresh()->payment_confirmed_at);
    }

    public function test_payment_cannot_be_confirmed_twice(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->paidTransactionFor($user);

        $this->actingAs($user)->post(route('transactions.confirm-payment', $transaction));
        $waktuPertama = $transaction->fresh()->payment_confirmed_at;

        $this->actingAs($user)
            ->post(route('transactions.confirm-payment', $transaction))
            ->assertForbidden();

        $this->assertEquals($waktuPertama, $transaction->fresh()->payment_confirmed_at);
    }

    public function test_unpaid_transaction_cannot_be_confirmed(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->paidTransactionFor($user);
        $transaction->update(['payment_status' => 'unpaid']);

        $this->actingAs($user)
            ->post(route('transactions.confirm-payment', $transaction))
            ->assertForbidden();

        $this->assertNull($transaction->fresh()->payment_confirmed_at);
    }
}

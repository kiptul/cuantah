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
            'status' => Pickup::STATUS_ASSIGNED,
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
}

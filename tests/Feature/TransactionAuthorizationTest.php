<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    public function test_admin_of_another_partner_cannot_manage_the_transaction(): void
    {
        $partner = $this->partner('Mitra A');
        $transaction = $this->transaction($partner);
        $outsideAdmin = $this->staff('admin', $this->partner('Mitra B'));

        $this->assertFalse($outsideAdmin->can('manage', $transaction));
        $this->assertFalse($outsideAdmin->can('handle', $transaction));
        $this->assertFalse($outsideAdmin->can('claim', $transaction));
    }

    public function test_admin_of_the_same_partner_can_manage_and_handle_the_transaction(): void
    {
        $partner = $this->partner('Mitra A');
        $transaction = $this->transaction($partner);
        $admin = $this->staff('admin', $partner);

        $this->assertTrue($admin->can('manage', $transaction));
        $this->assertTrue($admin->can('handle', $transaction));
        $this->assertTrue($admin->can('claim', $transaction));
    }

    public function test_employee_can_only_handle_the_pickup_assigned_to_them(): void
    {
        $partner = $this->partner('Mitra A');
        $transaction = $this->transaction($partner);
        $assignee = $this->staff('employee', $partner);
        $colleague = $this->staff('employee', $partner);
        $transaction->pickup->update(['assigned_user_id' => $assignee->id]);
        $transaction->refresh();

        $this->assertTrue($assignee->can('handle', $transaction));
        $this->assertFalse($colleague->can('handle', $transaction));
        $this->assertFalse($assignee->can('manage', $transaction));
    }

    public function test_employee_of_the_same_partner_can_claim_an_unassigned_pickup(): void
    {
        $partner = $this->partner('Mitra A');
        $transaction = $this->transaction($partner);
        $employee = $this->staff('employee', $partner);
        $outsideEmployee = $this->staff('employee', $this->partner('Mitra B'));

        $this->assertTrue($employee->can('claim', $transaction));
        $this->assertFalse($outsideEmployee->can('claim', $transaction));
    }

    public function test_transaction_owner_cannot_manage_handle_or_claim_their_transaction(): void
    {
        $transaction = $this->transaction($this->partner('Mitra A'));
        $owner = $transaction->user;

        $this->assertTrue($owner->can('view', $transaction));
        $this->assertFalse($owner->can('manage', $transaction));
        $this->assertFalse($owner->can('handle', $transaction));
        $this->assertFalse($owner->can('claim', $transaction));
    }

    public function test_employee_route_refuses_a_transaction_assigned_to_a_colleague(): void
    {
        $partner = $this->partner('Mitra A');
        $transaction = $this->transaction($partner);
        $assignee = $this->staff('employee', $partner);
        $colleague = $this->staff('employee', $partner);
        $transaction->pickup->update(['assigned_user_id' => $assignee->id]);

        $this->actingAs($colleague)
            ->post(route('employee.transactions.verify', $transaction), [
                'actual_liter' => 4.8,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => Transaction::STATUS_SCHEDULED,
            'actual_liter' => null,
        ]);
    }

    public function test_visible_pickups_scope_hides_every_pickup_from_a_regular_user(): void
    {
        $this->transaction($this->partner('Mitra A'));
        $regularUser = User::factory()->create(['role' => 'user']);

        $this->assertSame(0, Pickup::visibleTo($regularUser)->count());
    }

    private function partner(string $name): Partner
    {
        return Partner::create([
            'name' => $name,
            'type' => 'Collector',
            'phone' => '0812345678',
            'address' => 'Jl. '.$name,
            'latitude' => -6.3055,
            'longitude' => 107.3053,
            'capacity_liter' => 200,
            'status' => 'active',
        ]);
    }

    private function staff(string $role, Partner $partner): User
    {
        $user = User::factory()->create(['role' => $role]);
        $user->partners()->sync([$partner->id]);

        return $user;
    }

    private function transaction(Partner $partner): Transaction
    {
        $transaction = Transaction::create([
            'code' => 'CNT-TEST-'.$partner->id,
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $partner->id,
            'estimated_liter' => 5,
            'price_per_liter' => 4000,
            'estimated_total' => 20000,
            'method' => Transaction::METHOD_PICKUP,
            'status' => Transaction::STATUS_SCHEDULED,
        ]);

        $transaction->pickup()->create([
            'partner_id' => $partner->id,
            'address' => 'Jl. Pickup',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
            'status' => Pickup::STATUS_PENDING,
        ]);

        return $transaction->load('pickup');
    }
}

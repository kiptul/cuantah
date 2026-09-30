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
 * Dashboard tiap peran harus menampilkan hal yang menunggu tindakannya.
 */
class DashboardTest extends TestCase
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
     * @return array{admin: User, employee: User, user: User, partner: Partner, task: Transaction, disputed: Transaction, unpaid: Transaction}
     */
    private function mitraSibuk(): array
    {
        $partner = Partner::factory()->create(['name' => 'Mitra Sibuk', 'capacity_liter' => 100]);
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee', 'name' => 'Karyawan Rajin']);
        $user = User::factory()->create(['role' => 'user', 'name' => 'Penyetor Teliti']);
        $admin->partners()->attach($partner->id);
        $employee->partners()->attach($partner->id);
        $state = ['user_id' => $user->id, 'partner_id' => $partner->id];

        $task = Transaction::factory()->pickup()->create($state + ['status' => Transaction::STATUS_SCHEDULED]);
        Pickup::factory()->assignedTo($employee)->scheduledInDays(-1)->create([
            'transaction_id' => $task->id,
            'partner_id' => $partner->id,
        ]);

        $disputed = Transaction::factory()->completed(6)->create($state + [
            'disputed_at' => now(),
            'dispute_reason' => 'Takaran jauh di bawah yang saya serahkan.',
        ]);
        Pickup::factory()->assignedTo($employee)->create(['transaction_id' => $disputed->id, 'partner_id' => $partner->id, 'status' => 'completed']);

        $unpaid = Transaction::factory()->completed(4)->create($state + ['payment_status' => 'unpaid', 'paid_at' => null]);

        return compact('admin', 'employee', 'user', 'partner', 'task', 'disputed', 'unpaid');
    }

    public function test_admin_dashboard_surfaces_everything_waiting_for_a_decision(): void
    {
        $world = $this->mitraSibuk();

        $this->actingAs($world['admin'])
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Takaran jauh di bawah yang saya serahkan.')
            ->assertSee($world['unpaid']->code)
            ->assertSee(route('admin.transactions.mark-paid', $world['unpaid']), false)
            ->assertSee('Karyawan Rajin')
            ->assertSee('Mitra Sibuk')
            ->assertViewHas('attention', ['unassigned' => 0, 'overdue' => 1, 'disputes' => 1, 'unpaid' => 1]);
    }

    public function test_employee_dashboard_lists_open_tasks_ready_to_process(): void
    {
        $world = $this->mitraSibuk();

        $this->actingAs($world['employee'])
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Penyetor Teliti')
            ->assertSee(route('employee.transactions.show', $world['task']), false)
            ->assertSee('Terlewat');
    }

    public function test_depositor_dashboard_tracks_running_deposits_and_pending_payment(): void
    {
        OilPrice::factory()->create();
        $world = $this->mitraSibuk();

        $this->actingAs($world['user'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Setoran berjalan')
            ->assertSee($world['task']->code)
            ->assertSee('masih akan dibayarkan mitra');
    }
}

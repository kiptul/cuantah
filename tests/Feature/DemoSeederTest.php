<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data demo harus konsisten dengan aturan aplikasi dan aman dijalankan ulang.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();

        config(['cuantah.seed_password' => 'rahasia-uji']);
    }

    public function test_demo_data_fills_every_state_the_dashboards_show(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertGreaterThan(50, Transaction::where('status', Transaction::STATUS_COMPLETED)->count());
        foreach ([Transaction::STATUS_PENDING, Transaction::STATUS_SCHEDULED, Transaction::STATUS_REJECTED, Transaction::STATUS_CANCELLED] as $status) {
            $this->assertTrue(Transaction::where('status', $status)->exists(), "Tidak ada transaksi berstatus {$status}.");
        }
        $this->assertTrue(Transaction::whereNotNull('disputed_at')->whereNull('dispute_resolved_at')->exists());
        $this->assertTrue(Transaction::where('payment_status', 'unpaid')->exists());
        $this->assertTrue(Distribution::exists());

        // Ongkir tidak pernah melebihi nilai setoran, seperti di createDeposit.
        $this->assertFalse(Transaction::whereColumn('estimated_total', '<', 'pickup_fee')->where('estimated_total', 0)->exists());
    }

    public function test_partner_stock_stays_between_empty_and_full(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (Partner::where('status', 'active')->get() as $partner) {
            $stock = $partner->availableLiter();

            $this->assertGreaterThanOrEqual(0, $stock, "Stok {$partner->name} negatif.");
            $this->assertLessThan($partner->capacity_liter, $stock, "{$partner->name} penuh sehingga menolak setoran.");
        }
    }

    public function test_seeding_twice_does_not_duplicate_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $transactions = Transaction::count();
        $distributions = Distribution::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($transactions, Transaction::count());
        $this->assertSame($distributions, Distribution::count());
    }

    public function test_every_dashboard_opens_with_demo_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $pages = [
            ['admin@cuantah.test', 'admin.dashboard'],
            ['admin@cuantah.test', 'admin.reports.index'],
            ['admin.cikampek@cuantah.test', 'admin.dashboard'],
            ['budi@cuantah.test', 'employee.dashboard'],
            ['user@cuantah.test', 'dashboard'],
        ];

        foreach ($pages as [$email, $route]) {
            // AuthenticateSession mengikat sesi pada hash kata sandi pengguna
            // sebelumnya, jadi sesi dikosongkan tiap berganti akun.
            $this->flushSession();

            $this->actingAs(User::where('email', $email)->firstOrFail())
                ->get(route($route))
                ->assertOk();
        }
    }
}

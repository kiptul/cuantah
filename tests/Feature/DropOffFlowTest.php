<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur antar sendiri: penyetor menampilkan barcode, karyawan memindainya,
 * lalu transaksinya masuk ke daftar karyawan itu. Ini satu-satunya jalur
 * yang tidak melewati penugasan admin, dan sebelumnya tidak diuji ujung
 * ke ujung.
 */
class DropOffFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    private function setoranAntarSendiri(User $penyetor, Partner $mitra): Transaction
    {
        return Transaction::factory()->create([
            'user_id' => $penyetor->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory()->create()->id,
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_PENDING,
        ]);
    }

    public function test_depositor_can_open_the_barcode_of_their_own_drop_off(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $transaksi = $this->setoranAntarSendiri($penyetor, Partner::factory()->create());

        $this->actingAs($penyetor)
            ->get(route('transactions.barcode', $transaksi))
            ->assertOk()
            ->assertSee($transaksi->code);
    }

    public function test_a_pickup_transaction_has_no_barcode_page(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => $penyetor->id,
            'oil_price_id' => OilPrice::factory()->create()->id,
        ]);

        $this->actingAs($penyetor)
            ->get(route('transactions.barcode', $transaksi))
            ->assertNotFound();
    }

    public function test_someone_else_cannot_open_the_barcode(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $orangLain = User::factory()->create(['role' => 'user']);
        $transaksi = $this->setoranAntarSendiri($penyetor, Partner::factory()->create());

        $this->actingAs($orangLain)
            ->get(route('transactions.barcode', $transaksi))
            ->assertForbidden();
    }

    public function test_scanning_the_code_puts_the_transaction_in_the_scanning_employees_list(): void
    {
        $mitra = Partner::factory()->create();
        $penyetor = User::factory()->create(['role' => 'user']);
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($mitra->id);

        $transaksi = $this->setoranAntarSendiri($penyetor, $mitra);
        $transaksi->pickup()->create([
            'partner_id' => $mitra->id,
            'address' => $mitra->address,
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'awaiting_dropoff',
        ]);

        $this->actingAs($karyawan)
            ->post(route('employee.scan.store'), ['code' => $transaksi->code])
            ->assertRedirect(route('employee.transactions.show', $transaksi));

        $pickup = $transaksi->fresh()->pickup;
        $this->assertSame($karyawan->id, $pickup->assigned_user_id);
        $this->assertNotNull($pickup->scanned_at);
        $this->assertSame(Transaction::STATUS_SCHEDULED, $transaksi->fresh()->status);
    }

    public function test_an_employee_cannot_scan_a_code_belonging_to_another_partner(): void
    {
        $mitraLain = Partner::factory()->create();
        $penyetor = User::factory()->create(['role' => 'user']);
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach(Partner::factory()->create()->id);

        $transaksi = $this->setoranAntarSendiri($penyetor, $mitraLain);
        $transaksi->pickup()->create([
            'partner_id' => $mitraLain->id,
            'address' => $mitraLain->address,
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'awaiting_dropoff',
        ]);

        $this->actingAs($karyawan)
            ->post(route('employee.scan.store'), ['code' => $transaksi->code])
            ->assertSessionHasErrors('code');

        $this->assertNull($transaksi->fresh()->pickup->assigned_user_id);
    }

    public function test_an_unknown_code_reports_an_error_instead_of_failing(): void
    {
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach(Partner::factory()->create()->id);

        $this->actingAs($karyawan)
            ->post(route('employee.scan.store'), ['code' => 'CNT-TIDAK-ADA'])
            ->assertSessionHasErrors('code');
    }
}

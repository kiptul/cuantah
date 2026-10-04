<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Transaksi yang sudah selesai, ditolak, atau dibatalkan tidak boleh
 * berpindah status lagi.
 *
 * Tanpa penjagaan ini, liter yang sudah dihitung sebagai stok mitra bisa
 * dicabut sesudah penyalurannya tercatat, sehingga stok mitra menjadi
 * negatif dan laporan rantai pasok berhenti punya arti.
 */
class TransactionStateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    private function service(): TransactionService
    {
        return app(TransactionService::class);
    }

    private function transaksiSelesai(float $liter = 10): Transaction
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create(['capacity_liter' => 500]);

        $transaksi = Transaction::factory()->completed($liter)->create([
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
        ]);

        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $mitra->id,
            'status' => 'completed',
        ]);

        return $transaksi;
    }

    public function test_completed_transaction_cannot_be_rejected(): void
    {
        $transaksi = $this->transaksiSelesai();

        $this->expectException(ValidationException::class);

        try {
            $this->service()->reject($transaksi, 'Alasan apa pun.');
        } finally {
            $this->assertSame(Transaction::STATUS_COMPLETED, $transaksi->fresh()->status);
        }
    }

    public function test_completed_transaction_cannot_be_verified_twice(): void
    {
        $transaksi = $this->transaksiSelesai(10);

        $this->expectException(ValidationException::class);

        try {
            $this->service()->verify($transaksi, [
                'actual_liter' => 99,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ]);
        } finally {
            $this->assertSame('10.00', $transaksi->fresh()->actual_liter);
        }
    }

    public function test_cancelled_transaction_cannot_be_revived_by_completing_it(): void
    {
        $harga = OilPrice::factory()->create();
        $transaksi = Transaction::factory()->create([
            'oil_price_id' => $harga->id,
            'status' => Transaction::STATUS_CANCELLED,
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->service()->verify($transaksi, ['actual_liter' => 5, 'payment_method' => 'cash', 'payment_status' => 'paid']);
        } finally {
            $this->assertSame(Transaction::STATUS_CANCELLED, $transaksi->fresh()->status);
        }
    }

    /**
     * Inti dari penjagaan ini: stok mitra tidak boleh bisa dibuat negatif.
     */
    public function test_partner_stock_cannot_be_driven_negative_by_rejecting_a_distributed_transaction(): void
    {
        $transaksi = $this->transaksiSelesai(10);
        $mitra = $transaksi->partner;

        Distribution::factory()->create([
            'partner_id' => $mitra->id,
            'volume_liter' => 10,
        ]);

        $this->assertSame(0.0, $mitra->availableLiter());

        try {
            $this->service()->reject($transaksi, 'Ternyata tidak layak.');
        } catch (ValidationException) {
            // Memang ditolak, itulah perilaku yang dijaga.
        }

        $this->assertGreaterThanOrEqual(0.0, $mitra->fresh()->availableLiter());
    }

    public function test_scanning_a_completed_dropoff_code_again_is_rejected(): void
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($mitra->id);

        $transaksi = Transaction::factory()->completed(5)->create([
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
        ]);
        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $mitra->id,
            'status' => 'completed',
            'scanned_at' => now()->subHour(),
        ]);

        $this->assertNull($this->service()->scanDropOff($transaksi->code, $karyawan));
    }
}

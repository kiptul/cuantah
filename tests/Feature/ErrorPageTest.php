<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman galat memakai bahasa yang sama dengan sisa aplikasi dan
 * menawarkan jalan keluar. Bawaan Laravel berbahasa Inggris dan berhenti
 * di situ saja, sehingga pengunjung yang tersesat tidak punya tujuan.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();

        config(['app.debug' => false]);
    }

    public function test_unknown_address_shows_the_indonesian_not_found_page(): void
    {
        $this->get('/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman Tidak Ditemukan')
            ->assertSee('Kembali ke Beranda');
    }

    public function test_forbidden_page_explains_itself_and_offers_a_way_back(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $this->actingAs($penyetor)
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertSee('Tidak Punya Akses')
            ->assertSee('Ke Dasbor');
    }

    public function test_another_depositors_transaction_is_forbidden_not_merely_hidden(): void
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create();
        $pemilik = User::factory()->create(['role' => 'user']);
        $penyusup = User::factory()->create(['role' => 'user']);

        $transaksi = Transaction::factory()->create([
            'user_id' => $pemilik->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
        ]);

        $this->actingAs($penyusup)
            ->get(route('transactions.show', $transaksi))
            ->assertForbidden();
    }
}

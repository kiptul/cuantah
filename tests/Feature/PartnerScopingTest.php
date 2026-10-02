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
 * Isolasi data antar mitra.
 *
 * Scope visibleTo dipakai di lima belas tempat dan menjadi satu-satunya
 * pemisah data antar mitra. Berkas ini menjaganya dari regresi: sekali ada
 * aksi baru yang lupa memanggil pemeriksaan, salah satu test di sini gagal.
 */
class PartnerScopingTest extends TestCase
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
     * Dua mitra dengan satu transaksi selesai masing-masing.
     *
     * @return array{0: Partner, 1: Partner, 2: Transaction, 3: Transaction}
     */
    private function duaMitraBerisi(): array
    {
        $harga = OilPrice::factory()->create();
        $mitraA = Partner::factory()->create(['name' => 'Mitra A']);
        $mitraB = Partner::factory()->create(['name' => 'Mitra B']);

        $trxA = Transaction::factory()->completed(5)->create(['partner_id' => $mitraA->id, 'oil_price_id' => $harga->id]);
        $trxB = Transaction::factory()->completed(7)->create(['partner_id' => $mitraB->id, 'oil_price_id' => $harga->id]);

        return [$mitraA, $mitraB, $trxA, $trxB];
    }

    public function test_admin_only_lists_transactions_of_partners_they_are_linked_to(): void
    {
        [$mitraA, , $trxA, $trxB] = $this->duaMitraBerisi();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($mitraA->id);

        $terlihat = $this->actingAs($admin)
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->viewData('transactions')
            ->pluck('id')
            ->all();

        $this->assertContains($trxA->id, $terlihat);
        $this->assertNotContains($trxB->id, $terlihat, 'Transaksi mitra lain tidak boleh ikut terdaftar.');
    }

    public function test_staff_without_any_partner_sees_nothing_instead_of_everything(): void
    {
        $this->duaMitraBerisi();
        $admin = User::factory()->create(['role' => 'admin']);

        $terlihat = $this->actingAs($admin)
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->viewData('transactions');

        $this->assertCount(0, $terlihat, 'Staff tanpa mitra harus melihat nol baris, bukan seluruh data.');
    }

    public function test_admin_cannot_open_a_transaction_from_another_partner(): void
    {
        [$mitraA, , , $trxB] = $this->duaMitraBerisi();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($mitraA->id);

        $this->actingAs($admin)
            ->get(route('admin.transactions.show', $trxB))
            ->assertForbidden();
    }

    /**
     * Seluruh aksi tulis harus dijaga, bukan hanya halaman detail.
     */
    public function test_admin_cannot_modify_a_transaction_from_another_partner(): void
    {
        [$mitraA, , , $trxB] = $this->duaMitraBerisi();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($mitraA->id);

        $aksi = [
            ['admin.transactions.mark-paid', []],
            ['admin.transactions.reject', ['rejection_reason' => 'Alasan uji coba.']],
            ['admin.transactions.verify', ['actual_liter' => 3, 'payment_method' => 'cash', 'payment_status' => 'paid']],
        ];

        foreach ($aksi as [$rute, $data]) {
            $this->actingAs($admin)
                ->post(route($rute, $trxB), $data)
                ->assertForbidden();
        }

        $this->assertSame(Transaction::STATUS_COMPLETED, $trxB->fresh()->status, 'Status transaksi mitra lain tidak boleh berubah.');
    }

    /**
     * Menggantikan test pengambilan pickup lintas mitra.
     *
     * Pengambilan mandiri sudah dicabut: penjemputan kini hanya sampai ke
     * karyawan lewat penugasan admin. Batas yang dijaga test lama tetap perlu
     * dijaga, hanya pintunya berpindah ke halaman tugas dan verifikasinya.
     *
     * Penjagaannya sekarang bertumpu pada penugasan, bukan pada keanggotaan
     * mitra, sehingga sebenarnya lebih rapat: karyawan mitra lain tidak pernah
     * bisa ditugaskan, sebab penugasan dilakukan admin yang terikat mitra.
     */
    public function test_employee_cannot_reach_a_transaction_of_another_partner(): void
    {
        [$mitraA, $mitraB] = $this->duaMitraBerisi();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($mitraA->id);

        $trx = Transaction::factory()->pickup()->create([
            'partner_id' => $mitraB->id,
            'status' => Transaction::STATUS_VERIFICATION,
        ]);
        Pickup::factory()->create(['transaction_id' => $trx->id, 'partner_id' => $mitraB->id]);

        $this->actingAs($karyawan)
            ->get(route('employee.transactions.show', $trx))
            ->assertForbidden();
    }

    public function test_employee_cannot_verify_a_transaction_of_another_partner(): void
    {
        [$mitraA, $mitraB] = $this->duaMitraBerisi();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($mitraA->id);

        $trx = Transaction::factory()->pickup()->create([
            'partner_id' => $mitraB->id,
            'status' => Transaction::STATUS_VERIFICATION,
        ]);
        Pickup::factory()->create(['transaction_id' => $trx->id, 'partner_id' => $mitraB->id]);

        $this->actingAs($karyawan)
            ->post(route('employee.transactions.verify', $trx), [
                'actual_liter' => 5,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ])
            ->assertForbidden();

        $this->assertNotSame(
            Transaction::STATUS_COMPLETED,
            $trx->fresh()->status,
            'Transaksi mitra lain tidak boleh ikut diselesaikan.',
        );
    }

    public function test_depositor_only_sees_their_own_transactions(): void
    {
        $mitra = Partner::factory()->create();
        $pemilik = User::factory()->create(['role' => 'user']);
        $oranglain = User::factory()->create(['role' => 'user']);

        $milikPemilik = Transaction::factory()->create(['user_id' => $pemilik->id, 'partner_id' => $mitra->id]);
        $milikOrangLain = Transaction::factory()->create(['user_id' => $oranglain->id, 'partner_id' => $mitra->id]);

        $terlihat = $this->actingAs($pemilik)
            ->get(route('transactions.index'))
            ->assertOk()
            ->viewData('transactions')
            ->pluck('id')
            ->all();

        $this->assertSame([$milikPemilik->id], $terlihat);

        $this->actingAs($pemilik)
            ->get(route('transactions.show', $milikOrangLain))
            ->assertForbidden();
    }
}

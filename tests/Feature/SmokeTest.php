<?php

namespace Tests\Feature;

use App\Models\Distribution;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Setiap halaman harus terbuka dengan data yang wajar.
 *
 * Galat templat hanya muncul saat halamannya benar-benar dirender, dan
 * sebagian halaman admin jarang dibuka saat pengembangan. Berkas ini
 * menembak seluruhnya sekali jalan supaya kerusakan tidak menunggu
 * ditemukan pengguna.
 */
class SmokeTest extends TestCase
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
     * Satu mitra berisi transaksi di berbagai status, plus penyaluran.
     *
     * @return array{admin: User, employee: User, user: User, partner: Partner, transaction: Transaction, dropOff: Transaction, pickup: Pickup}
     */
    private function duniaKecil(): array
    {
        $harga = OilPrice::factory()->create();
        $mitra = Partner::factory()->create(['capacity_liter' => 1000]);

        $admin = User::factory()->create(['role' => 'admin']);
        $karyawan = User::factory()->create(['role' => 'employee']);
        $penyetor = User::factory()->create(['role' => 'user']);
        $admin->partners()->attach($mitra->id);
        $karyawan->partners()->attach($mitra->id);

        $selesai = Transaction::factory()->pickup()->completed(6)->create([
            'user_id' => $penyetor->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
        ]);
        $pickup = Pickup::factory()->create([
            'transaction_id' => $selesai->id,
            'partner_id' => $mitra->id,
            'assigned_user_id' => $karyawan->id,
            'status' => 'completed',
        ]);

        $antarSendiri = Transaction::factory()->create([
            'user_id' => $penyetor->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => $harga->id,
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_PENDING,
        ]);
        Pickup::factory()->create([
            'transaction_id' => $antarSendiri->id,
            'partner_id' => $mitra->id,
            'status' => 'awaiting_dropoff',
        ]);

        Distribution::factory()->create(['partner_id' => $mitra->id, 'volume_liter' => 2]);

        return [
            'admin' => $admin,
            'employee' => $karyawan,
            'user' => $penyetor,
            'partner' => $mitra,
            'transaction' => $selesai,
            'dropOff' => $antarSendiri,
            'pickup' => $pickup,
        ];
    }

    public static function publicPages(): array
    {
        return [
            'beranda' => ['/'],
            'tentang' => ['/tentang'],
            'cara kerja' => ['/cara-kerja'],
            'harga' => ['/harga'],
            'dampak' => ['/dampak'],
            'edukasi' => ['/edukasi'],
            'faq' => ['/faq'],
            'login' => ['/login'],
            'register' => ['/register'],
            'lupa password' => ['/lupa-password'],
        ];
    }

    #[DataProvider('publicPages')]
    public function test_public_pages_open_without_an_account(string $path): void
    {
        OilPrice::factory()->create();

        $this->get($path)->assertOk();
    }

    public function test_every_depositor_page_opens(): void
    {
        $dunia = $this->duniaKecil();

        $this->actingAs($dunia['user']);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('deposits.create'))->assertOk();
        $this->get(route('transactions.index'))->assertOk();
        $this->get(route('transactions.show', $dunia['transaction']))->assertOk();
        $this->get(route('transactions.qr', $dunia['dropOff']))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
    }

    public function test_every_employee_page_opens(): void
    {
        $dunia = $this->duniaKecil();

        $this->actingAs($dunia['employee']);

        $this->get(route('employee.dashboard'))->assertOk();
        $this->get(route('employee.scan'))->assertOk();
        $this->get(route('employee.transactions.index'))->assertOk();
        $this->get(route('employee.transactions.show', $dunia['transaction']))->assertOk();
    }

    public function test_every_admin_page_opens(): void
    {
        $dunia = $this->duniaKecil();

        $this->actingAs($dunia['admin']);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.transactions.index'))->assertOk();
        $this->get(route('admin.transactions.show', $dunia['transaction']))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.pickups.index'))->assertOk();
        $this->get(route('admin.partners.index'))->assertOk();
        $this->get(route('admin.prices.index'))->assertOk();
        $this->get(route('admin.distributions.index'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
        $this->get(route('admin.reports.employee', $dunia['employee']))->assertOk();
    }

    public function test_admin_pages_still_open_when_there_is_no_data_at_all(): void
    {
        $mitra = Partner::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($mitra->id);

        $this->actingAs($admin);

        $this->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.transactions.index'))->assertOk();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.pickups.index'))->assertOk();
        $this->get(route('admin.distributions.index'))->assertOk();
        $this->get(route('admin.reports.index'))->assertOk();
    }

    /**
     * Penyetor baru tanpa satu pun transaksi. Halaman kosong justru yang
     * paling sering luput dicoba saat pengembangan.
     */
    public function test_depositor_pages_open_for_a_brand_new_account(): void
    {
        OilPrice::factory()->create();
        Partner::factory()->create();
        $baru = User::factory()->create(['role' => 'user']);

        $this->actingAs($baru);

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('transactions.index'))->assertOk();
        $this->get(route('deposits.create'))->assertOk();
        $this->get(route('profile.edit'))->assertOk();
    }
}

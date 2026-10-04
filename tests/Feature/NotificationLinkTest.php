<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Notifikasi mengantar ke transaksi yang dibicarakannya.
 *
 * Sebelumnya isinya menyebut kode transaksi sebagai teks, dan orang yang
 * membacanya harus mencari sendiri kode itu di daftar. Padahal dua belas dari
 * tiga belas tempat notifikasi dibuat berbicara tentang sebuah transaksi.
 *
 * Halaman transaksinya berbeda untuk tiap peran, jadi yang diuji di sini bukan
 * sekadar ada tautan, melainkan tautannya mengarah ke halaman yang memang bisa
 * dibuka oleh peran yang membacanya.
 */
class NotificationLinkTest extends TestCase
{
    use RefreshDatabase;

    private Partner $mitra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mitra = Partner::factory()->create();
        OilPrice::factory()->create();
    }

    private function transaksi(array $atribut = []): Transaction
    {
        return Transaction::factory()->pickup()->create($atribut + [
            'partner_id' => $this->mitra->id,
            'status' => Transaction::STATUS_VERIFICATION,
        ]);
    }

    private function beriTahu(User $penerima, ?Transaction $transaksi): Notification
    {
        return Notification::create([
            'user_id' => $penerima->id,
            'transaction_id' => $transaksi?->id,
            'title' => 'Ada kabar',
            'message' => 'Isi kabarnya.',
            'type' => 'transaction',
        ]);
    }

    public function test_a_depositor_is_taken_to_their_own_transaction(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);
        $transaksi = $this->transaksi(['user_id' => $penyetor->id]);
        $this->beriTahu($penyetor, $transaksi);

        $this->actingAs($penyetor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('transactions.show', $transaksi), false);
    }

    public function test_an_employee_is_taken_to_the_staff_page_not_the_depositor_page(): void
    {
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($this->mitra->id);

        $transaksi = $this->transaksi();
        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $this->mitra->id,
            'assigned_user_id' => $karyawan->id,
            'status' => 'verification',
        ]);
        $this->beriTahu($karyawan, $transaksi);

        $halaman = $this->actingAs($karyawan)->get(route('employee.dashboard'))->assertOk();

        $halaman->assertSee(route('employee.transactions.show', $transaksi), false);
        $halaman->assertDontSee(route('transactions.show', $transaksi), false);
    }

    public function test_an_admin_is_taken_to_the_admin_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($this->mitra->id);

        $transaksi = $this->transaksi();
        $this->beriTahu($admin, $transaksi);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.transactions.show', $transaksi), false);
    }

    public function test_an_employee_whose_assignment_was_withdrawn_gets_no_link(): void
    {
        // Notifikasi lamanya tetap ada, tetapi halamannya sudah tertutup
        // baginya. Menautkannya hanya akan mengantar ke halaman 403.
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($this->mitra->id);

        $transaksi = $this->transaksi();
        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $this->mitra->id,
            'assigned_user_id' => null,
            'status' => 'pending',
        ]);
        $this->beriTahu($karyawan, $transaksi);

        $this->actingAs($karyawan)
            ->get(route('employee.dashboard'))
            ->assertOk()
            ->assertDontSee(route('employee.transactions.show', $transaksi), false);
    }

    public function test_an_admin_of_another_partner_gets_no_link(): void
    {
        $lain = Partner::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($lain->id);

        $transaksi = $this->transaksi();
        $this->beriTahu($admin, $transaksi);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.transactions.show', $transaksi), false);
    }

    public function test_a_notification_without_a_transaction_stays_plain_text(): void
    {
        // Pemberitahuan soal akun, dan notifikasi lama yang dibuat sebelum
        // kolomnya ada, tidak boleh menjadi tautan yang tidak menuju apa pun.
        $penyetor = User::factory()->create(['role' => 'user']);
        $this->beriTahu($penyetor, null);

        $this->actingAs($penyetor)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Isi kabarnya.');
    }

    public function test_a_deposit_notification_carries_its_transaction(): void
    {
        // Diuji lewat alur sungguhan, bukan lewat Notification::create di test:
        // yang dijaga adalah tempat pembuatannya ikut mengisi kolomnya.
        $penyetor = User::factory()->create(['role' => 'user']);
        $mitra = Partner::factory()->create(['latitude' => -6.2, 'longitude' => 106.8, 'capacity_liter' => 1000]);
        OilPrice::factory()->create(['is_active' => true, 'price_per_liter' => 4000]);

        $this->actingAs($penyetor)->post(route('deposits.store'), [
            'partner_id' => $mitra->id,
            'method' => Transaction::METHOD_DROP_OFF,
            'estimated_liter' => 5,
            'address' => 'Jl. Melati 10',
            'latitude' => -6.2,
            'longitude' => 106.8,
        ])->assertSessionHasNoErrors();

        $notifikasi = Notification::where('user_id', $penyetor->id)->latest('id')->first();

        $this->assertNotNull($notifikasi, 'Pengajuan setoran harus memberi tahu penyetornya.');
        $this->assertNotNull(
            $notifikasi->transaction_id,
            'Notifikasi dibuat tanpa menyimpan transaksi yang dibicarakannya.',
        );
    }
}

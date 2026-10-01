<?php

namespace Tests\Feature;

use App\Models\Distribution;
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
            'status' => 'assigned',
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

    /**
     * Membuat mitra beserta satu aturan ongkir untuk jarak 3 sampai 10 km.
     */
    private function partnerWithPickupFee(int $fee = 10000): Partner
    {
        $partner = Partner::create([
            'name' => 'Mitra Ongkir',
            'type' => 'Collector',
            'phone' => '0812345678',
            'address' => 'Jl. Mitra',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
            'capacity_liter' => 200,
            'status' => 'active',
        ]);
        $partner->deliveryFees()->create(['min_distance_km' => 3, 'max_distance_km' => 10, 'fee' => $fee]);

        return $partner;
    }

    public function test_pickup_deposit_worth_less_than_its_fee_is_rejected(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        // Titik jemput sekitar 4,4 km dari mitra sehingga terkena ongkir Rp10.000,
        // sementara 2 liter hanya bernilai Rp8.000.
        $response = $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_PICKUP,
            'estimated_liter' => 2,
            'address' => 'Jl. Jauh',
            'latitude' => -6.2655,
            'longitude' => 107.3053,
            'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time' => '09:30',
        ]);

        $response->assertSessionHasErrors('estimated_liter');
        $this->assertSame(0, Transaction::count(), 'Transaksi tanpa nilai bersih tidak boleh tersimpan.');
        $this->assertSame(0, Pickup::count(), 'Pickup ikut batal karena dibungkus satu transaksi basis data.');
    }

    public function test_pickup_deposit_above_its_fee_still_succeeds(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        // 5 liter bernilai Rp20.000, masih di atas ongkir Rp10.000.
        $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_PICKUP,
            'estimated_liter' => 5,
            'address' => 'Jl. Jauh',
            'latitude' => -6.2655,
            'longitude' => 107.3053,
            'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time' => '09:30',
        ])->assertSessionHasNoErrors();

        $transaction = Transaction::firstOrFail();
        $this->assertSame(10000, $transaction->pickup_fee);
        $this->assertSame(10000, $transaction->estimated_total, 'Bruto Rp20.000 dikurangi ongkir Rp10.000.');
    }

    public function test_drop_off_deposit_never_charges_a_pickup_fee(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        // Volume kecil yang sama tetap diterima bila diantar sendiri.
        $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_DROP_OFF,
            'estimated_liter' => 2,
            'address' => 'Jl. Mitra',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
        ])->assertSessionHasNoErrors();

        $transaction = Transaction::firstOrFail();
        $this->assertSame(0, $transaction->pickup_fee);
        $this->assertSame(8000, $transaction->estimated_total);
    }

    /**
     * Transaksi selesai yang sudah ditandai lunas oleh karyawan.
     */
    private function paidTransactionFor(User $user): Transaction
    {
        $price = OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        return Transaction::create([
            'code' => 'CNT-TEST-1',
            'user_id' => $user->id,
            'oil_price_id' => $price->id,
            'partner_id' => $partner->id,
            'estimated_liter' => 5,
            'actual_liter' => 5,
            'price_per_liter' => 4000,
            'estimated_total' => 20000,
            'total_value' => 20000,
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_COMPLETED,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'paid_at' => now(),
        ]);
    }

    /**
     * Transaksi yang masih berjalan, dipakai untuk menguji aksi yang hanya
     * boleh terjadi sebelum status akhir.
     */
    private function pendingTransactionFor(User $user): Transaction
    {
        $price = OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true]);
        $partner = $this->partnerWithPickupFee();

        return Transaction::create([
            'code' => 'CNT-TEST-2',
            'user_id' => $user->id,
            'oil_price_id' => $price->id,
            'partner_id' => $partner->id,
            'estimated_liter' => 5,
            'price_per_liter' => 4000,
            'estimated_total' => 20000,
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_PENDING,
        ]);
    }

    public function test_employee_completes_an_assigned_pickup_with_a_single_form(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $transaction = Transaction::factory()->pickup()->create(['status' => Transaction::STATUS_SCHEDULED, 'price_per_liter' => 4000]);
        $employee->partners()->attach($transaction->partner_id);
        Pickup::factory()->assignedTo($employee)->create([
            'transaction_id' => $transaction->id,
            'partner_id' => $transaction->partner_id,
        ]);

        $this->actingAs($employee)
            ->get(route('employee.transactions.show', $transaction))
            ->assertOk()
            ->assertSee('Form penjemputan')
            ->assertDontSee('Dijemput</button>', false);

        // Tanpa langkah "Dijemput" dan "Verifikasi": form penjemputan langsung
        // menutup transaksi dari status dijadwalkan.
        $this->actingAs($employee)
            ->post(route('employee.transactions.verify', $transaction), [
                'actual_liter' => 7.5,
                'payment_method' => 'cash',
                'payment_status' => 'paid',
            ])
            ->assertRedirect(route('employee.dashboard'))
            ->assertSessionHas('success');

        $transaction->refresh();
        $this->assertSame(Transaction::STATUS_COMPLETED, $transaction->status);
        $this->assertSame('paid', $transaction->payment_status);
        $this->assertSame(30000, $transaction->total_value);
        $this->assertSame('completed', $transaction->pickup->status);
    }

    public function test_depositor_is_not_asked_to_reconfirm_a_completed_payment(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->paidTransactionFor($user);

        $this->actingAs($user)
            ->get(route('transactions.show', $transaction))
            ->assertOk()
            ->assertDontSee('Saya sudah terima pembayaran');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Konfirmasi pembayaran');
    }

    public function test_admin_can_settle_a_transaction_that_was_paid_later(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->paidTransactionFor($user);
        $transaction->update(['payment_status' => 'unpaid', 'paid_at' => null]);
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($transaction->partner_id);

        $this->actingAs($admin)
            ->post(route('admin.transactions.mark-paid', $transaction))
            ->assertSessionHas('success');

        $transaction->refresh();
        $this->assertSame('paid', $transaction->payment_status);
        $this->assertNotNull($transaction->paid_at);
        $this->assertTrue($user->notifications()->where('title', 'Pembayaran diterima')->exists());
    }

    public function test_a_paid_transaction_cannot_be_settled_twice(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->paidTransactionFor($user);
        $paidAt = $transaction->paid_at;
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->attach($transaction->partner_id);

        $this->actingAs($admin)
            ->post(route('admin.transactions.mark-paid', $transaction))
            ->assertSessionHasErrors('payment_status');

        $this->assertEquals($paidAt, $transaction->fresh()->paid_at);
    }

    public function test_a_dispute_is_reported_to_the_partner_admins(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->completed(6)->create(['user_id' => $user->id]);
        $adminMitra = User::factory()->create(['role' => 'admin']);
        $adminMitra->partners()->attach($transaction->partner_id);
        $adminLain = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)->post(route('transactions.dispute', $transaction), [
            'dispute_reason' => 'Saya menyetor sekitar sepuluh liter, tetapi tercatat enam liter.',
        ]);

        $this->assertTrue($adminMitra->notifications()->where('title', 'Keberatan takaran baru')->exists());
        $this->assertFalse($adminLain->notifications()->exists());
    }

    public function test_distribution_cannot_exceed_collected_volume(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->partnerWithPickupFee();
        $admin->partners()->attach($partner->id);

        // Mitra ini baru mengumpulkan 5 liter dari satu transaksi selesai.
        $user = User::factory()->create(['role' => 'user']);
        Transaction::create([
            'code' => 'CNT-STOK-1', 'user_id' => $user->id, 'partner_id' => $partner->id,
            'oil_price_id' => OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true])->id,
            'estimated_liter' => 5, 'actual_liter' => 5, 'price_per_liter' => 4000,
            'estimated_total' => 20000, 'total_value' => 20000,
            'method' => Transaction::METHOD_DROP_OFF, 'status' => Transaction::STATUS_COMPLETED,
        ]);

        $this->actingAs($admin)->post(route('admin.distributions.store'), [
            'partner_id' => $partner->id,
            'volume_liter' => 50,
            'destination' => 'Pabrik Uji',
            'distributed_at' => now()->toDateString(),
        ])->assertSessionHasErrors('volume_liter');

        $this->assertSame(0, Distribution::count(), 'Penyaluran melebihi stok tidak boleh tercatat.');
    }

    public function test_distribution_within_collected_volume_succeeds_and_reduces_stock(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $partner = $this->partnerWithPickupFee();
        $admin->partners()->attach($partner->id);

        $user = User::factory()->create(['role' => 'user']);
        Transaction::create([
            'code' => 'CNT-STOK-2', 'user_id' => $user->id, 'partner_id' => $partner->id,
            'oil_price_id' => OilPrice::create(['price_per_liter' => 4000, 'effective_date' => now()->toDateString(), 'is_active' => true])->id,
            'estimated_liter' => 10, 'actual_liter' => 10, 'price_per_liter' => 4000,
            'estimated_total' => 40000, 'total_value' => 40000,
            'method' => Transaction::METHOD_DROP_OFF, 'status' => Transaction::STATUS_COMPLETED,
        ]);

        $this->assertSame(10.0, $partner->availableLiter());

        $this->actingAs($admin)->post(route('admin.distributions.store'), [
            'partner_id' => $partner->id,
            'volume_liter' => 4,
            'destination' => 'Pabrik Uji',
            'distributed_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(6.0, $partner->fresh()->availableLiter(), 'Sisa stok berkurang sebesar yang disalurkan.');
    }

    public function test_rejecting_a_transaction_requires_a_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->pendingTransactionFor($user);
        $admin->partners()->attach($transaction->partner_id);

        $this->actingAs($admin)
            ->post(route('admin.transactions.reject', $transaction), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(Transaction::STATUS_PENDING, $transaction->fresh()->status);
    }

    public function test_rejection_reason_is_stored_and_sent_to_the_depositor(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['role' => 'user']);
        $transaction = $this->pendingTransactionFor($user);
        $admin->partners()->attach($transaction->partner_id);

        $alasan = 'Jelantah tercampur air sehingga tidak bisa diolah.';

        $this->actingAs($admin)
            ->post(route('admin.transactions.reject', $transaction), ['rejection_reason' => $alasan])
            ->assertSessionHasNoErrors();

        $this->assertSame(Transaction::STATUS_REJECTED, $transaction->fresh()->status);
        $this->assertSame($alasan, $transaction->fresh()->rejection_reason);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'message' => 'Transaksi '.$transaction->code.' ditolak. Alasan: '.$alasan,
        ]);
    }

    public function test_deposit_is_blocked_when_partner_is_already_full(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        OilPrice::factory()->create();
        $partner = Partner::factory()->atKarawang()->create(['capacity_liter' => 10]);

        // Sudah terkumpul 12 L dan belum ada penyaluran keluar, jadi melampaui kapasitas.
        Transaction::factory()->completed(12)->create(['partner_id' => $partner->id]);

        $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_DROP_OFF,
            'estimated_liter' => 3,
            'address' => 'Jl. Mitra',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
        ])->assertSessionHasErrors('partner_id');
    }

    public function test_deposit_is_allowed_again_after_partner_distributes_its_stock(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        OilPrice::factory()->create();
        $partner = Partner::factory()->atKarawang()->create(['capacity_liter' => 10]);
        Transaction::factory()->completed(12)->create(['partner_id' => $partner->id]);

        // Penyaluran keluar mengosongkan kembali ruang mitra.
        Distribution::factory()->create(['partner_id' => $partner->id, 'volume_liter' => 12]);

        $this->actingAs($user)->post(route('deposits.store'), [
            'partner_id' => $partner->id,
            'method' => Transaction::METHOD_DROP_OFF,
            'estimated_liter' => 3,
            'address' => 'Jl. Mitra',
            'latitude' => -6.3055,
            'longitude' => 107.3053,
        ])->assertSessionHasNoErrors();
    }

    public function test_depositor_can_cancel_while_still_pending(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->post(route('transactions.cancel', $transaction))
            ->assertRedirect(route('transactions.index'));

        $this->assertSame(Transaction::STATUS_CANCELLED, $transaction->fresh()->status);
    }

    public function test_cancellation_is_refused_once_someone_is_working_on_it(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'status' => Transaction::STATUS_SCHEDULED,
        ]);

        $this->actingAs($user)
            ->post(route('transactions.cancel', $transaction))
            ->assertForbidden();

        $this->assertSame(Transaction::STATUS_SCHEDULED, $transaction->fresh()->status);
    }

    public function test_nobody_else_can_cancel_someone_elses_deposit(): void
    {
        $pemilik = User::factory()->create(['role' => 'user']);
        $oranglain = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->create(['user_id' => $pemilik->id]);

        $this->actingAs($oranglain)
            ->post(route('transactions.cancel', $transaction))
            ->assertForbidden();

        $this->assertSame(Transaction::STATUS_PENDING, $transaction->fresh()->status);
    }

    public function test_notifications_are_marked_read_only_when_the_bell_is_opened(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $notifikasi = $user->notifications()->create([
            'title' => 'Pengajuan setor diterima',
            'message' => 'Menunggu proses berikutnya.',
            'type' => 'transaction',
        ]);

        // Membuka dasbor saja tidak menandainya, supaya titik merah pada
        // lonceng hanya hilang ketika isinya benar-benar dilihat.
        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->assertNull($notifikasi->fresh()->read_at);

        $this->actingAs($user)->post(route('notifications.read'))->assertOk();
        $this->assertNotNull($notifikasi->fresh()->read_at);
    }

    public function test_depositor_can_dispute_the_measured_volume(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->completed(6)->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('transactions.dispute', $transaction), [
            'dispute_reason' => 'Saya menyetor sekitar sepuluh liter, tetapi tercatat enam liter.',
        ])->assertSessionHas('success');

        $this->assertNotNull($transaction->fresh()->disputed_at);
    }

    public function test_dispute_closes_after_three_days(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->completed(6)->create(['user_id' => $user->id]);
        $transaction->forceFill(['updated_at' => now()->subDays(4)])->saveQuietly();

        $this->actingAs($user)->post(route('transactions.dispute', $transaction), [
            'dispute_reason' => 'Keberatan yang diajukan terlambat sesudah batas waktu.',
        ])->assertForbidden();

        $this->assertNull($transaction->fresh()->disputed_at);
    }

    public function test_dispute_cannot_be_filed_twice(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->completed(6)->create(['user_id' => $user->id]);
        $alasan = ['dispute_reason' => 'Takaran tidak sesuai dengan yang saya serahkan.'];

        $this->actingAs($user)->post(route('transactions.dispute', $transaction), $alasan);
        $this->actingAs($user)->post(route('transactions.dispute', $transaction), $alasan)->assertForbidden();
    }

    public function test_only_staff_of_the_same_partner_can_answer_a_dispute(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $transaction = Transaction::factory()->completed(6)->create(['user_id' => $user->id]);
        $transaction->update(['disputed_at' => now(), 'dispute_reason' => 'Takaran tidak sesuai.']);

        $mitraLain = Partner::factory()->create();
        $adminLain = User::factory()->create(['role' => 'admin']);
        $adminLain->partners()->attach($mitraLain->id);

        $this->actingAs($adminLain)
            ->post(route('admin.transactions.resolve-dispute', $transaction), ['dispute_resolution' => 'Sudah kami telusuri ulang.'])
            ->assertForbidden();

        $adminBenar = User::factory()->create(['role' => 'admin']);
        $adminBenar->partners()->attach($transaction->partner_id);

        $this->actingAs($adminBenar)
            ->post(route('admin.transactions.resolve-dispute', $transaction), ['dispute_resolution' => 'Sudah kami telusuri ulang, selisih diganti.'])
            ->assertSessionHas('success');

        $this->assertNotNull($transaction->fresh()->dispute_resolved_at);
    }
}

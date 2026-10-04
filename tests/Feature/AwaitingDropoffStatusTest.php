<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Status menunggu diantar ikut dikenali seperti status lainnya.
 *
 * Setoran antar sendiri disimpan dengan status awaiting_dropoff sejak
 * diajukan, tetapi status itu tidak pernah terdaftar di STATUS_LABELS,
 * sehingga tidak ikut terbawa oleh statuses(). Saringan transaksi admin
 * memakai daftar itu untuk menentukan nilai yang sah, dan nilai yang tidak
 * dikenal dibuang diam-diam alih-alih ditolak.
 *
 * Akibatnya menyaring "menunggu diantar" mengembalikan seluruh transaksi,
 * persis seperti tidak menyaring sama sekali, dan pilihannya tidak pernah
 * muncul di borang saringan.
 */
class AwaitingDropoffStatusTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([$this->mitra->id]);

        return $admin;
    }

    private Partner $mitra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mitra = Partner::factory()->create();
        OilPrice::factory()->create();
    }

    public function test_the_status_is_listed_like_every_other_status(): void
    {
        $this->assertContains(
            Transaction::STATUS_AWAITING_DROPOFF,
            Transaction::statuses(),
            'Status yang dipakai layanan harus ikut terdaftar, bukan hanya ada di basis data.',
        );
    }

    public function test_the_status_has_a_label_in_everyday_indonesian(): void
    {
        $transaksi = Transaction::factory()->create([
            'partner_id' => $this->mitra->id,
            'status' => Transaction::STATUS_AWAITING_DROPOFF,
        ]);

        // Tanpa sebutannya sendiri, statusLabel() jatuh ke kunci mentahnya dan
        // pesan ke pengguna berbunyi "sudah awaiting_dropoff".
        $this->assertSame('menunggu diantar', $transaksi->statusLabel());
    }

    public function test_filtering_by_it_narrows_the_list_instead_of_being_ignored(): void
    {
        $menunggu = Transaction::factory()->create([
            'partner_id' => $this->mitra->id,
            'status' => Transaction::STATUS_AWAITING_DROPOFF,
        ]);
        $selesai = Transaction::factory()->completed(4)->create(['partner_id' => $this->mitra->id]);

        $hasil = $this->actingAs($this->admin())
            ->get(route('admin.transactions.index', ['status' => Transaction::STATUS_AWAITING_DROPOFF]))
            ->assertOk()
            ->viewData('transactions');

        $this->assertTrue($hasil->contains('id', $menunggu->id));
        $this->assertFalse(
            $hasil->contains('id', $selesai->id),
            'Saringan yang diabaikan mengembalikan semuanya, sehingga tampak seperti tidak bekerja.',
        );
    }

    public function test_the_filter_form_offers_it_as_a_choice(): void
    {
        $isi = $this->actingAs($this->admin())
            ->get(route('admin.transactions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            'value="'.Transaction::STATUS_AWAITING_DROPOFF.'"',
            $isi,
            'Saringan yang hanya bisa dicapai dengan mengetik alamat sendiri sama saja dengan tidak ada.',
        );
    }
}

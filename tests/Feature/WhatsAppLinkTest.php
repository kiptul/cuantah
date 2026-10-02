<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Tautan WhatsApp ke penyetor.
 *
 * Nomor disimpan apa adanya seperti yang diketik orang, biasanya berawalan nol.
 * wa.me menolak bentuk itu: nomor berawalan nol tidak pernah menemukan siapa
 * pun, dan WhatsApp terbuka pada percakapan kosong tanpa memberi tahu bahwa
 * nomornya salah. Karyawan akan menyangka penyetornya tidak membalas.
 */
class WhatsAppLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0:string, 1:string|null}>
     */
    public static function nomor(): array
    {
        return [
            'berawalan nol' => ['081200000001', '6281200000001'],
            'dengan spasi dan tanda hubung' => ['+62 812-0000-0001', '6281200000001'],
            'sudah internasional' => ['6281200000001', '6281200000001'],
            'tanpa nol maupun kode negara' => ['81200000001', '6281200000001'],
            'luar indonesia dibiarkan' => ['+1 415 555 2671', '14155552671'],
            'kosong' => ['', null],
            'terlalu pendek untuk nomor' => ['123', null],
        ];
    }

    #[DataProvider('nomor')]
    public function test_it_translates_a_stored_number_into_what_whatsapp_accepts(string $tersimpan, ?string $harap): void
    {
        $user = new User(['phone' => $tersimpan]);

        $this->assertSame($harap, $user->whatsappNumber());
    }

    public function test_the_task_page_links_to_a_number_whatsapp_can_find(): void
    {
        $mitra = Partner::factory()->create();
        OilPrice::factory()->create();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($mitra->id);

        $penyetor = User::factory()->create(['role' => 'user', 'phone' => '081200000002']);
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => $penyetor->id,
            'partner_id' => $mitra->id,
            'status' => Transaction::STATUS_VERIFICATION,
        ]);
        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $mitra->id,
            'assigned_user_id' => $karyawan->id,
            'status' => 'verification',
        ]);

        $halaman = $this->actingAs($karyawan)->get(route('employee.transactions.show', $transaksi))->assertOk();

        $halaman->assertSee('https://wa.me/6281200000002', false);
        $halaman->assertDontSee('https://wa.me/081200000002', false);
    }

    public function test_a_depositor_without_a_number_gets_no_broken_link(): void
    {
        $mitra = Partner::factory()->create();
        OilPrice::factory()->create();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->attach($mitra->id);

        $penyetor = User::factory()->create(['role' => 'user', 'phone' => null]);
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => $penyetor->id,
            'partner_id' => $mitra->id,
            'status' => Transaction::STATUS_VERIFICATION,
        ]);
        Pickup::factory()->create([
            'transaction_id' => $transaksi->id,
            'partner_id' => $mitra->id,
            'assigned_user_id' => $karyawan->id,
            'status' => 'verification',
        ]);

        $this->actingAs($karyawan)
            ->get(route('employee.transactions.show', $transaksi))
            ->assertOk()
            ->assertDontSee('wa.me', false)
            ->assertSee('Nomor WhatsApp belum diisi');
    }
}

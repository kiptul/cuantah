<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Daftar "Baru saya selesaikan" di dasbor karyawan.
 *
 * Daftar itu diurutkan dan diberi keterangan waktu memakai updated_at, yaitu
 * waktu tulis terakhir. Kartu statistik tepat di atasnya sudah memakai
 * completed_at, sehingga keduanya dapat menyebut hal berbeda pada satu layar:
 * baris berbunyi "2 menit yang lalu" sementara "Selesai hari ini" tetap nol.
 */
class EmployeeRecentCompletionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        /**
         * Dilewati hanya bila test memang akan memakai sqlite. Suite ini tidak
         * bergantung pada satu driver, jadi mengikat seluruh berkas pada satu
         * extension akan membuatnya tak pernah dieksekusi di mesin yang
         * menjalankannya lewat MySQL.
         */
        $connection = $_SERVER['DB_CONNECTION'] ?? (getenv('DB_CONNECTION') ?: 'sqlite');

        if ($connection === 'sqlite' && ! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for in-memory feature tests.');
        }

        parent::setUp();
    }

    private Partner $mitra;

    private User $karyawan;

    private function siapkanKaryawan(): void
    {
        $this->mitra = Partner::factory()->create();
        $this->karyawan = User::factory()->create(['role' => 'employee']);
        $this->karyawan->partners()->sync([$this->mitra->id]);
    }

    /**
     * Setoran selesai yang pickup-nya ditugaskan ke karyawan tersebut.
     *
     * Waktu selesai dan waktu sentuh terakhir ditulis lewat query builder,
     * sebab Eloquent menimpa updated_at pada setiap penyimpanan dan justru
     * perbedaan keduanya yang ingin diuji.
     */
    private function selesai(string $nama, int $selesaiJamLalu, int $disentuhJamLalu): Transaction
    {
        $penyetor = User::factory()->create(['role' => 'user', 'name' => $nama]);

        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => $penyetor->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => 2.0,
            'total_value' => 8000,
            'payment_status' => 'paid',
        ]);

        $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'completed',
            'assigned_user_id' => $this->karyawan->id,
        ]);

        DB::table('transactions')->where('id', $transaksi->id)->update([
            'completed_at' => now()->subHours($selesaiJamLalu),
            'updated_at' => now()->subHours($disentuhJamLalu),
        ]);

        return $transaksi->refresh();
    }

    public function test_urutan_mengikuti_waktu_selesai_bukan_waktu_sentuh(): void
    {
        $this->siapkanKaryawan();

        // Selesai lebih dulu, tetapi baru saja disunting admin.
        $this->selesai('Lama Tersentuh', selesaiJamLalu: 50, disentuhJamLalu: 0);
        // Selesai paling akhir, dan sejak itu tidak disentuh lagi.
        $this->selesai('Baru Selesai', selesaiJamLalu: 1, disentuhJamLalu: 1);

        $urutan = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()
            ->viewData('recent_completions')->pluck('user.name')->all();

        $this->assertSame(
            ['Baru Selesai', 'Lama Tersentuh'],
            $urutan,
            'Menyunting transaksi lama tidak boleh melemparnya ke puncak daftar "Baru saya selesaikan".'
        );
    }

    public function test_keterangan_waktu_berasal_dari_waktu_selesai(): void
    {
        $this->siapkanKaryawan();
        $transaksi = $this->selesai('Penyetor Uji', selesaiJamLalu: 50, disentuhJamLalu: 0);

        $isi = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString(
            $transaksi->completed_at->diffForHumans(),
            $isi,
            'Baris harus menyebut kapan transaksinya selesai.'
        );
        $this->assertStringNotContainsString(
            $transaksi->updated_at->diffForHumans(),
            $isi,
            'Baris tidak boleh menyebut kapan transaksinya terakhir disentuh.'
        );
    }

    public function test_baris_tidak_membantah_kartu_selesai_hari_ini(): void
    {
        $this->siapkanKaryawan();

        // Selesai tiga hari lalu, baru saja disunting admin.
        $transaksi = $this->selesai('Penyetor Uji', selesaiJamLalu: 72, disentuhJamLalu: 0);

        $halaman = $this->actingAs($this->karyawan)->get(route('employee.dashboard'))->assertOk();

        $this->assertSame(0, $halaman->viewData('today_count'), 'Transaksi itu tidak selesai hari ini.');
        $halaman->assertDontSee($transaksi->updated_at->diffForHumans());
    }

    public function test_halaman_tetap_terbuka_bila_waktu_selesai_kosong(): void
    {
        $this->siapkanKaryawan();
        $transaksi = $this->selesai('Penyetor Uji', selesaiJamLalu: 5, disentuhJamLalu: 5);

        DB::table('transactions')->where('id', $transaksi->id)->update(['completed_at' => null]);

        $this->actingAs($this->karyawan)->get(route('employee.dashboard'))
            ->assertOk()
            ->assertSee('Penyetor Uji');
    }
}

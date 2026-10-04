<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\DashboardService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Patokan waktu untuk hitungan "bulan ini".
 *
 * Dasbor penyetor memakai created_at, dasbor karyawan memakai updated_at, dan
 * dasbor admin memakai created_at, padahal ketiganya menanyakan hal yang sama:
 * berapa yang selesai bulan ini. Bulan yang sama karenanya bisa menunjukkan
 * angka berbeda tergantung siapa yang membukanya.
 * created_at adalah waktu pengajuan, dan updated_at adalah waktu tulis
 * terakhir; yang pertama salah bila penimbangan berbeda bulan dengan
 * pengajuan, yang kedua salah bila transaksi lama tersentuh lagi.
 */
class CompletedAtAnchorTest extends TestCase
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

    /**
     * Menulis stempel waktu lewat query builder.
     *
     * Eloquent menimpa updated_at pada setiap penyimpanan, sehingga keadaan
     * yang justru ingin diuji tidak bisa dibentuk lewat model.
     */
    private function stempel(Transaction $transaksi, array $waktu): void
    {
        DB::table('transactions')->where('id', $transaksi->id)->update($waktu);
    }

    private function setoranSelesai(User $penyetor, float $liter, array $waktu): Transaction
    {
        $transaksi = Transaction::factory()->create([
            'user_id' => $penyetor->id,
            'partner_id' => Partner::factory(),
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => $liter,
            'total_value' => 10000,
            'payment_status' => 'paid',
        ]);

        $this->stempel($transaksi, $waktu);

        return $transaksi->refresh();
    }

    public function test_verifikasi_mencatat_waktu_selesai(): void
    {
        $mitra = Partner::factory()->create();
        $transaksi = Transaction::factory()->create([
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_PENDING,
        ]);

        app(TransactionService::class)->verify($transaksi, [
            'actual_liter' => 2.0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ]);

        $this->assertNotNull($transaksi->refresh()->completed_at, 'Verifikasi harus mencatat kapan transaksi selesai.');
    }

    public function test_setoran_bulan_lalu_yang_ditimbang_bulan_ini_terhitung_bulan_ini(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $this->setoranSelesai($penyetor, 4.25, [
            'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        $ringkasan = app(DashboardService::class)->userSummary($penyetor->refresh());

        $this->assertSame(
            4.25,
            $ringkasan['month_liter'],
            'Patokan created_at akan membuang setoran ini ke bulan lalu, padahal jelantahnya ditimbang bulan ini.'
        );
    }

    public function test_sentuhan_di_bulan_berikutnya_tidak_memindahkan_setoran_lama(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $bulanLalu = now()->subMonthNoOverflow()->startOfMonth()->addDays(5);

        $this->setoranSelesai($penyetor, 9.5, [
            'created_at' => $bulanLalu,
            'completed_at' => $bulanLalu,
            // Sanggahan diselesaikan bulan ini: updated_at ikut bergeser.
            'updated_at' => now(),
        ]);

        $ringkasan = app(DashboardService::class)->userSummary($penyetor->refresh());

        $this->assertSame(
            0.0,
            $ringkasan['month_liter'],
            'Patokan updated_at akan menarik setoran bulan lalu ke bulan ini hanya karena tersentuh lagi.'
        );
    }

    public function test_dasbor_karyawan_memakai_patokan_yang_sama(): void
    {
        $mitra = Partner::factory()->create();
        $karyawan = User::factory()->create(['role' => 'employee']);
        $karyawan->partners()->sync([$mitra->id]);
        $penyetor = User::factory()->create(['role' => 'user']);

        $bulanLalu = now()->subMonthNoOverflow()->startOfMonth()->addDays(5);

        // Kontrol positif: tanpa ini, test bisa lulus hanya karena kedua
        // transaksi tersaring keluar sebelum tanggalnya sempat diperiksa.
        $this->tugasKaryawan($penyetor, $karyawan, $mitra, 6.0, [
            'created_at' => now(),
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tugasKaryawan($penyetor, $karyawan, $mitra, 11.0, [
            'created_at' => $bulanLalu,
            'completed_at' => $bulanLalu,
            // Sanggahan diselesaikan bulan ini: updated_at ikut bergeser.
            'updated_at' => now(),
        ]);

        $ringkasan = app(DashboardService::class)->employeeSummary($karyawan->refresh());

        $this->assertSame(
            6.0,
            $ringkasan['month_liter'],
            'Dasbor karyawan harus sepakat dengan dasbor penyetor soal setoran itu milik bulan mana.'
        );
    }

    /**
     * Setoran selesai yang pickup-nya ditugaskan ke karyawan tertentu.
     *
     * Ringkasan karyawan menyaring lewat penugasan pickup, jadi transaksi
     * tanpa record pickup tidak pernah sampai ke pemeriksaan tanggal.
     */
    private function tugasKaryawan(User $penyetor, User $karyawan, Partner $mitra, float $liter, array $waktu): Transaction
    {
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => $penyetor->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => $liter,
            'total_value' => 10000,
            'payment_status' => 'paid',
        ]);

        $transaksi->pickup()->create([
            'partner_id' => $mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'completed',
            'assigned_user_id' => $karyawan->id,
        ]);

        $this->stempel($transaksi, $waktu);

        return $transaksi->refresh();
    }

    /**
     * Transaksi selesai milik satu mitra, dengan stempel waktu yang ditentukan.
     */
    private function setoranMitra(Partner $mitra, float $liter, array $waktu): Transaction
    {
        $transaksi = Transaction::factory()->create([
            'user_id' => User::factory()->create(['role' => 'user'])->id,
            'partner_id' => $mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => $liter,
            'total_value' => 10000,
            'payment_status' => 'paid',
        ]);

        $this->stempel($transaksi, $waktu);

        return $transaksi->refresh();
    }

    private function adminMitra(Partner $mitra): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->partners()->sync([$mitra->id]);

        return $admin;
    }

    public function test_dasbor_admin_memakai_patokan_yang_sama(): void
    {
        $mitra = Partner::factory()->create();

        $this->setoranMitra($mitra, 4.25, [
            'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
            /**
             * Beberapa detik lalu, bukan tepat now(). Batas atas periodenya
             * eksklusif, dan MySQL menyimpan datetime dalam satuan detik,
             * sehingga stempel yang jatuh pada detik yang sama dengan now()
             * tertolak di batas dan membuat test ini gagal karena alasan yang
             * tidak ada hubungannya dengan patokan waktunya.
             */
            'completed_at' => now()->subSeconds(5)->max(now()->startOfMonth()),
            'updated_at' => now(),
        ]);

        $data = $this->actingAs($this->adminMitra($mitra))->app
            ->make(AdminDashboardService::class)
            ->summary();

        $this->assertSame(
            4.25,
            $data['kpis']['liter']['current'],
            'Patokan created_at akan membuang setoran ini ke bulan lalu, padahal jelantahnya ditimbang bulan ini.'
        );
        $this->assertSame(0.0, $data['kpis']['liter']['previous']);
    }

    public function test_grafik_bulanan_admin_memakai_patokan_yang_sama(): void
    {
        $mitra = Partner::factory()->create();

        $this->setoranMitra($mitra, 7.5, [
            'created_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(2),
            /**
             * Beberapa detik lalu, bukan tepat now(). Batas atas periodenya
             * eksklusif, dan MySQL menyimpan datetime dalam satuan detik,
             * sehingga stempel yang jatuh pada detik yang sama dengan now()
             * tertolak di batas dan membuat test ini gagal karena alasan yang
             * tidak ada hubungannya dengan patokan waktunya.
             */
            'completed_at' => now()->subSeconds(5)->max(now()->startOfMonth()),
            'updated_at' => now(),
        ]);

        $data = $this->actingAs($this->adminMitra($mitra))->app
            ->make(AdminDashboardService::class)
            ->summary();

        $bulanIni = collect($data['monthly'])->firstWhere('month', now()->format('Y-m'));
        $bulanLalu = collect($data['monthly'])->firstWhere('month', now()->subMonthNoOverflow()->format('Y-m'));

        /**
         * Batang grafik dan angka kartu KPI harus bercerita hal yang sama.
         * Keduanya dulu memakai created_at, jadi keliru bersama-sama; kekeliruan
         * yang seragam justru paling sulit disadari.
         */
        $this->assertSame(7.5, $bulanIni['volume']);
        $this->assertSame(0.0, $bulanLalu['volume']);
    }

    public function test_setoran_lama_yang_baru_selesai_tetap_masuk_grafik(): void
    {
        $mitra = Partner::factory()->create();

        /**
         * Jendela grafiknya dua belas bulan ke belakang. Dengan saringan
         * created_at, setoran yang diajukan lebih dari setahun lalu dan baru
         * ditimbang bulan ini tersaring habis sebelum sempat dikelompokkan,
         * sehingga volumenya hilang sama sekali, bukan sekadar salah bulan.
         */
        $this->setoranMitra($mitra, 3.5, [
            'created_at' => now()->subMonthsNoOverflow(13),
            'completed_at' => now()->subSeconds(5)->max(now()->startOfMonth()),
            'updated_at' => now(),
        ]);

        $data = $this->actingAs($this->adminMitra($mitra))->app
            ->make(AdminDashboardService::class)
            ->summary();

        $bulanIni = collect($data['monthly'])->firstWhere('month', now()->format('Y-m'));

        $this->assertSame(3.5, $bulanIni['volume']);
    }

    public function test_riwayat_lama_terisi_mundur_saat_migrasi(): void
    {
        $penyetor = User::factory()->create(['role' => 'user']);

        $transaksi = $this->setoranSelesai($penyetor, 3.0, [
            'created_at' => now()->subDays(10),
            'completed_at' => null,
            'updated_at' => now()->subDays(8),
        ]);

        DB::table('transactions')
            ->where('status', 'completed')
            ->whereNull('completed_at')
            ->update(['completed_at' => DB::raw('updated_at')]);

        $this->assertNotNull(
            $transaksi->refresh()->completed_at,
            'Pengisian mundur harus memakai updated_at, sebab membiarkannya kosong membuang seluruh riwayat dari hitungan bulanan.'
        );
    }
}

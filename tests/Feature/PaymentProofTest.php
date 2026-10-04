<?php

namespace Tests\Feature;

use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bukti pembayaran berupa foto.
 *
 * Sebelumnya uang berpindah tanpa jejak apa pun selain angka yang diketik
 * sendiri oleh pihak mitra. Penyetor yang merasa dibayar kurang hanya bisa
 * mengandalkan ingatan, dan mitra yang dituduh tidak membayar tidak punya
 * apa-apa untuk membantahnya.
 *
 * Uang berpindah di tiga tempat, dan ketiganya diuji di sini. Yang paling
 * mudah terlewat adalah penandaan lunas menyusul: ia satu-satunya jalan bagi
 * pembayaran yang ditunda, sehingga seluruh pembayaran transfer lewat sana.
 */
class PaymentProofTest extends TestCase
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

        Storage::fake('local');
    }

    private Partner $mitra;

    private User $karyawan;

    private User $admin;

    private User $penyetor;

    private function siapkan(): void
    {
        $this->mitra = Partner::factory()->create();

        $this->karyawan = User::factory()->create(['role' => 'employee']);
        $this->karyawan->partners()->sync([$this->mitra->id]);

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->admin->partners()->sync([$this->mitra->id]);

        $this->penyetor = User::factory()->create(['role' => 'user']);
    }

    /**
     * Penjemputan yang sudah ditugaskan kepada karyawan dan siap diverifikasi.
     */
    /**
     * Setoran yang diantar sendiri ke lokasi mitra.
     *
     * Dipakai test yang menguji jalur admin. Transaksi jemput ditutup karyawan
     * di lapangan, sehingga rute verifikasi admin menolaknya dengan 403 dan
     * aturan buktinya tidak akan pernah terbaca.
     */
    private function setoranAntarSendiri(): Transaction
    {
        return Transaction::factory()->create([
            'user_id' => $this->penyetor->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'method' => Transaction::METHOD_DROP_OFF,
            'status' => Transaction::STATUS_SCHEDULED,
        ]);
    }

    private function tugas(): Transaction
    {
        $transaksi = Transaction::factory()->pickup()->create([
            'user_id' => $this->penyetor->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_SCHEDULED,
        ]);

        $transaksi->pickup()->create([
            'partner_id' => $this->mitra->id,
            'address' => 'Jl. Uji',
            'latitude' => -6.3,
            'longitude' => 107.3,
            'status' => 'assigned',
            'assigned_user_id' => $this->karyawan->id,
        ]);

        return $transaksi;
    }

    /**
     * Transaksi selesai yang pembayarannya ditunda, yaitu satu-satunya
     * keadaan yang bisa ditandai lunas menyusul.
     */
    private function selesaiBelumDibayar(): Transaction
    {
        return Transaction::factory()->create([
            'user_id' => $this->penyetor->id,
            'partner_id' => $this->mitra->id,
            'oil_price_id' => OilPrice::factory(),
            'status' => Transaction::STATUS_COMPLETED,
            'actual_liter' => 5,
            'total_value' => 20000,
            'payment_method' => 'transfer',
            'payment_status' => 'unpaid',
            'completed_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $ganti
     * @return array<string, mixed>
     */
    private function isianVerifikasi(array $ganti = []): array
    {
        return array_merge([
            'actual_liter' => 5,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ], $ganti);
    }

    public function test_karyawan_tidak_bisa_menyelesaikan_pembayaran_tunai_tanpa_bukti(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->karyawan)
            ->post(route('employee.transactions.verify', $transaksi), $this->isianVerifikasi())
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(
            Transaction::STATUS_SCHEDULED,
            $transaksi->refresh()->status,
            'Transaksi tidak boleh ikut selesai ketika buktinya ditolak.'
        );
    }

    public function test_karyawan_menyelesaikan_pembayaran_tunai_dengan_bukti(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        $this->actingAs($this->karyawan)
            ->post(route('employee.transactions.verify', $transaksi), $this->isianVerifikasi([
                /**
                 * Dibuat lewat create() dengan tipe MIME, bukan image(), sebab
                 * image() menggambar raster sungguhan dan menuntut ekstensi GD.
                 * Aplikasi ini tidak pernah mendekode gambar: berkasnya disimpan
                 * apa adanya, dan ketiga aturannya, image, mimes, dan max, bekerja
                 * pada tipe MIME serta ukuran. Menuntut GD hanya membuat seluruh
                 * test bukti bayar galat di mesin yang tidak memasangnya.
                 */
                'payment_proof' => UploadedFile::fake()->create('serah-terima.jpg', 120, 'image/jpeg'),
            ]))
            ->assertSessionHasNoErrors();

        $transaksi->refresh();

        $this->assertSame(Transaction::STATUS_COMPLETED, $transaksi->status);
        $this->assertNotNull($transaksi->payment_proof_path);
        Storage::disk('local')->assertExists($transaksi->payment_proof_path);
    }

    public function test_pembayaran_yang_ditunda_belum_perlu_bukti(): void
    {
        $this->siapkan();
        $transaksi = $this->tugas();

        /**
         * Belum ada uang yang berpindah, jadi meminta buktinya berarti
         * menyuruh karyawan memotret sesuatu yang belum terjadi.
         */
        $this->actingAs($this->karyawan)
            ->post(route('employee.transactions.verify', $transaksi), $this->isianVerifikasi([
                'payment_status' => 'unpaid',
            ]))
            ->assertSessionHasNoErrors();

        $transaksi->refresh();

        $this->assertSame(Transaction::STATUS_COMPLETED, $transaksi->status);
        $this->assertNull($transaksi->payment_proof_path);
    }

    public function test_admin_tidak_bisa_memverifikasi_pembayaran_lunas_tanpa_bukti(): void
    {
        $this->siapkan();
        $transaksi = $this->setoranAntarSendiri();

        $this->actingAs($this->admin)
            ->post(route('admin.transactions.verify', $transaksi), $this->isianVerifikasi())
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(Transaction::STATUS_SCHEDULED, $transaksi->refresh()->status);
    }

    public function test_penandaan_lunas_menyusul_wajib_disertai_bukti(): void
    {
        $this->siapkan();
        $transaksi = $this->selesaiBelumDibayar();

        $this->actingAs($this->admin)
            ->post(route('admin.transactions.mark-paid', $transaksi))
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame(
            'unpaid',
            $transaksi->refresh()->payment_status,
            'Seluruh pembayaran transfer lewat pintu ini, jadi pintu inilah yang paling perlu dijaga.'
        );
    }

    public function test_penandaan_lunas_menyusul_dengan_bukti_berhasil(): void
    {
        $this->siapkan();
        $transaksi = $this->selesaiBelumDibayar();

        $this->actingAs($this->admin)
            ->post(route('admin.transactions.mark-paid', $transaksi), [
                'payment_proof' => UploadedFile::fake()->create('transfer.png', 120, 'image/png'),
            ])
            ->assertSessionHas('success');

        $transaksi->refresh();

        $this->assertSame('paid', $transaksi->payment_status);
        $this->assertNotNull($transaksi->payment_proof_path);
        Storage::disk('local')->assertExists($transaksi->payment_proof_path);
    }

    public function test_berkas_yang_bukan_gambar_ditolak(): void
    {
        $this->siapkan();
        $transaksi = $this->selesaiBelumDibayar();

        /**
         * Diberi akhiran .jpg tetapi isinya bukan gambar. Aturan mimes membaca
         * isi berkas, bukan nama yang ditentukan pengunggah, sehingga berkas
         * yang disamarkan tetap tertolak.
         */
        $this->actingAs($this->admin)
            ->post(route('admin.transactions.mark-paid', $transaksi), [
                'payment_proof' => UploadedFile::fake()->create('transfer.jpg', 10, 'application/x-httpd-php'),
            ])
            ->assertSessionHasErrors('payment_proof');

        $this->assertSame('unpaid', $transaksi->refresh()->payment_status);
    }

    public function test_berkas_terlalu_besar_ditolak(): void
    {
        $this->siapkan();
        $transaksi = $this->selesaiBelumDibayar();

        $this->actingAs($this->admin)
            ->post(route('admin.transactions.mark-paid', $transaksi), [
                'payment_proof' => UploadedFile::fake()->create('transfer.jpg', 5121, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('payment_proof');
    }

    private function transaksiDenganBukti(): Transaction
    {
        $transaksi = $this->selesaiBelumDibayar();

        $this->actingAs($this->admin)->post(route('admin.transactions.mark-paid', $transaksi), [
            'payment_proof' => UploadedFile::fake()->create('transfer.jpg', 120, 'image/jpeg'),
        ]);

        return $transaksi->refresh();
    }

    public function test_penyetor_pemilik_bisa_membuka_buktinya(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksiDenganBukti();

        $this->actingAs($this->penyetor)
            ->get(route('transactions.payment-proof', $transaksi))
            ->assertOk();
    }

    public function test_karyawan_mitra_lain_tidak_bisa_membuka_buktinya(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksiDenganBukti();

        $karyawanMitraLain = User::factory()->create(['role' => 'employee']);
        $karyawanMitraLain->partners()->sync([Partner::factory()->create()->id]);

        /**
         * Berkasnya tidak pernah berada di bawah public/, jadi rute inilah
         * satu-satunya jalan masuk, dan di sinilah batas mitra ditegakkan.
         */
        $this->actingAs($karyawanMitraLain)
            ->get(route('transactions.payment-proof', $transaksi))
            ->assertForbidden();
    }

    public function test_penyetor_lain_tidak_bisa_membuka_buktinya(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksiDenganBukti();

        $this->actingAs(User::factory()->create(['role' => 'user']))
            ->get(route('transactions.payment-proof', $transaksi))
            ->assertForbidden();
    }

    public function test_transaksi_lama_tanpa_bukti_menjawab_404_bukan_galat(): void
    {
        $this->siapkan();

        /**
         * Enam transaksi sudah selesai dan lunas sebelum kolom ini ada.
         * Keadaan itu sah, bukan kerusakan, jadi jawabannya tidak ditemukan.
         */
        $this->actingAs($this->penyetor)
            ->get(route('transactions.payment-proof', $this->selesaiBelumDibayar()))
            ->assertNotFound();
    }

    public function test_halaman_transaksi_penyetor_menampilkan_buktinya(): void
    {
        $this->siapkan();
        $transaksi = $this->transaksiDenganBukti();

        $this->actingAs($this->penyetor)
            ->get(route('transactions.show', $transaksi))
            ->assertOk()
            ->assertSee('Bukti pembayaran')
            ->assertSee(route('transactions.payment-proof', $transaksi));
    }

    public function test_transaksi_lama_dinyatakan_terus_terang_bukan_dibiarkan_kosong(): void
    {
        $this->siapkan();

        $transaksi = $this->selesaiBelumDibayar();
        $transaksi->update(['payment_status' => 'paid', 'paid_at' => now()]);

        $this->actingAs($this->penyetor)
            ->get(route('transactions.show', $transaksi))
            ->assertOk()
            ->assertSee('selesai sebelum bukti pembayaran mulai dilampirkan');
    }
}

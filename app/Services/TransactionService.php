<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function createDeposit(User $user, array $data): Transaction
    {
        $price = OilPrice::current();
        abort_if($price === null, 422, 'Harga jelantah aktif belum tersedia.');

        return DB::transaction(function () use ($user, $data, $price) {
            $estimatedLiter = (float) $data['estimated_liter'];
            $partner = Partner::with('deliveryFees')
                ->whereKey($data['partner_id'])
                ->where('status', 'active')
                ->firstOrFail();
            abort_if($partner->latitude === null || $partner->longitude === null, 422, 'Lokasi mitra belum tersedia.');

            // Yang diperiksa adalah sisa daya tampung, bukan sekadar apakah
            // mitranya sudah penuh. Pemeriksaan sebelumnya hanya menolak setoran
            // yang datang ketika mitra sudah terisi penuh, sehingga mitra yang
            // masih bersisa satu liter tetap menerima setoran empat ratus liter
            // dan penyetor menunggu untuk sesuatu yang tidak muat.
            $sisaLiter = round((float) $partner->capacity_liter - $partner->availableLiter(), 2);

            if ($sisaLiter <= 0) {
                // Mitra penuh bukan soal besarnya setoran, jadi galatnya menempel
                // pada pilihan mitra: yang perlu diganti mitranya, bukan angkanya.
                throw ValidationException::withMessages([
                    'partner_id' => sprintf(
                        'Mitra %s sedang penuh, kapasitasnya %s L dan belum ada penyaluran keluar. Pilih mitra lain dulu.',
                        $partner->name,
                        number_format((float) $partner->capacity_liter, 0, ',', '.'),
                    ),
                ]);
            }

            if ($estimatedLiter > $sisaLiter) {
                // Angkanya disebutkan supaya penyetor tahu harus turun ke berapa,
                // bukan menebak-nebak sampai setorannya diterima.
                throw ValidationException::withMessages([
                    'estimated_liter' => sprintf(
                        'Mitra %s hanya sanggup menerima %s L lagi, sedangkan kamu mengajukan %s L. Turunkan volumenya atau pilih mitra lain.',
                        $partner->name,
                        $this->angkaRapi($sisaLiter),
                        $this->angkaRapi($estimatedLiter),
                    ),
                ]);
            }

            $isPickup = $data['method'] === Transaction::METHOD_PICKUP;
            $latitude = $isPickup ? (float) $data['latitude'] : (float) $partner->latitude;
            $longitude = $isPickup ? (float) $data['longitude'] : (float) $partner->longitude;
            $address = $isPickup ? $data['address'] : $partner->address;
            $pickupFee = $isPickup
                ? $partner->deliveryFeeForDistance($this->distanceKm((float) $partner->latitude, (float) $partner->longitude, $latitude, $longitude))
                : 0;
            $grossTotal = (int) round($estimatedLiter * $price->price_per_liter);

            // Ongkir dipotong dari penerimaan user. Tanpa penjagaan ini setoran
            // jemput bervolume kecil bisa lolos dengan nominal nol: user sudah
            // menunggu, karyawan sudah berangkat, lalu tidak ada yang diterima.
            if ($isPickup && $grossTotal <= $pickupFee) {
                $minimumLiter = ceil(($pickupFee + 1) / $price->price_per_liter * 2) / 2;

                throw ValidationException::withMessages([
                    'estimated_liter' => sprintf(
                        'Dengan ongkir jemput Rp%s, setoran %s liter belum menghasilkan apa pun. Perlu minimal %s liter, atau pilih antar sendiri supaya tanpa ongkir.',
                        number_format($pickupFee, 0, ',', '.'),
                        rtrim(rtrim(number_format($estimatedLiter, 2, ',', '.'), '0'), ','),
                        rtrim(rtrim(number_format($minimumLiter, 2, ',', '.'), '0'), ','),
                    ),
                ]);
            }

            $transaction = Transaction::create([
                'code' => 'CNT-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'user_id' => $user->id,
                'oil_price_id' => $price->id,
                'partner_id' => $partner->id,
                'estimated_liter' => $estimatedLiter,
                'price_per_liter' => $price->price_per_liter,
                'estimated_total' => max($grossTotal - $pickupFee, 0),
                'pickup_fee' => $pickupFee,
                'method' => $data['method'],
                'status' => Transaction::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            Pickup::create([
                'transaction_id' => $transaction->id,
                'partner_id' => $partner->id,
                'address' => $address,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'pickup_date' => $isPickup ? $data['pickup_date'] : null,
                'pickup_time' => $isPickup ? $data['pickup_time'] : null,
                'status' => $isPickup ? 'pending' : 'awaiting_dropoff',
            ]);

            Notification::create([
                'user_id' => $user->id,
                'title' => 'Pengajuan setor diterima',
                'message' => 'Transaksi '.$transaction->code.' sedang menunggu proses berikutnya.',
                'type' => 'transaction',
            ]);

            /**
             * Mitra ikut diberi tahu. Sebelumnya hanya penyetor yang menerima
             * kabar, sehingga pihak yang justru harus menindaklanjuti tidak
             * pernah tahu ada setoran masuk. Drop-off bahkan tidak muncul di
             * daftar pickup sampai QR-nya dipindai, jadi tanpa pesan ini
             * mitra tidak punya satu pun jalan untuk mengetahuinya.
             */
            $this->notifyPartnerAdmins(
                $transaction,
                'Setoran baru masuk',
                $isPickup
                    ? $user->name.' meminta penjemputan '.$transaction->code.' pada '
                        .$transaction->pickup->pickup_date->translatedFormat('d M').'. Tugaskan karyawan untuk menjemputnya.'
                    : $user->name.' akan mengantar sendiri setoran '.$transaction->code.' ke mitra.',
            );

            return $transaction->load('pickup.partner', 'partner');
        });
    }

    public function assignPickupToEmployee(Pickup $pickup, int $employeeId): Pickup
    {
        $this->pastikanBelumFinal($pickup->transaction, 'di-assign ke karyawan');

        return DB::transaction(function () use ($pickup, $employeeId) {
            $pickup->update([
                'assigned_user_id' => $employeeId,
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);

            $pickup->transaction()->update(['status' => Transaction::STATUS_SCHEDULED]);

            Notification::create([
                'user_id' => $pickup->transaction->user_id,
                'title' => 'Pickup dijadwalkan',
                'message' => 'Pickup '.$pickup->transaction->code.' sudah di-assign ke karyawan CUANTAH.',
                'type' => 'pickup',
            ]);

            /**
             * Karyawannya sendiri ikut diberi tahu. Sebelumnya tugas muncul di
             * dasbornya tanpa pemberitahuan apa pun, sehingga ia hanya tahu
             * bila kebetulan membuka halaman itu.
             */
            Notification::create([
                'user_id' => $employeeId,
                'title' => 'Pickup ditugaskan kepadamu',
                'message' => 'Jemput '.$pickup->transaction->code.' di '.$pickup->address
                    .($pickup->pickup_date ? ' pada '.$pickup->pickup_date->translatedFormat('d M') : '')
                    .($pickup->pickup_time ? ', '.substr((string) $pickup->pickup_time, 0, 5) : '').'.',
                'type' => 'pickup',
            ]);

            return $pickup->refresh()->load('assignedUser', 'partner', 'transaction.user');
        });
    }

    public function unassignPickup(Pickup $pickup): Pickup
    {
        $this->pastikanBelumFinal($pickup->transaction, 'dilepas dari karyawan');

        return DB::transaction(function () use ($pickup) {
            $pickup->update([
                'assigned_user_id' => null,
                'status' => $pickup->transaction->method === Transaction::METHOD_DROP_OFF ? 'scanned' : 'pending',
                'assigned_at' => null,
            ]);

            $pickup->transaction()->update(['status' => Transaction::STATUS_PENDING]);

            return $pickup->refresh()->load('transaction.user');
        });
    }

    public function scanDropOff(string $code, User $employee): ?Pickup
    {
        return DB::transaction(function () use ($code, $employee) {
            $transaction = Transaction::query()
                ->where('code', $code)
                ->where('method', Transaction::METHOD_DROP_OFF)
                ->whereNotIn('status', Transaction::FINAL_STATUSES)
                ->visibleTo($employee)
                ->with('pickup')
                ->lockForUpdate()
                ->first();

            if (! $transaction || ! $transaction->pickup) {
                return null;
            }

            if ($transaction->pickup->assigned_user_id && $transaction->pickup->assigned_user_id !== $employee->id) {
                return null;
            }

            $transaction->pickup->update([
                'scanned_at' => now(),
                'assigned_user_id' => $transaction->pickup->assigned_user_id ?: $employee->id,
                'status' => 'assigned',
                'assigned_at' => $transaction->pickup->assigned_at ?: now(),
            ]);

            $transaction->update(['status' => Transaction::STATUS_SCHEDULED]);

            return $transaction->pickup->refresh()->load('transaction.user', 'assignedUser');
        });
    }

    /**
     * Menutup transaksi dengan hasil takaran dan pembayarannya.
     *
     * Satu form ini sekaligus menjadi akhir transaksi. Dulu karyawan harus
     * menekan "Dijemput" lalu "Verifikasi" lebih dulu, dan penyetor masih
     * diminta membenarkan pembayarannya, padahal semua itu terjadi di tempat
     * yang sama pada saat yang sama.
     *
     * @param  array{actual_liter: float|string, payment_method: string, payment_status: string, notes?: string|null}  $data
     */
    public function verify(Transaction $transaction, array $data): Transaction
    {
        $this->pastikanBelumFinal($transaction, 'diverifikasi');

        $buktiPath = ($data['payment_proof'] ?? null) instanceof UploadedFile
            ? $this->simpanBuktiBayar($data['payment_proof'], $transaction)
            : null;

        return DB::transaction(function () use ($transaction, $data, $buktiPath) {
            $actualLiter = (float) $data['actual_liter'];
            $total = max((int) round($actualLiter * $transaction->price_per_liter) - (int) $transaction->pickup_fee, 0);

            $transaction->update([
                'actual_liter' => $actualLiter,
                'total_value' => $total,
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_status'],
                'paid_at' => $data['payment_status'] === 'paid' ? now() : null,
                'payment_proof_path' => $buktiPath ?? $transaction->payment_proof_path,
                'status' => Transaction::STATUS_COMPLETED,
                'completed_at' => now(),
                'notes' => $data['notes'] ?? $transaction->notes,
            ]);

            $transaction->pickup?->update(['status' => 'completed']);

            Notification::create([
                'user_id' => $transaction->user_id,
                'title' => 'Transaksi selesai',
                'message' => sprintf(
                    'Transaksi %s selesai: %s L, total Rp%s%s.',
                    $transaction->code,
                    number_format($actualLiter, 2, ',', '.'),
                    number_format($total, 0, ',', '.'),
                    $data['payment_status'] === 'paid' ? ' sudah dibayar' : ' akan dibayarkan menyusul',
                ),
                'type' => 'payment',
            ]);

            return $transaction->refresh()->load('user', 'pickup.partner');
        });
    }

    /**
     * Mencatat keberatan penyetor atas volume hasil takaran.
     *
     * Admin mitra ikut diberi tahu. Sebelumnya keberatan hanya tercatat di
     * transaksinya, sehingga admin baru tahu bila kebetulan membuka detailnya.
     */
    public function dispute(Transaction $transaction, string $reason): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason) {
            $transaction->update([
                'disputed_at' => now(),
                'dispute_reason' => $reason,
            ]);

            Notification::create([
                'user_id' => $transaction->user_id,
                'title' => 'Keberatan terkirim',
                'message' => 'Keberatanmu atas takaran transaksi '.$transaction->code.' sudah diteruskan ke mitra.',
                'type' => 'transaction',
            ]);

            $this->notifyPartnerAdmins(
                $transaction,
                'Keberatan takaran baru',
                'Penyetor menyanggah takaran transaksi '.$transaction->code.'. Mohon ditanggapi.',
            );

            return $transaction->refresh();
        });
    }

    /**
     * Staff mitra menutup sanggahan dengan keterangan penyelesaiannya.
     */
    public function resolveDispute(Transaction $transaction, string $resolution): Transaction
    {
        return DB::transaction(function () use ($transaction, $resolution) {
            $transaction->update([
                'dispute_resolved_at' => now(),
                'dispute_resolution' => $resolution,
            ]);

            Notification::create([
                'user_id' => $transaction->user_id,
                'title' => 'Keberatan ditanggapi',
                'message' => 'Mitra menanggapi keberatanmu pada '.$transaction->code.': '.$resolution,
                'type' => 'transaction',
            ]);

            return $transaction->refresh();
        });
    }

    /**
     * Pembatalan oleh penyetor sendiri selama belum ada yang mengerjakannya.
     */
    public function cancel(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            $transaction->update(['status' => Transaction::STATUS_CANCELLED]);
            $transaction->pickup?->update(['status' => 'cancelled']);

            Notification::create([
                'user_id' => $transaction->user_id,
                'title' => 'Setoran dibatalkan',
                'message' => 'Kamu membatalkan transaksi '.$transaction->code.'.',
                'type' => 'transaction',
            ]);

            /**
             * Karyawan yang sudah ditugaskan ikut diberi tahu. Tanpa pesan ini
             * ia dapat berkendara ke alamat yang setorannya sudah dibatalkan,
             * sebab tugasnya hilang dari dasbor tanpa meninggalkan jejak.
             */
            if ($transaction->pickup?->assigned_user_id) {
                Notification::create([
                    'user_id' => $transaction->pickup->assigned_user_id,
                    'title' => 'Penjemputan dibatalkan',
                    'message' => 'Penyetor membatalkan '.$transaction->code.'. Tidak perlu berangkat ke '.$transaction->pickup->address.'.',
                    'type' => 'pickup',
                ]);
            }

            return $transaction->refresh();
        });
    }

    /**
     * Menolak transaksi disertai alasannya.
     *
     * Sebelumnya penyetor hanya menerima pesan generik, sehingga ia tidak
     * pernah tahu apa yang perlu diperbaiki pada setoran berikutnya.
     */
    public function reject(Transaction $transaction, ?string $reason = null, ?User $penolak = null): Transaction
    {
        $this->pastikanBelumFinal($transaction, 'ditolak');

        return DB::transaction(function () use ($transaction, $reason, $penolak) {
            $transaction->update([
                'status' => Transaction::STATUS_REJECTED,
                'rejection_reason' => $reason,
            ]);
            $transaction->pickup?->update(['status' => 'rejected']);

            Notification::create([
                'user_id' => $transaction->user_id,
                'title' => 'Transaksi ditolak',
                'message' => $reason
                    ? 'Transaksi '.$transaction->code.' ditolak. Alasan: '.$reason
                    : 'Transaksi '.$transaction->code.' tidak dapat diproses.',
                'type' => 'transaction',
            ]);

            /**
             * Penolakan di lapangan ikut dikabarkan ke admin mitra. Merekalah
             * yang perlu tahu ada penjemputan yang gagal tanpa harus memeriksa
             * daftar sendiri. Penolakan oleh admin tidak mengirim kabar ini,
             * sebab mengabari seseorang tentang tindakannya sendiri hanya
             * menambah kebisingan.
             */
            if ($penolak !== null) {
                $this->notifyPartnerAdmins(
                    $transaction,
                    'Setoran ditolak di lapangan',
                    $penolak->name.' menolak '.$transaction->code.'.'.($reason ? ' Alasan: '.$reason : ''),
                );
            }

            return $transaction->refresh();
        });
    }

    /**
     * Melunasi transaksi selesai yang saat ditutup dicatat belum dibayar.
     *
     * Sebelumnya status "belum dibayar" tidak punya jalan keluar: transaksi
     * selesai tidak bisa diubah lagi, sehingga utang ke penyetor tercatat
     * selamanya meski uangnya sudah diserahkan.
     *
     * @throws ValidationException
     */
    public function markPaid(Transaction $transaction, UploadedFile $bukti): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_COMPLETED || $transaction->payment_status !== 'unpaid') {
            throw ValidationException::withMessages([
                'payment_status' => 'Hanya transaksi selesai yang belum dibayar yang bisa ditandai lunas.',
            ]);
        }

        $buktiPath = $this->simpanBuktiBayar($bukti, $transaction);

        return DB::transaction(function () use ($transaction, $buktiPath) {
            $transaction->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
                'payment_proof_path' => $buktiPath,
            ]);

            Notification::create([
                'user_id' => $transaction->user_id,
                'title' => 'Pembayaran diterima',
                'message' => 'Pembayaran transaksi '.$transaction->code.' sebesar Rp'.number_format((int) $transaction->total_value, 0, ',', '.').' sudah dilunasi.',
                'type' => 'payment',
            ]);

            return $transaction->refresh();
        });
    }

    /**
     * Mengirim notifikasi ke seluruh admin yang terhubung dengan mitra transaksi.
     */
    /**
     * Menyimpan bukti pembayaran dan mengembalikan jalur berkasnya.
     *
     * Disimpan di disk privat, bukan di public/storage. Foto ini memuat
     * nominal uang dan kerap memuat wajah orang, sehingga tautan yang bisa
     * ditebak sudah cukup untuk membocorkannya tanpa perlu masuk akun.
     * Penyajiannya lewat rute yang memeriksa TransactionPolicy.
     *
     * Nama berkas dibuat oleh Laravel, tidak memakai nama asli dari
     * pengunggah, supaya nama yang disusun untuk menyesatkan tidak ikut
     * tersimpan.
     *
     * Berkas ditulis sebelum transaksi basis data dibuka. Bila basis data
     * gagal sesudahnya, yang tertinggal hanyalah berkas yang tidak ditunjuk
     * siapa pun; sebaliknya, menulis berkas di dalam transaksi tidak membuat
     * penulisannya ikut dibatalkan, sebab disk tidak mengenal rollback.
     */
    /**
     * Mengoreksi volume transaksi yang sudah selesai.
     *
     * Karyawan menakar di lapangan dan mengetik angkanya di ponsel, jadi
     * salah ketik tidak terhindarkan. Sampai sekarang tidak ada jalan
     * membetulkannya: transaksi selesai terkunci, dan penyelesaian sanggahan
     * hanya menulis tanggapan tanpa menyentuh angkanya.
     *
     * Harga diambil dari price_per_liter yang tersimpan di transaksi, bukan
     * harga hari ini. Mengoreksi salah ketik bulan lalu dengan harga bulan
     * ini akan mengubah dua hal sekaligus, dan yang kedua tidak diminta
     * siapa pun.
     */
    public function correctVolume(Transaction $transaction, float $liter, string $reason, ?User $admin = null): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'actual_liter' => 'Hanya transaksi selesai yang bisa dikoreksi.',
            ]);
        }

        $literSebelum = (float) $transaction->actual_liter;
        $nilaiSebelum = (int) $transaction->total_value;
        $nilaiSesudah = max((int) round($liter * $transaction->price_per_liter) - (int) $transaction->pickup_fee, 0);

        $this->pastikanStokMitraTidakMinus($transaction, $literSebelum, $liter);

        /**
         * Status bayar hanya dikembalikan bila nilainya naik. Pada koreksi
         * turun, uangnya sudah terlanjur keluar dan malah kelebihan;
         * menandainya belum dibayar berarti mencatat sesuatu yang tidak
         * terjadi, sedangkan aplikasi ini tidak punya alur pengembalian dana.
         */
        $kurangBayar = $transaction->payment_status === 'paid' && $nilaiSesudah > $nilaiSebelum;

        return DB::transaction(function () use ($transaction, $liter, $reason, $admin, $literSebelum, $nilaiSebelum, $nilaiSesudah, $kurangBayar) {
            $transaction->corrections()->create([
                'corrected_by' => $admin?->id,
                'liter_before' => $literSebelum,
                'liter_after' => $liter,
                'value_before' => $nilaiSebelum,
                'value_after' => $nilaiSesudah,
                'payment_status_before' => $transaction->payment_status,
                'payment_proof_path_before' => $transaction->payment_proof_path,
                'reason' => $reason,
            ]);

            $transaction->update([
                'actual_liter' => $liter,
                'total_value' => $nilaiSesudah,
                'payment_status' => $kurangBayar ? 'unpaid' : $transaction->payment_status,
                'paid_at' => $kurangBayar ? null : $transaction->paid_at,
            ]);

            Notification::create([
                'user_id' => $transaction->user_id,
                'title' => 'Volume transaksi dikoreksi',
                'message' => sprintf(
                    'Volume %s diperbaiki dari %s L menjadi %s L, sehingga nilainya menjadi Rp%s. Alasan: %s',
                    $transaction->code,
                    $this->angkaRapi($literSebelum),
                    $this->angkaRapi($liter),
                    number_format($nilaiSesudah, 0, ',', '.'),
                    $reason,
                ),
                'type' => 'transaction',
            ]);

            return $transaction->refresh();
        });
    }

    /**
     * Menahan koreksi yang membuat jelantah tersalur melebihi yang terkumpul.
     *
     * Stok mitra adalah selisih terkumpul dan tersalur. Koreksi turun pada
     * setoran yang jelantahnya sudah terlanjur disalurkan akan membuat
     * selisih itu minus, dan laporan rantai pasoknya berhenti berarti.
     */
    private function pastikanStokMitraTidakMinus(Transaction $transaction, float $literSebelum, float $literSesudah): void
    {
        if ($literSesudah >= $literSebelum || $transaction->partner === null) {
            return;
        }

        $sisa = $transaction->partner->availableLiter();
        $pengurangan = $literSebelum - $literSesudah;

        if ($pengurangan > $sisa + 0.001) {
            throw ValidationException::withMessages([
                'actual_liter' => sprintf(
                    'Koreksi ini mengurangi %s L sedangkan sisa jelantah mitra tinggal %s L, jadi yang sudah disalurkan akan melebihi yang pernah masuk.',
                    $this->angkaRapi($pengurangan),
                    $this->angkaRapi($sisa),
                ),
            ]);
        }
    }

    private function simpanBuktiBayar(UploadedFile $bukti, Transaction $transaction): string
    {
        return $bukti->store('bukti-bayar/'.$transaction->getKey(), 'local');
    }

    private function notifyPartnerAdmins(Transaction $transaction, string $title, string $message): void
    {
        User::query()
            ->where('role', 'admin')
            ->whereHas('partners', fn ($partner) => $partner->whereKey($transaction->partner_id))
            ->pluck('id')
            ->each(fn (int $adminId) => Notification::create([
                'user_id' => $adminId,
                'title' => $title,
                'message' => $message,
                'type' => 'transaction',
            ]));
    }

    /**
     * Menolak tindakan atas transaksi yang sudah mencapai status akhir.
     *
     * Tanpa ini, transaksi selesai masih bisa diverifikasi ulang atau
     * ditolak. Bila liternya sudah tercatat sebagai stok mitra dan sudah
     * ada penyaluran atasnya, stok mitra berubah menjadi negatif.
     *
     * @throws ValidationException
     */
    private function pastikanBelumFinal(Transaction $transaction, string $tindakan): void
    {
        if (! $transaction->isFinal()) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => sprintf(
                'Transaksi %s sudah %s, jadi tidak bisa %s lagi.',
                $transaction->code,
                $transaction->statusLabel(),
                $tindakan,
            ),
        ]);
    }

    /**
     * Angka liter tanpa nol di belakang yang tidak berarti.
     *
     * "100 L" lebih mudah dibaca daripada "100,00 L", tetapi "1,5 L" tetap
     * perlu desimalnya.
     */
    private function angkaRapi(float $nilai): string
    {
        return rtrim(rtrim(number_format($nilai, 2, ',', '.'), '0'), ',');
    }

    private function distanceKm(float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): float
    {
        $earthRadiusKm = 6371;
        $latitudeDelta = deg2rad($destinationLatitude - $originLatitude);
        $longitudeDelta = deg2rad($destinationLongitude - $originLongitude);
        $originLatitude = deg2rad($originLatitude);
        $destinationLatitude = deg2rad($destinationLatitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos($originLatitude) * cos($destinationLatitude) * sin($longitudeDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

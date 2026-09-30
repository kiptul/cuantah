<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
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

            // Kapasitas mitra sebelumnya hanya disimpan dan ditampilkan tanpa
            // pernah dipakai, sehingga mitra yang sudah penuh tetap menerima
            // setoran dan penyetor menunggu untuk sesuatu yang tidak muat.
            if ($partner->availableLiter() >= $partner->capacity_liter) {
                throw ValidationException::withMessages([
                    'partner_id' => sprintf(
                        'Mitra %s sedang penuh, kapasitasnya %s L dan belum ada penyaluran keluar. Pilih mitra lain dulu.',
                        $partner->name,
                        number_format($partner->capacity_liter, 0, ',', '.'),
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

    public function claimPickup(Pickup $pickup, User $employee): bool
    {
        return DB::transaction(function () use ($pickup, $employee) {
            $updated = Pickup::query()
                ->whereKey($pickup->id)
                ->whereNull('assigned_user_id')
                ->whereHas('transaction', fn ($transaction) => $transaction->visibleTo($employee)
                    ->whereNotIn('status', Transaction::FINAL_STATUSES))
                ->where(function ($query) {
                    $query->whereNotNull('scanned_at')
                        ->orWhereHas('transaction', fn ($transaction) => $transaction->where('method', Transaction::METHOD_PICKUP));
                })
                ->update([
                    'assigned_user_id' => $employee->id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 1) {
                $pickup->transaction()->update(['status' => Transaction::STATUS_SCHEDULED]);
            }

            return $updated === 1;
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

        return DB::transaction(function () use ($transaction, $data) {
            $actualLiter = (float) $data['actual_liter'];
            $total = max((int) round($actualLiter * $transaction->price_per_liter) - (int) $transaction->pickup_fee, 0);

            $transaction->update([
                'actual_liter' => $actualLiter,
                'total_value' => $total,
                'payment_method' => $data['payment_method'],
                'payment_status' => $data['payment_status'],
                'paid_at' => $data['payment_status'] === 'paid' ? now() : null,
                'status' => Transaction::STATUS_COMPLETED,
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

            return $transaction->refresh();
        });
    }

    /**
     * Menolak transaksi disertai alasannya.
     *
     * Sebelumnya penyetor hanya menerima pesan generik, sehingga ia tidak
     * pernah tahu apa yang perlu diperbaiki pada setoran berikutnya.
     */
    public function reject(Transaction $transaction, ?string $reason = null): Transaction
    {
        $this->pastikanBelumFinal($transaction, 'ditolak');

        return DB::transaction(function () use ($transaction, $reason) {
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
    public function markPaid(Transaction $transaction): Transaction
    {
        if ($transaction->status !== Transaction::STATUS_COMPLETED || $transaction->payment_status !== 'unpaid') {
            throw ValidationException::withMessages([
                'payment_status' => 'Hanya transaksi selesai yang belum dibayar yang bisa ditandai lunas.',
            ]);
        }

        return DB::transaction(function () use ($transaction) {
            $transaction->update([
                'payment_status' => 'paid',
                'paid_at' => now(),
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

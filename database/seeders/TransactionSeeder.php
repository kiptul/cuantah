<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\OilPrice;
use App\Models\Partner;
use App\Models\Pickup;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transaksi demo selama 12 bulan plus antrean kerja yang masih terbuka.
 *
 * Nilai dihitung dengan aturan yang sama seperti aplikasi: harga sesuai
 * tanggal transaksi, ongkir menurut jarak ke mitra terdekat, dan ongkir
 * dipotong dari penerimaan penyetor. Antrean terbuka sengaja memuat setiap
 * keadaan yang muncul di dashboard: pickup belum ditugaskan, jadwal
 * terlewat, drop-off belum dipindai, keberatan, dan pembayaran tertunda.
 *
 * Kode transaksi demo memuat "-DM" sehingga seeder ini dilewati bila
 * datanya sudah pernah dibuat.
 */
class TransactionSeeder extends Seeder
{
    private const PICKUP_TIMES = ['08:00', '09:30', '10:00', '13:00', '15:30', '16:00'];

    private const REJECTION_REASONS = [
        'Jelantah tercampur air sehingga tidak bisa diolah.',
        'Isi jeriken ternyata minyak bekas mesin, bukan minyak goreng.',
        'Jelantah bercampur sisa makanan dan endapan tebal.',
    ];

    private const DISPUTE_REASONS = [
        'Saya menyetor dua jeriken penuh, rasanya lebih dari yang tercatat.',
        'Takaran di rumah sekitar 12 liter tetapi tercatat jauh lebih sedikit.',
        'Volume tercatat tidak sesuai dengan botol takar yang saya pakai.',
    ];

    private int $sequence = 0;

    /** @var Collection<int, OilPrice> */
    private Collection $prices;

    /** @var Collection<int, Partner> */
    private Collection $partners;

    /** @var Collection<int, User> */
    private Collection $employees;

    /** @var Collection<int, User> */
    private Collection $depositors;

    /** @var array<string, array{address: string, latitude: float, longitude: float}> */
    private array $locations;

    public function run(): void
    {
        if (Transaction::where('code', 'like', '%-DM%')->exists()) {
            $this->command?->warn('  Transaksi demo sudah ada, TransactionSeeder dilewati.');

            return;
        }

        fake()->seed(20261003);

        $this->prices = OilPrice::where('is_active', true)->orderBy('effective_date')->get();
        $this->partners = Partner::with('deliveryFees')->where('status', 'active')->get();
        $this->employees = User::where('role', 'employee')->with('partners:id')->get();
        $this->locations = DepositorSeeder::locations();
        $this->depositors = User::where('role', 'user')
            ->whereIn('email', array_keys($this->locations))
            ->get();

        if ($this->prices->isEmpty() || $this->partners->isEmpty() || $this->depositors->isEmpty()) {
            $this->command?->warn('  Harga, mitra, atau penyetor belum ada; TransactionSeeder dilewati.');

            return;
        }

        DB::transaction(function () {
            $this->history();
            $this->openQueue();
        });
    }

    /**
     * Transaksi yang sudah berakhir, makin ramai menjelang bulan ini.
     */
    private function history(): void
    {
        foreach (range(11, 0) as $monthsAgo) {
            $monthStart = now()->subMonthsNoOverflow($monthsAgo)->startOfMonth();
            $monthEnd = $monthsAgo === 0 ? now()->subDays(4) : $monthStart->copy()->endOfMonth();

            if ($monthEnd->lt($monthStart)) {
                continue;
            }

            $count = 5 + intdiv(11 - $monthsAgo, 2) + fake()->numberBetween(0, 2);

            foreach (range(1, $count) as $ignored) {
                $createdAt = Carbon::createFromTimestamp(fake()->numberBetween($monthStart->timestamp, $monthEnd->timestamp))
                    ->setTime(fake()->numberBetween(7, 19), fake()->numberBetween(0, 59));
                $transaction = $this->deposit($this->randomDepositor(), $this->randomMethod(), $createdAt);
                $roll = fake()->numberBetween(1, 100);

                if ($roll <= 8) {
                    $this->cancel($transaction, $createdAt->copy()->addHours(2));

                    continue;
                }

                $this->assign($transaction, $createdAt->copy()->addHours(fake()->numberBetween(1, 20)));

                if ($roll <= 15) {
                    $this->reject($transaction, $createdAt->copy()->addDay());

                    continue;
                }

                $completedAt = $createdAt->copy()->addDays(fake()->numberBetween(1, 2))->setTime(fake()->numberBetween(8, 17), 0);
                $this->complete($transaction, $completedAt);

                if ($roll >= 97) {
                    $this->dispute($transaction, $completedAt->copy()->addDay(), resolved: true);
                }
            }
        }
    }

    /**
     * Pekerjaan yang masih berjalan dan hal yang menunggu tindakan.
     */
    private function openQueue(): void
    {
        $demoUser = $this->depositors->firstWhere('email', 'user@cuantah.test') ?? $this->randomDepositor();

        // Pickup baru yang belum diambil karyawan, dijadwalkan beberapa hari ke depan.
        $this->deposit($demoUser, Transaction::METHOD_PICKUP, now()->subHours(5), scheduleIn: 1);
        foreach ([1, 2, 3] as $days) {
            $this->deposit($this->randomDepositor(), Transaction::METHOD_PICKUP, now()->subHours(fake()->numberBetween(2, 30)), scheduleIn: $days);
        }

        // Pickup yang sudah ditugaskan: satu untuk hari ini, satu terlewat kemarin.
        foreach ([0, -1] as $days) {
            $transaction = $this->deposit($this->randomDepositor(), Transaction::METHOD_PICKUP, now()->subDays(3), scheduleIn: $days);
            $this->assign($transaction, now()->subDays(2));
        }

        // Drop-off: dua belum diantar, satu baru dipindai karyawan di mitra.
        $this->deposit($this->randomDepositor(), Transaction::METHOD_DROP_OFF, now()->subHours(3));
        $this->deposit($this->randomDepositor(), Transaction::METHOD_DROP_OFF, now()->subDay());
        $this->assign($this->deposit($this->randomDepositor(), Transaction::METHOD_DROP_OFF, now()->subHours(6)), now()->subMinutes(40));

        // Baru selesai, takarannya masih bisa disanggah penyetor.
        $recent = $this->deposit($demoUser, Transaction::METHOD_PICKUP, now()->subDays(3));
        $this->assign($recent, now()->subDays(3)->addHours(2));
        $this->complete($recent, now()->subDays(2)->setTime(10, 15));

        $recentDropOff = $this->deposit($this->randomDepositor(), Transaction::METHOD_DROP_OFF, now()->subDay()->setTime(9, 0));
        $this->assign($recentDropOff, now()->subDay()->setTime(9, 20));
        $this->complete($recentDropOff, now()->subDay()->setTime(9, 35));

        // Keberatan yang belum ditanggapi admin.
        $disputed = $this->deposit($this->randomDepositor(), Transaction::METHOD_PICKUP, now()->subDays(3));
        $this->assign($disputed, now()->subDays(3)->addHour());
        $this->complete($disputed, now()->subDays(2)->setTime(14, 0), actualRatio: 0.7);
        $this->dispute($disputed, now()->subDay(), resolved: false);

        // Selesai tetapi dibayar menyusul.
        foreach ([3, 5] as $daysAgo) {
            $unpaid = $this->deposit($this->randomDepositor(), Transaction::METHOD_PICKUP, now()->subDays($daysAgo + 1));
            $this->assign($unpaid, now()->subDays($daysAgo + 1)->addHours(3));
            $this->complete($unpaid, now()->subDays($daysAgo)->setTime(11, 0), paid: false);
        }
    }

    /**
     * Membuat pengajuan setor berstatus menunggu beserta data pickup-nya.
     */
    private function deposit(User $depositor, string $method, Carbon $createdAt, ?int $scheduleIn = null): Transaction
    {
        $address = $this->locations[$depositor->email];
        $partner = $this->nearestPartner($address['latitude'], $address['longitude']);
        $price = $this->priceAt($createdAt);
        $isPickup = $method === Transaction::METHOD_PICKUP;

        $fee = $isPickup
            ? $partner->deliveryFeeForDistance($this->distanceKm((float) $partner->latitude, (float) $partner->longitude, $address['latitude'], $address['longitude']))
            : 0;

        // Volume dibulatkan ke setengah liter dan dijamin melebihi ongkir,
        // sama seperti penjagaan di TransactionService::createDeposit.
        $liter = fake()->numberBetween(6, 50) / 2;
        $liter = max($liter, ceil(($fee + 1) / $price->price_per_liter * 2) / 2 + 1);
        $gross = (int) round($liter * $price->price_per_liter);

        $transaction = (new Transaction)->forceFill([
            'code' => sprintf('CNT-%s-DM%04d', $createdAt->format('ymd'), ++$this->sequence),
            'user_id' => $depositor->id,
            'oil_price_id' => $price->id,
            'partner_id' => $partner->id,
            'estimated_liter' => $liter,
            'price_per_liter' => $price->price_per_liter,
            'estimated_total' => max($gross - $fee, 0),
            'pickup_fee' => $fee,
            'method' => $method,
            'status' => Transaction::STATUS_PENDING,
            'notes' => fake()->boolean(25) ? fake()->randomElement(['Jeriken di depan pagar.', 'Hubungi dulu sebelum datang.', 'Minyak sudah disaring.']) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $transaction->save();

        // Antrean terbuka dijadwalkan relatif terhadap hari ini, sedangkan
        // transaksi lama satu sampai dua hari sesudah pengajuannya.
        $pickupDate = match (true) {
            ! $isPickup => null,
            $scheduleIn !== null => now()->addDays($scheduleIn),
            default => $createdAt->copy()->addDays(fake()->numberBetween(1, 2)),
        };

        $pickup = (new Pickup)->forceFill([
            'transaction_id' => $transaction->id,
            'partner_id' => $partner->id,
            'address' => $isPickup ? $address['address'] : $partner->address,
            'latitude' => $isPickup ? $address['latitude'] : $partner->latitude,
            'longitude' => $isPickup ? $address['longitude'] : $partner->longitude,
            'pickup_date' => $pickupDate?->toDateString(),
            'pickup_time' => $isPickup ? fake()->randomElement(self::PICKUP_TIMES) : null,
            'status' => $isPickup ? 'pending' : 'awaiting_dropoff',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
        $pickup->save();

        $this->notify($depositor, 'Pengajuan setor diterima', 'Transaksi '.$transaction->code.' sedang menunggu proses berikutnya.', 'transaction', $createdAt);

        return $transaction->setRelation('pickup', $pickup)->setRelation('partner', $partner);
    }

    private function assign(Transaction $transaction, Carbon $at): void
    {
        $employee = $this->employeeFor($transaction->partner_id);

        if (! $employee) {
            return;
        }

        $transaction->pickup->forceFill([
            'assigned_user_id' => $employee->id,
            'assigned_at' => $at,
            'scanned_at' => $transaction->method === Transaction::METHOD_DROP_OFF ? $at : null,
            'status' => 'assigned',
            'updated_at' => $at,
        ])->save();

        $transaction->forceFill(['status' => Transaction::STATUS_SCHEDULED, 'updated_at' => $at])->save();
    }

    private function complete(Transaction $transaction, Carbon $at, bool $paid = true, ?float $actualRatio = null): void
    {
        $actualLiter = round(max((float) $transaction->estimated_liter * ($actualRatio ?? fake()->randomFloat(2, 0.85, 1.08)), 0.5), 2);
        $total = max((int) round($actualLiter * $transaction->price_per_liter) - (int) $transaction->pickup_fee, 0);

        $transaction->forceFill([
            'actual_liter' => $actualLiter,
            'total_value' => $total,
            'payment_method' => fake()->boolean(70) ? 'cash' : 'transfer',
            'payment_status' => $paid ? 'paid' : 'unpaid',
            'paid_at' => $paid ? $at : null,
            'completed_at' => $at,
            'status' => Transaction::STATUS_COMPLETED,
            'updated_at' => $at,
        ])->save();

        $transaction->pickup->forceFill(['status' => 'completed', 'updated_at' => $at])->save();

        $this->notify(
            $transaction->user,
            'Transaksi selesai',
            sprintf(
                'Transaksi %s selesai: %s L, total Rp%s%s.',
                $transaction->code,
                number_format($actualLiter, 2, ',', '.'),
                number_format($total, 0, ',', '.'),
                $paid ? ' sudah dibayar' : ' akan dibayarkan menyusul',
            ),
            'payment',
            $at,
        );
    }

    private function reject(Transaction $transaction, Carbon $at): void
    {
        $reason = fake()->randomElement(self::REJECTION_REASONS);

        $transaction->forceFill(['status' => Transaction::STATUS_REJECTED, 'rejection_reason' => $reason, 'updated_at' => $at])->save();
        $transaction->pickup->forceFill(['status' => 'rejected', 'updated_at' => $at])->save();

        $this->notify($transaction->user, 'Transaksi ditolak', 'Transaksi '.$transaction->code.' ditolak. Alasan: '.$reason, 'transaction', $at);
    }

    private function cancel(Transaction $transaction, Carbon $at): void
    {
        $transaction->forceFill(['status' => Transaction::STATUS_CANCELLED, 'updated_at' => $at])->save();
        $transaction->pickup->forceFill(['status' => 'cancelled', 'updated_at' => $at])->save();

        $this->notify($transaction->user, 'Setoran dibatalkan', 'Kamu membatalkan transaksi '.$transaction->code.'.', 'transaction', $at);
    }

    private function dispute(Transaction $transaction, Carbon $at, bool $resolved): void
    {
        $resolution = 'Sudah kami takar ulang bersama karyawan; selisih kami bayarkan bersama setoran berikutnya.';

        $transaction->forceFill([
            'disputed_at' => $at,
            'dispute_reason' => fake()->randomElement(self::DISPUTE_REASONS),
            'dispute_resolved_at' => $resolved ? $at->copy()->addDay() : null,
            'dispute_resolution' => $resolved ? $resolution : null,
            'updated_at' => $resolved ? $at->copy()->addDay() : $at,
        ])->save();

        if ($resolved) {
            return;
        }

        User::where('role', 'admin')
            ->whereHas('partners', fn ($partner) => $partner->whereKey($transaction->partner_id))
            ->get()
            ->each(fn (User $admin) => $this->notify($admin, 'Keberatan takaran baru', 'Penyetor menyanggah takaran transaksi '.$transaction->code.'. Mohon ditanggapi.', 'transaction', $at));
    }

    /**
     * Notifikasi lama dianggap sudah dibaca supaya lonceng tidak penuh.
     */
    private function notify(User $user, string $title, string $message, string $type, Carbon $at): void
    {
        (new Notification)->forceFill([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'read_at' => $at->lt(now()->subDays(3)) ? $at->copy()->addHours(3) : null,
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();
    }

    private function randomDepositor(): User
    {
        return $this->depositors->random();
    }

    private function randomMethod(): string
    {
        return fake()->boolean(65) ? Transaction::METHOD_PICKUP : Transaction::METHOD_DROP_OFF;
    }

    private function employeeFor(int $partnerId): ?User
    {
        $candidates = $this->employees->filter(fn (User $employee) => $employee->partners->contains('id', $partnerId));

        return $candidates->isEmpty() ? null : $candidates->random();
    }

    private function priceAt(Carbon $date): OilPrice
    {
        return $this->prices->last(fn (OilPrice $price) => $price->effective_date->lte($date)) ?? $this->prices->first();
    }

    private function nearestPartner(float $latitude, float $longitude): Partner
    {
        return $this->partners
            ->sortBy(fn (Partner $partner) => $this->distanceKm((float) $partner->latitude, (float) $partner->longitude, $latitude, $longitude))
            ->first();
    }

    private function distanceKm(float $originLatitude, float $originLongitude, float $destinationLatitude, float $destinationLongitude): float
    {
        $latitudeDelta = deg2rad($destinationLatitude - $originLatitude);
        $longitudeDelta = deg2rad($destinationLongitude - $originLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($originLatitude)) * cos(deg2rad($destinationLatitude)) * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

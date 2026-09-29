<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    public const METHOD_DROP_OFF = 'drop_off';

    public const METHOD_PICKUP = 'pickup';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_VERIFICATION = 'verification';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Status akhir: transaksi tidak boleh berpindah lagi dari sini.
     *
     * @var array<int, string>
     */
    public const FINAL_STATUSES = [
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Sebutan status dalam bahasa sehari-hari, untuk pesan ke pengguna.
     *
     * @var array<string, string>
     */
    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'menunggu',
        self::STATUS_SCHEDULED => 'dijadwalkan',
        self::STATUS_PICKED_UP => 'dijemput',
        self::STATUS_VERIFICATION => 'dalam verifikasi',
        self::STATUS_COMPLETED => 'selesai',
        self::STATUS_REJECTED => 'ditolak',
        self::STATUS_CANCELLED => 'dibatalkan',
    ];

    protected $fillable = [
        'code',
        'user_id',
        'oil_price_id',
        'partner_id',
        'estimated_liter',
        'actual_liter',
        'price_per_liter',
        'estimated_total',
        'pickup_fee',
        'total_value',
        'method',
        'status',
        'payment_method',
        'payment_status',
        'paid_at',
        'payment_confirmed_at',
        'rejection_reason',
        'disputed_at',
        'dispute_reason',
        'dispute_resolved_at',
        'dispute_resolution',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_liter' => 'decimal:2',
            'actual_liter' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
            'disputed_at' => 'datetime',
            'dispute_resolved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function oilPrice(): BelongsTo
    {
        return $this->belongsTo(OilPrice::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function pickup(): HasOne
    {
        return $this->hasOne(Pickup::class);
    }

    /**
     * Transaksi yang sudah selesai, ditolak, atau dibatalkan.
     *
     * Sebelumnya tidak ada penjagaan sama sekali: transaksi selesai masih
     * bisa diverifikasi ulang atau ditolak, sehingga liter yang sudah
     * dihitung sebagai stok mitra bisa lenyap sesudah penyalurannya dicatat.
     */
    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /**
     * Seluruh status transaksi yang sah, berurutan sesuai alur.
     *
     * Diturunkan dari STATUS_LABELS agar tidak ada dua daftar status yang
     * bisa berbeda diam-diam. Dipakai menyaring status yang datang dari query
     * string: nilai di luar daftar ini diabaikan, bukan diteruskan ke where,
     * supaya URL yang diketik tangan tidak menghasilkan daftar kosong yang
     * membingungkan.
     *
     * @return array<int, string>
     */
    public static function statuses(): array
    {
        return array_keys(self::STATUS_LABELS);
    }

    /**
     * Seluruh metode setoran yang sah.
     *
     * @return array<int, string>
     */
    public static function methods(): array
    {
        return [self::METHOD_PICKUP, self::METHOD_DROP_OFF];
    }

    /**
     * Mencari transaksi berdasarkan kode atau nama penyetor.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        /**
         * Wildcard LIKE di-escape lebih dulu. Tanpa ini, admin yang mengetik
         * "%" mendapat seluruh transaksi alih-alih transaksi yang kodenya
         * benar-benar memuat karakter itu.
         */
        $escaped = addcslashes($term, '%_\\');

        return $query->where(function (Builder $query) use ($escaped) {
            $query->where('code', 'like', "%{$escaped}%")
                ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$escaped}%"));
        });
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isStaff()) {
            return $query->where('user_id', $user->id);
        }

        $partnerIds = $user->accessiblePartnerIds();

        if ($partnerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('partner_id', $partnerIds);
    }
}

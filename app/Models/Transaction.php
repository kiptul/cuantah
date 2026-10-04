<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

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
     * Lamanya penyetor boleh menyanggah takaran, dihitung sejak transaksi
     * dinyatakan selesai.
     *
     * Ditulis sekali di sini karena angkanya dipakai tiga pihak sekaligus:
     * policy yang mengizinkan, dasbor yang mendaftar, dan teks yang
     * menjanjikannya kepada penyetor. Ketiganya tidak boleh berbeda.
     */
    public const DISPUTE_WINDOW_DAYS = 3;

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
        'payment_proof_path',
        'completed_at',
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
            'completed_at' => 'datetime',
            'disputed_at' => 'datetime',
            'dispute_resolved_at' => 'datetime',
        ];
    }

    /**
     * Riwayat koreksi volume, terbaru lebih dulu.
     *
     * @return HasMany<TransactionCorrection, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(TransactionCorrection::class)->latest('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /**
     * Batas akhir penyetor boleh menyanggah takaran.
     *
     * Dihitung dari completed_at, bukan updated_at. Dengan updated_at, satu
     * kali admin menyunting catatan atau menutup sanggahan lain, hitungan
     * tiga hari dimulai lagi dari nol untuk transaksi yang sudah lama
     * selesai.
     */
    public function disputeDeadline(): ?Carbon
    {
        return $this->completed_at?->copy()->addDays(self::DISPUTE_WINDOW_DAYS);
    }

    /**
     * Apakah jendela sanggahan masih terbuka.
     *
     * Transaksi selesai tanpa completed_at tidak pernah terjadi lewat alur
     * verifikasi, dan bila toh muncul, lebih aman ditolak daripada dibuka
     * selamanya.
     */
    public function withinDisputeWindow(): bool
    {
        return $this->disputeDeadline()?->isFuture() ?? false;
    }

    /**
     * Tahapan yang sedang dijalani transaksi, bernilai 0 sampai 2.
     *
     * Penanda kemajuan di dasbor penyetor sebelumnya menyimpulkan tahapan dari
     * ada-tidaknya karyawan yang ditugaskan, bukan dari status transaksinya.
     * Penugasan bukan kemajuan: begitu seorang karyawan mengambil jadwal,
     * penanda melompat ke tahap terakhir, sehingga setoran yang penjemputannya
     * masih dua hari lagi tampak hampir selesai.
     *
     * picked_up dan verification ikut dipetakan meski alur sekarang tidak
     * pernah menuliskannya, supaya penanda tidak diam-diam mundur ke tahap
     * awal bila keduanya dipakai kembali.
     */
    public function progressStep(): int
    {
        return match ($this->status) {
            self::STATUS_SCHEDULED, self::STATUS_PICKED_UP => 1,
            self::STATUS_VERIFICATION, self::STATUS_COMPLETED => 2,
            default => 0,
        };
    }

    /**
     * Nilai yang benar-benar menjadi hak penyetor, atau null bila tidak ada.
     *
     * Transaksi yang ditolak maupun dibatalkan tidak pernah berujung
     * pembayaran, dan keduanya meninggalkan total_value kosong. Membaca
     * estimasi sebagai gantinya membuat angka perkiraan tampil sebagai uang
     * yang sudah diterima, lengkap dengan lencana "Ditolak" di sebelahnya.
     */
    public function settledValue(): ?int
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            return null;
        }

        return $this->total_value === null ? null : (int) $this->total_value;
    }

    /**
     * Liter hasil timbangan mitra, atau null bila tidak pernah ditimbang.
     */
    public function settledLiter(): ?float
    {
        if ($this->status !== self::STATUS_COMPLETED) {
            return null;
        }

        return $this->actual_liter === null ? null : (float) $this->actual_liter;
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
     * Aturan berkas untuk bukti pembayaran.
     *
     * Uang berpindah di tiga tempat: verifikasi oleh karyawan, verifikasi
     * oleh admin, dan penandaan lunas menyusul. Ketiganya memanggil daftar
     * ini supaya tidak ada pintu yang syaratnya lebih longgar tanpa
     * disengaja. Yang berbeda hanya kapan berkasnya diwajibkan, karena pada
     * verifikasi pembayaran bisa saja ditunda.
     *
     * Dipakai aturan image dan mimes, bukan extensions. Keduanya membaca isi
     * berkas, sedangkan extensions hanya memeriksa akhiran nama yang
     * ditentukan pengunggah.
     *
     * @return array<int, string>
     */
    public static function paymentProofRules(string $kehadiran): array
    {
        return [$kehadiran, 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
    }

    public function hasPaymentProof(): bool
    {
        return $this->payment_proof_path !== null;
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

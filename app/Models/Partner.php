<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'phone', 'address', 'latitude', 'longitude', 'capacity_liter', 'status'];

    public function pickups(): HasMany
    {
        return $this->hasMany(Pickup::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function deliveryFees(): HasMany
    {
        return $this->hasMany(PartnerDeliveryFee::class)->orderBy('min_distance_km');
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isStaff()) {
            return $query;
        }

        $partnerIds = $user->accessiblePartnerIds();

        if ($partnerIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('id', $partnerIds);
    }

    /**
     * Menghitung stok tiap mitra dalam satu query, bukan dua query per mitra.
     *
     * Halaman penyaluran menampilkan seluruh mitra sekaligus; tanpa ini
     * jumlah query tumbuh sebanding dengan jumlah mitra.
     */
    public function scopeWithAvailableLiter(Builder $query): Builder
    {
        return $query
            ->withSum(
                ['transactions as collected_liter' => fn (Builder $transaction) => $transaction->where('status', Transaction::STATUS_COMPLETED)],
                'actual_liter',
            )
            ->withSum('distributions as distributed_liter', 'volume_liter');
    }

    /**
     * Liter yang sudah terkumpul di mitra ini tetapi belum disalurkan.
     *
     * Dipakai untuk menahan pencatatan penyaluran yang melebihi jumlah yang
     * benar-benar pernah masuk. Tanpa ini, volume keluar dapat diisi bebas
     * sehingga laporan rantai pasok kehilangan artinya.
     */
    public function availableLiter(): float
    {
        if (array_key_exists('collected_liter', $this->attributes)) {
            return round((float) $this->attributes['collected_liter'] - (float) ($this->attributes['distributed_liter'] ?? 0), 2);
        }

        $terkumpul = (float) $this->transactions()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->sum('actual_liter');

        $tersalur = (float) $this->distributions()->sum('volume_liter');

        return round($terkumpul - $tersalur, 2);
    }

    public function deliveryFeeForDistance(float $distanceKm): int
    {
        $rule = $this->deliveryFees
            ->first(fn (PartnerDeliveryFee $fee) => $distanceKm >= (float) $fee->min_distance_km
                && ($fee->max_distance_km === null || $distanceKm <= (float) $fee->max_distance_km));

        return (int) ($rule?->fee ?? 0);
    }
}

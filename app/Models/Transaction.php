<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    public const METHOD_DROP_OFF = 'drop_off';

    public const METHOD_PICKUP = 'pickup';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_VERIFICATION = 'verification';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

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
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'estimated_liter' => 'decimal:2',
            'actual_liter' => 'decimal:2',
            'paid_at' => 'datetime',
            'payment_confirmed_at' => 'datetime',
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

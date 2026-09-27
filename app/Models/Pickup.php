<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pickup extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_DROP_OFF = 'awaiting_dropoff';

    public const STATUS_SCANNED = 'scanned';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_PICKED_UP = 'picked_up';

    public const STATUS_VERIFICATION = 'verification';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'transaction_id',
        'partner_id',
        'assigned_user_id',
        'address',
        'latitude',
        'longitude',
        'pickup_date',
        'pickup_time',
        'scanned_at',
        'status',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_user_id' => 'integer',
            'pickup_date' => 'date',
            'scanned_at' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->isStaff()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('transaction', fn (Builder $transaction) => $transaction->visibleTo($user));
    }
}

<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function assignedPickups(): HasMany
    {
        return $this->hasMany(Pickup::class, 'assigned_user_id');
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class)->withTimestamps();
    }

    /**
     * Surel reset password memakai versi bahasa Indonesia.
     *
     * Bawaan Laravel berbahasa Inggris, padahal ini satu-satunya surel yang
     * pernah dikirim aplikasi dan penerimanya rumah tangga serta pelaku UMKM.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    public function isStaff(): bool
    {
        return $this->isAdmin() || $this->isEmployee();
    }

    /**
     * Daftar pengguna yang boleh dikelola oleh seorang admin.
     *
     * Sejalan dengan UserPolicy::update: penyetor biasa terbuka untuk admin
     * mana pun, sedangkan staff hanya tampil bagi admin yang berbagi mitra.
     * Sebelumnya halaman pengguna menampilkan seluruh akun lintas mitra,
     * termasuk email admin mitra lain.
     */
    public function scopeManageableBy(Builder $query, self $actor): Builder
    {
        if (! $actor->isAdmin()) {
            return $query->whereRaw('1 = 0');
        }

        $partnerIds = $actor->accessiblePartnerIds();

        return $query->where(function (Builder $query) use ($actor, $partnerIds): void {
            $query->whereNotIn('role', ['admin', 'employee'])
                ->orWhereKey($actor->id)
                ->when($partnerIds !== [], fn (Builder $query) => $query->orWhereHas(
                    'partners',
                    fn (Builder $partner) => $partner->whereIn('partners.id', $partnerIds),
                ));
        });
    }

    /**
     * Id mitra yang boleh diakses, dihitung sekali per instans.
     *
     * canAccessPartnerId() dipanggil berkali-kali dalam satu permintaan,
     * antara lain di dalam perulangan. Tanpa penyimpanan ini setiap panggilan
     * menambah satu query pivot.
     *
     * @var array<int>|null
     */
    private ?array $cachedPartnerIds = null;

    /**
     * @return array<int>
     */
    public function accessiblePartnerIds(): array
    {
        if (! $this->isStaff()) {
            return [];
        }

        return $this->cachedPartnerIds ??= $this->partners()->pluck('partners.id')->all();
    }

    /**
     * Membuang id mitra yang tersimpan, dipakai sesudah pivot berubah.
     */
    public function forgetAccessiblePartnerIds(): void
    {
        $this->cachedPartnerIds = null;
    }

    public function canAccessPartnerId(?int $partnerId): bool
    {
        if (! $this->isStaff()) {
            return true;
        }

        if ($partnerId === null) {
            return false;
        }

        return in_array($partnerId, $this->accessiblePartnerIds(), true);
    }
}

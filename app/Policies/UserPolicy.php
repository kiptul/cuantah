<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Siapa yang boleh mengubah data seorang pengguna.
     *
     * Penyetor biasa dapat dikelola admin mana pun, sebab ia tidak terikat
     * pada satu mitra. Staff berbeda: ia hanya boleh disentuh admin yang
     * berbagi mitra dengannya. Tanpa batas itu, admin mitra A dapat mengubah
     * email admin mitra B lalu mengambil alih akunnya lewat lupa password.
     */
    public function update(User $actor, User $target): bool
    {
        if (! $actor->isAdmin()) {
            return false;
        }

        if ($actor->is($target)) {
            return true;
        }

        if (! $target->isStaff()) {
            return true;
        }

        $partnerIds = $actor->accessiblePartnerIds();

        if ($partnerIds === []) {
            return false;
        }

        return $target->partners()
            ->whereIn('partners.id', $partnerIds)
            ->exists();
    }
}

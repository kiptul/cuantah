<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $admin = auth()->user();

        $partners = Partner::query()
            ->accessibleTo($admin)
            ->orderBy('name')
            ->get();

        return view('admin.users.index', [
            'users' => User::with('partners')
                ->manageableBy($admin)
                ->withCount(['transactions' => fn ($transaction) => $transaction->visibleTo($admin)])
                ->latest()
                ->paginate(12),
            'partners' => $partners,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $partnerIds = $this->allowedPartnerIds($data['partner_ids'] ?? [], $data['role']);

        $user = User::create(collect($data)->except('partner_ids')->all());
        $user->partners()->sync($partnerIds);

        return back()->with('success', 'User berhasil dibuat.');
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validated();
        $partnerIds = $this->allowedPartnerIds($data['partner_ids'] ?? [], $data['role']);

        $attributes = collect($data)->except('partner_ids', 'password')->all();

        // Password hanya ikut berubah bila benar-benar diisi, supaya menyunting
        // nama atau nomor telepon tidak diam-diam mengosongkan kata sandi.
        if (filled($data['password'] ?? null)) {
            $attributes['password'] = Hash::make($data['password']);
        }

        $user->update($attributes);
        $user->partners()->sync($partnerIds);
        $user->forgetAccessiblePartnerIds();

        return back()->with('success', 'Data user berhasil diperbarui.');
    }

    /**
     * @param  array<int|string>  $partnerIds
     * @return array<int>
     */
    private function allowedPartnerIds(array $partnerIds, string $role): array
    {
        if (! in_array($role, ['admin', 'employee'], true)) {
            return [];
        }

        $availableIds = Partner::query()
            ->accessibleTo(auth()->user())
            ->pluck('id')
            ->all();

        return collect($partnerIds)
            ->map(fn (mixed $id) => (int) $id)
            ->intersect($availableIds)
            ->values()
            ->all();
    }
}

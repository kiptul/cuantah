<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Daftar pengguna yang boleh dikelola admin ini.
     *
     * Pencarian dan saringan peran ditambahkan karena daftar ini tumbuh
     * seiring jumlah penyetor: tanpa keduanya, menemukan satu karyawan
     * berarti membuka halaman demi halaman.
     */
    public function index(Request $request)
    {
        $admin = $request->user();
        $keyword = trim((string) $request->query('cari'));
        $role = $request->query('peran');

        $partners = Partner::query()
            ->accessibleTo($admin)
            ->orderBy('name')
            ->get();

        $users = User::with('partners')
            ->manageableBy($admin)
            ->when($keyword !== '', fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->where('name', 'like', '%'.$keyword.'%')
                    ->orWhere('email', 'like', '%'.$keyword.'%')
                    ->orWhere('phone', 'like', '%'.$keyword.'%'),
            ))
            ->when(in_array($role, ['user', 'employee', 'admin'], true), fn (Builder $query) => $query->where('role', $role))
            ->withCount(['transactions' => fn ($transaction) => $transaction->visibleTo($admin)])
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'partners' => $partners,
            'keyword' => $keyword,
            'peran' => $role,
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

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\Notification;
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
        $gantiPassword = filled($data['password'] ?? null);
        $emailLama = $user->email;

        if ($gantiPassword) {
            $attributes['password'] = Hash::make($data['password']);
        }

        $user->update($attributes);

        $this->beritahuPerubahanKredensial($user, $gantiPassword, $emailLama);
        $user->partners()->sync($this->keepHiddenPartners($user, $partnerIds, $data['role']));
        $user->forgetAccessiblePartnerIds();

        return back()->with('success', 'Data user berhasil diperbarui.');
    }

    /**
     * Memberi tahu pemilik akun bahwa kredensialnya diubah orang lain.
     *
     * Admin boleh menyetel kata sandi dan mengganti email pengguna, dan
     * keduanya cukup untuk mengambil alih akun. Tanpa pemberitahuan ini
     * perubahan tersebut tidak meninggalkan jejak yang bisa dilihat
     * pemiliknya sendiri.
     */
    private function beritahuPerubahanKredensial(User $user, bool $gantiPassword, string $emailLama): void
    {
        if (auth()->id() === $user->id) {
            return;
        }

        $perubahan = [];

        if ($gantiPassword) {
            $perubahan[] = 'password';
        }

        if ($user->email !== $emailLama) {
            $perubahan[] = 'alamat email';
        }

        if ($perubahan === []) {
            return;
        }

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Data masuk akunmu diubah admin',
            'message' => 'Admin '.auth()->user()->name.' mengubah '.implode(' dan ', $perubahan)
                .' akunmu. Bila kamu tidak meminta perubahan ini, segera hubungi mitramu.',
            'type' => 'account',
        ]);
    }

    /**
     * Menambahkan kembali mitra yang tidak terlihat oleh admin yang mengedit.
     *
     * Daftar centang hanya memuat mitra yang dapat diakses admin tersebut,
     * sedangkan sync mencabut semua yang tidak ikut terkirim. Tanpa langkah
     * ini, admin satu mitra yang menyunting nomor telepon karyawan ikut
     * memutus akses karyawan itu ke mitra lain yang bahkan tidak ia lihat.
     *
     * @param  array<int>  $partnerIds
     * @return array<int>
     */
    private function keepHiddenPartners(User $user, array $partnerIds, string $role): array
    {
        if (! in_array($role, ['admin', 'employee'], true)) {
            return [];
        }

        $terlihat = Partner::query()
            ->accessibleTo(auth()->user())
            ->pluck('id')
            ->all();

        $tersembunyi = $user->partners()
            ->pluck('partners.id')
            ->diff($terlihat);

        return collect($partnerIds)
            ->merge($tersembunyi)
            ->unique()
            ->values()
            ->all();
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

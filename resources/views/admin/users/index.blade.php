<x-layouts.admin title="Kelola User">
    <x-page-header eyebrow="Master Data" title="Kelola User" />

    <form method="post" action="{{ route('admin.users.store') }}" class="mb-6 rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
        @csrf
        <p class="font-black">Tambah User</p>
        <div class="mt-4 grid gap-3 md:grid-cols-5">
            <input name="name" value="{{ old('name') }}" placeholder="Nama" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            <input name="email" type="email" value="{{ old('email') }}" placeholder="Email" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            <input name="phone" value="{{ old('phone') }}" placeholder="Telepon" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
            <input name="password" type="password" placeholder="Password" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
            <select name="role" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                <option value="user">User</option>
                <option value="employee">Karyawan</option>
                <option value="admin">Admin</option>
            </select>
        </div>
        <div class="mt-4">
            <p class="text-sm font-bold">Mitra untuk admin/karyawan</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($partners as $partner)
                    <label class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="partner_ids[]" value="{{ $partner->id }}" class="rounded border-slate-300 text-emerald-700">
                        <span>{{ $partner->name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-slate-500">Belum ada mitra yang bisa dipilih.</p>
                @endforelse
            </div>
        </div>
        <button class="mt-4 rounded-md bg-emerald-700 px-4 py-2 font-bold text-white">Buat User</button>
    </form>

    <div class="grid gap-4">
        @foreach($users as $user)
            <form method="post" action="{{ route('admin.users.update', $user) }}" class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm shadow-emerald-950/5">
                @csrf
                @method('put')
                <div class="grid gap-3 md:grid-cols-5">
                    <input name="name" value="{{ $user->name }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    <input name="email" type="email" value="{{ $user->email }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>
                    <input name="phone" value="{{ $user->phone }}" placeholder="Telepon" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                    <select name="role" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                        <option value="user" @selected($user->role === 'user')>User</option>
                        <option value="employee" @selected($user->role === 'employee')>Karyawan</option>
                        <option value="admin" @selected($user->role === 'admin')>Admin</option>
                    </select>
                    <button class="rounded-md bg-emerald-700 px-4 py-2 font-bold text-white">Simpan</button>
                </div>

                <div class="mt-4">
                    <p class="text-sm font-bold">Mitra aktif</p>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        @forelse($partners as $partner)
                            <label class="flex items-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-sm">
                                <input type="checkbox" name="partner_ids[]" value="{{ $partner->id }}" class="rounded border-slate-300 text-emerald-700" @checked($user->partners->contains($partner))>
                                <span>{{ $partner->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-slate-500">Belum ada mitra yang bisa dipilih.</p>
                        @endforelse
                    </div>
                </div>

                <p class="mt-3 text-sm text-slate-500">{{ $user->transactions_count }} transaksi</p>
            </form>
        @endforeach
    </div>
    <div class="mt-4">{{ $users->links() }}</div>
</x-layouts.admin>

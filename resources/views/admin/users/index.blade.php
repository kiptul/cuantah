<x-layouts.admin title="Kelola User">
    <x-page-header eyebrow="Master Data" title="Kelola User">
        Admin dan karyawan hanya melihat data mitra yang dipilih di sini.
    </x-page-header>

    <details class="mb-5 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5" @if($errors->any() && old('name')) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-4">
            <span class="text-sm font-black text-slate-900">Tambah pengguna baru</span>
            <span class="rounded-lg bg-emerald-700 px-3 py-1.5 text-xs font-bold text-white">Buka formulir</span>
        </summary>
        <form method="post" action="{{ route('admin.users.store') }}" class="border-t border-slate-100 px-5 py-5">
            @csrf
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <x-form-field label="Nama" name="name" :value="old('name')" required />
                <x-form-field label="Email" name="email" type="email" :value="old('email')" required />
                <x-form-field label="Telepon" name="phone" :value="old('phone')" />
                <x-form-field label="Password" name="password" type="password" required hint="Minimal 8 karakter, memuat huruf dan angka." />
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-[200px_minmax(0,1fr)]">
                <label class="block">
                    <span class="text-sm font-bold text-slate-700">Peran</span>
                    <select name="role" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                        <option value="user" @selected(old('role') === 'user')>Penyetor</option>
                        <option value="employee" @selected(old('role') === 'employee')>Karyawan</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                    </select>
                </label>
                <x-partner-picker :partners="$partners" :selected="old('partner_ids', [])" />
            </div>

            <button class="mt-5 rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">Buat Pengguna</button>
        </form>
    </details>

    {{-- Pencarian dan saringan. Daftar ini bertambah seiring jumlah penyetor,
         sehingga menelusuri halaman demi halaman cepat menjadi tidak masuk akal. --}}
    <form method="get" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl bg-white px-5 py-4 shadow-sm ring-1 ring-slate-900/5">
        <label class="min-w-0 flex-1">
            <span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Cari</span>
            <input name="cari" value="{{ $keyword }}" placeholder="Nama, email, atau telepon"
                   class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
        </label>
        <label>
            <span class="text-xs font-bold uppercase tracking-[0.12em] text-slate-500">Peran</span>
            <select name="peran" class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100">
                <option value="">Semua</option>
                <option value="user" @selected($peran === 'user')>Penyetor</option>
                <option value="employee" @selected($peran === 'employee')>Karyawan</option>
                <option value="admin" @selected($peran === 'admin')>Admin</option>
            </select>
        </label>
        <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700">Terapkan</button>
        @if($keyword !== '' || $peran)
            <a href="{{ route('admin.users.index') }}" class="rounded-xl px-3 py-2.5 text-sm font-bold text-slate-500 transition hover:text-slate-800">Bersihkan</a>
        @endif
    </form>

    <div class="grid gap-3">
        @forelse($users as $user)
            @php
                $peranLabel = ['admin' => 'Admin', 'employee' => 'Karyawan'][$user->role] ?? 'Penyetor';
                $peranWarna = ['admin' => 'bg-emerald-100 text-emerald-800', 'employee' => 'bg-sky-100 text-sky-800'][$user->role] ?? 'bg-slate-100 text-slate-700';
            @endphp
            <details class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-900/5">
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-2 px-5 py-4">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-sm font-black text-slate-600">
                        {{ str($user->name)->substr(0, 1)->upper() }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-bold text-slate-900">{{ $user->name }}</span>
                        <span class="block truncate text-sm text-slate-500">{{ $user->email }}</span>
                    </span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $peranWarna }}">{{ $peranLabel }}</span>
                    <span class="hidden text-sm tabular-nums text-slate-400 sm:inline">{{ $user->transactions_count }} transaksi</span>
                    <span class="rounded-lg px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">Ubah</span>
                </summary>

                <form method="post" action="{{ route('admin.users.update', $user) }}" class="border-t border-slate-100 px-5 py-5">
                    @csrf
                    @method('put')
                    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        <x-form-field label="Nama" name="name" :value="$user->name" required />
                        <x-form-field label="Email" name="email" type="email" :value="$user->email" required />
                        <x-form-field label="Telepon" name="phone" :value="$user->phone" />
                        <x-form-field label="Password baru" name="password" type="password" hint="Kosongkan bila tidak diganti." />
                        <x-form-field label="Ulangi password baru" name="password_confirmation" type="password" />
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-[200px_minmax(0,1fr)]">
                        <label class="block">
                            <span class="text-sm font-bold text-slate-700">Peran</span>
                            <select name="role" @disabled(auth()->id() === $user->id)
                                    class="mt-1.5 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100 disabled:bg-slate-100 disabled:text-slate-500">
                                <option value="user" @selected($user->role === 'user')>Penyetor</option>
                                <option value="employee" @selected($user->role === 'employee')>Karyawan</option>
                                <option value="admin" @selected($user->role === 'admin')>Admin</option>
                            </select>
                            @if(auth()->id() === $user->id)
                                <input type="hidden" name="role" value="{{ $user->role }}">
                                <span class="mt-1.5 block text-xs text-slate-500">Peran sendiri tidak bisa diubah dari sini.</span>
                            @endif
                        </label>
                        <x-partner-picker :partners="$partners" :selected="$user->partners->pluck('id')->all()" />
                    </div>

                    <button class="mt-5 rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">Simpan Perubahan</button>
                </form>
            </details>
        @empty
            <x-empty-state title="Tidak ada pengguna yang cocok" body="Ubah kata kunci atau saringan perannya." />
        @endforelse
    </div>

    <div class="mt-5">{{ $users->links() }}</div>
</x-layouts.admin>

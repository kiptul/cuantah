<x-layouts.app title="Pengaturan Akun">
    <div class="mx-auto max-w-2xl">
        <div class="mb-6">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Akun</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-emerald-950 sm:text-3xl">Pengaturan Akun</h1>
        </div>

        <section class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 sm:p-6">
            <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Data diri</h2>
            <form method="post" action="{{ route('profile.update') }}" class="mt-4 grid gap-4">
                @csrf
                @method('put')
                <x-form-field label="Nama" name="name" :value="old('name', $user->name)" required />
                <x-form-field label="Email" name="email" type="email" :value="old('email', $user->email)" required />
                <x-form-field label="Telepon" name="phone" :value="old('phone', $user->phone)" hint="Dipakai karyawan untuk menghubungimu saat penjemputan." />
                <div>
                    <button class="rounded-xl bg-emerald-700 px-5 py-2.5 text-sm font-bold text-white shadow-sm shadow-emerald-900/20 transition hover:bg-emerald-800">Simpan Perubahan</button>
                </div>
            </form>
        </section>

        <section class="mt-5 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-900/5 sm:p-6">
            <h2 class="text-sm font-black uppercase tracking-[0.12em] text-slate-500">Ganti password</h2>
            <p class="mt-2 text-sm leading-6 text-slate-600">
                Setelah diganti, sesi di perangkat lain akan otomatis keluar.
            </p>
            <form method="post" action="{{ route('profile.password') }}" class="mt-4 grid gap-4">
                @csrf
                @method('put')
                <x-form-field label="Password saat ini" name="current_password" type="password" required />
                <x-form-field label="Password baru" name="password" type="password" required hint="Minimal 8 karakter, memuat huruf dan angka." />
                <x-form-field label="Ulangi password baru" name="password_confirmation" type="password" required />
                <div>
                    <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700">Ganti Password</button>
                </div>
            </form>
        </section>
    </div>
</x-layouts.app>

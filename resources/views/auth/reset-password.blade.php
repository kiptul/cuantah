<x-layouts.public title="Reset Password CUANTAH">
    <section class="relative overflow-hidden bg-[#f7faf5]">
        <div class="absolute left-0 top-0 h-56 w-56 rounded-full bg-emerald-100/70 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-lime-100/80 blur-3xl"></div>

        <div class="relative mx-auto flex min-h-[calc(100vh-88px)] max-w-md items-center px-4 py-14 lg:py-16">
            <form method="post" action="{{ route('password.update') }}" class="w-full rounded-[1.75rem] border border-emerald-100 bg-white p-6 shadow-xl shadow-emerald-950/10 sm:p-8">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-7">
                    <h2 class="text-2xl font-black text-emerald-950">Password Baru</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Buat password baru minimal 8 karakter.</p>
                </div>

                <label class="block text-sm font-black text-emerald-950">Email</label>
                <input name="email" value="{{ old('email', $email) }}" type="email" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 font-semibold text-emerald-950 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>

                <label class="mt-5 block text-sm font-black text-emerald-950">Password Baru</label>
                <input name="password" type="password" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 font-semibold text-emerald-950 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>

                <label class="mt-5 block text-sm font-black text-emerald-950">Ulangi Password Baru</label>
                <input name="password_confirmation" type="password" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 font-semibold text-emerald-950 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>

                @error('email')<p class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror
                @error('password')<p class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror

                <button class="mt-7 w-full rounded-2xl bg-emerald-700 px-4 py-3.5 font-black text-white shadow-lg shadow-emerald-950/15 transition hover:bg-emerald-800">Simpan Password Baru</button>
            </form>
        </div>
    </section>
</x-layouts.public>

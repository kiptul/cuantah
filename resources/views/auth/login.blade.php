<x-layouts.public title="Login CUANTAH">
    <section class="relative overflow-hidden bg-[#f7faf5]">
        <div class="absolute left-0 top-0 h-56 w-56 rounded-full bg-emerald-100/70 blur-3xl"></div>
        <div class="absolute bottom-0 right-0 h-72 w-72 rounded-full bg-lime-100/80 blur-3xl"></div>

        <div class="relative mx-auto grid min-h-[calc(100vh-88px)] max-w-6xl items-center gap-10 px-4 py-14 lg:grid-cols-[.9fr_1.1fr] lg:px-8 lg:py-16">
            <div class="mx-auto max-w-xl text-center lg:mx-0 lg:text-left">
                <div class="inline-flex items-center gap-4">
                    <x-brand-lockup variant="auth" />
                </div>

                <h1 class="mt-3 text-4xl font-black tracking-tight text-emerald-950 sm:text-5xl">
                    Masuk ke akunmu.
                </h1>

                <p class="mt-5 text-lg leading-8 text-slate-600">
                    Pantau setoran, pickup, dan riwayat pembayaran langsung dari dashboard.
                </p>

                <div class="mt-7 flex flex-wrap justify-center gap-3 lg:justify-start">
                    <span class="rounded-full bg-white px-4 py-2 text-sm font-black text-emerald-800 shadow-sm ring-1 ring-emerald-100">Setoran</span>
                    <span class="rounded-full bg-white px-4 py-2 text-sm font-black text-emerald-800 shadow-sm ring-1 ring-emerald-100">Pickup</span>
                    <span class="rounded-full bg-white px-4 py-2 text-sm font-black text-emerald-800 shadow-sm ring-1 ring-emerald-100">Pembayaran</span>
                </div>
            </div>

            <div class="mx-auto w-full max-w-md">
                <form method="post" action="{{ route('login') }}" class="rounded-[1.75rem] border border-emerald-100 bg-white p-6 shadow-xl shadow-emerald-950/10 sm:p-8">
                    @csrf

                    <div class="mb-7">
                        <h2 class="text-2xl font-black text-emerald-950">Login</h2>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Gunakan email dan password yang sudah terdaftar.</p>
                    </div>

                    <label class="block text-sm font-black text-emerald-950">Email</label>
                    <input name="email" value="{{ old('email') }}" type="email" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 font-semibold text-emerald-950 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>

                    <label class="mt-5 block text-sm font-black text-emerald-950">Password</label>
                    <input name="password" type="password" class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 font-semibold text-emerald-950 outline-none transition focus:border-emerald-400 focus:ring-4 focus:ring-emerald-100" required>

                    <label class="mt-5 flex items-center gap-3 text-sm font-semibold text-slate-600">
                        <input type="checkbox" name="remember" value="1" class="checkbox checkbox-sm border-emerald-200 [--chkbg:#047857] [--chkfg:white]">
                        Ingat saya
                    </label>

                    @error('email')<p class="mt-4 rounded-2xl bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">{{ $message }}</p>@enderror

                    <p class="mt-5 text-right text-sm">
                        <a class="font-black text-emerald-700 hover:text-emerald-800" href="{{ route('password.request') }}">Lupa password?</a>
                    </p>

                    <button class="mt-7 w-full rounded-2xl bg-emerald-700 px-4 py-3.5 font-black text-white shadow-lg shadow-emerald-950/15 transition hover:bg-emerald-800">Login</button>

                    <p class="mt-6 text-center text-sm text-slate-600">
                        Belum punya akun?
                        <a class="font-black text-emerald-700 hover:text-emerald-800" href="{{ route('register') }}">Register</a>
                    </p>
                </form>
            </div>
        </div>
    </section>
</x-layouts.public>

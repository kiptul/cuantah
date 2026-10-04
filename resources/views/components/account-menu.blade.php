@php
    $user = auth()->user();
    $inisial = str($user->name)->substr(0, 1)->upper();
@endphp

{{-- Menu akun. Sebelumnya header hanya menyediakan tombol Logout, sehingga
     tidak ada satu pun jalan menuju pengaturan akun dari dalam aplikasi. --}}
<div class="relative" data-account>
    <button type="button"
            class="flex items-center gap-2 rounded-full py-1 pl-1 pr-2.5 text-sm font-bold text-slate-700 transition hover:bg-slate-100"
            aria-haspopup="true" aria-expanded="false" data-account-toggle>
        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-700 text-xs font-black text-white">{{ $inisial }}</span>
        <span class="hidden max-w-[9rem] truncate sm:block">{{ $user->name }}</span>
        <svg class="h-3.5 w-3.5 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m6 9 6 6 6-6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>

    <div class="fixed z-50 hidden w-56 overflow-hidden rounded-2xl bg-white shadow-xl shadow-slate-950/10 ring-1 ring-slate-900/10"
         data-account-panel role="menu">
        <div class="border-b border-slate-100 px-4 py-3">
            <p class="truncate text-sm font-bold text-slate-900">{{ $user->name }}</p>
            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" role="menuitem">
            <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
                <circle cx="12" cy="7" r="4" stroke="currentColor" stroke-width="1.9" />
            </svg>
            Pengaturan akun
        </a>
        <form method="post" action="{{ route('logout') }}" class="border-t border-slate-100">
            @csrf
            <button class="flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm font-semibold text-rose-700 transition hover:bg-rose-50" role="menuitem">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                Keluar
            </button>
        </form>
    </div>
</div>

<script>
    (() => {
        const root = document.currentScript.previousElementSibling;
        if (!root || !root.matches('[data-account]')) return;

        const toggle = root.querySelector('[data-account-toggle]');
        const panel = root.querySelector('[data-account-panel]');

        // Panel dipasang fixed, bukan absolute: bilah nav induknya memakai
        // overflow-x-auto sehingga panel absolute ikut terpotong setinggi bilah.
        const posisikan = () => {
            const tombol = toggle.getBoundingClientRect();
            const kiri = Math.min(
                Math.max(8, tombol.right - panel.offsetWidth),
                window.innerWidth - panel.offsetWidth - 8,
            );
            panel.style.left = Math.round(kiri) + 'px';
            panel.style.top = Math.round(tombol.bottom + 8) + 'px';
        };

        const tutup = () => {
            panel.classList.add('hidden');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            if (!panel.classList.contains('hidden')) return tutup();

            panel.classList.remove('hidden');
            posisikan();
            toggle.setAttribute('aria-expanded', 'true');
        });

        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) tutup();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') tutup();
        });
        window.addEventListener('resize', tutup);
        window.addEventListener('scroll', () => {
            if (!panel.classList.contains('hidden')) posisikan();
        }, { passive: true });
    })();
</script>

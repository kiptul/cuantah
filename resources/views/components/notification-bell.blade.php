@php
    $notifications = auth()->user()?->notifications()->latest()->limit(8)->get() ?? collect();
    $unread = $notifications->whereNull('read_at')->count();
@endphp

{{-- Lonceng dengan titik merah. Notifikasi sebelumnya hanya terlihat bila
     penyetor kebetulan membuka dasbor dan menggulir sampai bawah. --}}
<div class="relative" data-bell>
    <button type="button"
            class="relative flex h-10 w-10 items-center justify-center rounded-full text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
            aria-haspopup="true" aria-expanded="false" aria-label="Notifikasi{{ $unread ? ", $unread belum dibaca" : '' }}"
            data-bell-toggle>
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M18 8.5a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16s-2-1.5-2-6.5Z" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" />
            <path d="M10.3 19a2 2 0 0 0 3.4 0" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" />
        </svg>
        @if($unread)
            <span class="absolute right-1.5 top-1.5 flex h-2.5 w-2.5" data-bell-dot>
                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-60 motion-reduce:hidden"></span>
                <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600 ring-2 ring-white"></span>
            </span>
        @endif
    </button>

    {{-- Latar gelap hanya di layar kecil. Tanpa itu panel terbaca seperti
         kotak yang menempel asal, sebab konten di belakangnya tetap terang
         dan tidak ada yang menandai bahwa fokus sedang berpindah. --}}
    <div class="fixed inset-x-0 bottom-0 z-40 hidden bg-slate-950/40 sm:hidden" data-bell-backdrop aria-hidden="true"></div>

    <div class="fixed inset-x-3 z-50 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-950/25 sm:absolute sm:inset-x-auto sm:right-0 sm:top-full sm:mt-2 sm:w-[22rem] sm:shadow-xl sm:shadow-slate-950/10"
         data-bell-panel role="menu">
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
            <p class="text-sm font-black text-slate-900">Notifikasi</p>
            @if($unread)
                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-bold text-red-700">{{ $unread }} baru</span>
            @endif
        </div>

        <div class="max-h-[22rem] divide-y divide-slate-100 overflow-y-auto">
            @forelse($notifications as $notification)
                <div @class(['px-4 py-3', 'bg-slate-50' => $notification->read_at === null])>
                    <div class="flex items-start gap-2.5">
                        @if($notification->read_at === null)
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-red-600" aria-hidden="true"></span>
                        @else
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0" aria-hidden="true"></span>
                        @endif
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-900">{{ $notification->title }}</p>
                            <p class="mt-0.5 text-sm leading-6 text-slate-600">{{ $notification->message }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-slate-500">Belum ada notifikasi.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    (() => {
        const root = document.currentScript.previousElementSibling;
        if (!root || !root.matches('[data-bell]')) return;

        const toggle = root.querySelector('[data-bell-toggle]');
        const panel = root.querySelector('[data-bell-panel]');
        const dot = root.querySelector('[data-bell-dot]');
        const backdrop = root.querySelector('[data-bell-backdrop]');
        let sudahDitandai = false;

        const tutup = () => {
            panel.classList.add('hidden');
            backdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            toggle.setAttribute('aria-expanded', 'false');
        };

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            const terbuka = !panel.classList.contains('hidden');
            if (terbuka) return tutup();

            if (window.matchMedia('(max-width: 639px)').matches) {
                const header = document.querySelector('header');
                const bawah = header ? Math.round(header.getBoundingClientRect().bottom) : 64;
                panel.style.top = (bawah + 8) + 'px';
                backdrop.style.top = bawah + 'px';
            } else {
                panel.style.top = '';
                backdrop.style.top = '';
            }

            panel.classList.remove('hidden');
            backdrop.classList.remove('hidden');
            // Gulir halaman dikunci selama panel terbuka di layar kecil,
            // supaya latar tidak bergeser di belakang panel.
            if (window.matchMedia('(max-width: 639px)').matches) {
                document.body.classList.add('overflow-hidden');
            }
            toggle.setAttribute('aria-expanded', 'true');

            // Ditandai hanya sekali, dan hanya ketika isinya benar-benar dibuka.
            if (dot && !sudahDitandai) {
                sudahDitandai = true;
                fetch(@json(route('notifications.read')), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                }).then(() => dot.remove()).catch(() => { sudahDitandai = false; });
            }
        });

        backdrop.addEventListener('click', tutup);
        document.addEventListener('click', (event) => {
            if (!root.contains(event.target)) tutup();
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') tutup();
        });
        // Lebar layar berubah saat panel terbuka membuat posisi dan kunci
        // guliran tidak lagi sesuai, jadi panel ditutup saja.
        window.addEventListener('resize', tutup);
    })();
</script>

<x-layouts.app :title="$title ?? 'Admin CUANTAH'">
    <x-slot:head>
        {{ $head ?? '' }}
    </x-slot:head>
    @php
        $adminNavItems = [
            ['label' => 'Ringkasan', 'url' => route('admin.dashboard'), 'route' => 'admin.dashboard', 'icon' => 'grid'],
            ['label' => 'Transaksi', 'url' => route('admin.transactions.index'), 'route' => 'admin.transactions.*', 'icon' => 'receipt'],
            ['label' => 'Pickup', 'url' => route('admin.pickups.index'), 'route' => 'admin.pickups.*', 'icon' => 'truck'],
            ['label' => 'Mitra', 'url' => route('admin.partners.index'), 'route' => 'admin.partners.*', 'icon' => 'users'],
            ['label' => 'Pengguna', 'url' => route('admin.users.index'), 'route' => 'admin.users.*', 'icon' => 'user'],
            ['label' => 'Harga', 'url' => route('admin.prices.index'), 'route' => 'admin.prices.*', 'icon' => 'tag'],
            ['label' => 'Penyaluran', 'url' => route('admin.distributions.index'), 'route' => 'admin.distributions.*', 'icon' => 'arrow'],
            ['label' => 'Laporan', 'url' => route('admin.reports.index'), 'route' => 'admin.reports.*', 'icon' => 'chart'],
        ];
    @endphp

    <div class="grid gap-5 lg:grid-cols-[238px_minmax(0,1fr)] lg:gap-7">
        <aside class="h-fit rounded-2xl border border-slate-200 bg-white p-3 shadow-sm shadow-slate-950/5 lg:sticky lg:top-24">
            <div class="border-b border-slate-100 px-3 pb-3">
                <p class="text-[11px] font-black uppercase tracking-[0.16em] text-slate-400">Operasional</p>
                <p class="mt-1 text-sm font-black text-slate-900">Kelola CUANTAH</p>
            </div>
            {{-- Membungkus, bukan menggulir. Dengan gulir mendatar, menu sesudah
                 item keempat tidak pernah terlihat di layar kecil karena tidak
                 ada isyarat bahwa area ini bisa digeser. --}}
            <nav class="mt-3 flex flex-wrap gap-1 pb-1 lg:block" aria-label="Navigasi admin">
                @foreach($adminNavItems as $item)
                    <a
                        href="{{ $item['url'] }}"
                        @class([
                            'flex shrink-0 items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition lg:mb-1',
                            'bg-emerald-700 text-white shadow-sm shadow-emerald-900/20' => request()->routeIs($item['route']),
                            'text-slate-600 hover:bg-emerald-50 hover:text-emerald-800' => ! request()->routeIs($item['route']),
                        ])
                    >
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-current/10" aria-hidden="true">
                            @if($item['icon'] === 'grid')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M4 4H10V10H4V4ZM14 4H20V10H14V4ZM4 14H10V20H4V14ZM14 14H20V20H14V14Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /></svg>
                            @elseif($item['icon'] === 'truck')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M3 6H14V16H3V6ZM14 9H18L21 12V16H14V9Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /><path d="M7 19A2 2 0 1 0 7 15A2 2 0 0 0 7 19ZM17 19A2 2 0 1 0 17 15A2 2 0 0 0 17 19Z" fill="currentColor" /></svg>
                            @elseif($item['icon'] === 'users')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M16 20V18C16 15.8 14.2 14 12 14H7C4.8 14 3 15.8 3 18V20M9.5 10C11.4 10 13 8.4 13 6.5C13 4.6 11.4 3 9.5 3C7.6 3 6 4.6 6 6.5C6 8.4 7.6 10 9.5 10ZM17 4C18.7 4.5 20 6 20 8C20 10 18.7 11.5 17 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                            @elseif($item['icon'] === 'user')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M20 21V19C20 16.8 18.2 15 16 15H8C5.8 15 4 16.8 4 19V21M12 11C14.2 11 16 9.2 16 7C16 4.8 14.2 3 12 3C9.8 3 8 4.8 8 7C8 9.2 9.8 11 12 11Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                            @elseif($item['icon'] === 'tag')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M3 12V4H11L21 14L14 21L3 12Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /><path d="M7.5 8.5H7.51" stroke="currentColor" stroke-width="3" stroke-linecap="round" /></svg>
                            @elseif($item['icon'] === 'arrow')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M5 7H19M19 7L15 3M19 7L15 11M19 17H5M5 17L9 13M5 17L9 21" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>
                            @elseif($item['icon'] === 'chart')
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M4 19V5M4 19H20M8 16V11M12 16V7M16 16V9" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                            @else
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none"><path d="M5 4H19V20H5V4ZM8 8H16M8 12H16M8 16H13" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                            @endif
                        </span>
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="mt-3 rounded-xl bg-emerald-50 px-3 py-3 text-xs leading-5 text-emerald-800">
                Pantau transaksi, pickup, dan penyaluran dari satu tempat.
            </div>
        </aside>
        <section class="min-w-0">{{ $slot }}</section>
    </div>
</x-layouts.app>

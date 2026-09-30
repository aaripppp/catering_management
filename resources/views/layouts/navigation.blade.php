@php
    $isAdmin = auth()->user()?->isAdmin() ?? false;

    $navigation = [
        [
            'label' => null,
            'items' => [
                ['name' => 'Dashboard', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard'), 'icon' => 'home'],
            ],
        ],
        [
            'label' => 'Master Data',
            'items' => $isAdmin ? [
                ['name' => 'Peserta Catering', 'href' => route('catering-members.index'), 'active' => request()->routeIs('catering-members.*'), 'icon' => 'users'],
                ['name' => 'Kelas', 'href' => route('school-classes.index'), 'active' => request()->routeIs('school-classes.*'), 'icon' => 'class'],
                ['name' => 'Kategori Harga', 'href' => route('catering-categories.index'), 'active' => request()->routeIs('catering-categories.*'), 'icon' => 'tag'],
            ] : [],
        ],
        [
            'label' => 'Catering',
            'items' => [
                ['name' => 'Absensi Catering', 'href' => route('catering-attendance.index'), 'active' => request()->routeIs('catering-attendance.*'), 'icon' => 'calendar'],
                ['name' => 'Rekap Absensi', 'href' => '#', 'icon' => 'chart', 'disabled' => true],
                ['name' => 'Tagihan Bulanan', 'href' => '#', 'icon' => 'receipt', 'disabled' => true],
            ],
        ],
        [
            'label' => 'Laporan',
            'items' => [
                ['name' => 'Laporan', 'href' => '#', 'icon' => 'document', 'disabled' => true],
            ],
        ],
    ];
@endphp

<div
    x-cloak
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-30 bg-navy-950/60 lg:hidden"
    aria-hidden="true"
></div>

<aside
    id="sidebar"
    class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-white/5 bg-navy-900 shadow-xl shadow-slate-950/10 transition-transform duration-200 lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    aria-label="Navigasi utama"
>
    <div class="flex h-20 shrink-0 items-center gap-3 border-b border-white/10 px-5">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white p-1.5 shadow-sm">
            <x-application-logo class="h-full w-full object-contain" />
        </span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold tracking-tight text-white">Catering Management</p>
            <p class="truncate text-[11px] text-slate-400">Sistem Manajemen Catering Sekolah</p>
        </div>
        <button type="button" class="ml-auto rounded-lg p-1.5 text-slate-400 hover:bg-white/10 hover:text-white lg:hidden" @click="sidebarOpen = false" aria-label="Tutup navigasi">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
            </svg>
        </button>
    </div>

    <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-5">
        @foreach ($navigation as $section)
            @if (empty($section['items']))
                @continue
            @endif

            <div>
                @if ($section['label'])
                    <p class="sidebar-heading">{{ $section['label'] }}</p>
                @endif

                <ul class="space-y-1">
                    @foreach ($section['items'] as $item)
                        @php
                            $isActive = $item['active'] ?? false;
                            $isDisabled = $item['disabled'] ?? false;
                        @endphp
                        <li>
                            <a
                                href="{{ $item['href'] }}"
                                @if ($isActive) aria-current="page" @endif
                                @if ($isDisabled) aria-disabled="true" tabindex="-1" @endif
                                @class([
                                    'sidebar-link',
                                    'sidebar-link-active' => $isActive,
                                    'sidebar-link-idle' => ! $isActive && ! $isDisabled,
                                    'sidebar-link-disabled' => $isDisabled,
                                ])
                            >
                                <svg class="h-[18px] w-[18px] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                    @switch($item['icon'])
                                        @case('home')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m3 10.5 9-7.5 9 7.5v9A1.5 1.5 0 0 1 19.5 21h-15A1.5 1.5 0 0 1 3 19.5v-9Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 21v-6h6v6" />
                                            @break
                                        @case('users')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.1a8.5 8.5 0 0 0-3-.52c-1.05 0-2.06.19-3 .52m6 0a6 6 0 0 1 3.3 2.1M15 19.1a4.5 4.5 0 1 0-6 0m0 0a6 6 0 0 0-3.3 2.1M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                                            @break
                                        @case('class')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25A2.25 2.25 0 0 1 6 3h12a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 18 21H6a2.25 2.25 0 0 1-2.25-2.25V5.25Z" />
                                            <path stroke-linecap="round" d="M8 8h8M8 12h5" />
                                            @break
                                        @case('tag')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75V12l6.75 6.75a2.12 2.12 0 0 0 3 0l4.5-4.5a2.12 2.12 0 0 0 0-3L12 4.5H6.75A2.25 2.25 0 0 0 4.5 6.75Z" />
                                            <path stroke-linecap="round" d="M8.25 8.25h.008v.008H8.25z" />
                                            @break
                                        @case('calendar')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 4.5h13.5A1.5 1.5 0 0 1 20.25 6v13.5h-16.5V6a1.5 1.5 0 0 1 1.5-1.5Z" />
                                            @break
                                        @case('chart')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5h15M7.5 16.5v-6m4.5 6v-12m4.5 12v-9" />
                                            @break
                                        @case('receipt')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 3.75h12v16.5l-3-1.5-3 1.5-3-1.5-3 1.5V3.75Z" />
                                            <path stroke-linecap="round" d="M9 8.25h6M9 12h6" />
                                            @break
                                        @default
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3.75h7.5l3 3v13.5H6.75V3.75Z" />
                                            <path stroke-linecap="round" d="M9 11.25h6M9 15h6" />
                                    @endswitch
                                </svg>
                                <span class="truncate">{{ $item['name'] }}</span>
                                @if ($isDisabled)
                                    <span class="ml-auto text-[9px] font-semibold uppercase tracking-wide text-slate-600">Segera</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-white/10 p-3">
        <p class="sidebar-heading">Pengaturan</p>
        <div class="space-y-1">
            <span class="sidebar-link sidebar-link-disabled">
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 21a6 6 0 0 0-9 0M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
                Pengguna
            </span>
            <a href="{{ route('profile.edit') }}" @class(['sidebar-link', 'sidebar-link-active' => request()->routeIs('profile.*'), 'sidebar-link-idle' => ! request()->routeIs('profile.*')])>
                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.6 3.6h4.8l.6 2.1 1.9 1.1 2.1-.6 2.4 4.2-1.5 1.6v2l1.5 1.6-2.4 4.2-2.1-.6-1.9 1.1-.6 2.1H9.6L9 20.3l-1.9-1.1-2.1.6-2.4-4.2L4.1 14v-2l-1.5-1.6L5 6.2l2.1.6L9 5.7l.6-2.1Z" />
                    <circle cx="12" cy="13" r="3" />
                </svg>
                Pengaturan
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="sidebar-link sidebar-link-idle w-full">
                    <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 8.25V5.625A2.625 2.625 0 0 0 11.625 3h-6A2.625 2.625 0 0 0 3 5.625v12.75A2.625 2.625 0 0 0 5.625 21h6a2.625 2.625 0 0 0 2.625-2.625V15.75M17.25 8.25 21 12m0 0-3.75 3.75M21 12H9" />
                    </svg>
                    Keluar
                </button>
            </form>
        </div>
    </div>
</aside>

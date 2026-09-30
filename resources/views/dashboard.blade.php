<x-app-layout>
    <x-slot name="header">
        Dashboard
    </x-slot>
    <x-slot name="description">Ringkasan dan akses cepat Catering Management.</x-slot>

    <div class="space-y-6">
        <livewire:catering-dashboard.index />

        <section class="card overflow-hidden">
            <div class="card-body flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <div class="icon-box h-12 w-12 rounded-xl">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5M5.25 5.25h13.5A1.5 1.5 0 0 1 19.5 6.75v10.5a1.5 1.5 0 0 1-1.5 1.5H6a1.5 1.5 0 0 1-1.5-1.5V6.75a1.5 1.5 0 0 1 1.5-1.5Z" />
                            <path stroke-linecap="round" d="M8 9h.01M8 15h.01" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-blue-600">Selamat datang</p>
                        <h2 class="mt-1 text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
                            Halo, {{ auth()->user()->name }}
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                            Catering Management membantu pengelolaan peserta, kelas, dan kategori harga catering sekolah dalam satu tempat.
                        </p>
                    </div>
                </div>
                <div class="shrink-0 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-left sm:text-right">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Akses sebagai</p>
                    <p class="mt-1 text-sm font-semibold text-slate-800">{{ auth()->user()->role->label() }}</p>
                </div>
            </div>
        </section>

        @if (auth()->user()->isAdmin())
            <section>
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-slate-900">Akses Cepat</h2>
                    <p class="mt-1 text-sm text-slate-500">Buka master data yang paling sering digunakan.</p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <a href="{{ route('catering-members.index') }}" class="card group card-body flex items-start gap-4 transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                        <div class="icon-box">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.1a8.5 8.5 0 0 0-3-.52c-1.05 0-2.06.19-3 .52m6 0a6 6 0 0 1 3.3 2.1M15 19.1a4.5 4.5 0 1 0-6 0m0 0a6 6 0 0 0-3.3 2.1M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-slate-900 group-hover:text-blue-700">Peserta Catering</h3>
                            <p class="mt-1 text-sm leading-5 text-slate-500">Kelola data peserta dan informasi wali.</p>
                            <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-blue-600">
                                Buka data
                                <span aria-hidden="true">&rarr;</span>
                            </span>
                        </div>
                    </a>

                    <a href="{{ route('school-classes.index') }}" class="card group card-body flex items-start gap-4 transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                        <div class="icon-box">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25A2.25 2.25 0 0 1 6 3h12a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 18 21H6a2.25 2.25 0 0 1-2.25-2.25V5.25Z" />
                                <path stroke-linecap="round" d="M8 8h8M8 12h5" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-slate-900 group-hover:text-blue-700">Kelas</h3>
                            <p class="mt-1 text-sm leading-5 text-slate-500">Atur kelas untuk pengelompokan peserta.</p>
                            <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-blue-600">
                                Buka data
                                <span aria-hidden="true">&rarr;</span>
                            </span>
                        </div>
                    </a>

                    <a href="{{ route('catering-categories.index') }}" class="card group card-body flex items-start gap-4 transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                        <div class="icon-box">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75V12l6.75 6.75a2.12 2.12 0 0 0 3 0l4.5-4.5a2.12 2.12 0 0 0 0-3L12 4.5H6.75A2.25 2.25 0 0 0 4.5 6.75Z" />
                                <path stroke-linecap="round" d="M8.25 8.25h.008v.008H8.25z" />
                            </svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="font-semibold text-slate-900 group-hover:text-blue-700">Kategori Harga</h3>
                            <p class="mt-1 text-sm leading-5 text-slate-500">Kelola paket dan harga catering harian.</p>
                            <span class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-blue-600">
                                Buka data
                                <span aria-hidden="true">&rarr;</span>
                            </span>
                        </div>
                    </a>
                </div>
            </section>
        @endif

        <section class="alert-info flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v4.5m0 3h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div>
                <p class="font-medium">Sistem siap digunakan</p>
                <p class="mt-0.5 text-xs text-blue-700/80">Modul lanjutan masih ditandai sebagai menu yang belum tersedia.</p>
            </div>
        </div>
        </section>
    </div>
</x-app-layout>

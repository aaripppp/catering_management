<div class="space-y-5" wire:poll.15s="pollRecap">

    {{-- Toolbar: selected date, badge, quick actions and date picker --}}
    <section class="card">
        <div class="card-body flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-semibold uppercase tracking-wide text-slate-900">Kebutuhan Catering Per Tanggal</h2>
                    @if ($recap['badge'])
                        <span class="badge badge-info">{{ $recap['badge'] }}</span>
                    @endif
                </div>
                <p class="mt-1 text-2xl font-bold tracking-tight text-slate-900">{{ $recap['dateLabel'] }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ $recap['weekdayLabel'] }}</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                <div class="flex gap-2">
                    <button type="button" wire:click="gotoYesterday"
                        @class([
                            'btn btn-sm',
                            'btn-primary' => $selectedDate === $yesterday,
                            'btn-secondary' => $selectedDate !== $yesterday,
                        ])>Kemarin</button>
                    <button type="button" wire:click="gotoToday"
                        @class([
                            'btn btn-sm',
                            'btn-primary' => $selectedDate === $today,
                            'btn-secondary' => $selectedDate !== $today,
                        ])>Hari Ini</button>
                    <button type="button" wire:click="gotoTomorrow"
                        @class([
                            'btn btn-sm',
                            'btn-primary' => $selectedDate === $tomorrow,
                            'btn-secondary' => $selectedDate !== $tomorrow,
                        ])>Besok</button>
                </div>

                <div>
                    <label for="recap-date" class="label">Pilih Tanggal</label>
                    <input id="recap-date" type="date" class="input" value="{{ $selectedDate }}"
                        wire:change="changeDate($event.target.value)" />
                </div>

                <a href="{{ route('catering-attendance.index') }}" class="btn btn-sm btn-secondary">Kelola Absensi</a>
            </div>
        </div>
    </section>

    {{-- Saved data status. Never claims a final figure, the cutoff is a manual SOP. --}}
    @if (! $recap['hasSavedData'])
        <section class="alert-info flex items-start gap-3">
            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <div>
                <p class="text-sm font-semibold">Belum ada data absensi catering tersimpan untuk tanggal ini.</p>
                <p class="mt-0.5 text-xs">
                    Ringkasan hanya memakai absensi yang sudah disimpan di <strong>Absensi Catering</strong>.
                    Status default pada matriks absensi belum dihitung sebagai porsi.
                </p>
            </div>
        </section>
    @else
        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-success">Data Tersimpan</span>
            <span class="text-xs text-slate-500">
                {{ number_format($recap['savedRows'], 0, ',', '.') }} baris absensi tersimpan pada tanggal ini.
                @if ($recap['savedRows'] < $recap['activeParticipants'])
                    Absensi tersimpan untuk {{ number_format($recap['savedRows'], 0, ',', '.') }} dari
                    {{ number_format($recap['activeParticipants'], 0, ',', '.') }} peserta aktif.
                @endif
            </span>
        </div>
    @endif

    @if ($recap['hasSavedData'])
        {{-- Overall portion total --}}
        <section class="card overflow-hidden">
            <div class="card-body flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Total Keseluruhan</p>
                    <p class="mt-1 text-sm text-slate-500">Total porsi dari status <strong>Ikut</strong> pada {{ $recap['dateLabel'] }}</p>
                </div>
                <div class="flex items-baseline gap-2 sm:text-right">
                    <span class="text-5xl font-bold leading-none tracking-tight text-slate-900">{{ number_format($recap['totalPortions'], 0, ',', '.') }}</span>
                    <span class="text-lg font-semibold text-slate-500">Porsi</span>
                </div>
            </div>
        </section>

        @if ($recap['totalPortions'] === 0)
            <section class="alert-success flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <div>
                    <p class="text-sm font-semibold">
                        0 Porsi · {{ $recap['liburRows'] === $recap['savedRows'] ? 'Libur / tidak ada catering' : 'Tidak ada peserta yang ikut' }}
                    </p>
                    <p class="mt-0.5 text-xs">
                        {{ number_format($recap['tidakIkutRows'], 0, ',', '.') }} baris Tidak Ikut &middot;
                        {{ number_format($recap['liburRows'], 0, ',', '.') }} baris Libur.
                    </p>
                </div>
            </section>
        @endif

        {{-- Employee placement data health --}}
        @if ($recap['unassignedEmployees'] > 0)
            <section class="alert-danger flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <div>
                        <p class="text-sm font-semibold">Pegawai belum memiliki penempatan: {{ $recap['unassignedEmployees'] }}</p>
                        <p class="mt-0.5 text-xs">
                            Pegawai ini tidak dihitung ke kelas, tingkat, maupun jenjang karena penempatannya belum diketahui.
                        </p>
                    </div>
                </div>
                @if ($recap['unplaced']['total'] > 0)
                    <button type="button" class="btn btn-sm btn-secondary shrink-0"
                        wire:click="openDetail('unplaced')">Lihat Daftar</button>
                @endif
            </section>
        @endif

        {{-- Jenjang sections --}}
        @foreach ($recap['jenjangs'] as $jenjang)
            @if ($jenjang['total'] > 0)
                <section class="card overflow-hidden">
                    <div class="card-header flex flex-wrap items-center justify-between gap-2">
                        <h3 class="card-title">{{ $jenjang['label'] }}</h3>
                        <span class="badge badge-info">Total {{ number_format($jenjang['total'], 0, ',', '.') }}</span>
                    </div>

                    <div class="table-wrap">
                        <table class="table w-full">
                            <thead>
                                <tr>
                                    <th>Kelas</th>
                                    <th class="text-center">Siswa</th>
                                    <th class="text-center">Guru/Pegawai</th>
                                    <th class="text-center">Total</th>
                                    <th class="w-28 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @if ($jenjang['employeeTotal'] > 0)
                                    <tr data-employee-assignment="education-level" data-employee-total="{{ $jenjang['employeeTotal'] }}">
                                        <td class="pl-8 text-slate-600">Guru Jenjang</td>
                                        <td class="text-center text-slate-400">—</td>
                                        <td class="text-center font-semibold text-blue-700">{{ number_format($jenjang['employeeTotal'], 0, ',', '.') }}</td>
                                        <td class="text-center font-semibold text-slate-800">{{ number_format($jenjang['employeeTotal'], 0, ',', '.') }}</td>
                                        <td class="text-right">
                                            <button type="button" class="btn btn-sm btn-ghost"
                                                wire:click="openDetail('jenjang:{{ $jenjang['label'] }}')">
                                                Lihat Detail
                                            </button>
                                        </td>
                                    </tr>
                                @endif

                                @foreach ($jenjang['levels'] as $level)
                                    @if ($level['total'] > 0)
                                        <tr class="bg-slate-50/80" data-parent-subtotal="level" data-parent-total="{{ $level['total'] }}">
                                            <td colspan="3" class="font-semibold text-slate-800">{{ $level['label'] }}</td>
                                            <td class="text-center font-semibold text-slate-800">{{ number_format($level['total'], 0, ',', '.') }}</td>
                                            <td></td>
                                        </tr>

                                        @if ($level['employeeTotal'] > 0)
                                            <tr data-employee-assignment="level" data-employee-total="{{ $level['employeeTotal'] }}">
                                                <td class="pl-8 text-slate-600">Guru Tingkat</td>
                                                <td class="text-center text-slate-400">—</td>
                                                <td class="text-center font-semibold text-blue-700">{{ number_format($level['employeeTotal'], 0, ',', '.') }}</td>
                                                <td class="text-center font-semibold text-slate-800">{{ number_format($level['employeeTotal'], 0, ',', '.') }}</td>
                                                <td class="text-right">
                                                    <button type="button" class="btn btn-sm btn-ghost"
                                                        wire:click="openDetail('level:{{ $jenjang['label'] }}:{{ $level['value'] }}')">
                                                        Lihat Detail
                                                    </button>
                                                </td>
                                            </tr>
                                        @endif

                                        @foreach ($level['classes'] as $class)
                                            <tr data-parent-subtotal="class" data-parent-total="{{ $class['total'] }}">
                                                <td class="pl-8 font-medium text-slate-900">{{ $class['name'] }}</td>
                                                <td class="text-center">{{ number_format($class['students'], 0, ',', '.') }}</td>
                                                <td class="text-center">{{ number_format($class['employees'], 0, ',', '.') }}</td>
                                                <td class="text-center font-semibold text-slate-900">{{ number_format($class['total'], 0, ',', '.') }}</td>
                                                <td class="text-right">
                                                    <button type="button" class="btn btn-sm btn-ghost"
                                                        wire:click="openDetail('class:{{ $class['id'] }}')">
                                                        Lihat Detail
                                                    </button>
                                                </td>
                                            </tr>

                                            @if ($class['employees'] > 0)
                                                <tr data-employee-assignment="class" data-employee-total="{{ $class['employees'] }}">
                                                    <td class="pl-12 text-slate-600">Guru Kelas</td>
                                                    <td class="text-center text-slate-400">—</td>
                                                    <td class="text-center font-semibold text-blue-700">{{ number_format($class['employees'], 0, ',', '.') }}</td>
                                                    <td class="text-center font-semibold text-slate-800">{{ number_format($class['employees'], 0, ',', '.') }}</td>
                                                    <td></td>
                                                </tr>
                                            @endif
                                        @endforeach
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endforeach

        {{-- Buckets outside the jenjang hierarchy --}}
        @foreach ([
            'general' => 'Pegawai Umum',
            'unplaced' => 'Belum Ditempatkan',
            'classless' => 'Siswa Tanpa Kelas',
        ] as $bucketKey => $bucketLabel)
            @if ($recap[$bucketKey]['total'] > 0)
                <section class="card overflow-hidden"
                    @if ($bucketKey === 'general') data-employee-assignment="general" data-employee-total="{{ $recap[$bucketKey]['total'] }}" @endif>
                    <div class="card-header flex flex-wrap items-center justify-between gap-2">
                        <h3 class="card-title">{{ $bucketLabel }}</h3>
                        <span class="badge badge-info">Total {{ number_format($recap[$bucketKey]['total'], 0, ',', '.') }}</span>
                    </div>
                    <div class="card-body">
                        <button type="button" class="btn btn-sm btn-secondary"
                            wire:click="openDetail('{{ $bucketKey }}')">Lihat Detail Nama</button>
                    </div>
                </section>
            @endif
        @endforeach
    @endif

    {{-- Detail modal. The panel is capped and centred, and only the participant
         list scrolls, so a long class never grows the modal past the viewport. --}}
    @if ($detailKey !== null)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeDetail()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4 sm:p-6" role="dialog" aria-modal="true"
            aria-labelledby="recap-detail-title">
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" wire:click="closeDetail" aria-hidden="true"></div>

            <div
                class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-3">
                    <div class="min-w-0">
                        <h3 id="recap-detail-title" class="truncate text-sm font-semibold leading-5 text-slate-900">
                            {{ $detailHeading['title'] ?? 'Detail Peserta' }}
                        </h3>
                        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                            @if (! empty($detailHeading['subtitle']))
                                <p class="text-xs text-slate-500">{{ $detailHeading['subtitle'] }}</p>
                            @endif
                            <span class="badge badge-info">{{ count($detailRows) }} peserta</span>
                        </div>
                    </div>
                    <button type="button"
                        class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                        wire:click="closeDetail" aria-label="Tutup detail">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Only this area scrolls, so the header and footer stay put. --}}
                <div class="min-h-0 flex-1 overflow-y-auto overflow-x-auto">
                    <table class="recap-detail-table table w-full">
                        <thead class="sticky top-0 z-10">
                            <tr>
                                <th class="w-12 bg-slate-50">No</th>
                                <th class="bg-slate-50">Nama</th>
                                <th class="w-28 bg-slate-50">Tipe</th>
                                <th class="w-24 bg-slate-50">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($detailRows as $index => $row)
                                <tr>
                                    <td class="text-slate-400">{{ $index + 1 }}</td>
                                    <td class="font-medium text-slate-900">{{ $row['name'] }}</td>
                                    <td>
                                        <span @class([
                                            'badge',
                                            'badge-info' => $row['type'] === 'Pegawai',
                                            'badge-neutral' => $row['type'] !== 'Pegawai',
                                        ])>{{ $row['type'] }}</span>
                                    </td>
                                    <td class="text-slate-600">{{ $row['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-2.5">
                    <p class="text-xs text-slate-500">{{ count($detailRows) }} peserta dihitung dalam porsi.</p>
                    <button type="button" class="btn btn-sm btn-secondary" wire:click="closeDetail">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>

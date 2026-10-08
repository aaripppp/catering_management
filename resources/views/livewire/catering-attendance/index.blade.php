<div
    class="space-y-5"
    x-data="{
        bulkDate: null,
        bulkDateLabel: '',
        bulkPickerStyle: '',
        bulkTrigger: null,
        changeFilter(filter, element) {
            $wire.changeFilter(filter, element.value);
        },
        openBulkStatusPicker(date, label, event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const width = Math.min(240, window.innerWidth - 16);
            const estimatedHeight = 230;
            const left = Math.min(
                Math.max(8, rect.left + (rect.width / 2) - (width / 2)),
                window.innerWidth - width - 8,
            );
            const top = rect.bottom + estimatedHeight + 8 <= window.innerHeight
                ? rect.bottom + 6
                : Math.max(8, rect.top - estimatedHeight - 6);

            this.bulkDate = date;
            this.bulkDateLabel = label;
            this.bulkTrigger = event.currentTarget;
            this.bulkPickerStyle = `left: ${left}px; top: ${top}px; width: ${width}px;`;
            this.$nextTick(() => this.$refs.bulkStatusPicker?.querySelector('button')?.focus());
        },
        closeBulkStatusPicker(returnFocus = false) {
            const trigger = this.bulkTrigger;

            this.bulkDate = null;

            if (returnFocus) {
                this.$nextTick(() => trigger?.focus());
            }
        },
        applyBulkStatus(status) {
            const date = this.bulkDate;

            if (! date) {
                return;
            }

            this.closeBulkStatusPicker();
            this.$wire.setDateStatus(date, status);
        }
    }"
    x-on:resize.window="closeBulkStatusPicker()"
    x-on:keydown.escape.window="closeBulkStatusPicker(true)"
>
    @php
        $statusLabels = collect($statusOptions)->mapWithKeys(fn ($status) => [$status->value => $status->label()]);
        $statusSymbols = collect($statusOptions)->mapWithKeys(fn ($status) => [$status->value => $status->shorthand()]);
        $statusClasses = [
            'ikut' => 'border-emerald-700 bg-emerald-500 text-white hover:bg-emerald-600 focus-visible:ring-emerald-300',
            'sakit' => 'border-amber-600 bg-amber-400 text-amber-950 hover:bg-amber-500 focus-visible:ring-amber-300',
            'izin' => 'border-blue-700 bg-blue-500 text-white hover:bg-blue-600 focus-visible:ring-blue-300',
            'alfa' => 'border-red-700 bg-red-500 text-white hover:bg-red-600 focus-visible:ring-red-300',
            'tidak_ikut' => 'border-violet-800 bg-violet-600 text-white hover:bg-violet-700 focus-visible:ring-violet-300',
            'ujian' => 'border-cyan-700 bg-cyan-500 text-white hover:bg-cyan-600 focus-visible:ring-cyan-300',
            'event_unit' => 'border-fuchsia-800 bg-fuchsia-600 text-white hover:bg-fuchsia-700 focus-visible:ring-fuchsia-300',
            'puasa' => 'border-indigo-800 bg-indigo-600 text-white hover:bg-indigo-700 focus-visible:ring-indigo-300',
            'libur' => 'border-slate-800 bg-slate-600 text-white hover:bg-slate-700 focus-visible:ring-slate-300',
        ];
        $statusSelectedClasses = [
            'ikut' => 'scale-[1.03] shadow-md ring-4 ring-emerald-300 ring-offset-2',
            'sakit' => 'scale-[1.03] shadow-md ring-4 ring-amber-300 ring-offset-2',
            'izin' => 'scale-[1.03] shadow-md ring-4 ring-blue-300 ring-offset-2',
            'alfa' => 'scale-[1.03] shadow-md ring-4 ring-red-300 ring-offset-2',
            'tidak_ikut' => 'scale-[1.03] shadow-md ring-4 ring-violet-300 ring-offset-2',
            'ujian' => 'scale-[1.03] shadow-md ring-4 ring-cyan-300 ring-offset-2',
            'event_unit' => 'scale-[1.03] shadow-md ring-4 ring-fuchsia-300 ring-offset-2',
            'puasa' => 'scale-[1.03] shadow-md ring-4 ring-indigo-300 ring-offset-2',
            'libur' => 'scale-[1.03] shadow-md ring-4 ring-slate-300 ring-offset-2',
        ];
        $selectedClass = collect($classOptions)->firstWhere('id', (int) $schoolClassId);
        $selectedGroupLabel = $participantGroupOptions[$participantGroup] ?? 'Kelompok peserta';
        $matrixTitle = $requiresClassSelection ? ($selectedClass['name'] ?? 'Kelas terpilih') : $selectedGroupLabel;
    @endphp

    <section class="card card-body">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <div>
                <label for="attendance-month" class="label">Bulan</label>
                <select id="attendance-month" class="select" x-on:change="changeFilter('month', $event.target)">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}" @selected($month === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('month')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="attendance-year" class="label">Tahun</label>
                <select id="attendance-year" class="select" x-on:change="changeFilter('year', $event.target)">
                    @foreach ($yearOptions as $value)
                        <option value="{{ $value }}" @selected($year === $value)>{{ $value }}</option>
                    @endforeach
                </select>
                @error('year')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="attendance-participant-group" class="label">Kelompok Peserta</label>
                <select
                    id="attendance-participant-group"
                    class="select"
                    x-on:change="changeFilter('participantGroup', $event.target)"
                >
                    <option value="">Pilih kelompok peserta</option>
                    @foreach ($participantGroupOptions as $value => $label)
                        <option value="{{ $value }}" @selected($participantGroup === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('participantGroup')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div @class(['hidden' => ! $requiresClassSelection])>
                <label for="attendance-jenjang" class="label">Jenjang</label>
                <select id="attendance-jenjang" class="select" x-on:change="changeFilter('jenjang', $event.target)">
                    <option value="">Pilih jenjang</option>
                    @foreach ($jenjangOptions as $option)
                        <option value="{{ $option }}" @selected($jenjang === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @error('jenjang')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div @class(['hidden' => ! $requiresClassSelection])>
                <label for="attendance-class" class="label">Kelas</label>
                <select
                    id="attendance-class"
                    class="select"
                    x-on:change="changeFilter('schoolClassId', $event.target)"
                    @disabled($jenjang === '')
                >
                    <option value="">Pilih kelas</option>
                    @foreach ($classOptions as $schoolClass)
                        <option value="{{ $schoolClass['id'] }}" @selected($schoolClassId === (string) $schoolClass['id'])>
                            {{ $schoolClass['name'] }}
                        </option>
                    @endforeach
                </select>
                @error('schoolClassId')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-end">
                <button
                    type="button"
                    wire:click="generateAttendance"
                    wire:loading.attr="disabled"
                    wire:target="generateAttendance"
                    class="btn-primary w-full justify-center"
                >
                    <span wire:loading.remove wire:target="generateAttendance">Generate Kehadiran</span>
                    <span wire:loading wire:target="generateAttendance">Memproses...</span>
                </button>
            </div>
        </div>

        <div wire:loading.delay.flex wire:target="changeFilter,loadMatrix" class="mt-4 items-center gap-2 text-sm text-blue-600">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z" />
            </svg>
            Memuat matriks absensi...
        </div>
    </section>

    @if (! $matrixLoaded)
        <section class="card px-6 py-14 text-center">
            <div class="mx-auto flex max-w-md flex-col items-center">
                <div class="icon-box">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 8.25h16.5M5.25 4.5h13.5A1.5 1.5 0 0 1 20.25 6v13.5h-16.5V6a1.5 1.5 0 0 1 1.5-1.5Z" />
                    </svg>
                </div>
                <p class="mt-3 font-medium text-slate-700">
                    @unless ($hasValidParticipantGroup)
                        Pilih kelompok peserta terlebih dahulu.
                    @elseif ($requiresClassSelection)
                        Pilih jenjang dan kelas terlebih dahulu.
                    @else
                        Memuat data peserta...
                    @endunless
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    @if ($participantGroup === '' || $requiresClassSelection)
                        Matriks absensi akan muncul setelah kelompok peserta dan kelas dipilih.
                    @else
                        Matriks absensi akan muncul setelah kelompok peserta dipilih.
                    @endif
                </p>
            </div>
        </section>
    @else
        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ([
                ['label' => 'Jumlah Peserta', 'value' => $summary['participants'], 'class' => 'text-slate-900'],
                ['label' => $statusLabels['ikut'], 'value' => $summary['ikut'], 'class' => 'text-emerald-700'],
                ['label' => 'Sakit', 'value' => $summary['sakit'], 'class' => 'text-amber-700'],
                ['label' => 'Izin', 'value' => $summary['izin'], 'class' => 'text-blue-700'],
                ['label' => $statusLabels['alfa'], 'value' => $summary['alfa'], 'class' => 'text-red-700'],
                ['label' => $statusLabels['tidak_ikut'], 'value' => $summary['tidak_ikut'], 'class' => 'text-violet-800'],
                ['label' => 'Ujian', 'value' => $summary['ujian'], 'class' => 'text-cyan-700'],
                ['label' => 'Event Unit', 'value' => $summary['event_unit'], 'class' => 'text-fuchsia-700'],
                ['label' => 'Puasa', 'value' => $summary['puasa'], 'class' => 'text-indigo-700'],
                ['label' => 'Libur', 'value' => $summary['libur'], 'class' => 'text-slate-600'],
            ] as $item)
                <div class="card px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $item['label'] }}</p>
                    <p class="mt-1 text-2xl font-semibold {{ $item['class'] }}">{{ $item['value'] }}</p>
                </div>
            @endforeach
        </section>

        @if ($participants === [])
            <section class="card px-6 py-14 text-center">
                <p class="font-medium text-slate-700">
                    @if ($requiresClassSelection)
                        Belum ada peserta catering aktif di kelas ini.
                    @else
                        Belum ada peserta catering aktif pada kelompok {{ $selectedGroupLabel }}.
                    @endif
                </p>
            </section>
        @else
            <section class="card overflow-hidden">
                <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">
                            {{ $matrixTitle }}
                            <span class="font-normal text-slate-400">&middot;</span>
                            {{ $monthOptions[$month] }} {{ $year }}
                        </h2>
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500" aria-label="Legenda status absensi">
                            @foreach ($statusOptions as $status)
                                <span><strong class="text-slate-700">{{ $status->shorthand() }}</strong> {{ $status->label() }}</span>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        @if ($requiresClassSelection && $schoolClassId !== '')
                            @php
                                $classInvoiceUrl = $this->classInvoiceUrl();
                            @endphp
                            @if ($classInvoiceUrl)
                                <a
                                    href="{{ $classInvoiceUrl }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn-secondary"
                                    title="Buka pratinjau PDF rekap tagihan catering kelas ini di tab baru"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l4 4v14H6V3Z" />
                                        <path stroke-linecap="round" d="M15 3v4h4" />
                                    </svg>
                                    <span>Cetak Invoice Kelas</span>
                                </a>
                            @endif
                        @endif
                        <button
                            type="button"
                            wire:click="downloadAllInvoices"
                            wire:loading.attr="disabled"
                            wire:target="downloadAllInvoices"
                            class="btn-secondary"
                            title="Unduh satu file ZIP berisi invoice PDF individual setiap peserta pada periode ini"
                        >
                            <svg wire:loading.remove wire:target="downloadAllInvoices" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span wire:loading.remove wire:target="downloadAllInvoices">Download Semua Invoice</span>
                            <span wire:loading wire:target="downloadAllInvoices">Menyiapkan invoice...</span>
                        </button>
                        <span
                            class="inline-flex min-w-28 items-center justify-end gap-1.5 text-xs font-medium"
                            role="status"
                            aria-live="polite"
                        >
                            <template x-if="$wire.saving">
                                <span class="inline-flex items-center gap-1.5 text-slate-500">
                                    <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z" />
                                    </svg>
                                    Menyimpan...
                                </span>
                            </template>
                            <template x-if="! $wire.saving && $wire.saveState.saved">
                                <span class="inline-flex items-center gap-1 text-emerald-600">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                                    </svg>
                                    Tersimpan
                                </span>
                            </template>
                        </span>
                    </div>
                </div>

                <div class="max-h-[68vh] overflow-auto" x-on:scroll="closeBulkStatusPicker()">
                    <table class="min-w-max border-separate border-spacing-0 text-xs">
                        <thead>
                            <tr>
                                <th class="sticky left-0 top-0 z-30 w-12 min-w-12 border-b border-r border-slate-200 bg-slate-50 px-2 py-3 text-center font-semibold uppercase tracking-wide text-slate-500">
                                    No
                                </th>
                                <th class="sticky left-12 top-0 z-30 min-w-56 border-b border-r border-slate-200 bg-slate-50 px-4 py-3 text-left font-semibold uppercase tracking-wide text-slate-500 shadow-[2px_0_0_0_rgb(226_232_240)]">
                                    Nama Peserta
                                </th>
                                @foreach ($dates as $date)
                                    <th
                                        wire:key="attendance-date-header-{{ $date['date'] }}"
                                        @class([
                                            'sticky top-0 z-20 min-w-12 border-b border-r border-slate-200 px-1 py-2 text-center font-semibold',
                                            'bg-slate-100 text-slate-500' => $date['isWeekend'],
                                            'bg-slate-50 text-slate-600' => ! $date['isWeekend'],
                                        ])
                                    >
                                        <span class="block text-sm text-slate-800">{{ $date['day'] }}</span>
                                        <span class="block text-[10px] font-medium">{{ $date['weekday'] }}</span>

                                        <button
                                            type="button"
                                            x-on:click="openBulkStatusPicker(@js($date['date']), @js($date['day'].' '.$monthOptions[$month].' '.$year), $event)"
                                            x-bind:aria-expanded="bulkDate === @js($date['date'])"
                                            wire:loading.attr="disabled"
                                            class="mx-auto mt-1 inline-flex h-6 w-6 items-center justify-center rounded-md text-blue-600 transition hover:bg-blue-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-400"
                                            title="Ubah status seluruh peserta tanggal {{ $date['day'] }} {{ $monthOptions[$month] }} {{ $year }}"
                                            aria-label="Ubah status seluruh peserta tanggal {{ $date['day'] }} {{ $monthOptions[$month] }} {{ $year }}"
                                            aria-haspopup="menu"
                                            aria-controls="bulk-attendance-status-picker"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" d="M5 7h14M5 12h14M5 17h14" />
                                            </svg>
                                        </button>
                                    </th>
                                @endforeach
                                <th class="sticky right-0 top-0 z-30 w-32 min-w-32 border-b border-l border-slate-200 bg-slate-50 px-3 py-3 text-center font-semibold uppercase tracking-wide text-slate-500 shadow-[-2px_0_0_0_rgb(226_232_240)]">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($participants as $participant)
                                <tr wire:key="attendance-participant-{{ $participant['id'] }}" class="group">
                                    <td class="sticky left-0 z-20 w-12 min-w-12 border-b border-r border-slate-200 bg-white px-2 py-2.5 text-center text-xs text-slate-500 group-hover:bg-slate-50">
                                        {{ $loop->iteration }}
                                    </td>
                                    <th class="sticky left-12 z-10 min-w-56 border-b border-r border-slate-200 bg-white px-4 py-2.5 text-left text-sm font-medium text-slate-800 shadow-[2px_0_0_0_rgb(226_232_240)] group-hover:bg-slate-50">
                                        {{ $participant['name'] }}
                                    </th>
                                    @foreach ($dates as $date)
                                        @php
                                            $status = $attendance[$participant['id']][$date['date']];
                                        @endphp
                                        <td
                                            wire:key="attendance-cell-{{ $participant['id'] }}-{{ $date['date'] }}"
                                            @class([
                                                'border-b border-r border-slate-100 p-1 text-center',
                                                'bg-slate-50/70' => $date['isWeekend'],
                                                'bg-white' => ! $date['isWeekend'],
                                            ])
                                        >
                                            <button
                                                type="button"
                                                wire:click="openStatusMenu({{ $participant['id'] }}, '{{ $date['date'] }}')"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border-2 text-xs font-bold shadow-sm transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-offset-2 {{ $statusClasses[$status] ?? 'border-red-700 bg-red-500 text-white' }}"
                                                title="{{ $participant['name'] }} - {{ $date['date'] }}: {{ $statusLabels[$status] ?? 'Status tidak valid' }}"
                                                aria-label="Ubah status {{ $participant['name'] }} tanggal {{ $date['day'] }} dari {{ $statusLabels[$status] ?? 'status tidak valid' }}"
                                            >
                                                {{ $statusSymbols[$status] ?? '?' }}
                                            </button>
                                        </td>
                                    @endforeach
                                        <td class="sticky right-0 z-10 min-w-32 border-b border-l border-slate-200 bg-white px-3 py-1.5 text-center group-hover:bg-slate-50">
                                            @php
                                                $memberInvoiceUrl = $this->memberInvoiceUrl($participant['id']);
                                            @endphp
                                            @if ($memberInvoiceUrl)
                                                <a
                                                    href="{{ $memberInvoiceUrl }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50"
                                                    title="Buka pratinjau invoice catering individual {{ $participant['name'] }} di tab baru"
                                                    aria-label="Cetak invoice {{ $participant['name'] }}"
                                                >
                                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 0-1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Z" />
                                                    </svg>
                                                    Cetak Invoice
                                                </a>
                                            @endif
                                        </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <template x-teleport="body">
                <div
                    x-cloak
                    x-show="bulkDate"
                    x-ref="bulkStatusPicker"
                    x-bind:style="bulkPickerStyle"
                    x-on:click.outside="closeBulkStatusPicker()"
                    x-transition.opacity.duration.100ms
                    id="bulk-attendance-status-picker"
                    class="fixed z-[60] rounded-xl border border-slate-200 bg-white p-3 shadow-xl"
                    role="menu"
                    aria-label="Pilih status untuk seluruh peserta"
                >
                    <p class="mb-2 truncate px-1 text-xs font-semibold text-slate-700">
                        Semua peserta &middot; <span x-text="bulkDateLabel"></span>
                    </p>
                    <div class="grid grid-cols-3 gap-1.5">
                        @foreach ($statusOptions as $status)
                            <button
                                type="button"
                                x-on:click="applyBulkStatus(@js($status->value))"
                                class="flex min-h-12 flex-col items-center justify-center rounded-lg border-2 px-1 py-1.5 text-[10px] font-semibold leading-tight shadow-sm transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-offset-1 {{ $statusClasses[$status->value] }}"
                                role="menuitem"
                            >
                                <span class="text-sm font-bold">{{ $status->shorthand() }}</span>
                                <span>{{ $statusLabels[$status->value] }}</span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </template>
        @endif
    @endif

    @if ($editingMemberId !== null && $editingDate !== null)
        @php
            $editingParticipant = collect($participants)->firstWhere('id', $editingMemberId);
            $editingDateInfo = collect($dates)->firstWhere('date', $editingDate);
            $editingStatus = $attendance[$editingMemberId][$editingDate];
        @endphp
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeStatusMenu()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="attendance-status-title">
            <div wire:click="closeStatusMenu" class="absolute inset-0 bg-slate-950/45 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-sm flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                    <div>
                        <h3 id="attendance-status-title" class="font-semibold text-slate-900">Pilih Status</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $editingParticipant['name'] ?? '-' }} &middot;
                            {{ $editingDateInfo['day'] ?? '-' }} {{ $monthOptions[$month] }} {{ $year }}
                        </p>
                    </div>
                    <button type="button" wire:click="closeStatusMenu" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="grid min-h-0 flex-1 grid-cols-2 gap-2 overflow-y-auto overscroll-contain p-5 sm:grid-cols-3">
                    @foreach ($statusOptions as $status)
                        <button
                            type="button"
                            wire:click="setCellStatus({{ $editingMemberId }}, '{{ $editingDate }}', '{{ $status->value }}')"
                            @class([
                                'flex flex-col items-center gap-1 rounded-xl border-2 px-2 py-3 text-xs font-semibold shadow-sm transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-offset-2',
                                $statusClasses[$status->value],
                                $statusSelectedClasses[$status->value] => $editingStatus === $status->value,
                            ])
                        >
                            <span class="text-base font-bold">{{ $status->shorthand() }}</span>
                            <span>{{ $status->label() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>

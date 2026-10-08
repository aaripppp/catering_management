@use(App\Enums\CateringParticipantGroup)

@php
    $statusStyles = [
        'ikut' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'sakit' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'izin' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'alfa' => 'bg-red-50 text-red-700 ring-red-200',
        'tidak_ikut' => 'bg-violet-50 text-violet-700 ring-violet-200',
        'ujian' => 'bg-cyan-50 text-cyan-700 ring-cyan-200',
        'event_unit' => 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-200',
        'puasa' => 'bg-indigo-50 text-indigo-700 ring-indigo-200',
        'libur' => 'bg-slate-100 text-slate-600 ring-slate-200',
    ];
@endphp

<div class="space-y-5">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Rekap Absensi</h2>
            <p class="mt-1 text-sm text-slate-500">Ringkasan kehadiran peserta catering.</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
            <button type="button" wire:click="downloadExcel" wire:loading.attr="disabled" wire:target="downloadExcel" class="inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="downloadExcel">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" />
                        </svg>
                        <span>Export Excel</span>
                    </span>
                </span>
                <span wire:loading wire:target="downloadExcel">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                            <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z" />
                        </svg>
                        <span>Menyiapkan...</span>
                    </span>
                </span>
            </button>
            <a href="{{ $pdfPreviewUrl }}" target="_blank" rel="noopener" class="inline-flex h-11 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700" title="Buka preview PDF di tab baru">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 3h9l4 4v14H6V3Zm9 0v4h4M9 13h6M9 17h4" />
                </svg>
                <span>Preview PDF</span>
            </a>
        </div>
    </section>

    <section class="card card-body">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div>
                <label for="recap-month" class="label">Bulan</label>
                <select id="recap-month" wire:model.live="month" class="select">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('month') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="recap-year" class="label">Tahun</label>
                <select id="recap-year" wire:model.live="year" class="select">
                    @foreach ($yearOptions as $value)
                        <option value="{{ $value }}">{{ $value }}</option>
                    @endforeach
                </select>
                @error('year') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="recap-participant-group" class="label">Kelompok Peserta</label>
                <select id="recap-participant-group" wire:model.live="participantGroup" class="select">
                    @foreach ($participantGroupOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('participantGroup') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            @if ($participantGroup === CateringParticipantGroup::Student->value)
                <div>
                    <label for="recap-level" class="label">Jenjang</label>
                    <select id="recap-level" wire:model.live="jenjang" class="select">
                        <option value="">Semua jenjang</option>
                        @foreach ($jenjangOptions as $value)
                            <option value="{{ $value }}">{{ $value }}</option>
                        @endforeach
                    </select>
                    @error('jenjang') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="recap-class" class="label">Kelas</label>
                    <select id="recap-class" wire:model.live="schoolClassId" class="select">
                        <option value="">Semua kelas</option>
                        @foreach ($classOptions as $classOption)
                            <option value="{{ $classOption['id'] }}">{{ $classOption['name'] }}</option>
                        @endforeach
                    </select>
                    @error('schoolClassId') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            @endif

            <div @class(['xl:col-span-2' => $participantGroup !== CateringParticipantGroup::Student->value])>
                <label for="recap-search" class="label">Cari Peserta</label>
                <input id="recap-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Nama peserta" />
                @error('search') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-4 flex justify-end border-t border-slate-100 pt-4">
            <button type="button" wire:click="resetFilters" class="inline-flex h-11 w-full items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-900 shadow-sm transition hover:bg-slate-50 sm:w-auto">Reset Filter</button>
        </div>

        <div wire:loading wire:target="month,year,participantGroup,jenjang,schoolClassId,search,resetFilters,setTab" class="mt-4">
            <span class="inline-flex items-center gap-2 text-sm text-blue-600">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z" />
                </svg>
                <span>Memuat rekap...</span>
            </span>
        </div>
    </section>

    <section class="card overflow-hidden">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Periode Rekap</p>
                <h2 class="mt-1 text-lg font-semibold text-slate-900">{{ $periodLabel }}</h2>
            </div>
            <div class="flex overflow-x-auto rounded-lg bg-slate-100 p-1" role="tablist" aria-label="Tampilan rekap">
                @foreach (['summary' => 'Ringkasan', 'participants' => 'Per Peserta', 'groups' => 'Per Kelas / Kelompok'] as $tabValue => $tabLabel)
                    <button
                        type="button"
                        wire:click="setTab('{{ $tabValue }}')"
                        @class([
                            'whitespace-nowrap rounded-md px-3 py-2 text-sm font-semibold transition',
                            'bg-white text-blue-700 shadow-sm' => $tab === $tabValue,
                            'text-slate-600 hover:text-slate-900' => $tab !== $tabValue,
                        ])
                        role="tab"
                        aria-selected="{{ $tab === $tabValue ? 'true' : 'false' }}"
                    >
                        {{ $tabLabel }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($tab === 'summary')
            <div class="p-5">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
                    <div class="rounded-xl bg-slate-900 p-4 text-white sm:col-span-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">Peserta Tercatat</p>
                        <p class="mt-2 text-3xl font-bold">{{ $summary['participants'] }}</p>
                        <p class="mt-1 text-xs text-slate-300">{{ number_format($summary['total_records'], 0, ',', '.') }} catatan absensi</p>
                    </div>
                    @foreach ($statusOptions as $status)
                        <div class="rounded-xl p-4 ring-1 ring-inset {{ $statusStyles[$status->value] }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold uppercase tracking-wide">{{ $status->label() }}</p>
                                <span class="text-xs font-bold">{{ $status->shorthand() }}</span>
                            </div>
                            <p class="mt-2 text-2xl font-bold">{{ number_format($summary[$status->value], 0, ',', '.') }}</p>
                        </div>
                    @endforeach
                </div>

                @if ($summary['total_records'] === 0)
                    <div class="mt-5 rounded-xl border border-dashed border-slate-300 px-6 py-10 text-center">
                        <p class="font-medium text-slate-700">Belum ada catatan absensi pada periode ini.</p>
                        <p class="mt-1 text-sm text-slate-500">Ubah periode atau filter untuk melihat data lainnya.</p>
                    </div>
                @endif
            </div>
        @elseif ($tab === 'participants')
            <div class="overflow-x-auto">
                <table class="table w-full min-w-[1180px]">
                    <thead class="whitespace-nowrap">
                        <tr>
                            <th class="w-12 text-center">No</th>
                            <th>Peserta</th>
                            <th>Kelas / Kelompok</th>
                            @foreach ($statusOptions as $status)
                                <th class="text-center" title="{{ $status->label() }}">{{ $status->shorthand() }}</th>
                            @endforeach
                            <th class="text-center">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($participants as $participant)
                            <tr wire:key="recap-participant-{{ $participant['member_id'] }}">
                                <td class="text-center text-slate-500">{{ $participants->firstItem() + $loop->index }}</td>
                                <td>
                                    <p class="whitespace-nowrap font-semibold text-slate-900">{{ $participant['member_name'] }}</p>
                                    @unless ($participant['is_active'])
                                        <span class="mt-1 inline-flex whitespace-nowrap rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 ring-1 ring-inset ring-slate-200">Tidak aktif</span>
                                    @endunless
                                </td>
                                <td class="whitespace-nowrap text-slate-600">{{ $participant['group_label'] }}</td>
                                @foreach ($statusOptions as $status)
                                    <td class="text-center font-medium text-slate-700">{{ $participant[$status->value] }}</td>
                                @endforeach
                                <td class="text-center font-bold text-slate-900">{{ $participant['total_days'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="py-12 text-center">
                                    <p class="font-medium text-slate-700">Tidak ada peserta yang sesuai dengan filter.</p>
                                    <p class="mt-1 text-sm text-slate-500">Data peserta muncul jika memiliki catatan absensi pada periode terpilih.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($participants->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $participants->links() }}</div>
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="table w-full min-w-[1060px]">
                    <thead class="whitespace-nowrap">
                        <tr>
                            <th class="w-12 text-center">No</th>
                            <th>Kelas / Kelompok</th>
                            <th class="text-center">Peserta</th>
                            @foreach ($statusOptions as $status)
                                <th class="text-center" title="{{ $status->label() }}">{{ $status->shorthand() }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr wire:key="recap-group-{{ md5($group['label']) }}">
                                <td class="text-center text-slate-500">{{ $loop->iteration }}</td>
                                <td class="whitespace-nowrap font-semibold text-slate-900">{{ $group['label'] }}</td>
                                <td class="text-center font-medium text-slate-700">{{ $group['participant_count'] }}</td>
                                @foreach ($statusOptions as $status)
                                    <td class="text-center text-slate-700">{{ $group[$status->value] }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="py-12 text-center">
                                    <p class="font-medium text-slate-700">Belum ada rekap kelas atau kelompok.</p>
                                    <p class="mt-1 text-sm text-slate-500">Rekap terbentuk dari catatan absensi pada periode terpilih.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs text-slate-500" aria-label="Legenda status absensi">
        @foreach ($statusOptions as $status)
            <span><strong class="text-slate-700">{{ $status->shorthand() }}</strong> {{ $status->label() }}</span>
        @endforeach
    </div>
</div>

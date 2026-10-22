<div
    class="space-y-5"
    x-data="{ dirty: false }"
    x-on:input="if ($event.target.matches('[data-attendance-input]')) dirty = true"
    x-on:unit-attendance-clean.window="dirty = false"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }"
>
    <section class="card card-body">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="unit-attendance-month" class="label">Bulan</label>
                    <select
                        id="unit-attendance-month"
                        class="select min-w-48"
                        x-bind:disabled="dirty"
                        x-on:change="$wire.changePeriod('month', Number($event.target.value))"
                    >
                        @foreach ($monthOptions as $value => $label)
                            <option value="{{ $value }}" @selected($month === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('month')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="unit-attendance-year" class="label">Tahun</label>
                    <select
                        id="unit-attendance-year"
                        class="select min-w-32"
                        x-bind:disabled="dirty"
                        x-on:change="$wire.changePeriod('year', Number($event.target.value))"
                    >
                        @foreach ($yearOptions as $value)
                            <option value="{{ $value }}" @selected($year === $value)>{{ $value }}</option>
                        @endforeach
                    </select>
                    @error('year')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <button
                    type="button"
                    wire:click="discardChanges"
                    wire:loading.attr="disabled"
                    class="btn-secondary justify-center"
                    x-bind:disabled="! dirty"
                >
                    Batalkan Perubahan
                </button>
                <button
                    type="button"
                    wire:click="save"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="btn-primary justify-center"
                    x-bind:disabled="! dirty"
                >
                    <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </button>
            </div>
        </div>

        <div x-show="dirty" x-cloak class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Ada perubahan yang belum disimpan. Simpan atau batalkan sebelum mengganti periode.
        </div>
        @error('matrix')<p class="mt-3 field-error">{{ $message }}</p>@enderror
    </section>

    <section class="card overflow-hidden">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="text-base font-semibold text-slate-900">Absensi Unit &middot; {{ $monthOptions[$month] }} {{ $year }}</h2>
            <p class="mt-1 text-sm text-slate-500">Kosong berarti belum ada data. Nilai 0 tetap disimpan sebagai data absensi.</p>
        </div>

        @if ($units === [])
            <div class="px-6 py-14 text-center text-sm text-slate-500">Belum ada unit catering aktif.</div>
        @else
            <div class="max-h-[68vh] overflow-auto">
                <table class="min-w-max border-separate border-spacing-0 text-xs">
                    <thead>
                        <tr>
                            <th class="sticky left-0 top-0 z-30 w-12 min-w-12 border-b border-r border-slate-200 bg-slate-50 px-2 py-3 text-center font-semibold uppercase tracking-wide text-slate-500">No</th>
                            <th class="sticky left-12 top-0 z-30 min-w-56 border-b border-r border-slate-200 bg-slate-50 px-4 py-3 text-left font-semibold uppercase tracking-wide text-slate-500 shadow-[2px_0_0_0_rgb(226_232_240)]">Unit</th>
                            @foreach ($dates as $date)
                                <th
                                    wire:key="unit-attendance-date-{{ $date['date'] }}"
                                    @class([
                                        'sticky top-0 z-20 min-w-16 border-b border-r border-slate-200 px-1 py-2 text-center font-semibold',
                                        'bg-slate-100 text-slate-500' => $date['isWeekend'],
                                        'bg-slate-50 text-slate-600' => ! $date['isWeekend'],
                                    ])
                                >
                                    <span class="block text-sm text-slate-800">{{ $date['day'] }}</span>
                                    <span class="block text-[10px] font-medium">{{ $date['weekday'] }}</span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($units as $unit)
                            <tr wire:key="unit-attendance-unit-{{ $unit['id'] }}" class="group">
                                <td class="sticky left-0 z-20 w-12 min-w-12 border-b border-r border-slate-200 bg-white px-2 py-2.5 text-center text-slate-500 group-hover:bg-slate-50">{{ $loop->iteration }}</td>
                                <th class="sticky left-12 z-10 min-w-56 whitespace-nowrap border-b border-r border-slate-200 bg-white px-4 py-2.5 text-left text-sm font-medium text-slate-800 shadow-[2px_0_0_0_rgb(226_232_240)] group-hover:bg-slate-50">{{ $unit['name'] }}</th>
                                @foreach ($dates as $date)
                                    <td
                                        wire:key="unit-attendance-cell-{{ $unit['id'] }}-{{ $date['date'] }}"
                                        @class([
                                            'border-b border-r border-slate-100 p-1.5 text-center',
                                            'bg-slate-50/70' => $date['isWeekend'],
                                            'bg-white' => ! $date['isWeekend'],
                                        ])
                                    >
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            inputmode="numeric"
                                            data-attendance-input
                                            wire:model="quantities.{{ $unit['id'] }}.{{ $date['date'] }}"
                                            class="h-9 w-14 rounded-md border border-slate-200 bg-white px-1 text-center text-sm text-slate-800 shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                                            aria-label="Jumlah {{ $unit['name'] }} tanggal {{ $date['day'] }} {{ $monthOptions[$month] }} {{ $year }}"
                                        />
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div wire:loading.delay.flex wire:target="changePeriod,discardChanges" style="display:none" class="items-center gap-2 border-t border-slate-200 px-5 py-3 text-sm text-blue-600">
            Memuat matriks absensi unit...
        </div>
    </section>
</div>

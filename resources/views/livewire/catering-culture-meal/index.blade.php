<div class="space-y-5">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Budaya Makan</h2>
            <p class="mt-1 text-sm text-slate-500">Monitoring kehadiran dan pemasukan manual Budaya Makan per periode.</p>
        </div>
    </section>

    <section class="card card-body">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="culture-meal-month" class="label">Bulan</label>
                <select id="culture-meal-month" wire:model.live="month" class="select">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('month') <p class="field-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="culture-meal-year" class="label">Tahun</label>
                <select id="culture-meal-year" wire:model.live="year" class="select">
                    @foreach ($yearOptions as $value)
                        <option value="{{ $value }}">{{ $value }}</option>
                    @endforeach
                </select>
                @error('year') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>

        <div wire:loading wire:target="month,year" class="mt-4">
            <span class="inline-flex items-center gap-2 text-sm text-blue-600">
                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z" />
                </svg>
                <span>Memuat data Budaya Makan...</span>
            </span>
        </div>
    </section>

    <section class="card">
        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Harga Budaya Makan</p>
                <p class="mt-1 text-2xl font-bold text-slate-900">{{ $cultureMealPriceLabel }}</p>
                <p class="mt-1 text-xs text-slate-500">Harga referensi Budaya Makan. Pemasukan tetap dicatat manual.</p>
            </div>
            <button type="button" wire:click="editCultureMealPrice" class="btn-secondary shrink-0">Edit Harga</button>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-60" wire:target="month,year">
        <div class="card card-body bg-slate-900 text-white">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-300">Data Tercatat</p>
                <span class="rounded-full bg-white/10 px-2 py-0.5 text-[11px] font-medium text-slate-200">{{ $periodLabel }}</span>
            </div>
            <p class="mt-3 text-3xl font-bold">{{ number_format($total, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-300">Jumlah kehadiran Budaya Makan yang tercatat pada periode ini.</p>
        </div>

        <div class="card card-body border-blue-200 bg-blue-50/60">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Total Tagihan Tercatat</p>
                <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-medium text-blue-700">{{ $periodLabel }}</span>
            </div>
            <p class="mt-3 text-3xl font-bold text-blue-700">{{ $totalRecordedBillLabel }}</p>
            <p class="mt-1 text-xs text-slate-500">Nilai tercatat berdasarkan jumlah Budaya Makan × harga referensi.</p>
            <p class="mt-2 text-xs font-medium text-blue-700">{{ number_format($total, 0, ',', '.') }} × {{ $cultureMealPriceLabel }}</p>
        </div>

        <div class="card card-body bg-blue-600 text-white">
            <div class="flex items-start justify-between gap-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-100">Pemasukan Manual</p>
                <span class="rounded-full bg-white/15 px-2 py-0.5 text-[11px] font-medium text-blue-50">{{ $periodLabel }}</span>
            </div>
            <p class="mt-3 text-3xl font-bold">{{ 'Rp '.number_format($manualTotal, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-blue-100">Total pemasukan manual yang dicatat pada periode ini.</p>
        </div>
    </section>

    <section class="card">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Monitoring Harian</h2>
                <p class="mt-1 text-sm text-slate-500">Jumlah kehadiran Budaya Makan per tanggal pada {{ $periodLabel }}.</p>
            </div>
            <span class="inline-flex shrink-0 items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $days->count() }} tanggal</span>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full min-w-[520px]">
                <thead class="whitespace-nowrap">
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th>Tanggal</th>
                        <th class="text-center">Jumlah Tercatat</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($days as $index => $day)
                        <tr wire:key="culture-meal-day-{{ $day['date']->toDateString() }}">
                            <td class="text-center text-slate-500">{{ $index + 1 }}</td>
                            <td class="whitespace-nowrap text-slate-700">{{ $day['date']->locale('id')->translatedFormat('d F Y') }}</td>
                            <td class="text-center font-medium {{ $day['count'] > 0 ? 'text-slate-900' : 'text-slate-400' }}">{{ $day['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" wire:loading.class="opacity-60" wire:target="saveIncome,deleteConfirmed">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Pemasukan Manual</h2>
                <p class="mt-1 text-sm text-slate-500">Catatan pemasukan Budaya Makan yang diinput manual.</p>
            </div>
            <button type="button" wire:click="createIncome" class="btn-primary shrink-0">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                Tambah Pemasukan
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="table w-full min-w-[720px]">
                <thead class="whitespace-nowrap">
                    <tr>
                        <th class="w-12 text-center">No</th>
                        <th>Tanggal</th>
                        <th>Kelas</th>
                        <th>Nominal</th>
                        <th>Catatan</th>
                        <th class="text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($incomeRows as $index => $income)
                        <tr wire:key="culture-meal-income-{{ $income->id }}">
                            <td class="text-center text-slate-500">{{ $index + 1 }}</td>
                            <td class="whitespace-nowrap text-slate-700">{{ $income->income_date->locale('id')->translatedFormat('d F Y') }}</td>
                            <td class="whitespace-nowrap text-slate-700">{{ $income->school_class_name }}</td>
                            <td class="whitespace-nowrap font-semibold text-slate-900">{{ $income->formatted_amount }}</td>
                            <td class="max-w-xs truncate text-slate-600">{{ $income->note ?? '-' }}</td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="editIncome({{ $income->id }})" class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-50">Edit</button>
                                    <button type="button" wire:click="confirmDeleteIncome({{ $income->id }})" class="btn-danger-soft">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <p class="font-medium text-slate-700">Belum ada pemasukan manual pada periode ini.</p>
                                <p class="mt-1 text-sm text-slate-500">Tambahkan pemasukan untuk mencatatnya pada periode terpilih.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- CREATE/EDIT INCOME MODAL --}}
    @if ($showFormModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeFormModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="income-modal-title">
            <div wire:click="closeFormModal" class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="income-modal-title" class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit Pemasukan' : 'Tambah Pemasukan' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Catat pemasukan Budaya Makan secara manual.</p>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup formulir">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form wire:submit="saveIncome" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                        <div>
                            <label for="income-date" class="label">Tanggal</label>
                            <input id="income-date" type="date" wire:model="income_date" class="input" />
                            @error('income_date')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="income-class" class="label">Kelas</label>
                            <select id="income-class" wire:model="school_class_id" class="select">
                                <option value="">Pilih kelas</option>
                                @foreach ($classOptions as $classOption)
                                    <option value="{{ $classOption['id'] }}">{{ $classOption['name'] }}</option>
                                @endforeach
                            </select>
                            @error('school_class_id')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="income-amount" class="label">Nominal (Rp)</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">Rp</span>
                                <input id="income-amount" type="number" inputmode="numeric" min="1" step="1" wire:model="amount" class="input pl-10" placeholder="16000" />
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">Masukkan angka penuh tanpa titik atau koma.</p>
                            @error('amount')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="income-note" class="label">Catatan</label>
                            <textarea id="income-note" rows="3" wire:model="note" class="input" placeholder="Contoh: Budaya makan kelas"></textarea>
                            @error('note')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeFormModal" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="saveIncome">{{ $editingId ? 'Simpan Perubahan' : 'Simpan' }}</span>
                            <span wire:loading wire:target="saveIncome">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- REFERENCE PRICE MODAL --}}
    @if ($showPriceModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closePriceModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="price-modal-title">
            <div wire:click="closePriceModal" class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="price-modal-title" class="text-base font-semibold text-slate-900">Harga Budaya Makan</h3>
                        <p class="mt-1 text-sm text-slate-500">Harga referensi, tidak mengubah pemasukan manual.</p>
                    </div>
                    <button type="button" wire:click="closePriceModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup formulir">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form wire:submit="saveCultureMealPrice" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                        <div>
                            <label for="price-amount" class="label">Harga (Rp)</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">Rp</span>
                                <input id="price-amount" type="number" inputmode="numeric" min="1" step="1" wire:model="culture_meal_price" class="input pl-10" placeholder="16000" />
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">Masukkan angka penuh tanpa titik atau koma.</p>
                            @error('culture_meal_price')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closePriceModal" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="saveCultureMealPrice">Simpan Perubahan</span>
                            <span wire:loading wire:target="saveCultureMealPrice">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- DELETE CONFIRMATION MODAL --}}
    @if ($showDeleteModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeDeleteModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="income-delete-modal-title">
            <div wire:click="closeDeleteModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="income-delete-modal-title" class="text-base font-semibold text-slate-900">Hapus Pemasukan?</h3>
                        <p class="mt-1 text-sm text-slate-500">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <button type="button" wire:click="closeDeleteModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6">
                    <p class="text-sm text-slate-700">Apakah Anda yakin ingin menghapus pemasukan berikut?</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $deletingLabel }}</p>
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <button type="button" wire:click="closeDeleteModal" class="btn-secondary">Batal</button>
                    <button type="button" wire:click="deleteConfirmed" class="btn-danger">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>

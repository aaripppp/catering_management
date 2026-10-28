<div class="space-y-5">
    <div class="flex justify-end">
        <button type="button" wire:click="create" wire:loading.attr="disabled" class="btn-primary">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M12 5v14M5 12h14" />
            </svg>
            Tambah Unit
        </button>
    </div>

    <section class="alert-info flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <div>
            <p class="text-sm font-semibold">Harga saat ini digunakan untuk input unit catering berikutnya.</p>
            <p class="mt-0.5 text-xs">Perubahan harga tidak akan mengubah snapshot absensi atau tagihan historis pada tahap berikutnya.</p>
        </div>
    </section>

    <section class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="w-14 text-center">No</th>
                        <th>Unit</th>
                        <th class="whitespace-nowrap">Harga / Orang</th>
                        <th>Status</th>
                        <th class="text-center">Urutan</th>
                        <th class="w-36 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($units as $unit)
                        <tr wire:key="catering-employee-unit-{{ $unit->id }}">
                            <td class="text-center text-sm text-slate-500">{{ $loop->iteration }}</td>
                            <td class="whitespace-nowrap font-medium text-slate-900">{{ $unit->name }}</td>
                            <td class="whitespace-nowrap font-semibold text-slate-900">{{ $unit->formatted_price_per_person }}</td>
                            <td>
                                @if ($unit->is_active)
                                    <span class="badge-success"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                @else
                                    <span class="badge-neutral"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Nonaktif</span>
                                @endif
                            </td>
                            <td class="text-center text-sm font-medium text-slate-700">{{ $unit->sort_order }}</td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit({{ $unit->id }})" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50">Edit</button>
                                    <button type="button" wire:click="confirmDelete({{ $unit->id }})" class="btn-danger-soft">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center">
                                <p class="font-medium text-slate-700">Belum ada unit catering.</p>
                                <p class="mt-1 text-sm text-slate-500">Tambahkan unit pertama untuk mulai mencatat absensi dan tagihan.</p>
                                <button type="button" wire:click="create" class="btn-primary mt-4">Tambah Unit</button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($showFormModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeFormModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="unit-catering-modal-title">
            <div wire:click="closeFormModal" class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="unit-catering-modal-title" class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit Unit Catering' : 'Tambah Unit Catering' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Lengkapi nama, harga, status, dan urutan unit.</p>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup formulir">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                        <div>
                            <label for="unit-catering-name" class="label">Nama Unit</label>
                            <input id="unit-catering-name" type="text" wire:model="name" class="input" placeholder="Contoh: SD" />
                            @error('name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                            <div>
                                <label for="unit-catering-price" class="label">Harga per Orang</label>
                                <div class="relative">
                                    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">Rp</span>
                                    <input id="unit-catering-price" type="number" inputmode="numeric" min="0" step="1" wire:model="price_per_person" class="input pl-10" placeholder="13000" />
                                </div>
                                @error('price_per_person')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="unit-default-employee-subsidy" class="label">Default Subsidi Pegawai / Porsi</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">Rp</span>
                                        <input id="unit-default-employee-subsidy" type="number" inputmode="numeric" min="0" step="1" wire:model="default_employee_subsidy_rate" class="input pl-10" placeholder="4000" />
                                    </div>
                                    @error('default_employee_subsidy_rate')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="unit-default-student-subsidy" class="label">Default Subsidi Siswa / Siswa</label>
                                    <div class="relative">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-slate-400">Rp</span>
                                        <input id="unit-default-student-subsidy" type="number" inputmode="numeric" min="0" step="1" wire:model="default_student_subsidy_rate" class="input pl-10" placeholder="1000" />
                                    </div>
                                    @error('default_student_subsidy_rate')<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">Masukkan angka penuh tanpa titik atau koma.</p>
                            @error('price_per_person')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="unit-catering-sort-order" class="label">Urutan</label>
                            <input id="unit-catering-sort-order" type="number" inputmode="numeric" min="1" step="1" wire:model="sort_order" class="input" placeholder="1" />
                            <p class="mt-1.5 text-xs text-slate-500">Jika urutan sama, unit yang dibuat lebih dahulu tampil lebih dulu.</p>
                            @error('sort_order')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="checkbox mt-0.5" />
                            <span><span class="font-medium text-slate-800">Status aktif</span><span class="mt-0.5 block text-xs text-slate-500">Unit aktif akan tersedia untuk input unit catering berikutnya.</span></span>
                        </label>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeFormModal" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-primary">
                            <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan Perubahan' : 'Tambah Unit' }}</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showDeleteModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeDeleteModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="delete-unit-modal-title">
            <div wire:click="closeDeleteModal" class="absolute inset-0 bg-slate-950/60" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-red-50 text-red-600">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </span>
                        <div>
                            <h3 id="delete-unit-modal-title" class="text-base font-semibold text-slate-900">Hapus Unit — {{ $deletingName }}</h3>
                            <p class="mt-1 text-sm text-slate-500">Tindakan ini bersifat permanen.</p>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDeleteModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4 sm:px-6">
                    <p class="text-sm text-slate-700">Data Unit dan seluruh riwayat terkait akan dihapus permanen dan tidak dapat dikembalikan.</p>

                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Data terkait yang akan dihapus</p>
                        <div class="mt-2 space-y-1 text-sm text-slate-700">
                            <div class="flex items-center justify-between"><span>Absensi Unit</span><span class="font-medium">{{ number_format($deleteAttendanceCount, 0, ',', '.') }} data</span></div>
                            <div class="flex items-center justify-between"><span>Tagihan Unit</span><span class="font-medium">{{ number_format($deleteBillCount, 0, ',', '.') }} data</span></div>
                            <div class="flex items-center justify-between"><span>Pembayaran Unit</span><span class="font-medium">{{ number_format($deletePaymentCount, 0, ',', '.') }} transaksi</span></div>
                        </div>
                    </div>

                    @if ($this->deleteRequiresStrongConfirmation)
                        @if ($deletePaymentCount > 0)
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">Unit ini memiliki riwayat pembayaran. Menghapus Unit akan menghapus seluruh transaksi pembayaran terkait.</div>
                        @endif
                        <div class="space-y-3">
                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" wire:model.live="deleteConfirmChecked" class="mt-0.5 rounded border-slate-300 text-red-600">
                                <span>Saya memahami bahwa seluruh data terkait akan dihapus permanen.</span>
                            </label>
                            <div>
                                <label class="label">Ketik nama Unit untuk konfirmasi</label>
                                <input type="text" wire:model.live="deleteConfirmName" class="input" placeholder="{{ $deletingName }}" />
                                <p class="mt-1 text-xs text-slate-500">Ketik persis: <span class="font-medium text-slate-700">{{ $deletingName }}</span></p>
                            </div>
                        </div>
                    @endif

                    @error('delete')
                        <p class="text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <button type="button" wire:click="closeDeleteModal" class="btn-secondary">Batal</button>
                    <button type="button" wire:click="deleteConfirmed" wire:loading.attr="disabled" wire:target="deleteConfirmed" class="btn-danger" @disabled(! $this->deleteCanProceed)>
                        <span wire:loading.remove wire:target="deleteConfirmed">Hapus Permanen</span>
                        <span wire:loading wire:target="deleteConfirmed">Menghapus...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

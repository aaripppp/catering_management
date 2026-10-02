<div class="space-y-5">

    <section class="card card-body">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:max-w-xl">
                <div>
                    <label for="school-class-search" class="label">Pencarian</label>
                    <div class="relative">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                            <circle cx="11" cy="11" r="6.5" />
                            <path stroke-linecap="round" d="m16 16 4 4" />
                        </svg>
                        <input id="school-class-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari nama atau tingkat kelas" class="input input-has-icon" />
                    </div>
                </div>
                <div>
                    <label for="school-class-status" class="label">Status</label>
                    <select id="school-class-status" wire:model.live="status" class="select">
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
            </div>

            <button type="button" wire:click="create" wire:loading.attr="disabled" class="btn-primary shrink-0">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" d="M12 5v14M5 12h14" />
                </svg>
                Tambah Kelas
            </button>
        </div>
    </section>

    <section class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="w-10 text-center">No</th>
                        <th>Nama Kelas</th>
                        <th>Jenjang</th>
                        <th>Tingkat</th>
                        <th>Jumlah Peserta</th>
                        <th>Status</th>
                        <th class="text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classes as $schoolClass)
                        <tr wire:key="school-class-{{ $schoolClass->id }}">
                            <td class="text-center text-slate-500 text-sm">
                                {{ $classes->firstItem() + $loop->index }}
                            </td>
                            <td class="font-medium text-slate-900">{{ $schoolClass->name }}</td>
                            <td class="text-slate-600">{{ $schoolClass->jenjang ?? '-' }}</td>
                            <td class="text-slate-600">{{ $schoolClass->level ?? '-' }}</td>
                            <td class="text-slate-600">{{ $schoolClass->members_count }}</td>
                            <td>
                                @if ($schoolClass->is_active)
                                    <span class="badge-success"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                @else
                                    <span class="badge-neutral"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit({{ $schoolClass->id }})" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50">
                                        Edit
                                    </button>
                                    <button type="button" wire:click="confirmDelete({{ $schoolClass->id }})" class="btn-danger-soft">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center">
                                    <div class="icon-box">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25A2.25 2.25 0 0 1 6 3h12a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 18 21H6a2.25 2.25 0 0 1-2.25-2.25V5.25Z" />
                                            <path stroke-linecap="round" d="M8 8h8M8 12h5" />
                                        </svg>
                                    </div>
                                    <p class="mt-3 font-medium text-slate-700">Belum ada kelas</p>
                                    <p class="mt-1 text-sm text-slate-500">Tambahkan kelas pertama untuk mulai mengelompokkan peserta.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($classes->hasPages())
        <div>{{ $classes->links() }}</div>
    @endif

    @if ($errors->has('delete'))
        <div class="alert-danger flex items-center gap-2" role="alert">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v4.5m0 3h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            {{ $errors->first('delete') }}
        </div>
    @endif

    {{-- CREATE/EDIT MODAL --}}
    @if ($showFormModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeFormModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="school-class-modal-title">
            <div wire:click="closeFormModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="school-class-modal-title" class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit Kelas' : 'Tambah Kelas' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Lengkapi informasi kelas di bawah ini.</p>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup formulir">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                        <div>
                            <label for="school-class-name" class="label">Nama Kelas</label>
                            <input id="school-class-name" type="text" wire:model="name" class="input" placeholder="Contoh: VII A" />
                            @error('name')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="school-class-level" class="label">Tingkat</label>
                            <select id="school-class-level" wire:model="level" class="select">
                                <option value="">Pilih tingkat</option>
                                @foreach ($levelOptions as $jenjang => $levels)
                                    <optgroup label="{{ $jenjang }}">
                                        @foreach ($levels as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('level')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="checkbox mt-0.5" />
                            <span><span class="font-medium text-slate-800">Status aktif</span><span class="mt-0.5 block text-xs text-slate-500">Kelas aktif dapat digunakan pada data peserta.</span></span>
                        </label>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeFormModal" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- DELETE CONFIRMATION MODAL --}}
    @if ($showDeleteModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeDeleteModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <div wire:click="closeDeleteModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="delete-modal-title" class="text-base font-semibold text-slate-900">Hapus Kelas?</h3>
                        <p class="mt-1 text-sm text-slate-500">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <button type="button" wire:click="closeDeleteModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6">
                    <p class="text-sm text-slate-700">Apakah Anda yakin ingin menghapus kelas ini?</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $deletingName }}</p>
                    @if ($schoolClass->members_count > 0)
                        <div class="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                            <p class="text-sm font-medium text-amber-800">PERINGATAN: Tidak Dapat Dihapus</p>
                            <p class="mt-1 text-sm text-amber-700">
                                Kelas <strong>"{{ $deletingName }}"</strong> masih memiliki
                                <strong>{{ $schoolClass->members_count }} peserta</strong> catering.
                            </p>
                            <p class="mt-1 text-sm text-amber-700">
                                Hapus peserta terlebih dahulu sebelum menghapus kelas ini.
                            </p>
                            <p class="mt-2 text-sm font-medium text-amber-800">Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    @endif
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <button type="button" wire:click="closeDeleteModal" class="btn-secondary">Batal</button>
                    <button type="button" wire:click="deleteConfirmed" class="btn-danger">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif

    </div>

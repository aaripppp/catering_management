@use(App\Enums\CateringParticipantGroup)

<div class="space-y-5">

    <section class="card card-body">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:max-w-xl">
                <div>
                    <label for="category-search" class="label">Pencarian</label>
                    <div class="relative">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
                        <input id="category-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari kategori harga" class="input input-has-icon" />
                    </div>
                </div>
                <div>
                    <label for="category-status" class="label">Status</label>
                    <select id="category-status" wire:model.live="status" class="select">
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
            </div>

            <button type="button" wire:click="create" wire:loading.attr="disabled" class="btn-primary shrink-0">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                Tambah Kategori
            </button>
        </div>
    </section>

    <section class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="w-10 text-center">No</th>
                        <th>Kategori</th>
                        <th>Kelompok Peserta</th>
                        <th>Harga/Hari</th>
                        <th>Peserta</th>
                        <th>Keterangan</th>
                        <th>Status</th>
                        <th class="text-right w-32">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr wire:key="catering-category-{{ $category->id }}">
                            <td class="text-center text-slate-500 text-sm">
                                {{ $categories->firstItem() + $loop->index }}
                            </td>
                            <td class="font-medium text-slate-900">{{ $category->name }}</td>
                            <td>
                                @if ($category->participant_group === CateringParticipantGroup::Student)
                                    <span class="badge-info"><span class="h-1.5 w-1.5 rounded-full bg-sky-500"></span>{{ $category->participant_group->label() }}</span>
                                @else
                                    <span class="badge-neutral"><span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>{{ $category->participant_group->label() }}</span>
                                @endif
                            </td>
                            <td class="font-semibold text-slate-900">{{ $category->formatted_price_per_day }}</td>
                            <td class="text-slate-600">{{ $category->members_count }}</td>
                            <td class="max-w-xs truncate text-slate-600">{{ $category->description ?? '-' }}</td>
                            <td>
                                @if ($category->is_active)
                                    <span class="badge-success"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                @else
                                    <span class="badge-neutral"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div class="flex items-center justify-end gap-1">
                                    <button type="button" wire:click="edit({{ $category->id }})" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50">Edit</button>
                                    <button type="button" wire:click="confirmDelete({{ $category->id }})" class="btn-danger-soft">Hapus</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center">
                                    <div class="icon-box">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75V12l6.75 6.75a2.12 2.12 0 0 0 3 0l4.5-4.5a2.12 2.12 0 0 0 0-3L12 4.5H6.75A2.25 2.25 0 0 0 4.5 6.75Z" /></svg>
                                    </div>
                                    <p class="mt-3 font-medium text-slate-700">Belum ada kategori harga</p>
                                    <p class="mt-1 text-sm text-slate-500">Tambahkan kategori untuk menentukan paket catering harian.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($categories->hasPages())
        <div>{{ $categories->links() }}</div>
    @endif

    @if ($errors->has('delete'))
        <div class="alert-danger flex items-center gap-2" role="alert">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v4.5m0 3h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            {{ $errors->first('delete') }}
        </div>
    @endif

    {{-- CREATE/EDIT MODAL --}}
    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
            <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]"></div>

            <div class="relative z-10 w-full max-w-lg rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="category-modal-title" class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit Kategori Harga' : 'Tambah Kategori Harga' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Atur nama paket, kelompok peserta, dan harga catering per hari.</p>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup formulir">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form wire:submit="save">
                    <div class="space-y-5 px-5 py-5 sm:px-6">
                        <div>
                            <label for="category-name" class="label">Nama Kategori</label>
                            <input id="category-name" type="text" wire:model="name" class="input" placeholder="Contoh: Siswa Umum" />
                            @error('name')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="category-price" class="label">Harga per Hari (Rp)</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">Rp</span>
                                <input id="category-price" type="number" inputmode="numeric" min="0" step="500" wire:model="price_per_day" class="input pl-10" placeholder="17000" />
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">Masukkan angka penuh tanpa titik atau koma.</p>
                            @error('price_per_day')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="category-participant-group" class="label">Kelompok Peserta</label>
                            <select id="category-participant-group" wire:model="participant_group" class="select">
                                <option value="">Pilih kelompok peserta</option>
                                @foreach ($participantGroupOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs text-slate-500">Menentukan apakah peserta ini ditampilkan sebagai siswa (dengan jenjang &amp; kelas) atau pegawai di halaman absensi.</p>
                            @error('participant_group')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="category-description" class="label">Keterangan</label>
                            <textarea id="category-description" rows="3" wire:model="description" class="input" placeholder="Contoh: Siswa reguler"></textarea>
                            @error('description')
                                <p class="field-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="checkbox mt-0.5" />
                            <span><span class="font-medium text-slate-800">Status aktif</span><span class="mt-0.5 block text-xs text-slate-500">Kategori aktif dapat dipilih pada data peserta.</span></span>
                        </label>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeFormModal" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- DELETE CONFIRMATION MODAL --}}
    @if ($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <div class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>

            <div class="relative z-10 w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="delete-modal-title" class="text-base font-semibold text-slate-900">Hapus Kategori?</h3>
                        <p class="mt-1 text-sm text-slate-500">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <button type="button" wire:click="closeDeleteModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="px-5 py-4 sm:px-6">
                    <p class="text-sm text-slate-700">Apakah Anda yakin ingin menghapus kategori ini?</p>
                    <p class="mt-2 font-medium text-slate-900">{{ $deletingName }}</p>
                    @if ($deletingMemberCount > 0)
                        <div class="mt-3 p-3 rounded-lg bg-amber-50 border border-amber-200">
                            <p class="text-sm font-medium text-amber-800">PERINGATAN: Kaskade Hapus</p>
                            <p class="mt-1 text-sm text-amber-700">
                                Kategori <strong>"{{ $deletingName }}"</strong> digunakan oleh
                                <strong>{{ $deletingMemberCount }} peserta</strong> catering.
                            </p>
                            <p class="mt-1 text-sm text-amber-700">
                                Menghapus kategori ini akan <strong>juga menghapus {{ $deletingMemberCount }} peserta catering terkait</strong>.
                            </p>
                            <p class="mt-2 text-sm font-medium text-amber-800">Tindakan ini tidak dapat dibatalkan.</p>
                        </div>
                    @endif
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <button type="button" wire:click="closeDeleteModal" class="btn-secondary">Batal</button>
                    <button type="button" wire:click="deleteConfirmed" class="btn-danger">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif

    </div>
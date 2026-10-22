<div class="space-y-5">
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
                        <th class="w-24 text-right">Aksi</th>
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
                            <td class="text-right">
                                <button type="button" wire:click="edit({{ $unit->id }})" class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center">
                                <p class="font-medium text-slate-700">Belum ada unit catering</p>
                                <p class="mt-1 text-sm text-slate-500">Jalankan seeder unit catering untuk menyiapkan data awal.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($showEditModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeEditModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="unit-catering-modal-title">
            <div wire:click="closeEditModal" class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="unit-catering-modal-title" class="text-base font-semibold text-slate-900">Edit Unit Catering</h3>
                        <p class="mt-1 text-sm text-slate-500">Perbarui nama, harga per orang, dan status unit.</p>
                    </div>
                    <button type="button" wire:click="closeEditModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup formulir">
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
                            <label for="unit-catering-price" class="label">Harga / Orang</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-slate-400">Rp</span>
                                <input id="unit-catering-price" type="number" inputmode="numeric" min="0" step="1" wire:model="price_per_person" class="input pl-10" placeholder="13000" />
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">Masukkan angka penuh tanpa titik atau koma.</p>
                            @error('price_per_person')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="checkbox mt-0.5" />
                            <span><span class="font-medium text-slate-800">Status aktif</span><span class="mt-0.5 block text-xs text-slate-500">Unit aktif akan tersedia untuk input unit catering berikutnya.</span></span>
                        </label>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeEditModal" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="save" class="btn-primary">
                            <span wire:loading.remove wire:target="save">Simpan Perubahan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

<div>
    @if ($open)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.set('open', false)" wire:click.self="$set('open', false)" class="modal-backdrop" wire:key="catering-member-modal" role="dialog" aria-modal="true" aria-labelledby="member-modal-title">
            <div class="modal-panel max-w-2xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="member-modal-title" class="text-base font-semibold text-slate-900">{{ $cateringMemberId ? 'Ubah Peserta' : 'Tambah Peserta' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Lengkapi data peserta dan pilihan catering.</p>
                    </div>
                    <button type="button" wire:click="$set('open', false)" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup formulir">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form wire:submit="save" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                        <div>
                            <label for="member-name" class="label">Nama Peserta</label>
                            <input id="member-name" type="text" wire:model="name" class="input" placeholder="Nama lengkap peserta" />
                            @error('name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="member-class" class="label">Kelas</label>
                                <select id="member-class" wire:model="school_class_id" class="select">
                                    <option value="">Tanpa kelas</option>
                                    @foreach ($classes as $schoolClass)
                                        <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                                    @endforeach
                                </select>
                                @error('school_class_id')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="member-category" class="label">Kategori Harga</label>
                                <select id="member-category" wire:model="catering_category_id" class="select">
                                    <option value="">Pilih kategori</option>
                                    @foreach ($categories as $cateringCategory)
                                        <option value="{{ $cateringCategory->id }}">{{ $cateringCategory->name }} ({{ $cateringCategory->formatted_price_per_day }}/hari)</option>
                                    @endforeach
                                </select>
                                @error('catering_category_id')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="member-gender" class="label">Jenis Kelamin</label>
                                <select id="member-gender" wire:model="gender" class="select">
                                    <option value="">Tidak diisi</option>
                                    @foreach ($genders as $gender)
                                        <option value="{{ $gender->value }}">{{ $gender->label() }}</option>
                                    @endforeach
                                </select>
                                @error('gender')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="member-phone" class="label">Telepon Peserta</label>
                                <input id="member-phone" type="text" wire:model="phone" class="input" placeholder="08xxxxxxxxxx" />
                                @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="member-guardian" class="label">Nama Wali</label>
                                <input id="member-guardian" type="text" wire:model="guardian_name" class="input" placeholder="Nama orang tua atau wali" />
                                @error('guardian_name')<p class="field-error">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="member-guardian-phone" class="label">Telepon Wali</label>
                                <input id="member-guardian-phone" type="text" wire:model="guardian_phone" class="input" placeholder="08xxxxxxxxxx" />
                                @error('guardian_phone')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        <div>
                            <label for="member-notes" class="label">Catatan</label>
                            <textarea id="member-notes" rows="3" wire:model="notes" class="input" placeholder="Alergi, catatan medis, atau preferensi khusus"></textarea>
                            @error('notes')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <label class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <input type="checkbox" wire:model="is_active" class="checkbox mt-0.5" />
                            <span><span class="font-medium text-slate-800">Status aktif</span><span class="mt-0.5 block text-xs text-slate-500">Peserta aktif dapat diproses dalam layanan catering.</span></span>
                        </label>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="$set('open', false)" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" class="btn-primary">
                            <span wire:loading.remove wire:target="save">Simpan</span>
                            <span wire:loading wire:target="save">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

<div class="space-y-5">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <a href="{{ route('catering-members.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-700">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6" /></svg>
            Kembali ke Peserta Catering
        </a>

        <div class="flex items-center gap-2 text-xs font-medium text-slate-500 sm:text-sm">
            @foreach ([1 => 'Upload', 2 => 'Preview', 3 => 'Selesai'] as $number => $label)
                <span class="flex h-7 w-7 items-center justify-center rounded-full {{ $step >= $number ? 'bg-blue-600 text-white' : 'bg-slate-200 text-slate-500' }}">{{ $number }}</span>
                <span class="hidden sm:inline {{ $step === $number ? 'font-semibold text-slate-900' : '' }}">{{ $label }}</span>
                @if (! $loop->last)
                    <span class="h-px w-5 bg-slate-300"></span>
                @endif
            @endforeach
        </div>
    </div>

    @if ($step === 1)
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
            <section class="card overflow-hidden">
                <div class="card-header">
                    <div>
                        <h2 class="card-title">Upload Data Peserta</h2>
                        <p class="muted mt-1">Pilih file XLSX siswa atau pegawai untuk divalidasi sebelum data disimpan.</p>
                    </div>
                </div>

                <div class="card-body space-y-5">
                    <div>
                        <label for="catering-member-import-file" class="label">File Excel</label>
                        <input
                            id="catering-member-import-file"
                            type="file"
                            wire:model="file"
                            accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                            class="block w-full rounded-lg border border-slate-300 bg-white text-sm text-slate-600 file:mr-4 file:border-0 file:bg-blue-50 file:px-4 file:py-2.5 file:font-semibold file:text-blue-700 hover:file:bg-blue-100"
                        />
                        <p class="mt-1.5 text-xs text-slate-500">Format .xlsx, maksimum 12 MB. Baris kosong akan diabaikan.</p>
                        @error('file')
                            <p class="field-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <button type="button" wire:click="previewImport" wire:loading.attr="disabled" class="btn-primary">
                        <span wire:loading.remove wire:target="previewImport">Validasi & Preview</span>
                        <span wire:loading wire:target="previewImport">Memvalidasi...</span>
                    </button>
                </div>
            </section>

            <aside class="card card-body h-fit">
                <div class="icon-box">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8m-6-6 6 6m-6-6v6h6" /></svg>
                </div>
                <h2 class="mt-3 text-base font-semibold text-slate-900">Template Excel</h2>
                <p class="mt-1 text-sm text-slate-500">Template memuat referensi kelas, kelompok kategori, dan panduan pengisian siswa maupun pegawai seperti guru, TU, yayasan, dan SDM.</p>
                <button type="button" wire:click="downloadTemplate" class="btn-secondary mt-4 w-full">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" /></svg>
                    Download Template
                </button>

                <div class="mt-4 space-y-2 border-t border-slate-200 pt-4 text-sm text-slate-600">
                    <p class="font-semibold text-slate-900">Wajib</p>
                    <p>Nama untuk semua peserta.</p>
                    <p class="pt-1 font-semibold text-slate-900">Kondisional</p>
                    <p>Kelas wajib untuk kategori siswa dan harus dikosongkan untuk kategori pegawai.</p>
                    <p class="pt-1 font-semibold text-slate-900">Opsional</p>
                    <p>Kategori, Jenis Kelamin, data wali, nomor HP, dan catatan.</p>
                    <p class="pt-1 text-xs text-slate-500">Kategori kosong otomatis menggunakan Siswa Umum.</p>
                </div>
            </aside>
        </div>
    @elseif ($step === 2)
        <section class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h2 class="card-title">Validasi & Preview</h2>
                    <p class="muted mt-1">Periksa seluruh baris sebelum melanjutkan import.</p>
                </div>

                @if ($preview['has_errors'])
                    <span class="badge-danger">Perbaiki file sebelum import</span>
                @else
                    <span class="badge-success">Semua data valid</span>
                @endif
            </div>

            <div class="grid gap-3 p-5 sm:grid-cols-3 sm:p-6">
                @foreach ([
                    ['value' => $preview['summary']['total'], 'label' => 'Data ditemukan', 'class' => 'border-slate-200 bg-slate-50 text-slate-900'],
                    ['value' => $preview['summary']['ready'], 'label' => 'Siap Import', 'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800'],
                    ['value' => $preview['summary']['errors'], 'label' => 'Bermasalah', 'class' => 'border-red-200 bg-red-50 text-red-800'],
                ] as $card)
                    <div class="rounded-xl border p-4 {{ $card['class'] }}">
                        <p class="text-2xl font-semibold">{{ $card['value'] }}</p>
                        <p class="mt-1 text-sm">{{ $card['label'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mx-5 mb-5 max-h-[55vh] overflow-auto rounded-xl border border-slate-200 sm:mx-6 sm:mb-6">
                <table class="table min-w-[760px]">
                    <thead class="sticky top-0 z-10">
                        <tr>
                            <th class="w-16 text-center">No</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>Kategori</th>
                            <th class="min-w-72">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($preview['rows'] as $row)
                            <tr class="{{ $row['status'] === 'error' ? 'bg-red-50/60' : '' }}">
                                <td class="text-center text-slate-500" title="Baris Excel {{ $row['row_number'] }}">{{ $loop->iteration }}</td>
                                <td class="font-medium text-slate-900">{{ $row['name'] ?: '-' }}</td>
                                <td class="text-slate-600">{{ $row['resolved_class_name'] ?: '-' }}</td>
                                <td class="text-slate-600">{{ $row['resolved_category_name'] ?: '-' }}</td>
                                <td>
                                    @if ($row['status'] === 'ready')
                                        <span class="badge-success">Siap Import</span>
                                    @else
                                        <span class="badge-danger">Bermasalah</span>
                                        <p class="mt-1 text-xs leading-relaxed text-red-700">{{ implode(' ', $row['errors']) }}</p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @error('import')
                <div class="alert-danger mx-5 mb-4 sm:mx-6">{{ $message }}</div>
            @enderror

            <div class="sticky bottom-0 flex flex-col-reverse justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:flex-row sm:px-6">
                <button type="button" wire:click="backToSetup" class="btn-secondary">Ganti File</button>
                <button type="button" wire:click="confirmImport" wire:loading.attr="disabled" @disabled($preview['has_errors']) class="btn-primary">
                    <span wire:loading.remove wire:target="confirmImport">Konfirmasi & Import</span>
                    <span wire:loading wire:target="confirmImport">Mengimpor...</span>
                </button>
            </div>
        </section>
    @else
        <section class="card card-body mx-auto max-w-2xl text-center">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12.5 4 4L19 7" /></svg>
            </div>
            <h2 class="mt-4 text-xl font-semibold text-slate-900">Import Berhasil</h2>
            <p class="mt-2 text-base text-slate-600">{{ $result['total'] }} peserta berhasil diimport</p>

            @if ($result['breakdown'] !== [])
                <div class="mt-6 divide-y divide-slate-200 rounded-xl border border-slate-200 text-left">
                    @foreach ($result['breakdown'] as $category => $count)
                        <div class="flex justify-between px-4 py-3 text-sm">
                            <span class="text-slate-600">{{ $category }}</span>
                            <span class="font-semibold text-slate-900">{{ $count }} peserta</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('catering-members.index') }}" class="btn-primary mt-6">Kembali ke Peserta Catering</a>
        </section>
    @endif
</div>

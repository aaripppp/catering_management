<div
    class="space-y-5"
    x-data="{
        openActionMenuId: null,
        actionMemberHasOpenPeriod: false,
        actionMenuStyle: '',
        actionTrigger: null,
        toggleActionMenu(memberId, hasOpenPeriod, trigger) {
            if (this.openActionMenuId === memberId) {
                this.closeActionMenu();

                return;
            }

            const rect = trigger.getBoundingClientRect();
            const width = Math.min(208, window.innerWidth - 16);
            const estimatedHeight = 186;
            const left = Math.min(
                Math.max(8, rect.right - width),
                window.innerWidth - width - 8,
            );
            const top = rect.bottom + estimatedHeight + 8 <= window.innerHeight
                ? rect.bottom + 6
                : Math.max(8, rect.top - estimatedHeight - 6);

            this.openActionMenuId = memberId;
            this.actionMemberHasOpenPeriod = hasOpenPeriod;
            this.actionTrigger = trigger;
            this.actionMenuStyle = `left: ${left}px; top: ${top}px; width: ${width}px;`;
            this.$nextTick(() => this.$refs.actionMenu?.querySelector('button')?.focus());
        },
        closeActionMenu(returnFocus = false) {
            const trigger = this.actionTrigger;

            this.openActionMenuId = null;
            this.actionMemberHasOpenPeriod = false;
            this.actionTrigger = null;

            if (returnFocus) {
                this.$nextTick(() => trigger?.focus());
            }
        },
    }"
    x-on:resize.window="closeActionMenu()"
    x-on:scroll.window="closeActionMenu()"
    x-on:keydown.escape.window="closeActionMenu(true)"
>

    <section class="card card-body">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div class="grid flex-1 gap-3 sm:grid-cols-2 xl:max-w-5xl xl:grid-cols-5">
                <div>
                    <label for="member-search" class="label">Pencarian</label>
                    <div class="relative">
                        <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="6.5" /><path stroke-linecap="round" d="m16 16 4 4" /></svg>
                        <input id="member-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Cari peserta" class="input input-has-icon" />
                    </div>
                </div>
                <div>
                    <label for="member-jenjang-filter" class="label">Jenjang</label>
                    <select id="member-jenjang-filter" wire:model.live="jenjang" class="select">
                        <option value="">Semua jenjang</option>
                        @foreach ($jenjangOptions as $jenjangOption)
                            <option value="{{ $jenjangOption }}">{{ $jenjangOption }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="member-class-filter" class="label">Kelas</label>
                    <select id="member-class-filter" wire:model.live="schoolClassId" class="select">
                        <option value="">Semua kelas</option>
                        @foreach ($classes as $schoolClass)
                            <option value="{{ $schoolClass->id }}">{{ $schoolClass->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="member-category-filter" class="label">Kategori</label>
                    <select id="member-category-filter" wire:model.live="cateringCategoryId" class="select">
                        <option value="">Semua kategori</option>
                        @foreach ($categories as $cateringCategory)
                            <option value="{{ $cateringCategory->id }}">{{ $cateringCategory->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="member-status-filter" class="label">Status</label>
                    <select id="member-status-filter" wire:model.live="status" class="select">
                        <option value="">Semua status</option>
                        <option value="active">Aktif</option>
                        <option value="inactive">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('catering-members.import') }}" class="btn-secondary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 15v4h14v-4" /></svg>
                    Import Data
                </a>
                <button type="button" wire:click="create" wire:loading.attr="disabled" class="btn-primary">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    Tambah Peserta
                </button>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="selectCurrentPage" wire:loading.attr="disabled"
                    wire:target="selectCurrentPage" class="btn btn-sm btn-secondary" @disabled($members->isEmpty())>
                    Pilih Halaman Ini
                </button>
                <button type="button" wire:click="selectAllFiltered" wire:loading.attr="disabled"
                    wire:target="selectAllFiltered" class="btn btn-sm btn-secondary" @disabled($members->total() === 0)>
                    Pilih Semua Hasil Filter
                </button>
            </div>

            @if ($selectedIds !== [])
                <div class="flex flex-wrap items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2">
                    <span class="text-sm font-semibold text-red-800">{{ count($selectedIds) }} peserta dipilih</span>
                    <button type="button" wire:click="clearSelection" class="btn btn-sm btn-secondary">Batalkan Pilihan</button>
                    <button type="button" wire:click="confirmBulkDelete" class="btn btn-sm btn-danger">Hapus Terpilih</button>
                </div>
            @endif
        </div>

        <div class="overflow-x-auto" x-on:scroll="closeActionMenu()">
            <table class="table w-full">
                <thead>
                    <tr>
                        <th class="w-10 text-center"><span class="sr-only">Pilih</span></th>
                        <th class="w-10 text-center">No</th>
                        <th class="min-w-56">Peserta</th>
                        <th class="min-w-44 whitespace-nowrap">Kelas / Penempatan</th>
                        <th>Kategori</th>
                        <th class="min-w-40">Wali</th>
                        <th class="min-w-44 whitespace-nowrap">Status Catering</th>
                        <th class="w-16 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($members as $member)
                        <tr wire:key="catering-member-{{ $member->id }}">
                            <td class="text-center">
                                <input type="checkbox" wire:model.live="selectedIds" value="{{ $member->id }}"
                                    class="checkbox" aria-label="Pilih {{ $member->name }}" />
                            </td>
                            <td class="text-center text-slate-500 text-sm">
                                {{ $members->firstItem() + $loop->index }}
                            </td>
                            <td>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="font-medium text-slate-900">{{ $member->name }}</span>
                                    @if (! $member->is_active)
                                        <span class="badge-neutral whitespace-nowrap">Nonaktif Peserta</span>
                                    @endif
                                </div>
                                <div class="mt-0.5 whitespace-nowrap text-xs text-slate-500">
                                    {{ $member->gender?->label() ?? '-' }}
                                    @if ($member->phone)
                                        &middot; {{ $member->phone }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-slate-600">
                                @php($assignment = $member->employeeAssignment)
                                @if ($member->schoolClass)
                                    <div class="whitespace-nowrap">
                                        @if ($assignment)
                                            <span class="text-xs text-slate-400">Kelas peserta:</span>
                                        @endif
                                        {{ $member->schoolClass->name }}
                                    </div>
                                @endif
                                @if ($assignment)
                                    <div class="flex items-center gap-1.5 whitespace-nowrap {{ $member->schoolClass ? 'mt-1' : '' }}">
                                        <span class="badge-info whitespace-nowrap">{{ $assignment->assignment_type?->label() }}</span>
                                        @if ($assignment->assignment_type?->value !== 'general')
                                            <span class="text-xs {{ $assignment->has_missing_target ? 'text-amber-600' : 'text-slate-500' }}">
                                                {{ $assignment->target_label }}
                                            </span>
                                        @endif
                                    </div>
                                @elseif (! $member->schoolClass)
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($member->cateringCategory)
                                    <span class="badge-info whitespace-nowrap">{{ $member->cateringCategory->name }}</span>
                                @else
                                    <span class="text-slate-400">-</span>
                                @endif
                            </td>
                            <td class="text-slate-600">
                                <div>{{ $member->guardian_name ?? '-' }}</div>
                                @if ($member->guardian_phone)
                                    <div class="mt-0.5 whitespace-nowrap text-xs text-slate-500">{{ $member->guardian_phone }}</div>
                                @endif
                            </td>
                            <td>
                                @php($openPeriod = $member->periods->first(fn ($period) => $period->end_date === null))
                                @php($lastEnd = $member->periods->whereNotNull('end_date')->max('end_date'))
                                @if ($openPeriod)
                                    <span class="badge-success"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Aktif</span>
                                    <div class="mt-1 whitespace-nowrap text-xs text-slate-500">
                                        Sejak {{ $openPeriod->start_date->locale('id')->translatedFormat('d F Y') }}
                                    </div>
                                @else
                                    <span class="badge-neutral"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Tidak Aktif</span>
                                    @if ($lastEnd)
                                        <div class="mt-1 whitespace-nowrap text-xs text-slate-500">
                                            Terakhir {{ $lastEnd->locale('id')->translatedFormat('d F Y') }}
                                        </div>
                                    @else
                                        <div class="mt-1 whitespace-nowrap text-xs text-slate-500">Belum pernah ikut</div>
                                    @endif
                                @endif
                            </td>
                            <td class="text-right">
                                <button
                                    type="button"
                                    x-on:click.stop="toggleActionMenu({{ $member->id }}, @js($openPeriod !== null), $el)"
                                    x-bind:aria-expanded="openActionMenuId === {{ $member->id }}"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-xl font-semibold leading-none text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                    aria-label="Buka aksi untuk {{ $member->name }}"
                                    aria-haspopup="menu"
                                    aria-controls="member-action-menu"
                                >
                                    <span aria-hidden="true">&#8942;</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <div class="mx-auto flex max-w-sm flex-col items-center">
                                    <div class="icon-box">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.1a8.5 8.5 0 0 0-3-.52c-1.05 0-2.06.19-3 .52m6 0a6 6 0 0 1 3.3 2.1M15 19.1a4.5 4.5 0 1 0-6 0m0 0a6 6 0 0 0-3.3 2.1M15.75 6.75a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" /></svg>
                                    </div>
                                    <p class="mt-3 font-medium text-slate-700">Belum ada peserta</p>
                                    <p class="mt-1 text-sm text-slate-500">Tambahkan peserta untuk mulai mengelola layanan catering.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="openActionMenuId !== null"
            x-ref="actionMenu"
            x-bind:style="actionMenuStyle"
            x-on:click.outside="closeActionMenu()"
            id="member-action-menu"
            class="fixed z-[60] overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-xl"
            style="display: none;"
            role="menu"
            aria-label="Aksi peserta"
        >
            <button type="button" x-on:click="const memberId = openActionMenuId; closeActionMenu(); $wire.edit(memberId)" class="block w-full px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900" role="menuitem">Edit Peserta</button>
            <button type="button" x-on:click="const memberId = openActionMenuId; closeActionMenu(); $wire.openHistoryModal(memberId)" class="block w-full px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:bg-slate-50 hover:text-slate-900" role="menuitem">Riwayat Catering</button>
            <button type="button" x-show="actionMemberHasOpenPeriod" x-on:click="const memberId = openActionMenuId; closeActionMenu(); $wire.openStopModal(memberId)" class="block w-full px-3 py-2 text-left text-sm font-semibold text-amber-700 transition hover:bg-amber-50 hover:text-amber-800" style="display: none;" role="menuitem">Berhenti Catering</button>
            <button type="button" x-show="! actionMemberHasOpenPeriod" x-on:click="const memberId = openActionMenuId; closeActionMenu(); $wire.openRejoinModal(memberId)" class="block w-full px-3 py-2 text-left text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800" style="display: none;" role="menuitem">Mulai Catering Lagi</button>
            <div class="my-1 border-t border-slate-100" role="separator"></div>
            <button type="button" x-on:click="const memberId = openActionMenuId; closeActionMenu(); $wire.confirmDelete(memberId)" class="block w-full px-3 py-2 text-left text-sm font-semibold text-red-600 transition hover:bg-red-50 hover:text-red-700" role="menuitem">Hapus Peserta</button>
        </div>
    </template>

    @if ($members->hasPages())
        <div>{{ $members->links() }}</div>
    @endif

    @if ($errors->has('delete'))
        <div class="alert-danger flex items-center gap-2" role="alert">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v4.5m0 3h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            {{ $errors->first('delete') }}
        </div>
    @endif

    @if ($errors->has('membership'))
        <div class="alert-danger flex items-center gap-2" role="alert">
            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9.75v4.5m0 3h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            {{ $errors->first('membership') }}
        </div>
    @endif

    {{-- CREATE/EDIT MODAL --}}
    @if ($showFormModal)
        <div
            x-scroll-lock
            x-on:keydown.escape.window="$wire.closeFormModal()"
            class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="member-modal-title"
        >
            <div wire:click="closeFormModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="member-modal-title" class="text-base font-semibold text-slate-900">{{ $editingId ? 'Edit Peserta' : 'Tambah Peserta' }}</h3>
                        <p class="mt-1 text-sm text-slate-500">Lengkapi data peserta dan pilihan catering.</p>
                    </div>
                    <button type="button" wire:click="closeFormModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup formulir">
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

                        @if (! $editingId)
                            <div>
                                <label for="member-mulai-catering" class="label">Mulai Catering</label>
                                <input id="member-mulai-catering" type="date" wire:model="mulai_catering" class="input" />
                                <p class="mt-1 text-xs text-slate-500">Tanggal pertama peserta mulai mengikuti catering. Tanggal ini bisa diisi tanggal lampau, hari ini, atau mendatang.</p>
                                @error('mulai_catering')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        @endif

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
                                <select id="member-category" wire:model.live="catering_category_id" class="select">
                                    <option value="">Pilih kategori</option>
                                    @foreach ($categories as $cateringCategory)
                                        <option value="{{ $cateringCategory->id }}">{{ $cateringCategory->name }} ({{ $cateringCategory->formatted_price_per_day }}/hari)</option>
                                    @endforeach
                                </select>
                                @error('catering_category_id')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        </div>

                        @if ($selectedCategoryIsEmployee)
                            <div class="rounded-lg border border-blue-200 bg-blue-50/60 p-4">
                                <div class="mb-3">
                                    <p class="text-sm font-semibold text-blue-900">Penempatan Pegawai</p>
                                    <p class="mt-0.5 text-xs text-blue-700">Dipakai untuk rekap catering, bukan sebagai wewenang wali kelas.</p>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <label for="member-assignment-type" class="label">Jenis Penempatan</label>
                                        <select id="member-assignment-type" wire:model.live="employee_assignment_type" class="select">
                                            <option value="">Pilih jenis penempatan</option>
                                            @foreach ($assignmentTypes as $assignmentValue => $assignmentLabel)
                                                <option value="{{ $assignmentValue }}">{{ $assignmentLabel }}</option>
                                            @endforeach
                                        </select>
                                        @error('employee_assignment_type')<p class="field-error">{{ $message }}</p>@enderror
                                    </div>

                                    @if ($selectedAssignmentType?->targetsSchoolClass())
                                        <div>
                                            <label for="member-assignment-class" class="label">Kelas</label>
                                            <select id="member-assignment-class" wire:model="employee_assignment_school_class_id" class="select">
                                                <option value="">Pilih kelas</option>
                                                @foreach ($assignmentClasses as $assignmentClass)
                                                    <option value="{{ $assignmentClass->id }}">{{ $assignmentClass->name }}</option>
                                                @endforeach
                                            </select>
                                            @error('employee_assignment_school_class_id')<p class="field-error">{{ $message }}</p>@enderror
                                        </div>
                                    @elseif ($selectedAssignmentType?->targetsLevel())
                                        <div>
                                            <label for="member-assignment-level" class="label">Tingkat</label>
                                            <select id="member-assignment-level" wire:model="employee_assignment_level" class="select">
                                                <option value="">Pilih tingkat</option>
                                                @foreach ($levelOptions as $jenjang => $levels)
                                                    <optgroup label="{{ $jenjang }}">
                                                        @foreach ($levels as $levelValue => $levelLabel)
                                                            <option value="{{ $levelValue }}">{{ $levelLabel }}</option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                            @error('employee_assignment_level')<p class="field-error">{{ $message }}</p>@enderror
                                        </div>
                                    @elseif ($selectedAssignmentType?->targetsEducationLevel())
                                        <div>
                                            <label for="member-assignment-education-level" class="label">Jenjang</label>
                                            <select id="member-assignment-education-level" wire:model="employee_assignment_education_level" class="select">
                                                <option value="">Pilih jenjang</option>
                                                @foreach ($educationLevelOptions as $educationLevelOption)
                                                    <option value="{{ $educationLevelOption }}">{{ $educationLevelOption }}</option>
                                                @endforeach
                                            </select>
                                            @error('employee_assignment_education_level')<p class="field-error">{{ $message }}</p>@enderror
                                        </div>
                                    @elseif ($selectedAssignmentType)
                                        <div>
                                            <span class="label">Penempatan</span>
                                            <p class="rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm text-slate-700">Umum &mdash; tidak terikat kelas, tingkat, atau jenjang.</p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif

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
                            <span><span class="font-medium text-slate-800">Status peserta aktif</span><span class="mt-0.5 block text-xs text-slate-500">Menandai data peserta tidak diarsipkan. Keikutsertaan catering diatur terpisah melalui periode.</span></span>
                        </label>
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
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
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeDeleteModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="delete-modal-title">
            <div wire:click="closeDeleteModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="delete-modal-title" class="text-base font-semibold text-slate-900">
                            {{ $isBulkDelete ? 'Hapus '.$deletingMemberCount.' Peserta?' : 'Hapus Peserta?' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                    <button type="button" wire:click="closeDeleteModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-4 sm:px-6">
                    @if ($isBulkDelete)
                        <p class="text-sm text-slate-700">
                            Anda akan menghapus <strong>{{ $deletingMemberCount }} peserta</strong> beserta seluruh data terkait.
                        </p>
                    @else
                        <p class="text-sm text-slate-700">Anda akan menghapus peserta berikut beserta data terkait:</p>
                        <p class="mt-2 font-medium text-slate-900">{{ $deletingName }}</p>
                    @endif

                    <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                        Data periode keikutsertaan, absensi, penempatan pegawai, tagihan, pembayaran, kredit, dan alokasi kredit terkait akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.
                    </div>

                    @error('delete')
                        <div class="mt-3 rounded-lg border border-red-300 bg-red-100 p-3 text-sm font-medium text-red-900" role="alert">
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <button type="button" wire:click="closeDeleteModal" class="btn-secondary">Batal</button>
                    <button type="button" wire:click="deleteConfirmed" wire:loading.attr="disabled"
                        wire:target="deleteConfirmed" class="btn-danger">
                        {{ $isBulkDelete ? 'Hapus '.$deletingMemberCount.' Peserta' : 'Ya, Hapus' }}
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- STOP CATERING MODAL --}}
    @if ($showStopModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeStopModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="stop-modal-title">
            <div wire:click="closeStopModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="stop-modal-title" class="text-base font-semibold text-slate-900">Berhenti Catering</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $stoppingName }}</p>
                    </div>
                    <button type="button" wire:click="closeStopModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form wire:submit="stopCatering" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                        <p class="text-sm text-slate-600">Mulai: <span class="font-medium text-slate-900">{{ $stoppingStart }}</span></p>
                        <div>
                            <label for="member-tanggal-terakhir" class="label">Tanggal Terakhir Ikut Catering</label>
                            <input id="member-tanggal-terakhir" type="date" wire:model="tanggal_terakhir" class="input" />
                            <p class="mt-1 text-xs text-slate-500">Tanggal ini adalah hari terakhir peserta masih dihitung ikut catering. Hari setelahnya tidak lagi dihitung.</p>
                            @error('tanggal_terakhir')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        @error('membership')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeStopModal" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="stopCatering" class="btn-primary">
                            <span wire:loading.remove wire:target="stopCatering">Simpan</span>
                            <span wire:loading wire:target="stopCatering">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- REJOIN CATERING MODAL --}}
    @if ($showRejoinModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeRejoinModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="rejoin-modal-title">
            <div wire:click="closeRejoinModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="rejoin-modal-title" class="text-base font-semibold text-slate-900">Mulai Catering Lagi</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $rejoiningName }}</p>
                    </div>
                    <button type="button" wire:click="closeRejoinModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <form wire:submit="rejoinCatering" class="flex min-h-0 flex-1 flex-col">
                    <div class="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                        <p class="text-sm text-slate-600">Peserta akan kembali dihitung ikut catering mulai tanggal yang dipilih.</p>
                        <div>
                            <label for="member-mulai-catering-lagi" class="label">Mulai Catering</label>
                            <input id="member-mulai-catering-lagi" type="date" wire:model="mulai_catering_lagi" class="input" />
                            @error('mulai_catering_lagi')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        @error('membership')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" wire:click="closeRejoinModal" class="btn-secondary">Batal</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="rejoinCatering" class="btn-primary">
                            <span wire:loading.remove wire:target="rejoinCatering">Simpan</span>
                            <span wire:loading wire:target="rejoinCatering">Menyimpan...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- MEMBERSHIP HISTORY MODAL --}}
    @if ($showHistoryModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeHistoryModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="history-modal-title">
            <div wire:click="closeHistoryModal" class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm" aria-hidden="true"></div>

            <div class="relative z-10 flex max-h-[90vh] max-h-[90dvh] w-full max-w-md flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="history-modal-title" class="text-base font-semibold text-slate-900">Periode Keikutsertaan</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $historyName }}</p>
                    </div>
                    <button type="button" wire:click="closeHistoryModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                    @forelse ($historyPeriods as $period)
                        <div class="flex items-center justify-between border-b border-slate-100 py-2.5 text-sm last:border-0">
                            <span class="text-slate-700">{{ $period['start'] }}</span>
                            <span class="text-slate-400">&ndash;</span>
                            <span class="{{ $period['end'] === null ? 'font-semibold text-emerald-600' : 'text-slate-700' }}">
                                {{ $period['end'] ?? 'Sekarang' }}
                            </span>
                        </div>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-500">Peserta belum memiliki riwayat keikutsertaan.</p>
                    @endforelse
                </div>

                <div class="flex shrink-0 justify-end border-t border-slate-200 bg-slate-50 px-5 py-4 sm:px-6">
                    <button type="button" wire:click="closeHistoryModal" class="btn-secondary">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    </div>

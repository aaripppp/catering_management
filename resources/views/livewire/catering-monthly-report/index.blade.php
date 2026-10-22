@use(App\Enums\CateringBillStatus)
@use(App\Enums\CateringParticipantGroup)

<div class="space-y-5">
    <section class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">Laporan Bulanan</h2>
            <p class="mt-1 text-sm text-slate-500">Rekap tagihan dan pembayaran catering per periode.</p>
        </div>
        <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
            <button type="button" wire:click="downloadExcel" wire:loading.attr="disabled" wire:target="downloadExcel" class="btn-secondary whitespace-nowrap">
                <span wire:loading.remove wire:target="downloadExcel">Export Excel</span>
                <span wire:loading wire:target="downloadExcel">Menyiapkan...</span>
            </button>
            <a href="{{ $pdfPreviewUrl }}" target="_blank" rel="noopener" class="btn-primary whitespace-nowrap">Preview PDF</a>
        </div>
    </section>

    <section class="card card-body">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div>
                <label for="report-month" class="label">Bulan</label>
                <select id="report-month" wire:model.live="month" class="select">
                    @foreach ($monthOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="report-year" class="label">Tahun</label>
                <select id="report-year" wire:model.live="year" class="select">
                    @foreach ($yearOptions as $value)
                        <option value="{{ $value }}">{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="report-group" class="label">Kelompok</label>
                <select id="report-group" wire:model.live="participantGroup" class="select">
                    @foreach ($participantGroupOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if ($participantGroup === CateringParticipantGroup::Student->value)
                <div>
                    <label for="report-level" class="label">Jenjang</label>
                    <select id="report-level" wire:model.live="jenjang" class="select">
                        <option value="">Semua jenjang</option>
                        @foreach ($jenjangOptions as $value)
                            <option value="{{ $value }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="report-class" class="label">Kelas</label>
                    <select id="report-class" wire:model.live="schoolClassId" class="select">
                        <option value="">Semua kelas</option>
                        @foreach ($classOptions as $schoolClass)
                            <option value="{{ $schoolClass['id'] }}">{{ $schoolClass['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
    </section>

    <section class="space-y-4" aria-labelledby="financial-overview-title">
        <div>
            <h3 id="financial-overview-title" class="text-lg font-bold text-slate-900">Ringkasan Keuangan Utama</h3>
            <p class="mt-1 text-xs text-slate-500">Ringkasan utama menggunakan Bulan dan Tahun. Filter peserta, jenjang, dan kelas berlaku untuk detail laporan di bawah.</p>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <h4 class="text-sm font-bold text-slate-900">Siswa</h4>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Tagihan Siswa</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold text-slate-900">Rp {{ number_format($financialOverview['student']['billed'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Tagihan bulan terpilih</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total Terbayar Siswa</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold text-emerald-700">Rp {{ number_format($financialOverview['student']['paid'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Pembayaran dan kredit terpakai</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-white p-4 shadow-sm sm:col-span-2 lg:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Tunggakan Siswa</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold text-amber-700">Rp {{ number_format($financialOverview['student']['remaining'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">Sisa dihitung per tagihan</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <h4 class="text-sm font-bold text-slate-900">Pegawai / Unit</h4>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Tagihan Unit</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold text-slate-900">Rp {{ number_format($financialOverview['unit']['billed'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total Terbayar Unit</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold text-emerald-700">Rp {{ number_format($financialOverview['unit']['paid'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-white p-4 shadow-sm sm:col-span-2 lg:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Tunggakan Unit</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold text-amber-700">Rp {{ number_format($financialOverview['unit']['remaining'], 0, ',', '.') }}</p>
                </div>
            </div>
            @if ($financialOverview['unit']['has_stale'])
                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-800">
                    Sebagian Tagihan Unit perlu disinkronkan. Nilai yang tampil menggunakan snapshot tagihan terakhir.
                </div>
            @endif
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4">
            <h4 class="text-sm font-bold text-slate-900">Budaya Makan</h4>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tagihan Tercatat</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold text-slate-900">Rp {{ number_format($financialOverview['culture']['billed'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format($financialOverview['culture']['recorded_count'], 0, ',', '.') }} data × Rp {{ number_format($financialOverview['culture']['price'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Pemasukan Manual</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold text-emerald-700">Rp {{ number_format($financialOverview['culture']['income'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-blue-200 bg-white p-4 shadow-sm sm:col-span-2 lg:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Selisih</p>
                    <p class="mt-2 whitespace-nowrap text-2xl font-bold {{ $financialOverview['culture']['difference'] < 0 ? 'text-violet-700' : 'text-blue-700' }}">
                        {{ $financialOverview['culture']['difference'] < 0 ? '-Rp '.number_format(abs($financialOverview['culture']['difference']), 0, ',', '.') : 'Rp '.number_format($financialOverview['culture']['difference'], 0, ',', '.') }}
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50/60 p-4 shadow-sm">
            <h4 class="text-sm font-bold text-blue-950">Total Keseluruhan</h4>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl border border-blue-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Total Semua Tagihan</p>
                    <p class="mt-2 whitespace-nowrap text-3xl font-bold text-blue-950">Rp {{ number_format($financialOverview['total']['billed'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-emerald-200 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total Semua Terbayar</p>
                    <p class="mt-2 whitespace-nowrap text-3xl font-bold text-emerald-700">Rp {{ number_format($financialOverview['total']['paid'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-white p-4 sm:col-span-2 lg:col-span-1">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Total Selisih Belum Tertutup</p>
                    <p class="mt-2 whitespace-nowrap text-3xl font-bold text-amber-700">Rp {{ number_format($financialOverview['total']['uncovered_difference'], 0, ',', '.') }}</p>
                </div>
            </div>
        </div>
    </section>

    @php
        $paidPercentage = $summary['bill_count'] > 0
            ? round(($summary['paid_count'] / $summary['bill_count']) * 100, 1)
            : 0;
        $formattedPaidPercentage = rtrim(rtrim(number_format($paidPercentage, 1, ',', '.'), '0'), ',');
    @endphp

    <section id="student-payment-statistics" class="space-y-4" aria-labelledby="student-payment-statistics-title">
        <div>
            <h3 id="student-payment-statistics-title" class="text-lg font-bold text-slate-900">Statistik Pembayaran Siswa</h3>
            <p class="mt-1 text-xs text-slate-500">Mengikuti filter peserta, jenjang, dan kelas yang dipilih.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
            <div class="card flex min-h-40 flex-col p-5">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" /></svg>
                </div>
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-blue-700">Peserta Ditagih</p>
                <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">{{ number_format($summary['bill_count'], 0, ',', '.') }} <span class="text-base font-semibold text-slate-500">peserta</span></p>
            </div>
            <div class="card flex min-h-40 flex-col p-5">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /><path stroke-linecap="round" stroke-linejoin="round" d="m8.5 12 2.25 2.25 4.75-5" /></svg>
                </div>
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-emerald-700">Jumlah Lunas</p>
                <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">{{ number_format($summary['paid_count'], 0, ',', '.') }} <span class="text-base font-semibold text-slate-500">peserta</span></p>
            </div>
            <div class="card flex min-h-40 flex-col p-5">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /><path stroke-linecap="round" d="M12 7.5V12h4.5" /></svg>
                </div>
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-amber-700">Jumlah Sebagian</p>
                <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">{{ number_format($summary['partial_count'], 0, ',', '.') }} <span class="text-base font-semibold text-slate-500">peserta</span></p>
            </div>
            <div class="card flex min-h-40 flex-col p-5">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /><path stroke-linecap="round" d="M8.5 12h7" /></svg>
                </div>
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Belum Bayar</p>
                <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">{{ number_format($summary['unpaid_count'], 0, ',', '.') }} <span class="text-base font-semibold text-slate-500">peserta</span></p>
            </div>
            <div class="card flex min-h-40 flex-col p-5">
                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /><path stroke-linecap="round" d="M12 3.75V12h8.25M7.5 8.25l9 7.5" /></svg>
                </div>
                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-violet-700">Persentase Lunas</p>
                <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">{{ $formattedPaidPercentage }}%</p>
            </div>
        </div>
    </section>

    <section id="actual-income" class="card card-body space-y-4" aria-labelledby="actual-income-title">
        <div>
            <h3 id="actual-income-title" class="text-lg font-bold text-slate-900">Pemasukan Aktual</h3>
            <p class="mt-1 text-xs text-slate-500">Berdasarkan tanggal transaksi, bukan periode tagihan.</p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label for="income-from-date" class="label">Dari Tanggal</label>
                <input id="income-from-date" type="date" wire:model.live="incomeFromDate" class="input" />
                @error('incomeFromDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="income-to-date" class="label">Sampai Tanggal</label>
                <input id="income-to-date" type="date" wire:model.live="incomeToDate" class="input" />
                @error('incomeToDate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200">
            <table class="table w-full text-sm">
                <thead><tr><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
                <tbody>
                    @foreach ([
                        ['Pemasukan Siswa', $actualIncome['student']],
                        ['Pemasukan Unit', $actualIncome['unit']],
                        ['Pemasukan Budaya Makan', $actualIncome['culture']],
                    ] as [$label, $amount])
                        <tr><td class="font-medium text-slate-700">{{ $label }}</td><td class="whitespace-nowrap text-right font-semibold text-slate-900">Rp {{ number_format($amount, 0, ',', '.') }}</td></tr>
                    @endforeach
                    <tr class="bg-blue-50/70">
                        <td class="font-bold text-blue-950">Total Pemasukan</td>
                        <td class="whitespace-nowrap text-right font-bold text-blue-950">Rp {{ number_format($actualIncome['total'], 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="space-y-4">
        <div class="overflow-x-auto rounded-xl bg-slate-100 p-1" role="tablist" aria-label="Bagian laporan bulanan">
            <div class="flex min-w-max gap-1">
                @foreach ([
                    'summary' => 'Ringkasan',
                    'groups' => $groupTabLabel,
                    'details' => 'Detail Pembayaran',
                ] as $tabValue => $tabLabel)
                    <button type="button" wire:click="setTab('{{ $tabValue }}')" role="tab" aria-selected="{{ $tab === $tabValue ? 'true' : 'false' }}" @class([
                        'rounded-lg px-4 py-2 text-sm font-semibold transition',
                        'bg-white text-blue-700 shadow-sm' => $tab === $tabValue,
                        'text-slate-600 hover:text-slate-900' => $tab !== $tabValue,
                    ])>{{ $tabLabel }}</button>
                @endforeach
            </div>
        </div>

        @if ($tab === 'summary')
            <div id="all-domain-summary" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <article id="summary-student" class="overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm">
                    <div class="border-b border-blue-100 bg-blue-50/70 px-4 py-3">
                        <h3 class="font-bold text-blue-950">Siswa</h3>
                    </div>
                    <table class="table w-full text-sm">
                        <thead><tr><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach ([
                                ['Total Tagihan', 'Rp '.number_format($summary['gross_amount'], 0, ',', '.')],
                                ['Total Terbayar', 'Rp '.number_format($summary['paid_amount'], 0, ',', '.')],
                                ['Tunggakan', 'Rp '.number_format($summary['outstanding_amount'], 0, ',', '.')],
                                ['Lebih Bayar', 'Rp '.number_format($summary['overpayment_amount'], 0, ',', '.')],
                                ['Lunas', number_format($summary['paid_count'], 0, ',', '.')],
                                ['Sebagian', number_format($summary['partial_count'], 0, ',', '.')],
                                ['Belum Bayar', number_format($summary['unpaid_count'], 0, ',', '.')],
                            ] as [$label, $value])
                                <tr><td class="font-medium text-slate-700">{{ $label }}</td><td class="whitespace-nowrap text-right font-semibold text-slate-900">{{ $value }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>

                <article id="summary-unit" class="overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-sm">
                    <div class="border-b border-emerald-100 bg-emerald-50/70 px-4 py-3">
                        <h3 class="font-bold text-emerald-950">Pegawai / Unit</h3>
                    </div>
                    <table class="table w-full text-sm">
                        <thead><tr><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach ([
                                ['Total Tagihan Unit', 'Rp '.number_format($financialOverview['unit']['billed'], 0, ',', '.')],
                                ['Total Terbayar Unit', 'Rp '.number_format($financialOverview['unit']['paid'], 0, ',', '.')],
                                ['Tunggakan Unit', 'Rp '.number_format($financialOverview['unit']['remaining'], 0, ',', '.')],
                                ['Lebih Bayar Unit', 'Rp '.number_format($financialOverview['unit']['overpaid'], 0, ',', '.')],
                                ['Unit Lunas', number_format($financialOverview['unit']['paid_count'], 0, ',', '.')],
                                ['Unit Sebagian', number_format($financialOverview['unit']['partial_count'], 0, ',', '.')],
                                ['Unit Belum Bayar', number_format($financialOverview['unit']['unpaid_count'], 0, ',', '.')],
                            ] as [$label, $value])
                                <tr><td class="font-medium text-slate-700">{{ $label }}</td><td class="whitespace-nowrap text-right font-semibold text-slate-900">{{ $value }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($financialOverview['unit']['has_stale'])
                        <div class="border-t border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800">
                            Sebagian Tagihan Unit perlu disinkronkan. Nilai menggunakan snapshot terakhir.
                        </div>
                    @endif
                </article>

                <article id="summary-culture" class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-sm">
                    <div class="border-b border-amber-100 bg-amber-50/70 px-4 py-3">
                        <h3 class="font-bold text-amber-950">Budaya Makan</h3>
                    </div>
                    <table class="table w-full text-sm">
                        <thead><tr><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            <tr><td class="font-medium text-slate-700">Data Tercatat</td><td class="whitespace-nowrap text-right font-semibold text-slate-900">{{ number_format($financialOverview['culture']['recorded_count'], 0, ',', '.') }}</td></tr>
                            <tr><td class="font-medium text-slate-700">Harga Budaya Makan</td><td class="whitespace-nowrap text-right font-semibold text-slate-900">Rp {{ number_format($financialOverview['culture']['price'], 0, ',', '.') }}</td></tr>
                            <tr><td class="font-medium text-slate-700">Tagihan Tercatat</td><td class="whitespace-nowrap text-right font-semibold text-slate-900">Rp {{ number_format($financialOverview['culture']['billed'], 0, ',', '.') }}</td></tr>
                            <tr><td class="font-medium text-slate-700">Pemasukan Manual</td><td class="whitespace-nowrap text-right font-semibold text-slate-900">Rp {{ number_format($financialOverview['culture']['income'], 0, ',', '.') }}</td></tr>
                            <tr>
                                <td class="font-medium text-slate-700">Selisih</td>
                                <td class="whitespace-nowrap text-right font-semibold {{ $financialOverview['culture']['difference'] < 0 ? 'text-violet-700' : 'text-slate-900' }}">
                                    {{ $financialOverview['culture']['difference'] < 0 ? '-Rp '.number_format(abs($financialOverview['culture']['difference']), 0, ',', '.') : 'Rp '.number_format($financialOverview['culture']['difference'], 0, ',', '.') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </article>

                <article id="summary-total" class="overflow-hidden rounded-xl border border-blue-300 bg-blue-50/40 shadow-sm">
                    <div class="border-b border-blue-200 bg-blue-100/70 px-4 py-3">
                        <h3 class="font-bold text-blue-950">Total Keseluruhan</h3>
                    </div>
                    <table class="table w-full text-sm">
                        <thead><tr><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
                        <tbody>
                            @foreach ([
                                ['Total Semua Tagihan', 'Rp '.number_format($financialOverview['total']['billed'], 0, ',', '.')],
                                ['Total Semua Terbayar', 'Rp '.number_format($financialOverview['total']['paid'], 0, ',', '.')],
                                ['Total Selisih Belum Tertutup', 'Rp '.number_format($financialOverview['total']['uncovered_difference'], 0, ',', '.')],
                            ] as [$label, $value])
                                <tr><td class="font-semibold text-blue-950">{{ $label }}</td><td class="whitespace-nowrap text-right font-bold text-blue-950">{{ $value }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </article>
            </div>
        @elseif ($summary['bill_count'] === 0)
            <div class="card card-body py-12 text-center">
                <p class="font-medium text-slate-700">Belum ada data laporan untuk periode/filter ini.</p>
            </div>
        @elseif ($tab === 'groups')
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table w-full min-w-[1500px]">
                        <thead class="whitespace-nowrap"><tr><th class="w-10 text-center">No</th><th>Kelas / Kelompok</th><th class="text-center">Peserta</th><th class="text-right">Total Tagihan</th><th class="text-right">Uang Masuk</th><th class="text-right">Kredit Digunakan</th><th class="text-right">Tunggakan</th><th class="text-right">Lebih Bayar</th><th class="text-center">Lunas</th><th class="text-center">Sebagian</th><th class="text-center">Belum Bayar</th></tr></thead>
                        <tbody>
                            @foreach ($groups as $index => $group)
                                <tr wire:key="report-group-{{ $index }}-{{ $group['label'] }}">
                                    <td class="text-center text-sm text-slate-500">{{ $index + 1 }}</td>
                                    <td class="whitespace-nowrap font-semibold text-slate-900">{{ $group['label'] }}</td>
                                    <td class="text-center">{{ $group['participant_count'] }}</td>
                                    <td class="whitespace-nowrap text-right">Rp {{ number_format($group['gross_amount'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap text-right text-emerald-700">Rp {{ number_format($group['money_in'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap text-right text-blue-700">Rp {{ number_format($group['credit_used'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap text-right text-amber-700">Rp {{ number_format($group['outstanding_amount'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap text-right text-violet-700">Rp {{ number_format($group['overpayment_amount'], 0, ',', '.') }}</td>
                                    <td class="text-center">{{ $group['paid_count'] }}</td><td class="text-center">{{ $group['partial_count'] }}</td><td class="text-center">{{ $group['unpaid_count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="table-wrap">
                <div class="overflow-x-auto">
                    <table class="table w-full min-w-[1380px]">
                        <thead class="whitespace-nowrap"><tr><th class="w-10 text-center">No</th><th>Peserta</th><th>Kelas / Kelompok</th><th>Kategori</th><th class="text-right">Tagihan</th><th class="text-right">Terbayar</th><th class="text-right">Kredit Dipakai</th><th class="text-right">Lebih Bayar</th><th class="text-right">Sisa</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($details as $detail)
                                <tr wire:key="report-detail-{{ $detail['id'] }}">
                                    <td class="text-center text-sm text-slate-500">{{ $details->firstItem() + $loop->index }}</td>
                                    <td class="whitespace-nowrap font-semibold text-slate-900">{{ $detail['member_name'] }}</td>
                                    <td class="whitespace-nowrap text-slate-600">{{ $detail['group_label'] }}</td><td class="whitespace-nowrap text-slate-600">{{ $detail['category_name'] }}</td>
                                    <td class="whitespace-nowrap text-right font-semibold">Rp {{ number_format($detail['gross_amount'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap text-right text-emerald-700">Rp {{ number_format($detail['paid_amount'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap text-right text-blue-700">Rp {{ number_format($detail['credit_applied_amount'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap text-right {{ $detail['generated_credit_amount'] > 0 ? 'text-violet-700' : 'text-slate-500' }}">Rp {{ number_format($detail['generated_credit_amount'], 0, ',', '.') }}</td>
                                    <td class="whitespace-nowrap text-right {{ $detail['outstanding_amount'] > 0 ? 'text-amber-700' : 'text-slate-500' }}">Rp {{ number_format($detail['outstanding_amount'], 0, ',', '.') }}</td>
                                    <td><span @class([
                                        'whitespace-nowrap',
                                        'badge-success' => $detail['payment_status'] === CateringBillStatus::Paid->value,
                                        'inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200' => $detail['payment_status'] === CateringBillStatus::Partial->value,
                                        'badge-neutral' => $detail['payment_status'] === CateringBillStatus::Unpaid->value,
                                    ])>{{ $detail['payment_status_label'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($details->hasPages())
                <div>{{ $details->links() }}</div>
            @endif
        @endif
    </section>

    <div wire:loading.flex wire:target="month,year,participantGroup,jenjang,schoolClassId,incomeFromDate,incomeToDate,setTab" class="fixed inset-x-0 bottom-5 z-40 justify-center px-4 pointer-events-none">
        <span class="rounded-full bg-navy-900 px-4 py-2 text-xs font-semibold text-white shadow-lg">Memperbarui laporan...</span>
    </div>
</div>

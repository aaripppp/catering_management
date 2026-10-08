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

    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card flex min-h-40 flex-col p-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 3.75h9.75L19.5 7.5v12.75H6V3.75Z" /><path stroke-linecap="round" d="M15.75 3.75V7.5h3.75M9 12h7.5M9 15.75h5.25" /></svg>
            </div>
            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-500">Total Tagihan</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">Rp {{ number_format($summary['gross_amount'], 0, ',', '.') }}</p>
        </div>
        <div class="card flex min-h-40 flex-col p-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5h16.5v10.5a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18V7.5Z" /><path stroke-linecap="round" d="M3.75 10.5h16.5M12 3.75v4.5m0 0-2.25-2.25M12 8.25 14.25 6" /></svg>
            </div>
            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-emerald-700">Jumlah Uang Masuk</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">Rp {{ number_format($summary['money_in'], 0, ',', '.') }}</p>
        </div>
        <div class="card flex min-h-40 flex-col p-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6.75h15v10.5h-15V6.75Z" /><path stroke-linecap="round" d="M4.5 10.5h15M8.25 14.25h3M17.25 3.75 19.5 6l-2.25 2.25" /></svg>
            </div>
            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-blue-700">Kredit Digunakan</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">Rp {{ number_format($summary['credit_used'], 0, ',', '.') }}</p>
        </div>
        <div class="card flex min-h-40 flex-col p-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="12" r="8.25" /><path stroke-linecap="round" d="M12 7.5V12l3 1.5" /></svg>
            </div>
            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-amber-700">Total Tunggakan</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">Rp {{ number_format($summary['outstanding_amount'], 0, ',', '.') }}</p>
        </div>
        <div class="card flex min-h-40 flex-col p-5">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-50 text-violet-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5h15v10.5h-15V7.5Z" /><path stroke-linecap="round" d="M12 14.25v-6m0 0L9.75 10.5M12 8.25l2.25 2.25" /></svg>
            </div>
            <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-violet-700">Total Lebih Bayar</p>
            <p class="mt-1 whitespace-nowrap text-2xl font-bold tracking-tight text-slate-900">Rp {{ number_format($summary['overpayment_amount'], 0, ',', '.') }}</p>
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

        @if ($summary['bill_count'] === 0)
            <div class="card card-body py-12 text-center">
                <p class="font-medium text-slate-700">Belum ada data laporan untuk periode/filter ini.</p>
            </div>
        @elseif ($tab === 'summary')
            <div class="table-wrap">
                <table class="table w-full">
                    <thead class="whitespace-nowrap"><tr><th>Keterangan</th><th class="text-right">Jumlah</th></tr></thead>
                    <tbody>
                        @foreach ([
                            ['Total Tagihan', 'Rp '.number_format($summary['gross_amount'], 0, ',', '.')],
                            ['Uang Masuk', 'Rp '.number_format($summary['money_in'], 0, ',', '.')],
                            ['Kredit Digunakan', 'Rp '.number_format($summary['credit_used'], 0, ',', '.')],
                            ['Tunggakan', 'Rp '.number_format($summary['outstanding_amount'], 0, ',', '.')],
                            ['Lebih Bayar', 'Rp '.number_format($summary['overpayment_amount'], 0, ',', '.')],
                            ['Lunas', number_format($summary['paid_count'], 0, ',', '.').' peserta'],
                            ['Sebagian', number_format($summary['partial_count'], 0, ',', '.').' peserta'],
                            ['Belum Bayar', number_format($summary['unpaid_count'], 0, ',', '.').' peserta'],
                        ] as [$label, $value])
                            <tr><td class="whitespace-nowrap font-medium text-slate-700">{{ $label }}</td><td class="whitespace-nowrap text-right font-semibold text-slate-900">{{ $value }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
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

    <div wire:loading.flex wire:target="month,year,participantGroup,jenjang,schoolClassId,setTab" class="fixed inset-x-0 bottom-5 z-40 justify-center px-4 pointer-events-none">
        <span class="rounded-full bg-navy-900 px-4 py-2 text-xs font-semibold text-white shadow-lg">Memperbarui laporan...</span>
    </div>
</div>

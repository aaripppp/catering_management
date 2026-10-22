@use(App\Enums\CateringUnitBillSyncStatus)
@use(App\Enums\CateringUnitBillPaymentStatus)

<div class="space-y-5" wire:key="catering-unit-bills-page">
    <section class="card card-body">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="unit-bill-month" class="label">Bulan</label>
                    <select id="unit-bill-month" wire:model.live="month" class="select min-w-48">
                        @foreach ($monthOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('month')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="unit-bill-year" class="label">Tahun</label>
                    <select id="unit-bill-year" wire:model.live="year" class="select min-w-32">
                        @foreach ($yearOptions as $value)
                            <option value="{{ $value }}">{{ $value }}</option>
                        @endforeach
                    </select>
                    @error('year')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <button
                    type="button"
                    wire:click="sync"
                    wire:loading.attr="disabled"
                    wire:target="sync"
                    class="inline-flex h-11 w-full min-w-[230px] items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto"
                >
                    <span wire:loading.remove wire:target="sync" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5 15a7 7 0 1 0 1.73-6.73L5 9m14-6a7 7 0 1 1-1.73 6.73L19 15" />
                        </svg>
                        Sinkronkan Tagihan Unit
                    </span>
                    <span wire:loading wire:target="sync" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                            <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z" />
                        </svg>
                        Menyinkronkan...
                    </span>
                </button>

                @if ($invoiceReady)
                    <a
                        href="{{ route('catering-unit-bills.invoice.all', ['month' => $month, 'year' => $year]) }}"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-11 w-full min-w-[210px] items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
                        title="Buka invoice semua unit periode {{ $monthOptions[$month] }} {{ $year }} di tab baru"
                        aria-label="Invoice Semua Unit {{ $monthOptions[$month] }} {{ $year }}"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        Invoice Semua Unit
                    </a>
                @else
                    <span
                        aria-disabled="true"
                        class="inline-flex h-11 w-full min-w-[210px] cursor-not-allowed items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-slate-200 bg-slate-50 px-5 text-sm font-semibold text-slate-400 sm:w-auto"
                        title="Sinkronkan Tagihan Unit terlebih dahulu."
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        Invoice Semua Unit
                    </span>
                @endif
            </div>
        </div>
        <p class="mt-3 text-xs text-slate-500">Periode {{ $monthOptions[$month] }} {{ $year }}. Nilai tagihan hanya berubah setelah sinkronisasi. Perubahan absensi tidak mengubah snapshot tagihan secara otomatis.</p>
        @unless ($invoiceReady)
            <p class="mt-1 text-xs text-amber-600">Sinkronkan Tagihan Unit terlebih dahulu untuk mengaktifkan invoice semua unit.</p>
        @endunless
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Unit</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-slate-900">{{ number_format($summary['bill_count'], 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Qty</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-slate-900">{{ number_format($summary['total_quantity'], 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Total Tagihan</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-navy-900">Rp {{ number_format($summary['payment']['total_billed'], 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Total Terbayar</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-emerald-700">Rp {{ number_format($summary['payment']['total_paid'], 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Total Tunggakan</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-amber-700">Rp {{ number_format($summary['payment']['total_remaining'], 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">Total Lebih Bayar</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-violet-700">Rp {{ number_format($summary['payment']['total_overpaid'], 0, ',', '.') }}</p>
        </div>
    </section>

    <section class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table w-full min-w-[1400px]">
                <thead class="whitespace-nowrap">
                    <tr>
                        <th class="w-10 text-center">No</th>
                        <th>Unit</th>
                        <th class="text-right">Total Qty</th>
                        <th class="text-right">Harga/Porsi</th>
                        <th class="text-right">Tagihan</th>
                        <th class="text-right">Terbayar</th>
                        <th class="text-right">Lebih Bayar</th>
                        <th class="text-right">Sisa</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $bill = $row['bill'];
                            $status = $row['status'];
                            $unitName = $bill?->unit_name ?? $row['unit']->name;
                            $rowPaymentSummary = $bill === null ? null : $paymentSummaries[$bill->id];
                            $canPay = $bill !== null && $status === CateringUnitBillSyncStatus::Synced && $bill->gross_amount > 0;
                        @endphp
                        <tr wire:key="unit-bill-row-{{ $row['unit']->id }}-{{ $bill?->id ?? 'none' }}">
                            <td class="text-center text-sm text-slate-500">{{ $loop->iteration }}</td>
                            <td>
                                <p class="whitespace-nowrap font-semibold text-slate-900">{{ $unitName }}</p>
                                <span @class([
                                    'mt-1 whitespace-nowrap',
                                    'badge-success' => $status === CateringUnitBillSyncStatus::Synced,
                                    'inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200' => $status === CateringUnitBillSyncStatus::Stale,
                                    'inline-flex items-center rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-200' => $status === CateringUnitBillSyncStatus::Unsynced,
                                    'badge-neutral' => $status === CateringUnitBillSyncStatus::NoData,
                                ])>
                                    {{ match ($status) {
                                        CateringUnitBillSyncStatus::Synced => 'Sinkron',
                                        CateringUnitBillSyncStatus::Stale => 'Perlu Sinkronisasi',
                                        CateringUnitBillSyncStatus::Unsynced => 'Belum Disinkronkan',
                                        CateringUnitBillSyncStatus::NoData => 'Belum Ada Data',
                                    } }}
                                </span>
                                @if ($bill === null && $row['source_row_count'] > 0)
                                    <p class="mt-0.5 whitespace-nowrap text-xs text-slate-500">Sumber absensi tersedia</p>
                                @endif
                                @if ($status === CateringUnitBillSyncStatus::Stale)
                                    <p class="mt-1 whitespace-nowrap text-xs text-amber-600">Sinkronkan tagihan terlebih dahulu.</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right font-medium text-slate-800">
                                {{ $bill === null ? '-' : number_format($bill->total_quantity, 0, ',', '.') }}
                            </td>
                            <td class="whitespace-nowrap text-right text-slate-600">
                                @if ($bill === null || ($bill->price_per_person_snapshot === null && $bill->days->isEmpty()))
                                    -
                                @elseif ($bill->price_per_person_snapshot === null)
                                    Tarif Campuran
                                @else
                                    Rp {{ number_format($bill->price_per_person_snapshot, 0, ',', '.') }}
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right font-semibold text-slate-900">
                                {{ $bill === null ? '-' : 'Rp '.number_format($bill->gross_amount, 0, ',', '.') }}
                            </td>
                            <td class="whitespace-nowrap text-right text-emerald-700">
                                {{ $rowPaymentSummary === null ? '-' : 'Rp '.number_format($rowPaymentSummary['paid_amount'], 0, ',', '.') }}
                            </td>
                            <td class="whitespace-nowrap text-right font-semibold {{ ($rowPaymentSummary['overpaid_amount'] ?? 0) > 0 ? 'text-violet-700' : 'text-slate-500' }}">
                                {{ $rowPaymentSummary === null ? '-' : 'Rp '.number_format($rowPaymentSummary['overpaid_amount'], 0, ',', '.') }}
                            </td>
                            <td class="whitespace-nowrap text-right font-semibold {{ ($rowPaymentSummary['remaining_amount'] ?? 0) > 0 ? 'text-amber-700' : 'text-slate-500' }}">
                                {{ $rowPaymentSummary === null ? '-' : 'Rp '.number_format($rowPaymentSummary['remaining_amount'], 0, ',', '.') }}
                            </td>
                            <td>
                                @if ($rowPaymentSummary === null)
                                    <span class="text-xs text-slate-400">-</span>
                                @else
                                    <span @class([
                                        'whitespace-nowrap',
                                        'badge-success' => $rowPaymentSummary['status'] === CateringUnitBillPaymentStatus::Paid,
                                        'inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200' => $rowPaymentSummary['status'] === CateringUnitBillPaymentStatus::Partial,
                                        'badge-neutral' => $rowPaymentSummary['status'] === CateringUnitBillPaymentStatus::Unpaid,
                                        'inline-flex items-center rounded-full bg-violet-50 px-2.5 py-1 text-xs font-semibold text-violet-700 ring-1 ring-inset ring-violet-200' => $rowPaymentSummary['status'] === CateringUnitBillPaymentStatus::Overpaid,
                                    ])>{{ $rowPaymentSummary['status_label'] }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex justify-end gap-1">
                                    @if ($bill !== null)
                                        @if ($canPay)
                                            <button type="button" wire:click="openPayment({{ $bill->id }})" wire:loading.attr="disabled" wire:target="openPayment({{ $bill->id }})" class="btn-primary whitespace-nowrap px-3 py-1.5 text-xs" wire:key="unit-bill-pay-{{ $bill->id }}">Bayar</button>
                                        @else
                                            <span
                                                aria-disabled="true"
                                                class="inline-flex cursor-not-allowed items-center rounded-lg bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-400 ring-1 ring-inset ring-slate-200"
                                                title="{{ $status === CateringUnitBillSyncStatus::Stale ? 'Sinkronkan tagihan terlebih dahulu.' : 'Tagihan unit tidak memiliki nilai tagihan.' }}"
                                            >Bayar</span>
                                        @endif
                                        <button type="button" wire:click="openHistory({{ $bill->id }})" class="btn-secondary whitespace-nowrap px-3 py-1.5 text-xs">Riwayat</button>
                                    @endif
                                    @if ($status === CateringUnitBillSyncStatus::Synced && $bill !== null)
                                        <a
                                            href="{{ route('catering-unit-bills.invoice', $bill) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-blue-600 transition hover:bg-blue-50"
                                            title="Buka invoice {{ $unitName }} di tab baru"
                                            aria-label="Invoice {{ $unitName }}"
                                        >
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                            Invoice
                                        </a>
                                    @elseif ($status === CateringUnitBillSyncStatus::NoData)
                                        <span class="px-2.5 py-1.5 text-xs text-slate-400" aria-hidden="true">-</span>
                                    @else
                                        <span
                                            aria-disabled="true"
                                            class="inline-flex cursor-not-allowed items-center rounded-full bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-400 ring-1 ring-inset ring-slate-200"
                                            title="Sinkronkan Tagihan Unit terlebih dahulu."
                                        >
                                            {{ $status === CateringUnitBillSyncStatus::Stale ? 'Perlu sinkronisasi' : 'Belum disinkronkan' }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-12 text-center">
                                <p class="font-medium text-slate-700">Belum ada unit catering untuk ditampilkan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($showPaymentModal && $paymentBillId && $paymentBill && $paymentSummary)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="unit-payment-modal-title">
            <div wire:click="closePaymentModal" class="absolute inset-0 bg-slate-950/55" aria-hidden="true"></div>
            <div class="relative z-10 flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="unit-payment-modal-title" class="text-base font-semibold text-slate-900">{{ $editingPaymentId ? 'Edit Pembayaran' : 'Pembayaran' }} {{ $paymentBill->unit_name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $monthOptions[$paymentBill->period_month] }} {{ $paymentBill->period_year }}</p>
                    </div>
                    <button type="button" wire:click="closePaymentModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Tagihan</p><p class="mt-1 whitespace-nowrap font-semibold text-slate-900">Rp {{ number_format($paymentSummary['billed_amount'], 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Terbayar</p><p class="mt-1 whitespace-nowrap font-semibold text-emerald-900">Rp {{ number_format($paymentSummary['paid_amount'], 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-amber-50 p-3"><p class="text-xs text-amber-700">Sisa</p><p class="mt-1 whitespace-nowrap font-semibold text-amber-900">Rp {{ number_format($paymentSummary['remaining_amount'], 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-violet-50 p-3"><p class="text-xs text-violet-700">Lebih Bayar</p><p class="mt-1 whitespace-nowrap font-semibold text-violet-900">Rp {{ number_format($paymentSummary['overpaid_amount'], 0, ',', '.') }}</p></div>
                    </div>

                    <form wire:submit="savePayment" class="space-y-4">
                        <div>
                            <label for="unit-payment-date" class="label">Tanggal Bayar</label>
                            <input id="unit-payment-date" type="date" wire:model="paymentDate" class="input" />
                            @error('paymentDate') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="unit-payment-amount" class="label">Nominal (Rp)</label>
                            <input id="unit-payment-amount" type="number" inputmode="numeric" min="1" step="1" wire:model="paymentAmount" class="input" />
                            @error('paymentAmount') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="unit-payment-method" class="label">Metode Pembayaran</label>
                            <input id="unit-payment-method" type="text" wire:model="paymentMethod" class="input" placeholder="Opsional, mis. Transfer" maxlength="100" />
                            @error('paymentMethod') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="unit-payment-note" class="label">Catatan</label>
                            <textarea id="unit-payment-note" rows="2" wire:model="paymentNote" class="input" placeholder="Opsional" maxlength="1000"></textarea>
                            @error('paymentNote') <p class="field-error">{{ $message }}</p> @enderror
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" wire:click="closePaymentModal" class="btn-secondary whitespace-nowrap">Batal</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="savePayment" class="btn-primary whitespace-nowrap">
                                <span wire:loading.remove wire:target="savePayment">{{ $editingPaymentId ? 'Simpan Perubahan' : 'Simpan Pembayaran' }}</span>
                                <span wire:loading wire:target="savePayment">Menyimpan...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @if ($showHistoryModal && $historyBill && $historySummary)
        <div class="fixed inset-0 z-[9999] flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="unit-payment-history-title">
            <div wire:click="closeHistoryModal" class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]" aria-hidden="true"></div>
            <div class="relative z-10 flex max-h-[92dvh] w-full max-w-3xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="unit-payment-history-title" class="text-base font-semibold text-slate-900">Riwayat Pembayaran {{ $historyBill->unit_name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $monthOptions[$historyBill->period_month] }} {{ $historyBill->period_year }}</p>
                    </div>
                    <button type="button" wire:click="closeHistoryModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Tagihan</p><p class="mt-1 whitespace-nowrap font-semibold text-slate-900">Rp {{ number_format($historySummary['billed_amount'], 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Terbayar</p><p class="mt-1 whitespace-nowrap font-semibold text-emerald-900">Rp {{ number_format($historySummary['paid_amount'], 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-amber-50 p-3"><p class="text-xs text-amber-700">Sisa</p><p class="mt-1 whitespace-nowrap font-semibold text-amber-900">Rp {{ number_format($historySummary['remaining_amount'], 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-violet-50 p-3"><p class="text-xs text-violet-700">Lebih Bayar</p><p class="mt-1 whitespace-nowrap font-semibold text-violet-900">Rp {{ number_format($historySummary['overpaid_amount'], 0, ',', '.') }}</p></div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-slate-200">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="whitespace-nowrap bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3 text-right">Nominal</th>
                                    <th class="px-4 py-3">Metode</th>
                                    <th class="px-4 py-3">Catatan</th>
                                    <th class="px-4 py-3">Dicatat Oleh</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse ($historyRows as $payment)
                                    <tr wire:key="unit-payment-history-{{ $payment->id }}">
                                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $payment->paid_at->format('d/m/Y') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900">Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $payment->payment_method ?: '-' }}</td>
                                        <td class="max-w-48 px-4 py-3 text-slate-600">{{ $payment->note ?: '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $payment->creator?->name ?: '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right">
                                            <div class="flex justify-end gap-1">
                                                <button type="button" wire:click="editPayment({{ $payment->id }})" class="whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">Edit</button>
                                                <button type="button" wire:click="confirmDeletePayment({{ $payment->id }})" class="btn-danger-soft whitespace-nowrap">Hapus</button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada riwayat pembayaran.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeletePaymentModal)
        <div class="fixed inset-0 z-[10000] flex items-center justify-center overflow-y-auto p-4" role="dialog" aria-modal="true" aria-labelledby="delete-unit-payment-title">
            <div wire:click="closeDeletePaymentModal" class="absolute inset-0 bg-slate-950/60" aria-hidden="true"></div>
            <div class="relative z-10 w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 id="delete-unit-payment-title" class="font-semibold text-slate-900">Hapus Pembayaran?</h3>
                    <p class="mt-1 text-sm text-slate-500">Terbayar, sisa, lebih bayar, dan status akan langsung dihitung ulang.</p>
                </div>
                <div class="flex justify-end gap-2 px-5 py-4">
                    <button type="button" wire:click="closeDeletePaymentModal" class="btn-secondary whitespace-nowrap">Batal</button>
                    <button type="button" wire:click="deletePayment" wire:loading.attr="disabled" wire:target="deletePayment" class="btn-danger whitespace-nowrap">Hapus</button>
                </div>
            </div>
        </div>
    @endif

    <div wire:loading.flex wire:target="month,year" class="fixed inset-x-0 bottom-5 z-40 justify-center px-4 pointer-events-none">
        <span class="rounded-full bg-navy-900 px-4 py-2 text-xs font-semibold text-white shadow-lg">Memperbarui tagihan unit...</span>
    </div>
</div>

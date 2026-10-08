@use(App\Enums\CateringBillStatus)
@use(App\Enums\CateringParticipantGroup)

@php
    $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
@endphp

<div class="space-y-5">
    <section class="card card-body">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
            <div>
                <label for="bill-month" class="label">Bulan</label>
                <select id="bill-month" wire:model.live="month" class="select">
                    @foreach ($monthNames as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="bill-year" class="label">Tahun</label>
                <select id="bill-year" wire:model.live="year" class="select">
                    @foreach ($yearOptions as $value)
                        <option value="{{ $value }}">{{ $value }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="bill-group" class="label">Kelompok</label>
                <select id="bill-group" wire:model.live="participantGroup" class="select">
                    @foreach ($participantGroupOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            @if ($participantGroup === CateringParticipantGroup::Student->value)
                <div>
                    <label for="bill-level" class="label">Jenjang</label>
                    <select id="bill-level" wire:model.live="jenjang" class="select">
                        <option value="">Semua jenjang</option>
                        @foreach ($jenjangOptions as $value)
                            <option value="{{ $value }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="bill-class" class="label">Kelas</label>
                    <select id="bill-class" wire:model.live="schoolClassId" class="select">
                        <option value="">Semua kelas</option>
                        @foreach ($classOptions as $classOption)
                            <option value="{{ $classOption['id'] }}">{{ $classOption['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        <p class="mt-3 text-xs text-slate-500">Memperbarui jumlah hari dari absensi terbaru, mempertahankan harga periode, dan menghitung ulang pembayaran serta kredit.</p>
        <div class="mt-3 flex flex-col gap-3 border-t border-slate-100 pt-4 sm:flex-row sm:items-center sm:justify-end">
            <button type="button" wire:click="generate" wire:loading.attr="disabled" wire:target="generate" class="inline-flex h-11 w-full min-w-[190px] flex-none items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-blue-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">
                <span wire:loading.remove wire:target="generate">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v6h6M20 20v-6h-6M5 15a7 7 0 1 0 1.73-6.73L5 9m14-6a7 7 0 1 1-1.73 6.73L19 15" />
                        </svg>
                        <span>Sinkronkan Tagihan</span>
                    </span>
                </span>
                <span wire:loading wire:target="generate">
                    <span class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" />
                            <path class="opacity-75" fill="currentColor" d="M12 3a9 9 0 0 1 9 9h-3a6 6 0 0 0-6-6V3Z" />
                        </svg>
                        <span>Memproses...</span>
                    </span>
                </span>
            </button>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Jumlah Tagihan</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-slate-900">{{ number_format((int) ($summary->bill_count ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Nilai Tagihan</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-navy-900">Rp {{ number_format((int) ($summary->gross_amount ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Jumlah Uang Masuk</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-emerald-700">Rp {{ number_format((int) ($summary->money_in ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-violet-600">Kredit Digunakan</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-violet-700">Rp {{ number_format((int) ($summary->credit_used ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Total Tunggakan</p>
            <p class="mt-2 whitespace-nowrap text-2xl font-bold text-amber-700">Rp {{ number_format((int) ($summary->outstanding_amount ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="card card-body">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Periode</p>
            <p class="mt-2 whitespace-nowrap text-lg font-bold text-slate-900">{{ $monthNames[$month] }} {{ $year }}</p>
        </div>
    </section>

    <section class="card card-body">
        <div class="grid gap-3 sm:grid-cols-2 lg:max-w-3xl">
            <div>
                <label for="bill-search" class="label">Pencarian</label>
                <input id="bill-search" type="search" wire:model.live.debounce.300ms="search" class="input" placeholder="Nama peserta atau kelas" />
            </div>
            <div>
                <label for="bill-status" class="label">Status Pembayaran</label>
                <select id="bill-status" wire:model.live="paymentStatus" class="select">
                    <option value="">Semua status</option>
                    @foreach ($paymentStatusOptions as $statusOption)
                        <option value="{{ $statusOption->value }}">{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    <section class="table-wrap">
        <div class="overflow-x-auto">
            <table class="table w-full min-w-[1480px]">
                <thead class="whitespace-nowrap">
                    <tr>
                        <th class="w-10 text-center">No</th>
                        <th>Peserta</th>
                        <th>Kelas / Kelompok</th>
                        <th class="text-center">Hari Aktif</th>
                        <th>Kategori</th>
                        <th class="text-right">Harga/Hari</th>
                        <th class="text-right">Tagihan</th>
                        <th class="text-right">Terbayar</th>
                        <th class="text-right">Lebih Bayar</th>
                        <th class="text-right">Sisa</th>
                        <th>Status</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($bills as $bill)
                        @php
                            $paid = $bill->effectivePaidAmount();
                            $overpayment = $bill->generatedCreditAmount();
                            $remaining = $bill->remainingAmount();
                        @endphp
                        <tr wire:key="catering-bill-{{ $bill->id }}">
                            <td class="text-center text-sm text-slate-500">{{ $bills->firstItem() + $loop->index }}</td>
                            <td>
                                <p class="whitespace-nowrap font-semibold text-slate-900">{{ $bill->member_name }}</p>
                                @if ((int) $bill->current_active_days !== $bill->active_days)
                                    <span class="mt-1 inline-flex whitespace-nowrap rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-200">Perlu sinkronisasi</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-slate-600">{{ $bill->school_class_name ?: $bill->participant_group->label() }}</td>
                            <td class="text-center font-medium text-slate-800">{{ $bill->active_days }}</td>
                            <td class="whitespace-nowrap text-slate-600">{{ $bill->category_name }}</td>
                            <td class="whitespace-nowrap text-right text-slate-600">Rp {{ number_format($bill->price_per_day, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap text-right font-semibold text-slate-900">Rp {{ number_format($bill->gross_amount, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap text-right text-emerald-700">Rp {{ number_format($paid, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap text-right font-semibold {{ $overpayment > 0 ? 'text-violet-700' : 'text-slate-500' }}">Rp {{ number_format($overpayment, 0, ',', '.') }}</td>
                            <td class="whitespace-nowrap text-right font-semibold {{ $remaining > 0 ? 'text-amber-700' : 'text-slate-500' }}">Rp {{ number_format($remaining, 0, ',', '.') }}</td>
                            <td>
                                <span @class([
                                    'whitespace-nowrap',
                                    'badge-success' => $bill->payment_status === CateringBillStatus::Paid,
                                    'inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-200' => $bill->payment_status === CateringBillStatus::Partial,
                                    'badge-neutral' => $bill->payment_status === CateringBillStatus::Unpaid,
                                ])>{{ $bill->payment_status->label() }}</span>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <div class="flex justify-end gap-1">
                                    @if ($remaining > 0)
                                        <button type="button" wire:click="openPayment({{ $bill->id }})" class="btn-primary whitespace-nowrap px-3 py-1.5 text-xs">Bayar</button>
                                    @endif
                                    <button type="button" wire:click="openPayment({{ $bill->id }})" class="btn-secondary whitespace-nowrap px-3 py-1.5 text-xs">Riwayat</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                                <td colspan="12" class="py-12 text-center">
                                <p class="font-medium text-slate-700">Belum ada tagihan pada periode ini</p>
                                <p class="mt-1 text-sm text-slate-500">Pilih cakupan peserta lalu klik Sinkronkan Tagihan.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($bills->hasPages())
        <div>{{ $bills->links() }}</div>
    @endif

    @if ($showPaymentModal && $selectedBill)
        @php $selectedRemaining = $selectedBill->remainingAmount(); @endphp
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closePaymentModal()" class="fixed inset-0 z-50 flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="payment-modal-title">
            <div wire:click="closePaymentModal" class="absolute inset-0 bg-slate-950/55 backdrop-blur-[1px]" aria-hidden="true"></div>
            <div class="relative z-10 flex max-h-[92dvh] w-full max-w-2xl flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="flex shrink-0 items-start justify-between border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 id="payment-modal-title" class="text-base font-semibold text-slate-900">Pembayaran {{ $selectedBill->member_name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ $monthNames[$selectedBill->period_month] }} {{ $selectedBill->period_year }} · {{ $selectedBill->school_class_name ?: $selectedBill->category_name }}</p>
                    </div>
                    <button type="button" wire:click="closePaymentModal" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Tutup">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" /></svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-lg bg-slate-50 p-3"><p class="text-xs text-slate-500">Tagihan</p><p class="mt-1 whitespace-nowrap font-semibold text-slate-900">Rp {{ number_format($selectedBill->gross_amount, 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Total Dibayar</p><p class="mt-1 whitespace-nowrap font-semibold text-emerald-900">Rp {{ number_format($selectedBill->paidAmount(), 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-amber-50 p-3"><p class="text-xs text-amber-700">Sisa</p><p class="mt-1 whitespace-nowrap font-semibold text-amber-900">Rp {{ number_format($selectedRemaining, 0, ',', '.') }}</p></div>
                        <div class="rounded-lg bg-sky-50 p-3"><p class="text-xs text-sky-700">Kredit</p><p class="mt-1 whitespace-nowrap font-semibold text-sky-900">Rp {{ number_format($selectedBill->generatedCreditAmount(), 0, ',', '.') }}</p></div>
                    </div>

                    @if ($selectedRemaining > 0 || $editingPaymentId !== null)
                        <form wire:submit="recordPayment" class="space-y-4 rounded-xl border border-slate-200 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-sm font-semibold text-slate-900">{{ $editingPaymentId ? 'Edit Pembayaran' : 'Tambah Pembayaran' }}</h4>
                                @if ($editingPaymentId)
                                    <button type="button" wire:click="cancelPaymentEdit" class="whitespace-nowrap text-xs font-semibold text-slate-500 hover:text-slate-800">Batal edit</button>
                                @endif
                            </div>
                            <div>
                                <label for="payment-amount" class="label">Nominal Pembayaran (Rp)</label>
                                <input id="payment-amount" type="number" min="1" step="1" inputmode="numeric" wire:model.live.debounce.250ms="paymentAmount" class="input" />
                                @error('paymentAmount') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="payment-date" class="label">Tanggal Pembayaran</label>
                                <input id="payment-date" type="date" wire:model="paymentDate" class="input" />
                                @error('paymentDate') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="payment-note" class="label">Catatan</label>
                                <textarea id="payment-note" rows="2" wire:model="paymentNote" class="input" placeholder="Opsional"></textarea>
                                @error('paymentNote') <p class="field-error">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                                <div><span class="text-slate-500">Sisa setelah bayar</span><strong class="mt-1 block whitespace-nowrap text-slate-900">Rp {{ number_format($remainingAfterPayment, 0, ',', '.') }}</strong></div>
                                <div><span class="text-slate-500">Menjadi kredit</span><strong class="mt-1 block whitespace-nowrap text-sky-700">Rp {{ number_format($overpaymentAmount, 0, ',', '.') }}</strong></div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" wire:loading.attr="disabled" wire:target="recordPayment" class="btn-primary whitespace-nowrap">
                                    <span wire:loading.remove wire:target="recordPayment">{{ $editingPaymentId ? 'Simpan Perubahan' : 'Simpan Pembayaran' }}</span>
                                    <span wire:loading wire:target="recordPayment">Menyimpan...</span>
                                </button>
                            </div>
                        </form>
                    @endif

                    <div>
                        <h4 class="text-sm font-semibold text-slate-900">Riwayat Pembayaran</h4>
                        <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full min-w-[560px] text-sm">
                                <thead class="whitespace-nowrap bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                    <tr><th class="px-4 py-3">Tanggal</th><th class="px-4 py-3 text-right">Nominal</th><th class="px-4 py-3">Catatan</th><th class="px-4 py-3 text-right">Aksi</th></tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @forelse ($selectedBill->payments as $payment)
                                        <tr wire:key="payment-history-{{ $payment->id }}">
                                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $payment->paid_at->format('d/m/Y') }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900">
                                                Rp {{ number_format($payment->amount, 0, ',', '.') }}
                                                @if ($payment->generatedCredit)<span class="mt-1 block whitespace-nowrap text-[10px] font-medium text-sky-700">Kredit Rp {{ number_format($payment->generatedCredit->original_amount, 0, ',', '.') }}</span>@endif
                                            </td>
                                            <td class="max-w-48 px-4 py-3 text-slate-600">{{ $payment->note ?: '-' }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                                <div class="flex justify-end gap-1">
                                                    <button type="button" wire:click="editPayment({{ $payment->id }})" class="whitespace-nowrap rounded-lg px-2.5 py-1.5 text-xs font-semibold text-blue-600 hover:bg-blue-50">Edit</button>
                                                    <button type="button" wire:click="confirmDeletePayment({{ $payment->id }})" class="btn-danger-soft whitespace-nowrap">Hapus</button>
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada transaksi pembayaran.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 px-4 py-3 text-sm">
                            <span class="whitespace-nowrap text-slate-600">Kredit terpakai: <strong class="text-slate-900">Rp {{ number_format($selectedBill->creditAppliedAmount(), 0, ',', '.') }}</strong></span>
                            <span class="whitespace-nowrap text-slate-600">Status: <strong class="text-slate-900">{{ $selectedBill->payment_status->label() }}</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showDeletePaymentModal)
        <div x-scroll-lock x-on:keydown.escape.window="$wire.closeDeletePaymentModal()" class="fixed inset-0 z-[60] flex items-center justify-center overflow-hidden p-4" role="dialog" aria-modal="true" aria-labelledby="delete-payment-title">
            <div wire:click="closeDeletePaymentModal" class="absolute inset-0 bg-slate-950/60" aria-hidden="true"></div>
            <div class="relative z-10 w-full max-w-md rounded-xl border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 id="delete-payment-title" class="font-semibold text-slate-900">Hapus Pembayaran?</h3>
                    <p class="mt-1 text-sm text-slate-500">Saldo, status, dan kredit akan langsung dihitung ulang.</p>
                </div>
                <div class="flex justify-end gap-2 px-5 py-4">
                    <button type="button" wire:click="closeDeletePaymentModal" class="btn-secondary whitespace-nowrap">Batal</button>
                    <button type="button" wire:click="deletePayment" wire:loading.attr="disabled" wire:target="deletePayment" class="btn-danger whitespace-nowrap">Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>

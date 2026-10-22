<?php

namespace App\Livewire\CateringUnitBills;

use App\Enums\CateringUnitBillPaymentStatus;
use App\Enums\CateringUnitBillSyncStatus;
use App\Models\CateringUnitBill;
use App\Models\CateringUnitPayment;
use App\Services\CateringUnitBillingService;
use App\Services\CateringUnitPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class Index extends Component
{
    public int $month;

    public int $year;

    public bool $showPaymentModal = false;

    public ?int $paymentBillId = null;

    public string $paymentAmount = '';

    public string $paymentDate = '';

    public string $paymentMethod = '';

    public string $paymentNote = '';

    public ?int $editingPaymentId = null;

    public bool $showHistoryModal = false;

    public ?int $historyBillId = null;

    public bool $showDeletePaymentModal = false;

    public ?int $deletingPaymentId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', CateringUnitBill::class);

        $this->month = now()->month;
        $this->year = now()->year;
    }

    public function updatedMonth(): void
    {
        $this->validateOnly('month', $this->periodRules());
    }

    public function updatedYear(): void
    {
        $this->validateOnly('year', $this->periodRules());
    }

    public function sync(CateringUnitBillingService $billingService): void
    {
        Gate::authorize('sync', CateringUnitBill::class);
        $validated = $this->validate($this->periodRules());

        try {
            $bills = $billingService->syncPeriod(
                month: $validated['month'],
                year: $validated['year'],
                generatedBy: auth()->id(),
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch(
                'toast',
                type: 'danger',
                message: 'Tagihan unit gagal disinkronkan. Silakan coba kembali.',
            );

            return;
        }

        $this->dispatch(
            'toast',
            type: $bills->isEmpty() ? 'info' : 'success',
            message: $bills->isEmpty()
                ? 'Tidak ada data absensi atau tagihan unit untuk disinkronkan.'
                : $bills->count().' tagihan unit berhasil disinkronkan.',
        );
    }

    public function openPayment(int $billId, CateringUnitPaymentService $paymentService): void
    {
        Gate::authorize('create', CateringUnitPayment::class);
        $bill = CateringUnitBill::query()->findOrFail($billId);
        $summary = $paymentService->summaryForBill($bill);

        $this->showPaymentModal = true;
        $this->paymentBillId = $bill->id;
        $this->paymentAmount = $summary['remaining_amount'] > 0 ? (string) $summary['remaining_amount'] : '';
        $this->paymentDate = now()->toDateString();
        $this->paymentMethod = '';
        $this->paymentNote = '';
        $this->editingPaymentId = null;
        $this->showHistoryModal = false;
        $this->historyBillId = null;
        $this->resetValidation();
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->paymentBillId = null;
        $this->paymentAmount = '';
        $this->paymentDate = '';
        $this->paymentMethod = '';
        $this->paymentNote = '';
        $this->editingPaymentId = null;
        $this->showDeletePaymentModal = false;
        $this->deletingPaymentId = null;
        $this->resetValidation();
    }

    public function savePayment(CateringUnitPaymentService $paymentService): void
    {
        $bill = CateringUnitBill::query()->findOrFail($this->paymentBillId);

        if ($this->editingPaymentId === null) {
            Gate::authorize('create', CateringUnitPayment::class);
        } else {
            Gate::authorize('update', CateringUnitPayment::class);
        }

        $validated = $this->validate([
            'paymentAmount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'paymentDate' => ['required', 'date'],
            'paymentMethod' => ['nullable', 'string', 'max:100'],
            'paymentNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $paidAt = CarbonImmutable::parse($validated['paymentDate'])->startOfDay();
        $note = $validated['paymentNote'] ?: null;
        $paymentMethod = $validated['paymentMethod'] ?: null;

        try {
            if ($this->editingPaymentId === null) {
                $paymentService->create(
                    bill: $bill,
                    amount: (int) $validated['paymentAmount'],
                    actor: auth()->user(),
                    note: $note,
                    paidAt: $paidAt,
                    paymentMethod: $paymentMethod,
                );
            } else {
                $payment = $bill->payments()->findOrFail($this->editingPaymentId);
                $paymentService->update(
                    payment: $payment,
                    amount: (int) $validated['paymentAmount'],
                    paidAt: $paidAt,
                    note: $note,
                    paymentMethod: $paymentMethod,
                );
            }
        } catch (ValidationException $exception) {
            $this->addError(
                'paymentAmount',
                $exception->validator->errors()->first() ?: 'Pembayaran gagal disimpan.',
            );

            return;
        }

        $wasEditing = $this->editingPaymentId !== null;
        $this->closePaymentModal();
        $this->dispatch(
            'toast',
            type: $wasEditing ? 'info' : 'success',
            message: $wasEditing ? 'Pembayaran unit berhasil diperbarui.' : 'Pembayaran unit berhasil dicatat.',
        );
    }

    public function openHistory(int $billId): void
    {
        Gate::authorize('viewAny', CateringUnitPayment::class);
        $bill = CateringUnitBill::query()->findOrFail($billId);

        $this->showHistoryModal = true;
        $this->historyBillId = $bill->id;
        $this->showDeletePaymentModal = false;
        $this->deletingPaymentId = null;
    }

    public function closeHistoryModal(): void
    {
        $this->showHistoryModal = false;
        $this->historyBillId = null;
        $this->showDeletePaymentModal = false;
        $this->deletingPaymentId = null;
    }

    public function editPayment(int $paymentId): void
    {
        Gate::authorize('update', CateringUnitPayment::class);
        $bill = CateringUnitBill::query()->findOrFail($this->historyBillId ?? $this->paymentBillId);
        $payment = $bill->payments()->findOrFail($paymentId);

        $this->showPaymentModal = true;
        $this->paymentBillId = $bill->id;
        $this->editingPaymentId = $payment->id;
        $this->paymentAmount = (string) $payment->amount;
        $this->paymentDate = $payment->paid_at->toDateString();
        $this->paymentMethod = (string) $payment->payment_method;
        $this->paymentNote = (string) $payment->note;
        $this->showHistoryModal = false;
        $this->historyBillId = null;
        $this->resetValidation();
    }

    public function confirmDeletePayment(int $paymentId): void
    {
        Gate::authorize('delete', CateringUnitPayment::class);
        $bill = CateringUnitBill::query()->findOrFail($this->historyBillId ?? $this->paymentBillId);
        $bill->payments()->findOrFail($paymentId);

        $this->deletingPaymentId = $paymentId;
        $this->showDeletePaymentModal = true;
    }

    public function closeDeletePaymentModal(): void
    {
        $this->showDeletePaymentModal = false;
        $this->deletingPaymentId = null;
    }

    public function deletePayment(CateringUnitPaymentService $paymentService): void
    {
        Gate::authorize('delete', CateringUnitPayment::class);
        $bill = CateringUnitBill::query()->findOrFail($this->historyBillId ?? $this->paymentBillId);
        $payment = $bill->payments()->findOrFail($this->deletingPaymentId);

        $paymentService->delete($payment);
        $this->closeDeletePaymentModal();
        $this->dispatch('toast', type: 'danger', message: 'Pembayaran unit berhasil dihapus.');
    }

    public function render(CateringUnitBillingService $billingService, CateringUnitPaymentService $paymentService): View
    {
        Gate::authorize('viewAny', CateringUnitBill::class);

        $rows = $billingService->periodStatus($this->month, $this->year);
        $bills = $rows->pluck('bill')->filter();
        $invoiceReady = $rows->every(fn (array $row): bool => ! in_array($row['status'], [
            CateringUnitBillSyncStatus::Stale,
            CateringUnitBillSyncStatus::Unsynced,
        ], true));
        $paymentSummaries = $this->paymentSummaries($bills, $paymentService);
        $paymentBill = ! $this->showPaymentModal || $this->paymentBillId === null
            ? null
            : CateringUnitBill::query()->find($this->paymentBillId);
        $historyBill = $this->historyBillId === null
            ? null
            : CateringUnitBill::query()->find($this->historyBillId);

        return view('livewire.catering-unit-bills.index', [
            'rows' => $rows,
            'invoiceReady' => $invoiceReady,
            'summary' => [
                'bill_count' => $bills->count(),
                'total_quantity' => (int) $bills->sum('total_quantity'),
                'gross_amount' => (int) $bills->sum('gross_amount'),
                'payment' => $paymentService->periodSummary($this->month, $this->year),
            ],
            'paymentSummaries' => $paymentSummaries,
            'paymentBill' => $paymentBill,
            'paymentSummary' => $paymentBill === null ? null : $paymentService->summaryForBill($paymentBill),
            'historyBill' => $historyBill,
            'historySummary' => $historyBill === null ? null : $paymentService->summaryForBill($historyBill),
            'historyRows' => $historyBill === null ? collect() : $paymentService->historyForBill($historyBill),
            'monthOptions' => $this->monthOptions(),
            'yearOptions' => range(now()->year - 4, now()->year + 3),
        ]);
    }

    /**
     * Load one grouped payment aggregate for the whole page, then let the
     * payment service derive each bill summary without a query per row.
     *
     * @param  Collection<int, CateringUnitBill>  $bills
     * @return array<int, array{
     *     billed_amount: int,
     *     paid_amount: int,
     *     remaining_amount: int,
     *     overpaid_amount: int,
     *     status: CateringUnitBillPaymentStatus,
     *     status_label: string
     * }>
     */
    private function paymentSummaries(Collection $bills, CateringUnitPaymentService $paymentService): array
    {
        $paidTotals = $bills->isEmpty()
            ? collect()
            : CateringUnitPayment::query()
                ->whereIn('catering_unit_bill_id', $bills->pluck('id'))
                ->selectRaw('catering_unit_bill_id, SUM(amount) as total_amount')
                ->groupBy('catering_unit_bill_id')
                ->pluck('total_amount', 'catering_unit_bill_id');

        $summaries = [];

        foreach ($bills as $bill) {
            $bill->setAttribute('payments_sum_amount', (int) ($paidTotals[$bill->id] ?? 0));
            $summaries[$bill->id] = $paymentService->summaryForBill($bill);
        }

        return $summaries;
    }

    /** @return array<string, array<int, string>> */
    private function periodRules(): array
    {
        return [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2000,2100'],
        ];
    }

    /** @return array<int, string> */
    private function monthOptions(): array
    {
        return [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
    }
}

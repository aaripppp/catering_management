<?php

namespace App\Livewire\CateringBills;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\CateringAttendance;
use App\Models\CateringBill;
use App\Models\CateringCreditAllocation;
use App\Models\CateringPayment;
use App\Models\SchoolClass;
use App\Services\CateringBillingService;
use App\Services\CateringPaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public int $month;

    public int $year;

    public string $participantGroup = CateringParticipantGroup::Student->value;

    public string $jenjang = '';

    public string $schoolClassId = '';

    public string $paymentStatus = '';

    public string $search = '';

    public bool $showPaymentModal = false;

    public ?int $paymentBillId = null;

    public string $paymentAmount = '';

    public string $paymentDate = '';

    public string $paymentNote = '';

    public ?int $editingPaymentId = null;

    public bool $showDeletePaymentModal = false;

    public ?int $deletingPaymentId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', CateringBill::class);

        $this->month = now()->month;
        $this->year = now()->year;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['month', 'year', 'participantGroup', 'jenjang', 'schoolClassId', 'paymentStatus', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function updatedParticipantGroup(): void
    {
        $this->jenjang = '';
        $this->schoolClassId = '';
    }

    public function updatedJenjang(): void
    {
        $this->schoolClassId = '';
    }

    public function generate(CateringBillingService $billingService): void
    {
        Gate::authorize('generate', CateringBill::class);
        $validated = $this->validateSelection();
        $group = CateringParticipantGroup::from($validated['participantGroup']);
        $schoolClass = $this->selectedSchoolClass();

        $result = $billingService->generate(
            year: $validated['year'],
            month: $validated['month'],
            group: $group,
            schoolClass: $schoolClass,
            jenjang: $group === CateringParticipantGroup::Student && $schoolClass === null
                ? ($validated['jenjang'] ?: null)
                : null,
            actor: auth()->user(),
        );

        $this->resetPage();
        $this->dispatch(
            'toast',
            type: 'success',
            message: sprintf(
                'Tagihan disinkronkan: %d dibuat, %d diperbarui.',
                $result['created'],
                $result['regenerated'],
            ),
        );
    }

    public function openPayment(int $billId): void
    {
        $bill = $this->paymentBillQuery()->findOrFail($billId);
        Gate::authorize('recordPayment', $bill);

        $this->paymentBillId = $bill->id;
        $this->paymentAmount = $bill->remainingAmount() > 0 ? (string) $bill->remainingAmount() : '';
        $this->paymentDate = now()->toDateString();
        $this->paymentNote = '';
        $this->editingPaymentId = null;
        $this->resetValidation();
        $this->showPaymentModal = true;
    }

    public function closePaymentModal(): void
    {
        $this->showPaymentModal = false;
        $this->paymentBillId = null;
        $this->paymentAmount = '';
        $this->paymentDate = '';
        $this->paymentNote = '';
        $this->editingPaymentId = null;
        $this->showDeletePaymentModal = false;
        $this->deletingPaymentId = null;
        $this->resetValidation();
    }

    public function recordPayment(CateringPaymentService $paymentService): void
    {
        $bill = CateringBill::query()->findOrFail($this->paymentBillId);
        Gate::authorize('recordPayment', $bill);

        $validated = $this->validate([
            'paymentAmount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'paymentDate' => ['required', 'date'],
            'paymentNote' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->editingPaymentId !== null) {
            $payment = $bill->payments()->findOrFail($this->editingPaymentId);
            Gate::authorize('updatePayment', $bill);
            $paymentService->update(
                payment: $payment,
                amount: (int) $validated['paymentAmount'],
                paidAt: CarbonImmutable::parse($validated['paymentDate'])->startOfDay(),
                note: $validated['paymentNote'] ?: null,
            );
        } else {
            $paymentService->record(
                bill: $bill,
                amount: (int) $validated['paymentAmount'],
                actor: auth()->user(),
                note: $validated['paymentNote'] ?: null,
                paidAt: CarbonImmutable::parse($validated['paymentDate'])->startOfDay(),
            );
        }

        $wasEditing = $this->editingPaymentId !== null;
        $this->resetPaymentForm();
        $this->dispatch(
            'toast',
            type: $wasEditing ? 'info' : 'success',
            message: $wasEditing ? 'Pembayaran berhasil diperbarui.' : 'Pembayaran berhasil dicatat.',
        );
    }

    public function editPayment(int $paymentId): void
    {
        $bill = CateringBill::query()->findOrFail($this->paymentBillId);
        Gate::authorize('updatePayment', $bill);
        $payment = $bill->payments()->findOrFail($paymentId);

        $this->editingPaymentId = $payment->id;
        $this->paymentAmount = (string) $payment->amount;
        $this->paymentDate = $payment->paid_at->toDateString();
        $this->paymentNote = (string) $payment->note;
        $this->resetValidation();
    }

    public function cancelPaymentEdit(): void
    {
        $this->resetPaymentForm();
    }

    public function confirmDeletePayment(int $paymentId): void
    {
        $bill = CateringBill::query()->findOrFail($this->paymentBillId);
        Gate::authorize('deletePayment', $bill);
        $bill->payments()->findOrFail($paymentId);

        $this->deletingPaymentId = $paymentId;
        $this->showDeletePaymentModal = true;
    }

    public function closeDeletePaymentModal(): void
    {
        $this->showDeletePaymentModal = false;
        $this->deletingPaymentId = null;
    }

    public function deletePayment(CateringPaymentService $paymentService): void
    {
        $bill = CateringBill::query()->findOrFail($this->paymentBillId);
        Gate::authorize('deletePayment', $bill);
        $payment = $bill->payments()->findOrFail($this->deletingPaymentId);

        $paymentService->delete($payment);
        $this->closeDeletePaymentModal();
        $this->resetPaymentForm();
        $this->dispatch('toast', type: 'danger', message: 'Pembayaran berhasil dihapus.');
    }

    public function render(): View
    {
        Gate::authorize('viewAny', CateringBill::class);

        $baseQuery = $this->filteredBillsQuery();
        $paymentTotals = CateringPayment::query()
            ->select('catering_bill_id')
            ->selectRaw('SUM(amount) as paid_amount')
            ->groupBy('catering_bill_id');
        $creditTotals = CateringCreditAllocation::query()
            ->select('catering_bill_id')
            ->selectRaw('SUM(amount) as credit_applied_amount')
            ->groupBy('catering_bill_id');
        $summary = (clone $baseQuery)
            ->leftJoinSub($paymentTotals, 'payment_totals', function (JoinClause $join): void {
                $join->on('catering_bills.id', '=', 'payment_totals.catering_bill_id');
            })
            ->leftJoinSub($creditTotals, 'credit_totals', function (JoinClause $join): void {
                $join->on('catering_bills.id', '=', 'credit_totals.catering_bill_id');
            })
            ->toBase()
            ->selectRaw('COUNT(*) as bill_count')
            ->selectRaw('COALESCE(SUM(catering_bills.gross_amount), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(COALESCE(payment_totals.paid_amount, 0)), 0) as money_in')
            ->selectRaw('COALESCE(SUM(COALESCE(credit_totals.credit_applied_amount, 0)), 0) as credit_used')
            ->selectRaw(<<<'SQL'
                COALESCE(SUM(
                    CASE
                        WHEN catering_bills.gross_amount > COALESCE(payment_totals.paid_amount, 0) + COALESCE(credit_totals.credit_applied_amount, 0)
                        THEN catering_bills.gross_amount - COALESCE(payment_totals.paid_amount, 0) - COALESCE(credit_totals.credit_applied_amount, 0)
                        ELSE 0
                    END
                ), 0) as outstanding_amount
                SQL)
            ->first();
        $periodStart = CarbonImmutable::create($this->year, $this->month, 1)->startOfMonth();
        $bills = $baseQuery
            ->addSelect([
                'current_active_days' => CateringAttendance::query()
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('catering_member_id', 'catering_bills.catering_member_id')
                    ->where('attendance_date', '>=', $periodStart->toDateString())
                    ->where('attendance_date', '<', $periodStart->addMonth()->toDateString())
                    ->where('status', CateringAttendanceStatus::Ikut),
            ])
            ->withSum('payments as paid_amount', 'amount')
            ->withSum('creditAllocations as credit_applied_amount', 'amount')
            ->withSum('generatedCredits as generated_credit_amount', 'original_amount')
            ->orderBy('member_name')
            ->orderBy('id')
            ->paginate(15);
        $selectedBill = $this->paymentBillId === null
            ? null
            : $this->paymentBillQuery()->find($this->paymentBillId);
        $paymentAmount = max(0, (int) $this->paymentAmount);
        $editingPayment = $this->editingPaymentId === null
            ? null
            : $selectedBill?->payments->firstWhere('id', $this->editingPaymentId);
        $remainingAmount = $selectedBill === null
            ? 0
            : max(
                0,
                $selectedBill->gross_amount
                    - $selectedBill->creditAppliedAmount()
                    - ($selectedBill->paidAmount() - ($editingPayment?->amount ?? 0)),
            );

        return view('livewire.catering-bills.index', [
            'bills' => $bills,
            'summary' => $summary,
            'selectedBill' => $selectedBill,
            'remainingAfterPayment' => max(0, $remainingAmount - $paymentAmount),
            'overpaymentAmount' => max(0, $paymentAmount - $remainingAmount),
            'classOptions' => $this->classOptions(),
            'participantGroupOptions' => CateringParticipantGroup::options(),
            'jenjangOptions' => SchoolLevel::educationLevels(),
            'paymentStatusOptions' => CateringBillStatus::cases(),
            'yearOptions' => range(now()->year + 1, now()->year - 4),
        ]);
    }

    /** @return array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string} */
    private function validateSelection(): array
    {
        return $this->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'between:2020,2100'],
            'participantGroup' => ['required', Rule::enum(CateringParticipantGroup::class)],
            'jenjang' => ['nullable', Rule::in(SchoolLevel::educationLevels())],
            'schoolClassId' => ['nullable', 'integer', Rule::exists('school_classes', 'id')],
        ]);
    }

    private function selectedSchoolClass(): ?SchoolClass
    {
        if ($this->participantGroup !== CateringParticipantGroup::Student->value || $this->schoolClassId === '') {
            return null;
        }

        return SchoolClass::query()
            ->active()
            ->when($this->jenjang !== '', fn (Builder $query): Builder => $query->forJenjang($this->jenjang))
            ->findOrFail((int) $this->schoolClassId);
    }

    /** @return array<int, array{id: int, name: string}> */
    private function classOptions(): array
    {
        if ($this->participantGroup !== CateringParticipantGroup::Student->value) {
            return [];
        }

        return SchoolClass::query()
            ->select(['id', 'name'])
            ->active()
            ->when($this->jenjang !== '', fn (Builder $query): Builder => $query->forJenjang($this->jenjang))
            ->orderedForSelection()
            ->get()
            ->map(fn (SchoolClass $schoolClass): array => [
                'id' => $schoolClass->id,
                'name' => $schoolClass->name,
            ])
            ->all();
    }

    /** @return Builder<CateringBill> */
    private function filteredBillsQuery(): Builder
    {
        return CateringBill::query()
            ->where('period_month', $this->month)
            ->where('period_year', $this->year)
            ->where('participant_group', $this->participantGroup)
            ->when(
                $this->jenjang !== '',
                fn (Builder $query): Builder => $query->whereIn('school_level', SchoolLevel::valuesForEducationLevel($this->jenjang)),
            )
            ->when($this->schoolClassId !== '', fn (Builder $query): Builder => $query->where('school_class_id', (int) $this->schoolClassId))
            ->when($this->paymentStatus !== '', fn (Builder $query): Builder => $query->where('payment_status', $this->paymentStatus))
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $searchQuery): void {
                    $searchQuery
                        ->where('member_name', 'like', '%'.$this->search.'%')
                        ->orWhere('school_class_name', 'like', '%'.$this->search.'%');
                });
            });
    }

    /** @return Builder<CateringBill> */
    private function paymentBillQuery(): Builder
    {
        return CateringBill::query()
            ->withSum('payments as paid_amount', 'amount')
            ->withSum('creditAllocations as credit_applied_amount', 'amount')
            ->withSum('generatedCredits as generated_credit_amount', 'original_amount')
            ->with([
                'payments' => fn (HasMany $query): HasMany => $query->with(['creator', 'generatedCredit'])->latest('paid_at')->latest('id'),
            ]);
    }

    private function resetPaymentForm(): void
    {
        $bill = $this->paymentBillId === null
            ? null
            : $this->paymentBillQuery()->find($this->paymentBillId);

        $this->editingPaymentId = null;
        $this->paymentAmount = $bill !== null && $bill->remainingAmount() > 0
            ? (string) $bill->remainingAmount()
            : '';
        $this->paymentDate = now()->toDateString();
        $this->paymentNote = '';
        $this->resetValidation();
    }
}

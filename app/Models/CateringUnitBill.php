<?php

namespace App\Models;

use Database\Factories\CateringUnitBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'catering_employee_unit_id',
    'generated_by',
    'period_month',
    'period_year',
    'unit_name',
    'total_quantity',
    'price_per_person_snapshot',
    'gross_amount',
    'employee_subsidy_enabled',
    'employee_subsidy_qty',
    'employee_subsidy_rate',
    'employee_subsidy_amount',
    'student_subsidy_enabled',
    'student_subsidy_qty',
    'student_subsidy_rate',
    'student_subsidy_amount',
    'total_subsidy_amount',
    'net_amount',
    'generated_at',
])]
class CateringUnitBill extends Model
{
    /** @use HasFactory<CateringUnitBillFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'total_quantity' => 'integer',
            'price_per_person_snapshot' => 'integer',
            'gross_amount' => 'integer',
            'employee_subsidy_enabled' => 'boolean',
            'employee_subsidy_qty' => 'integer',
            'employee_subsidy_rate' => 'integer',
            'employee_subsidy_amount' => 'integer',
            'student_subsidy_enabled' => 'boolean',
            'student_subsidy_qty' => 'integer',
            'student_subsidy_rate' => 'integer',
            'student_subsidy_amount' => 'integer',
            'total_subsidy_amount' => 'integer',
            'net_amount' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CateringEmployeeUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(CateringEmployeeUnit::class, 'catering_employee_unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** @return HasMany<CateringUnitBillDay, $this> */
    public function days(): HasMany
    {
        return $this->hasMany(CateringUnitBillDay::class);
    }

    /** @return HasMany<CateringUnitPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(CateringUnitPayment::class);
    }

    /** @return HasMany<CateringUnitCreditAllocation, $this> */
    public function creditAllocations(): HasMany
    {
        return $this->hasMany(CateringUnitCreditAllocation::class);
    }

    /** @return HasMany<CateringUnitCredit, $this> */
    public function generatedCredits(): HasMany
    {
        return $this->hasMany(CateringUnitCredit::class, 'source_unit_bill_id');
    }

    public function billedAmount(): int
    {
        $attributes = $this->getAttributes();

        if (array_key_exists('net_amount', $attributes) && $attributes['net_amount'] !== null) {
            return (int) $attributes['net_amount'];
        }

        return max(0, (int) $this->gross_amount - (int) ($this->total_subsidy_amount ?? 0));
    }

    public function paidAmount(): int
    {
        return array_key_exists('payments_sum_amount', $this->attributes)
            ? (int) $this->attributes['payments_sum_amount']
            : (int) $this->payments()->sum('amount');
    }

    public function creditAppliedAmount(): int
    {
        return array_key_exists('credit_allocations_sum_amount', $this->attributes)
            ? (int) $this->attributes['credit_allocations_sum_amount']
            : (int) $this->creditAllocations()->sum('amount');
    }

    public function recognizedPaidAmount(): int
    {
        return min($this->billedAmount(), $this->paidAmount() + $this->creditAppliedAmount());
    }

    public function remainingAmount(): int
    {
        return max(0, $this->billedAmount() - $this->paidAmount() - $this->creditAppliedAmount());
    }

    public function generatedCreditAmount(): int
    {
        return (int) $this->generatedCredits()->sum('original_amount');
    }

    public function hasFinancialHistory(): bool
    {
        return $this->payments()->exists()
            || $this->creditAllocations()->exists()
            || $this->generatedCredits()->exists();
    }
}

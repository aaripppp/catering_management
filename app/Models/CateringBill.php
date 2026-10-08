<?php

namespace App\Models;

use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use Database\Factories\CateringBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'catering_member_id',
    'school_class_id',
    'catering_category_id',
    'generated_by',
    'period_month',
    'period_year',
    'member_name',
    'participant_group',
    'school_class_name',
    'school_level',
    'guardian_name',
    'guardian_phone',
    'category_name',
    'price_per_day',
    'saved_days',
    'active_days',
    'sakit_days',
    'izin_days',
    'alfa_days',
    'off_days',
    'ujian_days',
    'event_unit_days',
    'puasa_days',
    'libur_days',
    'gross_amount',
    'payment_status',
    'generated_at',
])]
class CateringBill extends Model
{
    /** @use HasFactory<CateringBillFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_month' => 'integer',
            'period_year' => 'integer',
            'price_per_day' => 'integer',
            'participant_group' => CateringParticipantGroup::class,
            'saved_days' => 'integer',
            'active_days' => 'integer',
            'sakit_days' => 'integer',
            'izin_days' => 'integer',
            'alfa_days' => 'integer',
            'off_days' => 'integer',
            'ujian_days' => 'integer',
            'event_unit_days' => 'integer',
            'puasa_days' => 'integer',
            'libur_days' => 'integer',
            'gross_amount' => 'integer',
            'payment_status' => CateringBillStatus::class,
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CateringMember, $this> */
    public function cateringMember(): BelongsTo
    {
        return $this->belongsTo(CateringMember::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return BelongsTo<CateringCategory, $this> */
    public function cateringCategory(): BelongsTo
    {
        return $this->belongsTo(CateringCategory::class);
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** @return HasMany<CateringPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(CateringPayment::class);
    }

    /** @return HasMany<CateringCreditAllocation, $this> */
    public function creditAllocations(): HasMany
    {
        return $this->hasMany(CateringCreditAllocation::class);
    }

    /** @return HasMany<CateringCredit, $this> */
    public function generatedCredits(): HasMany
    {
        return $this->hasMany(CateringCredit::class, 'source_bill_id');
    }

    public function paidAmount(): int
    {
        return array_key_exists('paid_amount', $this->attributes)
            ? (int) $this->attributes['paid_amount']
            : (int) $this->payments()->sum('amount');
    }

    public function creditAppliedAmount(): int
    {
        return array_key_exists('credit_applied_amount', $this->attributes)
            ? (int) $this->attributes['credit_applied_amount']
            : (int) $this->creditAllocations()->sum('amount');
    }

    public function effectivePaidAmount(): int
    {
        return $this->paidAmount() + $this->creditAppliedAmount();
    }

    public function remainingAmount(): int
    {
        return max(0, $this->gross_amount - $this->effectivePaidAmount());
    }

    public function generatedCreditAmount(): int
    {
        return array_key_exists('generated_credit_amount', $this->attributes)
            ? (int) $this->attributes['generated_credit_amount']
            : (int) $this->generatedCredits()->sum('original_amount');
    }

    public function hasFinancialHistory(): bool
    {
        return $this->payments()->exists()
            || $this->creditAllocations()->exists()
            || $this->generatedCredits()->exists();
    }

    public static function statusFor(int $grossAmount, int $effectivePaid): CateringBillStatus
    {
        if ($effectivePaid === 0) {
            return CateringBillStatus::Unpaid;
        }

        return $effectivePaid < $grossAmount
            ? CateringBillStatus::Partial
            : CateringBillStatus::Paid;
    }
}

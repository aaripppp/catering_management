<?php

namespace App\Services;

use App\Models\CateringCultureMealIncome;
use App\Models\SchoolClass;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Persists manual Budaya Makan income entries.
 *
 * Manual income is independent from the recorded Budaya Makan attendance count:
 * nothing here reads attendance, multiplies a rate, or writes to bills,
 * invoices, payments or credits. Each entry stores a snapshot of the selected
 * class name so the history stays readable if the class is later removed.
 */
class CateringCultureMealIncomeService
{
    /**
     * @param  array{income_date: string, school_class_id: int, amount: int, note?: string|null}  $data
     */
    public function create(array $data, User $actor): CateringCultureMealIncome
    {
        $schoolClass = $this->resolveClass($data['school_class_id']);

        return CateringCultureMealIncome::create([
            'income_date' => $data['income_date'],
            'school_class_id' => $schoolClass->id,
            'school_class_name' => $schoolClass->name,
            'amount' => (int) $data['amount'],
            'note' => $data['note'] ?? null,
            'created_by' => $actor->id,
        ]);
    }

    /**
     * @param  array{income_date: string, school_class_id: int, amount: int, note?: string|null}  $data
     */
    public function update(CateringCultureMealIncome $income, array $data): CateringCultureMealIncome
    {
        $schoolClass = $this->resolveClass($data['school_class_id']);

        $income->update([
            'income_date' => $data['income_date'],
            'school_class_id' => $schoolClass->id,
            'school_class_name' => $schoolClass->name,
            'amount' => (int) $data['amount'],
            'note' => $data['note'] ?? null,
        ]);

        return $income;
    }

    public function delete(CateringCultureMealIncome $income): void
    {
        $income->delete();
    }

    /**
     * @return Collection<int, CateringCultureMealIncome>
     */
    public function monthlyRows(int $month, int $year): Collection
    {
        [$start, $end] = $this->monthBounds($month, $year);

        return CateringCultureMealIncome::query()
            ->with('schoolClass:id,name')
            ->where('income_date', '>=', $start)
            ->where('income_date', '<', $end)
            ->orderByDesc('income_date')
            ->orderByDesc('id')
            ->get();
    }

    public function monthlyTotal(int $month, int $year): int
    {
        [$start, $end] = $this->monthBounds($month, $year);

        return (int) CateringCultureMealIncome::query()
            ->where('income_date', '>=', $start)
            ->where('income_date', '<', $end)
            ->sum('amount');
    }

    private function resolveClass(int $schoolClassId): SchoolClass
    {
        return SchoolClass::query()->findOrFail($schoolClassId);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function monthBounds(int $month, int $year): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return [$start->toDateString(), $start->addMonth()->toDateString()];
    }
}

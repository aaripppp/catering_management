<?php

namespace App\Console\Commands;

use App\Models\CateringEmployeeUnit;
use App\Models\CateringUnitCredit;
use App\Models\CateringUnitCreditAllocation;
use App\Services\CateringUnitFinancialReconciliationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('catering:reconcile-unit-credits {--unit=* : Batasi rekonsiliasi ke ID unit tertentu (bisa diulang)}')]
#[Description('Bangun ulang kredit lebih bayar Unit dan alokasinya secara deterministik')]
class ReconcileCateringUnitCredits extends Command
{
    public function handle(CateringUnitFinancialReconciliationService $reconciliation): int
    {
        $requestedUnitIds = collect($this->option('unit'))
            ->filter(fn (mixed $unitId): bool => is_numeric($unitId))
            ->map(fn (mixed $unitId): int => (int) $unitId)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($this->option('unit') !== [] && $requestedUnitIds === []) {
            $this->error('Tidak ada ID unit valid yang diberikan.');

            return self::FAILURE;
        }

        if ($requestedUnitIds === []) {
            $unitCount = CateringEmployeeUnit::query()->count();
            $appliedAmount = $reconciliation->reconcileAll();
            $creditQuery = CateringUnitCredit::query();
            $allocationQuery = CateringUnitCreditAllocation::query();
        } else {
            $foundUnitIds = CateringEmployeeUnit::query()
                ->whereKey($requestedUnitIds)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn (mixed $unitId): int => (int) $unitId)
                ->all();
            $missingUnitIds = array_values(array_diff($requestedUnitIds, $foundUnitIds));

            if ($missingUnitIds !== []) {
                $this->error('Unit tidak ditemukan: '.implode(', ', $missingUnitIds));

                return self::FAILURE;
            }

            $unitCount = count($foundUnitIds);
            $appliedAmount = $reconciliation->reconcileUnits($foundUnitIds);
            $creditQuery = CateringUnitCredit::query()->whereIn('catering_employee_unit_id', $foundUnitIds);
            $allocationQuery = CateringUnitCreditAllocation::query()
                ->whereIn('catering_unit_credit_id', CateringUnitCredit::query()
                    ->select('id')
                    ->whereIn('catering_employee_unit_id', $foundUnitIds));
        }

        $this->info(sprintf(
            'Rekonsiliasi kredit Unit selesai: %d unit, %d kredit, Rp %s dialokasikan.',
            $unitCount,
            $creditQuery->count(),
            number_format($appliedAmount, 0, ',', '.'),
        ));

        if ($allocationQuery->exists()) {
            $this->line(sprintf(
                'Total nilai alokasi kredit: Rp %s.',
                number_format((int) $allocationQuery->sum('amount'), 0, ',', '.'),
            ));
        }

        return self::SUCCESS;
    }
}

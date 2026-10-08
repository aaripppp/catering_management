<?php

namespace App\Services;

use App\Enums\CateringBillStatus;
use App\Enums\CateringParticipantGroup;
use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CateringMonthlyReportService
{
    /** @var array<int, string> */
    private const MONTH_NAMES = [
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

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     * @return array{bill_count: int, gross_amount: int, money_in: int, credit_used: int, outstanding_amount: int, overpayment_amount: int, paid_count: int, partial_count: int, unpaid_count: int}
     */
    public function summary(array $filters): array
    {
        $row = DB::query()
            ->fromSub($this->billRowsQuery($filters), 'report_bills')
            ->selectRaw('COUNT(*) as bill_count')
            ->selectRaw('COALESCE(SUM(gross_amount), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as money_in')
            ->selectRaw('COALESCE(SUM(credit_applied_amount), 0) as credit_used')
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as outstanding_amount')
            ->selectRaw('COALESCE(SUM(generated_credit_amount), 0) as overpayment_amount')
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as paid_count', [CateringBillStatus::Paid->value])
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as partial_count', [CateringBillStatus::Partial->value])
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as unpaid_count', [CateringBillStatus::Unpaid->value])
            ->first();

        return [
            'bill_count' => (int) ($row?->bill_count ?? 0),
            'gross_amount' => (int) ($row?->gross_amount ?? 0),
            'money_in' => (int) ($row?->money_in ?? 0),
            'credit_used' => (int) ($row?->credit_used ?? 0),
            'outstanding_amount' => (int) ($row?->outstanding_amount ?? 0),
            'overpayment_amount' => (int) ($row?->overpayment_amount ?? 0),
            'paid_count' => (int) ($row?->paid_count ?? 0),
            'partial_count' => (int) ($row?->partial_count ?? 0),
            'unpaid_count' => (int) ($row?->unpaid_count ?? 0),
        ];
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     * @return array<int, array{label: string, participant_count: int, gross_amount: int, money_in: int, credit_used: int, outstanding_amount: int, overpayment_amount: int, paid_count: int, partial_count: int, unpaid_count: int}>
     */
    public function groupRows(array $filters): array
    {
        $studentGroup = CateringParticipantGroup::Student->value;
        $groupClassExpression = 'CASE WHEN participant_group = ? THEN school_class_id ELSE NULL END';
        $groupLabelExpression = 'CASE WHEN participant_group = ? THEN school_class_name ELSE category_name END';
        $rows = DB::query()
            ->fromSub($this->billRowsQuery($filters), 'report_bills')
            ->select('participant_group')
            ->selectRaw($groupClassExpression.' as group_class_id', [$studentGroup])
            ->selectRaw($groupLabelExpression.' as group_label', [$studentGroup])
            ->selectRaw('COUNT(*) as participant_count')
            ->selectRaw('COALESCE(SUM(gross_amount), 0) as gross_amount')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as money_in')
            ->selectRaw('COALESCE(SUM(credit_applied_amount), 0) as credit_used')
            ->selectRaw('COALESCE(SUM(outstanding_amount), 0) as outstanding_amount')
            ->selectRaw('COALESCE(SUM(generated_credit_amount), 0) as overpayment_amount')
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as paid_count', [CateringBillStatus::Paid->value])
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as partial_count', [CateringBillStatus::Partial->value])
            ->selectRaw('SUM(CASE WHEN payment_status = ? THEN 1 ELSE 0 END) as unpaid_count', [CateringBillStatus::Unpaid->value])
            ->groupBy('participant_group')
            ->groupByRaw($groupClassExpression, [$studentGroup])
            ->groupByRaw($groupLabelExpression, [$studentGroup])
            ->get();
        $classIds = $rows
            ->where('participant_group', CateringParticipantGroup::Student->value)
            ->pluck('group_class_id')
            ->filter()
            ->unique()
            ->values();
        $classOrder = $classIds->isEmpty()
            ? collect()
            : SchoolClass::query()
                ->whereKey($classIds)
                ->orderedForSelection()
                ->pluck('id')
                ->flip();

        return $rows
            ->map(function (object $row) use ($classOrder): array {
                $isStudent = $row->participant_group === CateringParticipantGroup::Student->value;

                return [
                    'label' => $isStudent
                        ? ((string) ($row->group_label ?? '') ?: '-')
                        : ((string) ($row->group_label ?? '') ?: CateringParticipantGroup::Employee->label()),
                    'participant_count' => (int) $row->participant_count,
                    'gross_amount' => (int) $row->gross_amount,
                    'money_in' => (int) $row->money_in,
                    'credit_used' => (int) $row->credit_used,
                    'outstanding_amount' => (int) $row->outstanding_amount,
                    'overpayment_amount' => (int) $row->overpayment_amount,
                    'paid_count' => (int) $row->paid_count,
                    'partial_count' => (int) $row->partial_count,
                    'unpaid_count' => (int) $row->unpaid_count,
                    'sort_group' => $isStudent ? 0 : 1,
                    'sort_order' => $isStudent
                        ? (int) ($classOrder[$row->group_class_id] ?? PHP_INT_MAX)
                        : PHP_INT_MAX,
                ];
            })
            ->sortBy(fn (array $row): array => [$row['sort_group'], $row['sort_order'], $row['label']])
            ->values()
            ->map(function (array $row): array {
                unset($row['sort_group'], $row['sort_order']);

                return $row;
            })
            ->all();
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     * @return LengthAwarePaginator<int, array<string, int|string>>
     */
    public function paginateDetails(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $paginator = $this->billRowsQuery($filters)
            ->orderBy('member_name')
            ->orderBy('id')
            ->paginate($perPage);

        $paginator->setCollection($paginator->getCollection()->map($this->mapDetail(...)));

        return $paginator;
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     * @return Collection<int, array<string, int|string>>
     */
    public function detailRows(array $filters): Collection
    {
        return $this->billRowsQuery($filters)
            ->orderBy('member_name')
            ->orderBy('id')
            ->get()
            ->map($this->mapDetail(...));
    }

    public function periodLabel(int $month, int $year): string
    {
        return (self::MONTH_NAMES[$month] ?? (string) $month).' '.$year;
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     */
    public function filterLabel(array $filters): string
    {
        $parts = [];
        $group = CateringParticipantGroup::tryFrom($filters['participantGroup']);
        $parts[] = $group?->label() ?? 'Semua Peserta';

        if ($filters['jenjang'] !== '') {
            $parts[] = $filters['jenjang'];
        }

        if ($filters['schoolClassId'] !== '') {
            $parts[] = SchoolClass::query()->whereKey((int) $filters['schoolClassId'])->value('name') ?? '-';
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  array{month: int, year: int, participantGroup: string, jenjang: string, schoolClassId: string}  $filters
     */
    private function billRowsQuery(array $filters): Builder
    {
        $paymentTotals = DB::table('catering_payments')
            ->select('catering_bill_id')
            ->selectRaw('SUM(amount) as paid_amount')
            ->groupBy('catering_bill_id');
        $creditTotals = DB::table('catering_credit_allocations')
            ->select('catering_bill_id')
            ->selectRaw('SUM(amount) as credit_applied_amount')
            ->groupBy('catering_bill_id');
        $generatedCreditTotals = DB::table('catering_credits')
            ->selectRaw('source_bill_id as catering_bill_id')
            ->selectRaw('SUM(original_amount) as generated_credit_amount')
            ->groupBy('source_bill_id');

        return DB::table('catering_bills')
            ->leftJoinSub($paymentTotals, 'payment_totals', function (JoinClause $join): void {
                $join->on('catering_bills.id', '=', 'payment_totals.catering_bill_id');
            })
            ->leftJoinSub($creditTotals, 'credit_totals', function (JoinClause $join): void {
                $join->on('catering_bills.id', '=', 'credit_totals.catering_bill_id');
            })
            ->leftJoinSub($generatedCreditTotals, 'generated_credit_totals', function (JoinClause $join): void {
                $join->on('catering_bills.id', '=', 'generated_credit_totals.catering_bill_id');
            })
            ->where('catering_bills.period_month', $filters['month'])
            ->where('catering_bills.period_year', $filters['year'])
            ->when(
                in_array($filters['participantGroup'], array_column(CateringParticipantGroup::cases(), 'value'), true),
                fn (Builder $query): Builder => $query->where('catering_bills.participant_group', $filters['participantGroup']),
            )
            ->when(
                $filters['jenjang'] !== '',
                fn (Builder $query): Builder => $query->whereIn('catering_bills.school_level', SchoolLevel::valuesForEducationLevel($filters['jenjang'])),
            )
            ->when(
                $filters['schoolClassId'] !== '',
                fn (Builder $query): Builder => $query->where('catering_bills.school_class_id', (int) $filters['schoolClassId']),
            )
            ->select([
                'catering_bills.id',
                'catering_bills.member_name',
                'catering_bills.participant_group',
                'catering_bills.school_class_id',
                'catering_bills.school_class_name',
                'catering_bills.school_level',
                'catering_bills.category_name',
                'catering_bills.gross_amount',
                'catering_bills.payment_status',
            ])
            ->selectRaw('COALESCE(payment_totals.paid_amount, 0) as paid_amount')
            ->selectRaw('COALESCE(credit_totals.credit_applied_amount, 0) as credit_applied_amount')
            ->selectRaw('COALESCE(generated_credit_totals.generated_credit_amount, 0) as generated_credit_amount')
            ->selectRaw(<<<'SQL'
                CASE
                    WHEN catering_bills.gross_amount > COALESCE(payment_totals.paid_amount, 0) + COALESCE(credit_totals.credit_applied_amount, 0)
                    THEN catering_bills.gross_amount - COALESCE(payment_totals.paid_amount, 0) - COALESCE(credit_totals.credit_applied_amount, 0)
                    ELSE 0
                END as outstanding_amount
                SQL);
    }

    /** @return array<string, int|string> */
    private function mapDetail(object $row): array
    {
        $participantGroup = CateringParticipantGroup::from((string) $row->participant_group);
        $status = CateringBillStatus::from((string) $row->payment_status);

        return [
            'id' => (int) $row->id,
            'member_name' => (string) $row->member_name,
            'group_label' => $participantGroup === CateringParticipantGroup::Student
                ? ((string) ($row->school_class_name ?? '') ?: '-')
                : ((string) ($row->category_name ?? '') ?: $participantGroup->label()),
            'category_name' => (string) $row->category_name,
            'gross_amount' => (int) $row->gross_amount,
            'paid_amount' => (int) $row->paid_amount,
            'credit_applied_amount' => (int) $row->credit_applied_amount,
            'generated_credit_amount' => (int) $row->generated_credit_amount,
            'outstanding_amount' => (int) $row->outstanding_amount,
            'payment_status' => $status->value,
            'payment_status_label' => $status->label(),
        ];
    }
}

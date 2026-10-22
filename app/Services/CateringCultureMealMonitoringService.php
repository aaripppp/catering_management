<?php

namespace App\Services;

use App\Enums\CateringAttendanceStatus;
use App\Enums\CateringParticipantGroup;
use App\Models\CateringAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the read-only Budaya Makan monitoring for a chosen month.
 *
 * Budaya Makan is the user-facing label for the internal "alfa" attendance
 * status. It is informational only: the count never produces income, a bill,
 * an invoice or a payment, and the stored "alfa" value is never renamed.
 *
 * Only student attendance inside a valid membership coverage period is counted.
 * The model's coveredByMembership scope is reused so coverage matches the rest
 * of the domain exactly, and the student participant group is filtered through
 * the category so legacy employee rows never contaminate the count. Rows
 * outside coverage stay stored and are simply ignored here.
 */
class CateringCultureMealMonitoringService
{
    /**
     * Monitor Budaya Makan for the given month.
     *
     * @return array{total: int, days: Collection<int, array{date: CarbonImmutable, count: int}>}
     */
    public function monitor(int $month, int $year): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->addMonth();

        $counts = $this->dailyCounts($start, $end);
        $days = collect();

        // Every calendar date in the month is present, even when nothing was
        // recorded, so operational monitoring is easy to scan. Iterating to the
        // exclusive next-month boundary handles 28/29/30/31 day months and leap
        // years without any special casing.
        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            $days->push([
                'date' => $date,
                'count' => $counts[$date->toDateString()] ?? 0,
            ]);
        }

        return [
            'total' => (int) $days->sum('count'),
            'days' => $days,
        ];
    }

    /**
     * Set-based Budaya Makan count per calendar day, membership covered students only.
     *
     * One grouped query over the month, so the number of queries does not grow
     * with the number of days or members.
     *
     * @return array<string, int>
     */
    private function dailyCounts(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return CateringAttendance::query()
            ->coveredByMembership()
            ->toBase()
            ->join('catering_members', 'catering_members.id', '=', 'catering_attendances.catering_member_id')
            ->join('catering_categories', 'catering_categories.id', '=', 'catering_members.catering_category_id')
            ->where('catering_categories.participant_group', CateringParticipantGroup::Student->value)
            ->where('catering_attendances.status', CateringAttendanceStatus::Alfa->value)
            ->where('catering_attendances.attendance_date', '>=', $start->toDateString())
            ->where('catering_attendances.attendance_date', '<', $end->toDateString())
            ->selectRaw('DATE(catering_attendances.attendance_date) as day')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}

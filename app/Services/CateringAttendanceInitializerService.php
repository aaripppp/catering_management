<?php

namespace App\Services;

use App\Enums\CateringAttendanceStatus;
use App\Models\CateringAttendance;
use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CateringAttendanceInitializerService
{
    public static function ensureDate(CarbonInterface|string $date): void
    {
        $attendanceDate = CarbonImmutable::parse($date)->startOfDay();

        self::ensureRange($attendanceDate, $attendanceDate);
    }

    public static function ensureMonth(int $year, int $month): int
    {
        $startDate = CarbonImmutable::create($year, $month, 1)->startOfMonth();

        return self::ensureRange($startDate, $startDate->endOfMonth());
    }

    private static function ensureRange(CarbonImmutable $startDate, CarbonImmutable $endDate): int
    {
        $startStr = $startDate->toDateString();
        $endStr = $endDate->toDateString();

        $memberIds = CateringMember::query()
            ->active()
            ->whereHas('periods', function ($query) use ($startStr, $endStr): void {
                $query->whereDate('start_date', '<=', $endStr)
                    ->where(function ($q) use ($startStr): void {
                        $q->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $startStr);
                    });
            })
            ->pluck('id')
            ->all();

        if ($memberIds === []) {
            return 0;
        }

        $periods = CateringMemberPeriod::query()
            ->whereIn('catering_member_id', $memberIds)
            ->whereDate('start_date', '<=', $endStr)
            ->where(function ($q) use ($startStr): void {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $startStr);
            })
            ->get()
            ->groupBy('catering_member_id');

        $dates = [];

        for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
            $dateString = $date->toDateString();
            $dates[$dateString] = [
                'stored_date' => self::dateForStorage($date),
                'status' => $date->isWeekend()
                    ? CateringAttendanceStatus::Libur->value
                    : CateringAttendanceStatus::Ikut->value,
            ];
        }

        $existingRows = CateringAttendance::query()
            ->whereIntegerInRaw('catering_member_id', $memberIds)
            ->where('attendance_date', '>=', $startDate->toDateString())
            ->where('attendance_date', '<', $endDate->addDay()->toDateString())
            ->get(['catering_member_id', 'attendance_date'])
            ->mapWithKeys(fn (CateringAttendance $attendance): array => [
                $attendance->catering_member_id.'|'.$attendance->attendance_date->toDateString() => true,
            ])
            ->all();

        $now = now();
        $rows = [];
        $createdCount = 0;

        foreach ($memberIds as $memberId) {
            $memberPeriods = $periods->get($memberId) ?? collect();
            foreach ($dates as $date => $defaults) {
                $isCovered = $memberPeriods->contains(function ($period) use ($date): bool {
                    /** @var CateringMemberPeriod $period */
                    $pStart = $period->start_date->toDateString();
                    $pEnd = $period->end_date?->toDateString();
                    if ($pStart > $date) {
                        return false;
                    }
                    if ($pEnd === null) {
                        return true;
                    }

                    return $pEnd >= $date;
                });

                if (! $isCovered) {
                    continue;
                }

                if (isset($existingRows[$memberId.'|'.$date])) {
                    continue;
                }

                $rows[] = [
                    'catering_member_id' => $memberId,
                    'attendance_date' => $defaults['stored_date'],
                    'status' => $defaults['status'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (count($rows) === 500) {
                    $createdCount += CateringAttendance::query()->insertOrIgnore($rows);
                    $rows = [];
                }
            }
        }

        if ($rows !== []) {
            $createdCount += CateringAttendance::query()->insertOrIgnore($rows);
        }

        return $createdCount;
    }

    private static function dateForStorage(CarbonInterface|string $date): string
    {
        return CarbonImmutable::parse($date)
            ->startOfDay()
            ->format((new CateringAttendance)->getDateFormat());
    }
}

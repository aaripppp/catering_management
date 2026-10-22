<?php

namespace App\Services;

use App\Models\CateringMember;
use App\Models\CateringMemberPeriod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CateringMembershipService
{
    public function start(int $memberId, CarbonInterface $startDate): CateringMemberPeriod
    {
        return DB::transaction(function () use ($memberId, $startDate): CateringMemberPeriod {
            CateringMember::query()->lockForUpdate()->findOrFail($memberId);

            $this->ensureNoOpenPeriod($memberId);
            $this->ensureNoOverlap($memberId, $startDate, null);

            return CateringMemberPeriod::query()->create([
                'catering_member_id' => $memberId,
                'start_date' => $startDate->toDateString(),
                'end_date' => null,
            ]);
        });
    }

    public function stop(int $memberId, CarbonInterface $endDate): CateringMemberPeriod
    {
        return DB::transaction(function () use ($memberId, $endDate): CateringMemberPeriod {
            CateringMember::query()->lockForUpdate()->findOrFail($memberId);

            $openPeriod = CateringMemberPeriod::query()
                ->where('catering_member_id', $memberId)
                ->whereNull('end_date')
                ->lockForUpdate()
                ->first();

            if ($openPeriod === null) {
                throw new InvalidArgumentException('Peserta tidak memiliki periode keikutsertaan yang sedang berjalan.');
            }

            if ($endDate->lt($openPeriod->start_date)) {
                throw new InvalidArgumentException('Tanggal terakhir ikut tidak boleh sebelum tanggal mulai periode berjalan.');
            }

            $openPeriod->update([
                'end_date' => $endDate->toDateString(),
            ]);

            return $openPeriod->fresh();
        });
    }

    public function rejoin(int $memberId, CarbonInterface $startDate): CateringMemberPeriod
    {
        return DB::transaction(function () use ($memberId, $startDate): CateringMemberPeriod {
            CateringMember::query()->lockForUpdate()->findOrFail($memberId);

            $this->ensureNoOpenPeriod($memberId);
            $this->ensureNoOverlap($memberId, $startDate, null);

            return CateringMemberPeriod::query()->create([
                'catering_member_id' => $memberId,
                'start_date' => $startDate->toDateString(),
                'end_date' => null,
            ]);
        });
    }

    /**
     * @return Collection<int, CateringMemberPeriod>
     */
    public function periodsOverlapping(array $memberIds, CarbonInterface $start, CarbonInterface $end): Collection
    {
        if ($memberIds === []) {
            return collect();
        }

        return CateringMemberPeriod::query()
            ->whereIn('catering_member_id', $memberIds)
            ->where(function ($query) use ($start): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $start->toDateString());
            })
            ->whereDate('start_date', '<=', $end->toDateString())
            ->get();
    }

    public function isCovered(int $memberId, CarbonInterface $date): bool
    {
        return CateringMemberPeriod::query()
            ->where('catering_member_id', $memberId)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date->toDateString());
            })
            ->exists();
    }

    public function validateMembershipPeriods(int $memberId, CarbonInterface $start, ?CarbonInterface $end): void
    {
        if ($end !== null && $end->lt($start)) {
            throw new InvalidArgumentException('Tanggal berakhir tidak boleh sebelum tanggal mulai.');
        }

        $this->ensureNoOverlap($memberId, $start, $end);
    }

    private function ensureNoOpenPeriod(int $memberId): void
    {
        $hasOpen = CateringMemberPeriod::query()
            ->where('catering_member_id', $memberId)
            ->whereNull('end_date')
            ->exists();

        if ($hasOpen) {
            throw new InvalidArgumentException('Peserta masih memiliki periode keikutsertaan yang sedang berjalan.');
        }
    }

    private function ensureNoOverlap(int $memberId, CarbonInterface $start, ?CarbonInterface $end): void
    {
        $query = CateringMemberPeriod::query()
            ->where('catering_member_id', $memberId)
            ->whereDate('start_date', '<=', ($end ?? $start)->toDateString())
            ->where(function ($q) use ($start): void {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $start->toDateString());
            });

        if ($query->exists()) {
            throw new InvalidArgumentException('Periode keikutsertaan tidak boleh bertabrakan dengan periode yang sudah ada.');
        }
    }
}

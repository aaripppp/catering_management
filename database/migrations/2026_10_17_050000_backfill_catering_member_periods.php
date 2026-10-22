<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BACKFILL_START = '2026-09-01';

    /**
     * Backfill the initial open membership period for participants that existed
     * before member periods were introduced.
     *
     * The system went live on 2026-09-01, so every master-active participant at
     * migration time is treated as participating from that date with no end.
     * Members created afterwards choose their own start date, and archived
     * members deliberately receive no period.
     */
    public function up(): void
    {
        $now = now();

        DB::table('catering_members')
            ->where('is_active', true)
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('catering_member_periods')
                    ->whereColumn('catering_member_periods.catering_member_id', 'catering_members.id');
            })
            ->orderBy('id')
            ->chunkById(200, function ($members) use ($now): void {
                $rows = $members->map(fn ($member): array => [
                    'catering_member_id' => $member->id,
                    'start_date' => self::BACKFILL_START,
                    'end_date' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                if ($rows !== []) {
                    DB::table('catering_member_periods')->insert($rows);
                }
            });
    }

    /**
     * Intentionally not reversible: the backfilled rows are indistinguishable
     * from periods an admin would create by hand, so rolling back would risk
     * deleting real participation history.
     */
    public function down(): void {}
};

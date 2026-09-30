<?php

namespace App\Console\Commands;

use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('school-classes:normalize-levels {--dry-run : Preview changes without updating data}')]
#[Description('Normalize legacy school class levels to canonical values')]
class NormalizeSchoolClassLevels extends Command
{
    public function handle(): int
    {
        $updates = [];
        $ambiguousRows = [];

        $schoolClasses = SchoolClass::query()
            ->select(['id', 'name', 'level'])
            ->orderBy('id')
            ->get();

        foreach ($schoolClasses as $schoolClass) {
            $normalizedLevel = match ($schoolClass->level) {
                'VII' => SchoolLevel::Grade7->value,
                'VIII' => SchoolLevel::Grade8->value,
                'IX' => SchoolLevel::Grade9->value,
                'TK' => match ($schoolClass->name) {
                    'TK A' => SchoolLevel::TkA->value,
                    'TK B' => SchoolLevel::TkB->value,
                    default => null,
                },
                default => SchoolLevel::tryFrom((string) $schoolClass->level)?->value,
            };

            if ($normalizedLevel === null) {
                $ambiguousRows[] = [$schoolClass->id, $schoolClass->name, $schoolClass->level ?? 'NULL'];

                continue;
            }

            if ($normalizedLevel !== $schoolClass->level) {
                $updates[] = [$schoolClass->id, $schoolClass->name, $schoolClass->level, $normalizedLevel];
            }
        }

        if ($ambiguousRows !== []) {
            $this->error('Normalization stopped because ambiguous legacy rows require manual review.');
            $this->table(['ID', 'Name', 'Current level'], $ambiguousRows);

            return self::FAILURE;
        }

        if ($updates === []) {
            $this->info('All school class levels are already canonical.');

            return self::SUCCESS;
        }

        $this->table(['ID', 'Name', 'Current level', 'Canonical level'], $updates);

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No data was changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($updates): void {
            foreach ($updates as [$id, , , $normalizedLevel]) {
                SchoolClass::query()->whereKey($id)->update(['level' => $normalizedLevel]);
            }
        });

        $this->info(count($updates).' school class levels normalized.');

        return self::SUCCESS;
    }
}

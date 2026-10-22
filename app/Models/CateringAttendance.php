<?php

namespace App\Models;

use App\Enums\CateringAttendanceStatus;
use Database\Factories\CateringAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;

#[Fillable(['catering_member_id', 'attendance_date', 'status'])]
class CateringAttendance extends Model
{
    /** @use HasFactory<CateringAttendanceFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => CateringAttendanceStatus::class,
        ];
    }

    /**
     * @return BelongsTo<CateringMember, $this>
     */
    public function cateringMember(): BelongsTo
    {
        return $this->belongsTo(CateringMember::class);
    }

    /**
     * Restrict attendance to dates covered by an inclusive membership period.
     *
     * @param  Builder<CateringAttendance>  $query
     * @return Builder<CateringAttendance>
     */
    public function scopeCoveredByMembership(Builder $query): Builder
    {
        return $query->whereExists(function (QueryBuilder $periods): void {
            $periods
                ->selectRaw('1')
                ->from('catering_member_periods')
                ->whereColumn('catering_member_periods.catering_member_id', 'catering_attendances.catering_member_id')
                ->whereRaw('DATE(catering_member_periods.start_date) <= DATE(catering_attendances.attendance_date)')
                ->where(function (QueryBuilder $query): void {
                    $query->whereNull('catering_member_periods.end_date')
                        ->orWhereRaw('DATE(catering_member_periods.end_date) >= DATE(catering_attendances.attendance_date)');
                });
        });
    }
}

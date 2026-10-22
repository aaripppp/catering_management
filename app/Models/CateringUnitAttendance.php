<?php

namespace App\Models;

use Database\Factories\CateringUnitAttendanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catering_employee_unit_id', 'attendance_date', 'quantity', 'price_per_person_snapshot'])]
class CateringUnitAttendance extends Model
{
    /** @use HasFactory<CateringUnitAttendanceFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'quantity' => 'integer',
            'price_per_person_snapshot' => 'integer',
        ];
    }

    /** @return BelongsTo<CateringEmployeeUnit, $this> */
    public function cateringEmployeeUnit(): BelongsTo
    {
        return $this->belongsTo(CateringEmployeeUnit::class);
    }
}
